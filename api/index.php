<?php

require("config.inc.php");
require("logging.inc.php");
require("errorhandling.inc.php");
require("gpg.inc.php");
require("groups.inc.php");
require("auth.inc.php");
require("twofactor.inc.php");
require("locking.inc.php");
require("fileutils.inc.php");

takeLock();

header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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

    $alreadyTrustedDevice = isset($data["trustedDeviceToken"]) && $data["trustedDeviceToken"] != null;
    $trustDevice = isset($data["trustDevice"]) && $data["trustDevice"] == true;
    $hasTrustedDeviceName = isset($data["trustedDeviceName"]) && $data["trustedDeviceName"] != "";
    if (!$alreadyTrustedDevice && $trustDevice && !$hasTrustedDeviceName) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Please provide a name for this device. It will be used for the overview of trusted devices on the user profile page."]);
        exit();
    }

    if (!isset($data["username"]) || !isset($data["password"])) {
        deny();
    }

    $username = strtolower($data["username"]);
    $password = $data["password"];

    $userProfilesPath = getDataPath() . "/userprofiles";
    if (!file_exists("$userProfilesPath/$username")) {
        deny();
    }

    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);

    $expectedOtp = "";
    if (isset($user["otpKey"])) {
        $expectedOtp = generateOtp($user["otpKey"]);
    }

    if (!verifyCredentials($username, $password)) {
        deny();
    }

    if (!isset($data["otp"])) {
        $data["otp"] = "";
    }

    $verifiedTrustedDevice = false;
    if (isset($data["trustedDeviceToken"]) && isset($user["trustedDevices"])) {
        foreach ($user["trustedDevices"] as $trustedDevice) {
            if ($trustedDevice["token"] === $data["trustedDeviceToken"]) {
                $verifiedTrustedDevice = true;
                break;
            }
        }

        if (!$verifiedTrustedDevice) {
            writelog("Tried to authenticate as trusted device, but token was not recognized");
            deny();
        }
    }

    $correctOtpKey = $expectedOtp == $data["otp"];
    $correctEmergencyKey = isset($user["emergencyPasswords"]) && in_array($data["otp"], $user["emergencyPasswords"]);
    if (!$verifiedTrustedDevice && !$correctOtpKey && !$correctEmergencyKey) {
        deny();
    }

    // Remove consumed emergency one-time password
    if ($correctEmergencyKey) {
        writelog("Emergency one-time password was used for $username");
        $user["emergencyPasswords"] = array_diff($user["emergencyPasswords"], [$data["otp"]]);
        file_put_contents("$userProfilesPath/$username", json_encode($user));
    }

    // Is this a device not previously trusted, that we should start trusting?
    $trustedDevice = null;
    if (!$alreadyTrustedDevice && $trustDevice) {
        $trustedDeviceName = $data["trustedDeviceName"];
        writelog("Storing trusted device for user $username with device name $trustedDeviceName");

        if (!isset($user["trustedDevices"])) {
            $user["trustedDevices"] = [];
        }

        $trustedDevice = [
            "id" => uniqid(),
            "token" => base64_encode(openssl_random_pseudo_bytes(512)),
            "name" => $trustedDeviceName,
            "added" => gmdate("Y-m-d\\TH:i:s\\Z"),
            "addedFromRemoteAddr" => $_SERVER["REMOTE_ADDR"]
        ];
        $user["trustedDevices"][] = $trustedDevice;

        file_put_contents("$userProfilesPath/$username", json_encode($user));
    }

    $token = generateToken($username, $password);
    echo json_encode([
        "token" => $token,
        "tokenExpiryMinutes" => getconfig()["tokenExpiryMinutes"],
        "trustedDevice" => $trustedDevice
    ]);
    exit();
}


/*
 * Logout endpoint
 */
