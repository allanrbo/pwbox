<?php

require("config.inc.php");
require("logging.inc.php");
require("errorhandling.inc.php");
require("gpg.inc.php");
require("auth.inc.php");
require("cors.inc.php");

corsAllowAll();

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
    $authInfo = extractTokenFromHeader();

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
    if(isset($data["recipients"])) {
        $recipients = array_unique(array_merge($recipients, $data["recipients"]));
    }

    unset($data["recipients"]);
    unset($data["id"]);

    $secretId = gpgCreateSecretFile($authInfo["username"], $authInfo["password"], $recipients, json_encode($data));
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

    $data = json_decode(file_get_contents("php://input"), true);
    $recipients = [$authInfo["username"]];
    if(isset($data["recipients"])) {
        $recipients = array_unique(array_merge($recipients, $data["recipients"]));
    }

    unset($data["recipients"]);
    unset($data["id"]);

    $secretId = gpgUpdateSecretFile($authInfo["username"], $authInfo["password"], $recipients, $secretId, json_encode($data));
    echo json_encode(["status" => "ok", "id" => $secretId]);
    exit();
}


/*
 * Secrets list endpoint
 */
if($method == "GET" && $uri == "/secret") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    echo json_encode(gpgListAllSecretFiles($authInfo["username"], $authInfo["password"]));
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

    $secretsPath = getconfig()["secretsPath"];
    if(!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    echo json_encode(array_merge(["id" => $secretId], gpgGetSecretFile($authInfo["username"], $authInfo["password"], $secretId)));
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

    unlink("$secretsPath/$secretId");

    echo json_encode(["status" => "ok"]);
    exit();
}



/*
 * Default endpoint
 */
http_response_code(404);
echo json_encode(["status" => "notFound"]);
