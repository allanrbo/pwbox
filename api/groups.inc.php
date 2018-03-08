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
    $groupsPath = getconfig()["groupsPath"];

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
    $groupsPath = getconfig()["groupsPath"];
    $group = json_decode(file_get_contents("$groupsPath/$groupName"), true);
    return in_array($username, $group["members"]);
}


function getGroupMemberships($username) {
    $groupsPath = getconfig()["groupsPath"];

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


function reencryptSecretsUsingGroup($authInfo, $groupName, $removeGroup) {
    $secretsPath = getconfig()["secretsPath"];
    $groupsPath = getconfig()["groupsPath"];

    $secrets = gpgListAllSecretFiles($authInfo["username"], $authInfo["password"], $secretsPath);
    foreach ($secrets as $secret) {
        if ($secret["groups"] && in_array($groupName, $secret["groups"])) {
            $secretId = $secret["id"];
            $fullSecret = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$secretsPath/$secretId");

            $recipients = [$authInfo["username"]];

            if ($removeGroup) {
                $fullSecret["groups"] = array_diff($fullSecret["groups"], [$groupName]);
            }

            foreach ($fullSecret["groups"] as $groupName2) {
                // Ensure the group exists
                if (!file_exists("$groupsPath/$groupName2")) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "Group $groupName not found."]);
                    exit();
                }

                $group = json_decode(file_get_contents("$groupsPath/$groupName"), true);
                $recipients = array_unique(array_merge($recipients, $group["members"]));
            }

            $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($fullSecret));
            file_put_contents("$secretsPath/$secretId", $ciphertext);
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