if ($method == "POST" && $uri == "/logout") {
    writelog("Requested $method on $uri");

    $authInfo = extractTokenFromHeader();

    $tokenPath = getconfig()["tokenPath"];
    $tokenRaw = base64_decode($authInfo["token"]);
    $tokenId = bin2hex(substr($tokenRaw, 0, 15));
    unlink("$tokenPath/$tokenId");

    exit();
}


/*
 * User creation endpoint
 */
if ($method == "POST" && $uri == "/user") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["username"]) || !gpgUsernameValid($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username is invalid."]);
        exit();
    }

    $username = strtolower($data["username"]);

    if (!isset($data["password"]) || !gpgPassphraseValid($data["password"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Password is invalid."]);
        exit();
    }

    if (gpgUserExists($username)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "User already exists"]);
        exit();
    }

    gpgCreateUser($username, $data["password"]);

    unset($data["username"]);
    unset($data["password"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
    $data["modifiedBy"] = $authInfo["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
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

    $username = strtolower($matches[1]);

    if($username != $authInfo["username"]) {
        requireAdminGroup($authInfo);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if ($username == $authInfo["username"] && isset($data["lockedOut"]) && $data["lockedOut"] === true) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "You cannot lock yourself out."]);
        exit();
    }

    unset($data["username"]);
    unset($data["groupMemberships"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
    $data["modifiedBy"] = $authInfo["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
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

    deleteAllTokensForUser($authInfo["username"]);

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
    $userProfilesPath = getDataPath() . "/userprofiles";
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

    $user["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
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

    $username = strtolower($matches[1]);

    if($username != $authInfo["username"]) {
        requireAdminGroup($authInfo);
    }

    if (gpgUserExists($username)) {
        $userProfilesPath = getDataPath() . "/userprofiles";
        $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);

        // Add extra "virtual" fields for convenience
        $user["username"] = $username;
        $user["groupMemberships"] = getGroupMemberships($username);
        $user["otpEnabled"] = isset($user["otpKey"]);

        // Remove sensitive data that should stay internal only
        unset($user["otpKey"]);
        unset($user["emergencyPasswords"]);
        unset($user["secretsListCache"]);
        if (isset($user["trustedDevices"])) {
            for ($i = 0; $i < count($user["trustedDevices"]); $i++) {
                unset($user["trustedDevices"][$i]["token"]);
            }
        }

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

    if(!isset($data["title"]) || !$data["title"]) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Empty title not allowed."]);
        exit();
    }

    if (!isset($data["owner"])) {
        $data["owner"] = $authInfo["username"];
    }

    $recipients = [$data["owner"]];

    $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));

    if (isset($data["groups"])) {
        $recipients = array_unique(array_merge($recipients, getAllMembers($data["groups"])));
    }

    unset($data["id"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
    $data["modifiedBy"] = $authInfo["username"];

    $secretId = uniqid();
    $data["id"] = $secretId;
    $secretsPath = getDataPath() . "/secrets";
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
    $secretsPath = getDataPath() . "/secrets";
    if (!file_exists("$secretsPath/$secretId")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Read the old secret to ensure that the user currently has access to the secret being updated
    gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

    $data = json_decode(file_get_contents("php://input"), true);

    if(!isset($data["title"]) || !$data["title"]) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Empty title not allowed."]);
        exit();
    }

    if (!isset($data["owner"])) {
        $data["owner"] = $authInfo["username"];
    }

    $recipients = [$data["owner"]];

    $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));

    if (isset($data["groups"])) {
        $recipients = array_unique(array_merge($recipients, getAllMembers($data["groups"])));
    }

    unset($data["id"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
    $data["modifiedBy"] = $authInfo["username"];

    $secretsPath = getDataPath() . "/secrets";
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

    $username = $authInfo["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);
    $secretsListCache = isset($user["secretsListCache"]) ? json_decode(gpgDecryptSecret($authInfo["username"], $authInfo["password"], $user["secretsListCache"]), true) : [];

    $secrets = gpgListAllSecretFiles($authInfo["username"], $authInfo["password"], $secretsListCache);

    if ($secrets != $secretsListCache) {
        $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], [$authInfo["username"]], json_encode($secrets));
        $user["secretsListCache"] = $ciphertext;
        file_put_contents("$userProfilesPath/$username", json_encode($user));
    }

    $secretsWithAccess = [];
    foreach ($secrets as $secret) {
        if ($secret["hasAccess"]) {
            $secretsWithAccess[] = $secret;
        }
    }

    echo json_encode($secretsWithAccess);

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
    $secretsPath = getDataPath() . "/secrets";
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

    $secretsPath = getDataPath() . "/secrets";
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
    requireAdminGroup($authInfo);

    $data = json_decode(file_get_contents("php://input"), true);
    $data["members"] = isset($data["members"]) ? $data["members"] : [];

    if (!isset($data["name"]) || !groupNameValid($data["name"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid group name. Must consist of letters A-Z or numbers 0-9."]);
        exit();
    }

    $groupName = $data["name"];

    $groupsPath = getDataPath() . "/groups";

    // Ensure the group does not exists already
    $groupsPath = getDataPath() . "/groups";
    if (file_exists("$groupsPath/$groupName")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Group with this name already exists."]);
        exit();
    }

    unset($data["name"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
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
    requireAdminGroup($authInfo);

    $groupName = $matches[1];

    // Ensure the group exists already
    $groupsPath = getDataPath() . "/groups";
    if (!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);
    $data["members"] = isset($data["members"]) ? $data["members"] : [];

    unset($data["name"]);
    $data["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
    $data["modifiedBy"] = $authInfo["username"];

    // Ensure at least one admin
    if ($groupName == "Administrators" && (!isset($data["members"]) || !$data["members"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "There must be at least one administrator."]);
        exit();
    }

    // Clean up in case there are any leftovers from previous interrupted runs
    @mkdir("/tmp/pwbox/reencryption/", 0700, true);
    deleteOldSubDirs("/tmp/pwbox/reencryption/");

    // Copy groups to tmp directory and modify, so we have not yet commited to the main storage in case we fail during reencryption
    $groupsPath = getDataPath() . "/groups";
    $tmpPath = "/tmp/pwbox/reencryption/" . uniqid();
    register_shutdown_function(function() use ($tmpPath) {
        shell_exec("rm -fr ${tmpPath}*");
    });
    mkdir("{$tmpPath}_groups", 0700, true);
    shell_exec("cp $groupsPath/* {$tmpPath}_groups");
    file_put_contents("{$tmpPath}_groups/$groupName", json_encode($data));

    // Output reencrypted secrets to tmp directory, so we have not yet commited to the main storage in case we fail during reencryption
    mkdir("{$tmpPath}_secrets", 0700, true);
    reencryptSecretsUsingGroup($authInfo, $groupName, "{$tmpPath}_secrets", "{$tmpPath}_groups");
    $secretsPath = getDataPath() . "/secrets";

    // Commit to main storage. Not quite atomically, but almost.
    shell_exec("mv {$tmpPath}_groups/* $groupsPath ; mv {$tmpPath}_secrets/* $secretsPath ; rm -fr {$tmpPath}*");

    echo json_encode(["status" => "ok", "name" => $groupName]);
    exit();
}


/*
 * Group list endpoint
 */
if ($method == "GET" && $uri == "/group") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $groupsPath = getDataPath() . "/groups";

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
    requireAdminGroup($authInfo);

    $groupName = $matches[1];

    // Ensure the group exists
    $groupsPath = getDataPath() . "/groups";
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
    requireAdminGroup($authInfo);

    $groupName = $matches[1];

    if($groupName == "Administrators") {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Cannot delete administrators group."]);
        exit();
    }

    $groupsPath = getDataPath() . "/groups";
    if (!file_exists("$groupsPath/$groupName")) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    // Clean up in case there are any leftovers from previous interrupted runs
    @mkdir("/tmp/pwbox/reencryption/", 0700, true);
    deleteOldSubDirs("/tmp/pwbox/reencryption/");

    // Copy groups to tmp directory and modify, so we have not yet commited to the main storage in case we fail during reencryption
    $groupsPath = getDataPath() . "/groups";
    $tmpPath = "/tmp/pwbox/reencryption/" . uniqid();
    register_shutdown_function(function() use ($tmpPath) {
        shell_exec("rm -fr ${tmpPath}*");
    });
    @mkdir("{$tmpPath}_groups", 0700, true);
    shell_exec("cp $groupsPath/* {$tmpPath}_groups");
    unlink("{$tmpPath}_groups/$groupName");

    // Output reencrypted secrets to tmp directory, so we have not yet commited to the main storage in case we fail during reencryption
    @mkdir("{$tmpPath}_secrets", 0700, true);
    reencryptSecretsUsingGroup($authInfo, $groupName, "{$tmpPath}_secrets", "{$tmpPath}_groups");
    $secretsPath = getDataPath() . "/secrets";

    // Commit to main storage. Not quite atomically, but almost.
    shell_exec("rm $groupsPath/$groupName ; mv {$tmpPath}_secrets/* $secretsPath ; rm -fr {$tmpPath}*");

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Export CSV
 */
if ($method == "GET" && $uri == "/csv") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    header("Content-Type: text/csv");

    $out = fopen("php://output", "w");

    fputcsv($out, ["pwboxId", "title", "username", "password", "notes", "owner", "groups"]);

    $secretsPath = getDataPath() . "/secrets";
    $secrets = array_diff(scandir($secretsPath), [".", ".."]);
    foreach ($secrets as $secret) {
        $s = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secret");

        $id = isset($s["id"]) ? $s["id"] : "";
        $title = isset($s["title"]) ? $s["title"] : "";
        $username = isset($s["username"]) ? $s["username"] : "";
        $password = isset($s["password"]) ? $s["password"] : "";
        $notes = isset($s["notes"]) ? $s["notes"] : "";
        $owner = isset($s["owner"]) ? $s["owner"] : "";
        $groups = isset($s["groups"]) ? implode(",", $s["groups"]) : "";

        fputcsv($out, [$secret, $title, $username, $password, $notes, $owner, $groups]);
    };

    fclose($out);
    exit();
}


/*
 * Import CSV
 */
if ($method == "PUT" && $uri == "/csv") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $expectedColumns = ["pwboxId", "title", "username", "password", "notes", "owner", "groups"];

    $in = fopen("php://input", "r");

    // Parse and verify header row
    $headerrow = fgetcsv($in);
    if ($headerrow != $expectedColumns) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Incorrect column headers in given CSV file. Please ensure the format is exactly as in the exported CSV."]);
        exit();
    }

    // Parse and verify all rows
    $users = gpgListAllUsers();
    $groupsPath = getDataPath() . "/groups";
    $groups = array_diff(scandir($groupsPath), [".", ".."]);
    $toImport = [];
    while (!feof($in)) {
        $row = fgetcsv($in);

        if (!$row || count($row) == 0) {
            continue;
        }

        if (count($row) != count($expectedColumns)) {
            http_response_code(400);
            writelog("Got row with " . count($row) . " columns but expected " . count($expectedColumns) . " columns");
            echo json_encode(["status" => "error", "message" => "Row with incorrect column count in given CSV file. Please ensure the format is exactly as in the exported CSV."]);
            exit();
        }

        $o = [];
        for ($i=0; $i < count($expectedColumns); $i++) {
            $o[$expectedColumns[$i]] = $row[$i];
        }

        // Verify the title column
        if (!$o["title"]) {
            http_response_code(400);
            writelog("Got row with empty title column");
            echo json_encode(["status" => "error", "message" => "A row contained an empty title."]);
            exit();
        }

        // Verify the owner column
        if (!$o["owner"]) {
            $o["owner"] = $authInfo["username"];
        }
        if (!in_array($o["owner"], $users)) {
            http_response_code(400);
            writelog("Got row with unknown owner " . $o["owner"]);
            echo json_encode(["status" => "error", "message" => "A row contained an unknown user in the owner column."]);
            exit();
        }

        // Parse and verify the groups column
        if (!$o["groups"]) {
            $o["groups"] = [];
        } else {
            $o["groups"] = explode(",", $o["groups"]);
            foreach ($o["groups"] as $group) {
                if (!in_array($group, $groups)) {
                    http_response_code(400);
                    writelog("Got row with unknown group " . $group);
                    echo json_encode(["status" => "error", "message" => "A row contained an unknown group in the groups column."]);
                    exit();
                }
            }
        }

        $toImport[] = $o;
    }

    fclose($in);

    // Prepare tmp dir to avoid commiting to main storage in case we fail during encryption of imported secrets
    @mkdir("/tmp/pwbox/import/", 0700, true);
    deleteOldSubDirs("/tmp/pwbox/import/");
    $tmpPath = "/tmp/pwbox/import/" . uniqid();
    register_shutdown_function(function() use ($tmpPath) {
        shell_exec("rm -fr ${tmpPath}*");
    });
    mkdir($tmpPath, 0700, true);

    // Import rows
    $secretsPath = getDataPath() . "/secrets";
    foreach ($toImport as $row) {
        $secret = [];
        $secretId = uniqid();

        if ($row["pwboxId"] && file_exists("$secretsPath/$row[pwboxId]")) {
            $secret = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$row[pwboxId]");
            $secretId = $row["pwboxId"];
        }

        $modified = false;
        foreach ($expectedColumns as $col) {
            if ($col == "pwboxId") {
                continue;
            }

            if (!isset($secret[$col]) || $secret[$col] != $row[$col]) {
                $secret[$col] = $row[$col];
                $modified = true;
            }
        }

        if (!$modified) {
            continue;
        }

        $recipients = [$secret["owner"]];
        $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));
        $recipients = array_unique(array_merge($recipients, getAllMembers($secret["groups"])));
        $secret["modified"] = gmdate("Y-m-d\\TH:i:s\\Z");
        $secret["modifiedBy"] = $authInfo["username"];
        $secret["id"] = $secretId;
        $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($secret));
        file_put_contents("$tmpPath/$secretId", $ciphertext);
    }

    shell_exec("mv {$tmpPath}/* $secretsPath ; rm -fr {$tmpPath}");

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Export tar of encrypted secrets
 */
if ($method == "GET" && $uri == "/backuptarsecrets") {
    writelog("Requested $method on $uri");

    $backupTokensPath = getDataPath() . "/backuptokens";
    if (file_exists("$backupTokensPath/secretsbackup") && $_SERVER["HTTP_AUTHORIZATION"] === "Bearer " . json_decode(file_get_contents("$backupTokensPath/secretsbackup"), true)["token"]) {
        // Proceed to allow download of backup tar based on backup token
    } else {
        $authInfo = extractTokenFromHeader();
        requireAdminGroup($authInfo);
    }

    header("Content-Type: application/tar");
    header("Content-Transfer-Encoding: binary");
    header("Content-Disposition: attachment; filename=\"secrets.tar\"");

    $secretsPath = getDataPath() . "/secrets";

    $dirname = dirname($secretsPath);
    $basename  = basename($secretsPath);
    echo shell_exec("tar --to-stdout -c --owner=0 --group=0 -C $dirname $basename");

    exit();
}


/*
 * Import tar of encrypted secrets
 */
if ($method == "PUT" && $uri == "/backuptarsecrets") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $data = file_get_contents("php://input");

    // Prepare tmp dir to avoid commiting to main storage in case we fail during encryption of imported secrets
    @mkdir("/tmp/pwbox/import/", 0700, true);
    deleteOldSubDirs("/tmp/pwbox/import/");
    $tmpDir = "/tmp/pwbox/import/" . uniqid();
    @mkdir($tmpDir, 0700, true);
    register_shutdown_function(function() use ($tmpDir) {
        shell_exec("rm -fr $tmpDir");
    });

    file_put_contents("$tmpDir/secrets.tar", $data);
    shell_exec("tar -xf $tmpDir/secrets.tar -C $tmpDir");

    // Ensure secrets dir exists
    if(!file_exists("$tmpDir/secrets/")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Given tar file does not contain a secrets directory."]);
        exit();
    }

    $users = gpgListAllUsers();
    $groupsPath = getDataPath() . "/groups";
    $groups = array_diff(scandir($groupsPath), [".", ".."]);

    // Ensure able to read all given secrets, and that all referenced users and groups exist
    foreach (array_diff(scandir("$tmpDir/secrets/"), [".", ".."]) as $key => $value) {
        $secretId = $value;

        $path = "$tmpDir/secrets/$secretId";
        $ciphertext = file_get_contents($path);
        $recipients = gpgRecipientsFromCiphertext($ciphertext);
        if(!in_array($authInfo["username"], $recipients)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Tar file contained a secret your key were not able to decrypt. Did not import anything."]);
            exit();
        }

        $secret = gpgGetSecretFile($authInfo["username"], $authInfo["password"], $path);

        // Verify title field
        if(!isset($secret["title"]) || !$secret["title"]) {
            http_response_code(400);
            writelog("Tar file contained a secret with empty title " . $secretId);
            echo json_encode(["status" => "error", "message" => "Tar file contained a secret with empty title. Did not import anything."]);
            exit();
        }

        if (!isset($secret["owner"])) {
            $secret["owner"] = $authInfo["username"];
        }

        // Verify the owner field
        if (!in_array($secret["owner"], $users)) {
            http_response_code(400);
            writelog("Tar file contained a secret with unknown owner " . $o["owner"]);
            echo json_encode(["status" => "error", "message" => "Tar file contained a secret with unknown owner. Did not import anything."]);
            exit();
        }

        // Verify the groups field
        if (isset($secret["groups"])) {
            foreach ($secret["groups"] as $group) {
                if (!in_array($group, $groups)) {
                    http_response_code(400);
                    writelog("Tar file contained a secret with an unknown group " . $group);
                    echo json_encode(["status" => "error", "message" => "Tar file contained a secret with an unknown group. Did not import anything."]);
                    exit();
                }
            }
        }

        // Recalculate field, in case owner or group membership changed since this secret was exported
        $recipients = [$secret["owner"]];
        $recipients = array_unique(array_merge($recipients, getAllMembers(["Administrators"])));
        if (isset($secret["groups"])) {
            $recipients = array_unique(array_merge($recipients, getAllMembers($secret["groups"])));
        }

        // Rewrite secret, in case owner or group membership changed since this secret was exported
        $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($secret));
        file_put_contents($path, $ciphertext);
    }

    $secretsPath = getDataPath() . "/secrets";
    shell_exec("mv $tmpDir/secrets/* $secretsPath");

    exit();
}


