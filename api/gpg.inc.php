<?php

function gpgInvoke($cmd, $stdin = "", $passphrase = null, $getStdErr = false, $acceptFailure = false) {
    $gpghome = getDataPath() . "/gpghome";
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
        3 => ["pipe", "r"]
    ];

    $passPhraseFdParam = "";
    if($passphrase != null) {
        $passPhraseFdParam = "--passphrase-fd 3";
    }

    $pipes = null;
    $fullCmd = "/usr/bin/gpg --home $gpghome --batch --pinentry-mode loopback $passPhraseFdParam  $cmd";
    writelog("Invoking GPG: " . $fullCmd);
    $process = proc_open($fullCmd, $descriptorspec, $pipes);

    if(!is_resource($process)) {
        throw new Exception("Could not start GPG process");
    }

    if($passphrase != null) {
        fwrite($pipes[3], $passphrase);
        fclose($pipes[3]);
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $retval = proc_close($process);
    if(!$acceptFailure && $retval != 0) {
        throw new Exception("GPG returned $retval.\n$stderr");
    }

    if($getStdErr) {
        return $stderr;
    }

    return $output;
}


function gpgUsernameValid($username) {
    if(strlen($username) == 0) return false;
    if(preg_match("/[^a-zA-Z0-9]/", $username)) {
        return false;
    }
    return true;
}


function gpgPassphraseValid($passphrase) {
    // Allow only printable ASCII as password
    if(preg_match("/[^\\x20-\\x7E]/", $passphrase)) {
        return false;
    }
    return true;
}


function gpgSystemUserExists() {
    $r = gpgInvoke("--list-sigs");
    return preg_match("/uid .*?system$/m", $r);
}


function gpgListAllUsers() {
    $r = gpgInvoke("--list-sigs");
    $matches = null;
    preg_match_all("/uid .*?([a-zA-Z0-9]+)$/m", $r, $matches);
    $users = $matches[1];
    return array_diff($users, ["system"]);
}


function gpgUserExists($username) {
    return in_array($username, gpgListAllUsers());
}


function gpgCreateUser($username, $passphrase) {
    writelog("Creating user with username: $username");

    if(!gpgUsernameValid($username)) {
        throw new Exception("Invalid username: $username.");
    }

    if(!gpgPassphraseValid($passphrase)) {
        throw new Exception("Invalid passphrase.");
    }

    if(gpgUserExists($username)) {
        throw new Exception("User already exists: $username.");
    }

    $batch = "
        Key-Type: RSA
        Subkey-Type: RSA
        Name-Real: $username
        Expire-Date: 0
        %no-protection
    ";

    gpgInvoke("--gen-key", $batch);

    // TODO protect against empty password logins in case this fails...
    if ($passphrase) {
        gpgChangePassphrase($username, "", $passphrase);
    }
}


function gpgChangePassphrase($username, $oldPassphrase, $newPassphrase) {
    // Unfortunately a way to change password in GnuPG via batch mode was not found.
    // Thefore an Expect-script was used.

    writelog("Changing passphrase for user with username: $username");

    if(!gpgUserExists($username)) {
        throw new Exception("User does not exists: $username.");
    }

    if(!gpgPassphraseValid($oldPassphrase)) {
        throw new Exception("Invalid old passphrase.");
    }

    if(!gpgPassphraseValid($newPassphrase)) {
        throw new Exception("Invalid new passphrase.");
    }

    // Getconfig has validated this path. Further escape it for the Expect script.
    $gpghome = getDataPath() . "/gpghome";
    $gpghomeEscaped = str_replace("\"", "\\\"", $gpghome);

    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $pipes = null;
    $fullCmd = "/usr/bin/expect";
    writelog("Invoking expect: " . $fullCmd);
    $process = proc_open($fullCmd, $descriptorspec, $pipes);

    if(!is_resource($process)) {
        throw new Exception("Could not start expect process");
    }

    // Escape for the Expect-script
    $oldPassphraseEscaped = str_replace("\"", "\\\"", $oldPassphrase);
    $newPassphraseEscaped = str_replace("\"", "\\\"", $newPassphrase);

    $stdin = "
        set timeout 10

        spawn /usr/bin/gpg --home \"$gpghomeEscaped\" --pinentry-mode loopback --edit-key \"$username\" passwd

        expect {
            \"Enter passphrase:\" { }
            timeout { exit 100 }
        }

        send \"$oldPassphraseEscaped\\r\"

        expect {
            \"Enter passphrase:\" { }
            timeout { exit 102 }
        }

        send \"$newPassphraseEscaped\\r\"

        expect {
            \"gpg>\" { }
            timeout { exit 104 }
        }

        send \"save\\r\"

        expect {
            eof { }
            timeout { exit 105 }
        }

        set waitval [wait]
        set exitval [lindex \$waitval 3]
        exit \$exitval
    ";

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $retval = proc_close($process);
    if($retval != 0) {
        throw new Exception("Expect returned $retval.");
    }
}


