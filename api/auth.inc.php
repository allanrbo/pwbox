<?php

function ensureSystemUserExists() {
    if (!gpgSystemUserExists()) {
        gpgCreateUser("system", "");
    }
}


function getUtcTime() {
    $origTimeZone = date_default_timezone_get();
    date_default_timezone_set("UTC");
    $utctime = time();
    date_default_timezone_set($origTimeZone);
    return $utctime;
}


function generateToken($username, $password) {
    ensureSystemUserExists();
    $tokenContent = [
        "username" => $username,
        "password" => $password,
        "expiretime" => getUtcTime() + getconfig()["tokenExpiryMinutes"]*60,
        "starttime" => getUtcTime(),
    ];
    $tokenRaw = gpgEncryptSecret($username, $password, ["system"], json_encode($tokenContent), false);
    return base64_encode($tokenRaw);
}


function deny() {
    sleep(3);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
};


function verifyCredentials($username, $password) {
    if ("username" == "system") {
        return false;
    }

    if (!gpgUserExists($username)) {
        return false;
    }

    try {
        gpgEncryptSecret($username, $password, [$username], "dummy");
    } catch (Exception $e) {
        if (stripos($e->getMessage(), "bad passphrase") !== false) {
            return false;
        }
        throw $e;
    }

    return true;
}


function extractTokenFromHeader() {
    $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
    if (strpos($authHeader, "Bearer ") !== 0) {
        http_response_code(400);
        echo json_encode(["status" => "unauthorized", "message" => "Unsupported auth type."]);
        exit();
    }

    $token = substr($authHeader, strlen("Bearer "));
    $tokenRaw = base64_decode($token);

    // Check whether the token was signed by us
    $json = null;
    try {
        $json = gpgDecryptSecret("system", null, $tokenRaw);
    } catch (Exception $e) {
        writelog("Invalid auth token");
        deny();
    }

    // Ensure token is not expired
    $authInfo = json_decode($json, true);
    if ($authInfo["expiretime"] < getUtcTime() || $authInfo["starttime"] > getUtcTime()) {
        writelog("Expired auth token");
        deny();
    }

    // Ensure user is not locked out
    $userProfilesPath = getDataPath() . "/userprofiles";
    $user = json_decode(file_get_contents("$userProfilesPath/$authInfo[username]"), true);
    if (isset($user["lockedOut"]) && $user["lockedOut"] === true) {
        writelog("User is locked out");
        deny();
    }

    // Ensure that the password in the token is still valid
    try {
        gpgEncryptSecret($authInfo["username"], $authInfo["password"], [$authInfo["username"]], "dummy");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), "bad passphrase") !== false) {
            writelog("Invalid auth token. User password has changed.");
            deny();
        }
        throw $e;
    }

    return $authInfo;
}
