<?php

function gpgInvoke($cmd, $stdin = "", $passphrase = null, $getStdErr = false, $acceptFailure = false) {
    $gpghome = getconfig()["gpghome"];
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
    $fullCmd = "/usr/bin/gpg --home $gpghome --batch $passPhraseFdParam --no-tty $cmd";
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
    $matches = null;
    preg_match_all("/uid +(.+)/", $r, $matches);
    $users = $matches[1];
    return in_array("system", $users);
}


function gpgListAllUsers($includeSystem = false) {
    $r = gpgInvoke("--list-sigs");
    $matches = null;
    preg_match_all("/uid +(.+)/", $r, $matches);
    $users = $matches[1];

    $usersWithoutSystem = [];
    foreach ($users as $user) {
        if($user == "system") continue;
        $usersWithoutSystem[] = $user;
    }

    return $usersWithoutSystem;
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
    ";

    if($passphrase) {
        $batch .= "
            Passphrase: $passphrase
        ";
    }

    gpgInvoke("--gen-key", $batch);
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
    $gpghome = getconfig()["gpghome"];
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

        spawn /usr/bin/gpg --home \"$gpghomeEscaped\" --edit-key \"$username\" passwd

        expect {
            \"Enter passphrase:\" { }
            timeout { exit 100 }
        }

        send \"$oldPassphraseEscaped\\r\"

        expect {
            \"Enter the new passphrase for this secret key.\" { }
            timeout { exit 101 }
        }

        expect {
            \"Enter passphrase:\" { }
            timeout { exit 102 }
        }

        send \"$newPassphraseEscaped\\r\"

        expect {
            \"Repeat passphrase:\" { }
            timeout { exit 103 }
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


function gpgUpdateSecretFile($signUser, $signUserPassphrase, $recipientUsernames, $secretId, $text) {
    $ciphertext = gpgEncryptSecret($signUser, $signUserPassphrase, $recipientUsernames, $text);
    file_put_contents(getconfig()["secretsPath"] . "/" . $secretId, $ciphertext);
    return $secretId;
}


function gpgListAllSecretFiles($username, $passphrase) {
    $secretsPath = getconfig()["secretsPath"];
    $secretsFiles = array_diff(scandir($secretsPath), [".", ".."]);

    $r = [];
    foreach ($secretsFiles as $file) {
        $ciphertext = file_get_contents("$secretsPath/$file");

        $recipients = gpgRecipientsFromCiphertext($ciphertext);
        if(!in_array($username, $recipients)) {
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

        $r[] = [
            "id" => $file,
            "title" => $title,
            "username" => $recusername,
            "recipients" => $recipients,
            "modified" => $modified,
        ];
    }

    return $r;
}


function gpgGetSecretFile($username, $passphrase, $secretId) {
    $secretsPath = getconfig()["secretsPath"];
    $ciphertext = file_get_contents("$secretsPath/$secretId");

    $recipients = gpgRecipientsFromCiphertext($ciphertext);
    if(!in_array($username, $recipients)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized."]);
        exit();
    }

    $secret = json_decode(gpgDecryptSecret($username, $passphrase, $ciphertext), true);
    sort($recipients);
    $secret["recipients"] = $recipients;
    return $secret;
}