/*
 * Export tar of users, keys, and groups
 */
if ($method == "GET" && $uri == "/backuptarusers") {
    writelog("Requested $method on $uri");

    $backupTokensPath = getDataPath() . "/backuptokens";
    if (file_exists("$backupTokensPath/usersbackup") && $_SERVER["HTTP_AUTHORIZATION"] === "Bearer " . json_decode(file_get_contents("$backupTokensPath/usersbackup"), true)["token"]) {
        // Proceed to allow download of backup tar based on backup token
    } else {
        $authInfo = extractTokenFromHeader();
        requireAdminGroup($authInfo);
    }

    header("Content-Type: application/tar");
    header("Content-Transfer-Encoding: binary");
    header("Content-Disposition: attachment; filename=\"users.tar\"");

    $tmpDir = "/tmp/pwbox/export/" . uniqid();
    @mkdir($tmpDir, 0700, true);
    register_shutdown_function(function() use ($tmpDir) {
        shell_exec("rm -fr $tmpDir");
    });

    $gpgHomePath = getDataPath() . "/gpghome";
    $groupsPath = getDataPath() . "/groups";
    $userProfilesPath = getDataPath() . "/userprofiles";

    shell_exec("cp -r $gpgHomePath $tmpDir/gpghome");
    shell_exec("cp -r $groupsPath $tmpDir/groups");
    shell_exec("cp -r $userProfilesPath $tmpDir/userprofiles");

    echo shell_exec("tar --to-stdout -c --owner=0 --group=0 -C $tmpDir .");

    exit();
}


