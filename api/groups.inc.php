<?php

function groupNameValid($groupName) {
    if(!isset($groupName)) return false;
    if(strlen($groupName) == 0) return false;
    if(strlen($groupName) > 100) return false;
    if(preg_match("/[^a-zA-Z0-9]/", $groupName)) {
        return false;
    }
    return true;
}


function getAllMembers($groupNames) {
    $groupsPath = getDataPath() . "/groups";

    $members = [];
    foreach ($groupNames as $groupName) {
        // Ensure the group exists
        if(!file_exists("$groupsPath/$groupName")) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Group $groupName not found."]);
            exit();
        }

        $group = json_decode(file_get_contents("$groupsPath/$groupName"), true);
        $members = array_unique(array_merge($group["members"], $members));
    }

    return $members;
}


function isGroupMember($groupName, $username) {
    $groupsPath = getDataPath() . "/groups";
    $group = json_decode(file_get_contents("$groupsPath/$groupName"), true);
    return in_array($username, $group["members"]);
}


function getGroupMemberships($username) {
    $groupsPath = getDataPath() . "/groups";

    $groups = [];
    foreach (array_diff(scandir($groupsPath), [".", ".."]) as $key => $value) {
        $groupName = $value;
        $group = json_decode(file_get_contents("$groupsPath/$groupName"), true);
        if (in_array($username, $group["members"])) {
            $groups[] = $groupName;
        }
    }

    return $groups;
}


function reencryptSecretsUsingGroup($authInfo, $groupName, $outPath, $groupsPath) {
    $secretsPath = getDataPath() . "/secrets";

    $username = $authInfo["username"];
    $userProfilesPath = getDataPath() . "/userprofiles";
    $user = json_decode(file_get_contents("$userProfilesPath/$username"), true);
    $secretsListCache = isset($user["secretsListCache"]) ? json_decode(gpgDecryptSecret($authInfo["username"], $authInfo["password"], $user["secretsListCache"]), true) : [];

    $secrets = gpgListAllSecretFiles($authInfo["username"], $authInfo["password"], $secretsListCache);

    foreach ($secrets as $secret) {
        if (isset($secret["groups"]) && in_array($groupName, $secret["groups"])) {
            $secretId = $secret["id"];
            $fullSecret = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

            $adminGroup = json_decode(file_get_contents("$groupsPath/Administrators"), true);
            $recipients = $adminGroup["members"];
            $missingGroups = [];

            foreach ($fullSecret["groups"] as $groupName2) {
                if (!file_exists("$groupsPath/$groupName2")) {
                    $missingGroups[] = $groupName2;
                    continue;
                }

                $group = json_decode(file_get_contents("$groupsPath/$groupName2"), true);
                $recipients = array_unique(array_merge($recipients, $group["members"]));
            }

            $fullSecret["groups"] = array_diff($fullSecret["groups"], $missingGroups);

            $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($fullSecret));
            file_put_contents("$outPath/$secretId", $ciphertext);
        }
    }
}


function requireAdminGroup($authInfo) {
    if(!isGroupMember("Administrators", $authInfo["username"])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Not member of administrators group."]);
        exit();
    }
}


function deleteOldSubDirs($path) {
    if (!is_dir($path)) {
        return;
    }

    $dh = opendir($path);
    while (($file = readdir($dh)) !== false) {
        if ($file == "." || $file == "..") {
            continue;
        }

        if (time() > filemtime($path . $file) + 3600) {
            shell_exec("rm -fr $path/$file");
        }
    }

    closedir($dh);
}