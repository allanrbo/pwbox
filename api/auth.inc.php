<?php

function ensureSystemUserExists() {
    if(!gpgSystemUserExists()) {
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
        "expire" => getUtcTime() + getconfig()["tokenExpiryMinutes"]*60,
    ];
    $tokenRaw = gpgEncryptSecret($username, $password, ["system"], json_encode($tokenContent), false);
    return base64_encode($tokenRaw);
}


function verifyCredentials($username, $password) {
    if("username" == "system") {
        return false;
    }

    if(!gpgUserExists($username)) {
        return false;
    }

    try {
        gpgEncryptSecret($username, $password, [$username], "dummy");
    } catch (Exception $e) {
        if(strpos($e->getMessage(), "bad passphrase") !== false) {
            return false;
        }
        throw $e;
    }

    return true;
}


function extractTokenFromHeader() {
    $authHeader = $_SERVER["HTTP_AUTHORIZATION"];
    if(strpos($authHeader, "Bearer ") !== 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Unsupported auth type."]);
        exit();
    }

    $token = substr($authHeader, strlen("Bearer "));
    $tokenRaw = base64_decode($token);

    $json = null;
    try {
        $json = gpgDecryptSecret("system", null, $tokenRaw);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid auth token."]);
        exit();
    }

    $authInfo = json_decode($json, true);
    if($authInfo["expire"] < getUtcTime()) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Expired auth token."]);
        exit();
    }

    return $authInfo;
}