/*
 * Import tar of users
 */
if ($method == "PUT" && $uri == "/backuptarusers") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $data = file_get_contents("php://input");

    $tmpDir = "/tmp/pwbox/import/" . uniqid();
    @mkdir($tmpDir, 0700, true);
    register_shutdown_function(function() use ($tmpDir) {
        shell_exec("rm -fr $tmpDir");
    });

    file_put_contents("$tmpDir/users.tar", $data);
    shell_exec("tar -xf $tmpDir/users.tar -C $tmpDir");

    // Ensure gpghome dir exists
    if(!file_exists("$tmpDir/gpghome/")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Given tar file does not contain a gpghome directory."]);
        exit();
    }

    // Ensure groups dir exists
    if(!file_exists("$tmpDir/groups/")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Given tar file does not contain a groups directory."]);
        exit();
    }

    // Ensure userprofiles dir exists
    if(!file_exists("$tmpDir/userprofiles/")) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Given tar file does not contain a userprofiles directory."]);
        exit();
    }

    $gpgHomePath = getDataPath() . "/gpghome";
    $groupsPath = getDataPath() . "/groups";
    $userProfilesPath = getDataPath() . "/userprofiles";

    $cmd = "rm $gpgHomePath/* ; mv $tmpDir/gpghome/* $gpgHomePath ; ";
    $cmd .= "rm $groupsPath/* ; mv $tmpDir/groups/* $groupsPath ; ";
    $cmd .= "rm $userProfilesPath/* ; mv $tmpDir/userprofiles/* $userProfilesPath ; ";
    shell_exec($cmd);

    exit();
}



