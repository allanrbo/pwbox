<?php

require("config.inc.php");
require("logging.inc.php");
require("errorhandling.inc.php");
require("gpg.inc.php");
require("groups.inc.php");
require("auth.inc.php");

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$uriPrefix = getconfig()["uriPrefix"];
$uri = $_SERVER["REQUEST_URI"];
if(strpos($uri, $uriPrefix) !== 0) {
    throw new Exception("Unexpected URI prefix. Check configuration.");
}
$uri = substr($uri, strlen($uriPrefix));


/*
 * Authentication endpoint
 */
if($method == "POST" && $uri == "/authenticate") {
    writelog("Requested $method on $uri");

    $data = json_decode(file_get_contents("php://input"), true);
    $username = $data["username"];
    $password = $data["password"];

    if(!verifyCredentials($username, $password)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        exit();
    }

    $token = generateToken($username, $password);
    echo json_encode(["token" => $token]);
    exit();
}


/*
 * User creation endpoint
 */
if($method == "POST" && $uri == "/user") {
    writelog("Requested $method on $uri");
    //$authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);

    if(!gpgUsernameValid($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username is invalid."]);
        exit();
    }

    if(!gpgPassphraseValid($data["password"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password is invalid."]);
        exit();
    }

    if(gpgUserExists($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "User already exists"]);
        exit();
    }

    gpgCreateUser($data["username"], $data["password"]);
    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * User password update endpoint
 */
$matches = null;
if($method == "PUT" && preg_match("/\/user\/([A-Za-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $username = $matches[1];

    $data = json_decode(file_get_contents("php://input"), true);

    if($username != $authInfo["username"]) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Only allowed to change password on own user."]);
        exit();
    }

    if(!gpgPassphraseValid($data["password"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "New password is invalid."]);
        exit();
    }

    gpgChangePassphrase($username, $authInfo["password"], $data["password"]);
    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * User list endpoint
 */
if($method == "GET" && $uri == "/user") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $usersObjects = [];
    foreach (gpgListAllUsers() as $username) {
        $usersObjects[] = ["username" => $username];
    }

    echo json_encode($usersObjects);
    exit();
}


/*
 * Secrets creation endpoint
 */
if($method == "POST" && $uri == "/secret") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);
    $recipients = [$authInfo["username"]];

    if(isset($data["groups"])) {
        $members = getAllMembers($authInfo, $data["groups"]);
        $recipients = array_unique(array_merge($recipients, $members));
    }

    unset($data["id"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];

    $secretId = uniqid();
    $data["id"] = $secretId;
    $secretsPath = getconfig()["secretsPath"];
    $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($data));
    file_put_contents("$secretsPath/$secretId", $ciphertext);

    echo json_encode(["status" => "ok", "id" => $secretId]);
    exit();
}


/*
 * Secrets update endpoint
 */
$matches = null;
if($method == "PUT" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    // Ensure the secret exists already
    $secretsPath = getconfig()["secretsPath"];
    if(!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read the old secret to ensure that the user currently has access to the secret being updated
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

    $data = json_decode(file_get_contents("php://input"), true);
    $recipients = [$authInfo["username"]];

    if(isset($data["groups"])) {
        $members = getAllMembers($authInfo, $data["groups"]);
        $recipients = array_unique(array_merge($recipients, $members));
    }

    unset($data["id"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];

    $secretsPath = getconfig()["secretsPath"];
    $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($data));
    file_put_contents("$secretsPath/$secretId", $ciphertext);

    echo json_encode(["status" => "ok", "id" => $secretId]);
    exit();
}


/*
 * Secrets list endpoint
 */
if($method == "GET" && $uri == "/secret") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretsPath = getconfig()["secretsPath"];
    echo json_encode(gpgListAllSecretFiles($authInfo["username"], $authInfo["password"], $secretsPath));
    exit();
}


/*
 * Specific secret endpoint
 */
$matches = null;
if($method == "GET" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    // Ensure the secret exists
    $secretsPath = getconfig()["secretsPath"];
    if(!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    echo json_encode(array_merge(["id" => $secretId], gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId")));
    exit();
}


/*
 * Delete secret endpoint
 */
$matches = null;
if($method == "DELETE" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    $secretsPath = getconfig()["secretsPath"];
    if(!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read the secret to ensure that the user currently has access to the secret being deleted
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

    unlink("$secretsPath/$secretId");

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Group creation endpoint
 */
if($method == "POST" && $uri == "/group") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);
    $members = [$authInfo["username"]];
    if(isset($data["members"])) {
        $members = array_unique(array_merge($members, $data["members"]));
    }

    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];

    if(!isset($data["name"]) || !groupNameValid($data["name"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid group name. Must consist of letters A-Z or numbers 0-9."]);
        exit();
    }

    $groupsPath = getconfig()["groupsPath"];

    // Ensure the group does not exists already
    $groupsPath = getconfig()["groupsPath"];
    if(file_exists("$groupsPath/$data[name]")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Group with this name already exists."]);
        exit();
    }

    // Use $members as the GPG recipients
    $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $members, json_encode($data));
    file_put_contents("$groupsPath/$data[name]", $ciphertext);

    echo json_encode(["status" => "ok", "name" => $data["name"]]);
    exit();
}


/*
 * Group update endpoint
 */
$matches = null;
if($method == "PUT" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    // Ensure the group exists already
    $groupsPath = getconfig()["groupsPath"];
    if(!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read existing group to ensure the user is currently a member
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$groupsPath/$groupName");

    $data = json_decode(file_get_contents("php://input"), true);
    $members = [$authInfo["username"]];
    if(isset($data["members"])) {
        $members = array_unique(array_merge($members, $data["members"]));
    }

    unset($data["members"]);
    unset($data["name"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];

    $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $members, json_encode($data));
    file_put_contents("$groupsPath/$groupName", $ciphertext);

    reencryptSecretsUsingGroup($authInfo, $groupName, false);

    echo json_encode(["status" => "ok", "name" => $groupName]);
    exit();
}


/*
 * Group list endpoint
 */
if($method == "GET" && $uri == "/group") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupsPath = getconfig()["groupsPath"];

    $groups = [];
    foreach (array_diff(scandir($groupsPath), [".", ".."]) as $key => $value) {
        $groupname = $value;
        $ciphertext = file_get_contents("$groupsPath/$groupname");
        $members = gpgRecipientsFromCiphertext($ciphertext);
        $isMember = in_array($authInfo["username"], $members);

        $groups[] = ["name" => $groupname, "members" => $members, "isMember" => $isMember];
    }

    echo json_encode($groups);
    exit();
}


/*
 * Specific group endpoint
 */
$matches = null;
if($method == "GET" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    // Ensure the group exists
    $groupsPath = getconfig()["groupsPath"];
    if(!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    $group = array_merge(
        ["name" => $groupName],
        gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$groupsPath/$groupName")
    );

    // The GPG recipients are the members
    $members = $group["recipients"];
    $group["members"] = $members;
    unset($group["recipients"]);

    echo json_encode($group);
    exit();
}


/*
 * Delete group endpoint
 */
$matches = null;
if($method == "DELETE" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    $groupsPath = getconfig()["groupsPath"];
    if(!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read group to ensure the user is currently member of the group being deleted
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$groupsPath/$groupName");

    reencryptSecretsUsingGroup($authInfo, $groupName, true);

    unlink("$groupsPath/$groupName");

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Default endpoint
 */
http_response_code(404);
echo json_encode(["status" => "notFound"]);
