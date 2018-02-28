<?php

require("config.inc.php");
require("logging.inc.php");
require("errorhandling.inc.php");
require("gpg.inc.php");
require("groups.inc.php");
require("auth.inc.php");
require("twofactor.inc.php");

header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$uriPrefix = getconfig()["uriPrefix"];
$uri = $_SERVER["REQUEST_URI"];
if (strpos($uri, $uriPrefix) !== 0) {
    throw new Exception("Unexpected URI prefix. Check configuration.");
}
$uri = substr($uri, strlen($uriPrefix));

/*
 * Authentication endpoint
 */
if ($method == "POST" && $uri == "/authenticate") {
    writelog("Requested $method on $uri");

    $data = json_decode(file_get_contents("php://input"), true);
    $username = $data["username"];
    $password = $data["password"];

    $userProfilesPath = getconfig()["userProfilesPath"];
    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);
    $expectedOtp = "";
    if (isset($user["otpKey"])) {
        $expectedOtp = generateOtp($user["otpKey"]);
    }

    if (!verifyCredentials($username, $password)) {
        sleep(3);
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        exit();
    }

    if (!isset($data["otp"])) {
        $data["otp"] = "";
    }

    if (isset($user["otpKey"]) && $expectedOtp != $data["otp"] && (isset($user["emergencyPasswords"]) && !in_array($data["otp"], $user["emergencyPasswords"]))) {
        sleep(3);
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        exit();
    }

    // Remove consumed emergency one-time password
    if (isset($user["otpKey"]) && isset($data["otp"]) && (isset($user["emergencyPasswords"]) && in_array($data["otp"], $user["emergencyPasswords"]))) {
        writelog("Emergency one-time password was used for $username");
        $user["emergencyPasswords"] = array_diff($user["emergencyPasswords"], [$data["otp"]]);
        file_put_contents("$userProfilesPath/$username", json_encode($user));
    }

    $token = generateToken($username, $password);
    echo json_encode(["token" => $token]);
    exit();
}


/*
 * User creation endpoint
 */
if ($method == "POST" && $uri == "/user") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    if(!isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["username"]) || !gpgUsernameValid($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username is invalid."]);
        exit();
    }

    if (!isset($data["password"]) || !gpgPassphraseValid($data["password"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password is invalid."]);
        exit();
    }

    if (gpgUserExists($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "User already exists"]);
        exit();
    }

    $username = $data["username"];
    gpgCreateUser($username, $data["password"]);

    unset($data["username"]);
    unset($data["password"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];
    $userProfilesPath = getconfig()["userProfilesPath"];
    file_put_contents("$userProfilesPath/$username", json_encode($data));

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * User update endpoint
 */
$matches = null;
if ($method == "PUT" && preg_match("/\/user\/([A-Za-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $username = $matches[1];

    if($username != $authInfo["username"] && !isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if ($username == $authInfo["username"] && isset($data["lockedOut"]) && $data["lockedOut"] === true) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "You cannot lock yourself out."]);
        exit();
    }

    unset($data["username"]);
    unset($data["groupMemberships"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];
    $userProfilesPath = getconfig()["userProfilesPath"];
    file_put_contents("$userProfilesPath/$username", json_encode($data));

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * User password update endpoint
 */
$matches = null;
if ($method == "POST" && $uri == "/changepassword") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);

    if (!gpgPassphraseValid($data["password"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "New password is invalid."]);
        exit();
    }

    if ($authInfo["password"] != $data["oldPassword"]) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Old password incorrect."]);
        exit();
    }

    gpgChangePassphrase($authInfo["username"], $authInfo["password"], $data["password"]);
    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * User two-factor one-time password key generation endpoint
 */
$matches = null;
if ($method == "POST" && $uri == "/changeotpkey") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);


    $username = $authInfo["username"];
    $userProfilesPath = getconfig()["userProfilesPath"];
    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);

    $r = ["status" => "ok"];

    if (isset($data["disable"]) && $data["disable"] === true) {
        unset($user["otpKey"]);
        unset($user["emergencyPasswords"]);
    } else {
        list($secretHex, $otpUrl) = generateOtpKey();
        $emergencyPasswords = generateEmergencyPasswords();
        $user["otpKey"] = $secretHex;
        $user["emergencyPasswords"] = $emergencyPasswords;

        $r["otpUrl"] = $otpUrl;
        $r["emergencyPasswords"] = $emergencyPasswords;
    }

    $user["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $user["modifiedBy"] = $authInfo["username"];
    file_put_contents("$userProfilesPath/$username", json_encode($user));

    echo json_encode($r);

    exit();
}