/*
 * Backup key status endpoint
 */
if ($method == "GET" && $uri == "/backuptokens") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $backupTokensPath = getDataPath() . "/backuptokens";

    echo json_encode([
        "secretBackupTokenEnabled" => file_exists("$backupTokensPath/secretsbackup"),
        "usersBackupTokenEnabled" => file_exists("$backupTokensPath/usersbackup")
    ]);

    exit();
}


/*
 * Backup token generation endpoint
 */
if ($method == "POST" && ($uri == "/changebackuptoken/secrets" || $uri == "/changebackuptoken/users")) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $data = json_decode(file_get_contents("php://input"), true);

    $backupTokensPath = getDataPath() . "/backuptokens";
    $tokenFilePath = null;
    if ($uri == "/changebackuptoken/secrets") $tokenFilePath = "$backupTokensPath/secretsbackup";
    if ($uri == "/changebackuptoken/users") $tokenFilePath = "$backupTokensPath/usersbackup";

    $r = ["status" => "ok"];

    if (isset($data["disable"]) && $data["disable"] === true) {
        unlink($tokenFilePath);
    } else {
        $token = base64_encode(openssl_random_pseudo_bytes(512));

        $endpoint = null;
        if ($uri == "/changebackuptoken/secrets") $endpoint = "/backuptarsecrets";
        if ($uri == "/changebackuptoken/users") $endpoint = "/backuptarusers";

        $r["url"] = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . getconfig()["uriPrefix"] . $endpoint;
        $r["token"] = $token;

        file_put_contents($tokenFilePath, json_encode([
            "token" => $token,
            "modified" => gmdate("Y-m-d\\TH:i:s\\Z")
        ]));
    }

    echo json_encode($r);

    exit();
}


