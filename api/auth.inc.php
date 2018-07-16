<?php

function getUtcTime() {
    $origTimeZone = date_default_timezone_get();
    date_default_timezone_set("UTC");
    $utctime = time();
    date_default_timezone_set($origTimeZone);
    return $utctime;
}


function generateToken($username, $password) {
    $tokenRaw = openssl_random_pseudo_bytes(512);

    $authInfo = [
        "token" => base64_encode($tokenRaw),
        "username" => $username,
        "password" => $password,
        "expiretime" => getUtcTime() + getconfig()["tokenExpiryMinutes"]*60,
        "starttime" => getUtcTime(),
    ];

    $tokenPath = getconfig()["tokenPath"];
    $tokenId = bin2hex(substr($tokenRaw, 0, 15));
    file_put_contents("$tokenPath/$tokenId", json_encode($authInfo));

    return $authInfo["token"];
}


function cleanUpTokenDirectory() {
    $tokenExpiryMinutes = getconfig()["tokenExpiryMinutes"];
    $tokenExpirySeconds = $tokenExpiryMinutes * 60;

    $tokenPath = getconfig()["tokenPath"];
    $dh = opendir($tokenPath);
    while (($fileName = readdir($dh)) !== false) {
        $filePath = "$tokenPath/$fileName";
        if (!is_file($filePath)) {
            continue;
        }

        if(time() - filemtime($filePath) > $tokenExpirySeconds) {
            unlink($filePath);
        }
    }

    closedir($dh);
}


function deleteAllTokensForUser($username) {
    $tokenPath = getconfig()["tokenPath"];
    $dh = opendir($tokenPath);
    while (($fileName = readdir($dh)) !== false) {
        $filePath = "$tokenPath/$fileName";
        if (!is_file($filePath)) {
            continue;
        }

        $authInfo = json_decode(file_get_contents($filePath), true);

        if ($authInfo["username"] == $username) {
            unlink($filePath);
        }
    }

    closedir($dh);
}


function deny() {
    sleep(3);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
};


function verifyCredentials($username, $password) {
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
    $tokenId = bin2hex(substr($tokenRaw, 0, 15));
    $tokenPath = getconfig()["tokenPath"];

    if (!file_exists("$tokenPath/$tokenId")) {
        writelog("Auth token file $tokenPath/$tokenId does not exist");
        deny();
    }

    $authInfo = json_decode(file_get_contents("$tokenPath/$tokenId"), true);

    // Compare against entire token
    if ($token !== $authInfo["token"]) {
        writelog("Invalid auth token");
        deny();
    }

    // Ensure token is not expired
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

    cleanUpTokenDirectory();

    return $authInfo;
}