/*
 * User list endpoint
 */
if ($method == "GET" && $uri == "/user") {
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
 * Specific user endpoint
 */
if ($method == "GET" && preg_match("/\/user\/([A-Za-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $username = $matches[1];

    if($username != $authInfo["username"] && !isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

    if (gpgUserExists($username)) {
        $userProfilesPath = getconfig()["userProfilesPath"];
        $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);
        $user["username"] = $username;
        $user["groupMemberships"] = getGroupMemberships($username);
        $user["otpEnabled"] = isset($user["otpKey"]);
        unset($user["otpKey"]);
        unset($user["emergencyPasswords"]);
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
    }

    exit();
}


/*
 * Secrets creation endpoint
 */
if ($method == "POST" && $uri == "/secret") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);
    $recipients = [$authInfo["username"]];
    $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));

    if (isset($data["groups"])) {
        $recipients = array_unique(array_merge($recipients, getAllMembers($data["groups"])));
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
if ($method == "PUT" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    // Ensure the secret exists already
    $secretsPath = getconfig()["secretsPath"];
    if (!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read the old secret to ensure that the user currently has access to the secret being updated
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

    $data = json_decode(file_get_contents("php://input"), true);
    $recipients = [$authInfo["username"]];
    $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));

    if (isset($data["groups"])) {
        $recipients = array_unique(array_merge($recipients, getAllMembers($data["groups"])));
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
if ($method == "GET" && $uri == "/secret") {
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
if ($method == "GET" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    // Ensure the secret exists
    $secretsPath = getconfig()["secretsPath"];
    if (!file_exists("$secretsPath/$secretId")) {
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
if ($method == "DELETE" && preg_match("/\/secret\/([a-z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $secretId = $matches[1];

    $secretsPath = getconfig()["secretsPath"];
    if (!file_exists("$secretsPath/$secretId")) {
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
if ($method == "POST" && $uri == "/group") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    if(!isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $data["members"] = isset($data["members"]) ? $data["members"] : [];

    if (!isset($data["name"]) || !groupNameValid($data["name"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid group name. Must consist of letters A-Z or numbers 0-9."]);
        exit();
    }

    $groupName = $data["name"];

    $groupsPath = getconfig()["groupsPath"];

    // Ensure the group does not exists already
    $groupsPath = getconfig()["groupsPath"];
    if (file_exists("$groupsPath/$groupName")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Group with this name already exists."]);
        exit();
    }

    unset($data["name"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];
    file_put_contents("$groupsPath/$groupName", json_encode($data));

    echo json_encode(["status" => "ok", "name" => $groupName]);
    exit();
}


/*
 * Group update endpoint
 */
$matches = null;
if ($method == "PUT" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    if(!isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

    // Ensure the group exists already
    $groupsPath = getconfig()["groupsPath"];
    if (!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $data["members"] = isset($data["members"]) ? $data["members"] : [];

    unset($data["name"]);
    $data["modified"] = gmdate("Y-m-d\TH:i:s\Z");
    $data["modifiedBy"] = $authInfo["username"];

    file_put_contents("$groupsPath/$groupName", json_encode($data));

    reencryptSecretsUsingGroup($authInfo, $groupName, false);

    echo json_encode(["status" => "ok", "name" => $groupName]);
    exit();
}


/*
 * Group list endpoint
 */
if ($method == "GET" && $uri == "/group") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupsPath = getconfig()["groupsPath"];

    $groups = [];
    foreach (array_diff(scandir($groupsPath), [".", ".."]) as $key => $value) {
        $groupname = $value;
        $members = json_decode(file_get_contents("$groupsPath/$groupname"), true)["members"];
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
if ($method == "GET" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    // Ensure the group exists
    $groupsPath = getconfig()["groupsPath"];
    if (!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    $group = array_merge(
        ["name" => $groupName],
        json_decode(file_get_contents("$groupsPath/$groupName"), true)
    );

    echo json_encode($group);
    exit();
}


/*
 * Delete group endpoint
 */
$matches = null;
if ($method == "DELETE" && preg_match("/\/group\/([a-zA-Z0-9]+)/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupName = $matches[1];

    $groupsPath = getconfig()["groupsPath"];
    if (!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    if(!isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }

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