function gpgEncryptSecret($signUser, $signUserPassphrase, $recipientUsernames, $text, $ascii = true) {
    if(!gpgUsernameValid($signUser)) {
        throw new Exception("Invalid signUser: $signUser.");
    }

    foreach($recipientUsernames as $recipientUser) {
        if(!gpgUsernameValid($recipientUser)) {
            throw new Exception("Invalid recipientUser: $recipientUser.");
        }
    }

    writelog("Encrypting secret for '" . implode(",", $recipientUsernames) . "' signed by '$signUser'");

    $armor = "";
    if($ascii) {
        $armor = "--armor";
    }

    $recipients = "";
    foreach($recipientUsernames as $recipientUsername) {
        $recipients .= "--recipient $recipientUsername ";
    }

    $ciphertext = gpgInvoke(
        "--default-key $signUser --encrypt $armor --sign $recipients",
        $text,
        $signUserPassphrase);

    return $ciphertext;
}


function gpgDecryptSecret($user, $passphrase, $ciphertext) {
    if(!gpgUsernameValid($user)) {
        throw new Exception("Invalid user: $user.");
    }

    $text = gpgInvoke("--default-key $user --decrypt", $ciphertext, $passphrase);

    return $text;
}


function gpgRecipientsFromCiphertext($ciphertext) {
    $recipients = [];
    $metadata = gpgInvoke("-vv --list-packets", $ciphertext, null, true, true);
    $nextLineIsUid = false;
    foreach (explode("\n", $metadata) as $line) {
        if($nextLineIsUid) {
            $matches = null;
            if(preg_match("/ +\"([^\"]+)\"/", $line, $matches)) {
                $recipients[] = $matches[1];
            }
        }

        if(preg_match("/^gpg: encrypted with/", $line)) {
            $nextLineIsUid = true;
        } else {
            $nextLineIsUid = false;
        }
    }

    return $recipients;
}


function gpgListAllSecretFiles($username, $passphrase, $cache) {
    $secretsPath = getDataPath() . "/secrets";
    $secretsFiles = array_diff(scandir($secretsPath), [".", ".."]);

    $cacheById = [];
    foreach ($cache as $entry) {
        $cacheById[$entry["id"]] = $entry;
    }

    $r = [];
    foreach ($secretsFiles as $file) {
        $filemtime = filemtime("$secretsPath/$file");

        if (isset($cacheById[$file]) && isset($cacheById[$file]["filemtime"]) && $cacheById[$file]["filemtime"] >= $filemtime) {
            $r[] = $cacheById[$file];
            continue;
        }

        $ciphertext = file_get_contents("$secretsPath/$file");

        $recipients = gpgRecipientsFromCiphertext($ciphertext);
        if(!in_array($username, $recipients)) {
            $r[] = [
                "id" => $file,
                "hasAccess" => false,
                "filemtime" => $filemtime,
            ];

            continue;
        }

        sort($recipients);
        $secret = json_decode(gpgDecryptSecret($username, $passphrase, $ciphertext), true);

        $title = "";
        if(isset($secret["title"])) {
            $title = $secret["title"];
        }

        $recusername = "";
        if(isset($secret["username"])) {
            $recusername = $secret["username"];
        }

        $modified = "";
        if(isset($secret["modified"])) {
            $modified = $secret["modified"];
        }

        $groups = [];
        if(isset($secret["groups"])) {
            $groups = $secret["groups"];
        }

        $r[] = [
            "id" => $file,
            "hasAccess" => true,
            "title" => $title,
            "username" => $recusername,
            "groups" => $groups,
            "modified" => $modified,
            "filemtime" => $filemtime,
        ];
    }

    return $r;
}


function gpgGetSecretFile($username, $passphrase, $path) {
    $ciphertext = file_get_contents($path);

    $recipients = gpgRecipientsFromCiphertext($ciphertext);
    if(!in_array($username, $recipients)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized."]);
        exit();
    }

    $secret = json_decode(gpgDecryptSecret($username, $passphrase, $ciphertext), true);
    return $secret;
}