/*
 * Remove trusted device endpoint
 */
if ($method == "POST" && $uri == "/removetrusteddevicebyid") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID of trusted device to remove required."]);
        exit();
    }

    $username = $authInfo["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);

    $found = false;
    if (isset($user["trustedDevices"])) {
        for ($i = 0; $i < count($user["trustedDevices"]); $i++) {
            if ($user["trustedDevices"][$i]["id"] == $data["id"]) {
                array_splice($user["trustedDevices"], $i, 1);
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Trusted device to remove not found."]);
        exit();
    }

    file_put_contents("$userProfilesPath/$username", json_encode($user));

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Remove trusted device endpoint, unauthenticated, only when give a valid token
 */
if ($method == "POST" && $uri == "/removetrusteddevicebyidunauthenticated") {
    writelog("Requested $method on $uri");

    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID of trusted device to remove required."]);
        exit();
    }

    if (!isset($data["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username required."]);
        exit();
    }

    if (!isset($data["token"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Token required."]);
        exit();
    }

    $username = $data["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
    $path = "$userProfilesPath/$username";
    if (!file_exists($path)) {
        writelog("Attempt to delete trusted device for unknown user $username");
        // Avoiding information leakage on whether this is valid user
        deny();
    }

    $user = json_decode(file_get_contents($path), true);

    $found = false;
    if (isset($user["trustedDevices"])) {
        for ($i = 0; $i < count($user["trustedDevices"]); $i++) {
            if ($user["trustedDevices"][$i]["id"] == $data["id"] && $user["trustedDevices"][$i]["token"] == $data["token"]) {
                array_splice($user["trustedDevices"], $i, 1);
                $found = true;
                break;
            }
        }
    }

    if (!$found) {
        writelog("Attempt to delete trusted device using bad token for user $username");
        // Avoiding information leakage on whether this is valid user
        deny();
    }

    file_put_contents("$userProfilesPath/$username", json_encode($user));

    echo json_encode(["status" => "ok"]);
    exit();
}


/*
 * Get OS info
 */
if ($method == "GET" && $uri == "/hostinfo") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $r = shell_exec("/usr/bin/hostnamectl");

    echo json_encode(["consoleoutput" => $r]);
    exit();
}


/*
 * Check for OS update
 */
if ($method == "POST" && $uri == "/updateoscheck") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $r = shell_exec("sudo /root/pwboxscripts/checkOsUpdate.sh");

    $upgradable = false;
    if (strstr($r, "The following packages will be upgraded:")) {
        $upgradable = true;
    }

    if (strstr($r, "0 upgraded,")) {
        $r .= "\r\nNo upgrades available.";
    }

    echo json_encode(["upgradable" => $upgradable, "consoleoutput" => $r]);
    exit();
}


/*
 * Perform OS update
 */
if ($method == "POST" && $uri == "/updateosperform") {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    @mkdir(getDataPath() . "/upgradeoutput/", 0700, true);
    $r = shell_exec("sudo /root/pwboxscripts/enqueueOsUpdate.sh 2&>1");
    $r = trim($r);

    echo json_encode(["updateJobId" => $r]);
    exit();
}


/*
 * Get status of OS update job
 */
$matches = null;
if ($method == "GET" && preg_match("/\/updateosperformstatus\/([a-f\d]{8}(-[a-f\d]{4}){4}[a-f\d]{8})/", $uri, $matches)) {
    writelog("Requested $method on $uri");
    $authInfo = extractTokenFromHeader();
    requireAdminGroup($authInfo);

    $updateJobId = $matches[1];

    $logFilePath = getDataPath() . "/upgradeoutput/$updateJobId.txt";
    if (!file_exists($logFilePath)) {
        http_response_code(404);
        echo json_encode(["status" => "notFound"]);
        exit();
    }

    $r = file_get_contents($logFilePath);
    $status = "running";
    if (strstr($r, "OS upgrade complete.")) {
        $status = "complete";
    }

    echo json_encode(["status" => $status, "consoleoutput" => $r]);
    exit();
}


/*
 * Default endpoint
 */
http_response_code(404);
echo json_encode(["status" => "notFound"]);
