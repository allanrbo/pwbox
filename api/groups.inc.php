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


function getAllMembers($authInfo, $groupNames) {
    $groupsPath = getconfig()["groupsPath"];

    $members = [];
    foreach ($data["groups"] as $groupName) {
        // Ensure the group exists
        if(!file_exists("$groupsPath/$groupName")) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Group $groupName not found."]);
            exit();
        }

        // The GPG recipients of the group are the members
        $group = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$groupsPath/$groupName");
        $members = array_unique(array_merge($group["recipients"], $members));
    }

    return $members;
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

                // The GPG recipients of the group are the members
                $group = gpgGetSecretFile($authInfo["username"], $authInfo["password"], "$groupsPath/$groupName");
                $recipients = array_unique(array_merge($recipients, $group["recipients"]));
            }

            $ciphertext = gpgEncryptSecret($authInfo["username"], $authInfo["password"], $recipients, json_encode($fullSecret));
            file_put_contents("$secretsPath/$secretId", $ciphertext);
        }
    }
}
