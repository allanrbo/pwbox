<?php

function getconfig() {
    static $config = null;
    if($config == null) {
        $configPath = dirname(__FILE__) . "/../config.json";
        $config = json_decode(file_get_contents($configPath), true);
        if($config == null) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Internal error. Failed to load config."]);
            exit();
        }
    }

    return $config;
}


function ensurePermissions() {
    $config = getconfig();
    $uid = posix_geteuid();

    if(!file_exists($config["logPath"])) {
        $r = @mkdir($config["logPath"], 0755, true);
        if($r === false) {
            http_response_code(500);
            echo json_encode(["error" => "Internal error. Failed to create log directory."]);
            exit();
        }
    }

    if(fileowner($config["logPath"]) != $uid) {
        http_response_code(500);
        echo json_encode(["error" => "Internal error. Not owner of log directory."]);
        exit();
    }

    if(!file_exists($config["gpghome"])) {
        $r = @mkdir($config["gpghome"], 0700, true);
        if($r === false) {
            http_response_code(500);
            echo json_encode(["error" => "Internal error. Failed to create GPG home directory."]);
            exit();
        }
    }

    if(fileowner($config["gpghome"]) != $uid) {
        http_response_code(500);
        echo json_encode(["error" => "Internal error. Not owner of GPG home directory."]);
        exit();
    }

    if(!file_exists($config["secretsPath"])) {
        $r = @mkdir($config["secretsPath"], 0700, true);
        if($r === false) {
            http_response_code(500);
            echo json_encode(["error" => "Internal error. Failed to create GPG home directory."]);
            exit();
        }
    }

    if(fileowner($config["secretsPath"]) != $uid) {
        http_response_code(500);
        echo json_encode(["error" => "Internal error. Not owner of secrets directory."]);
        exit();
    }
}
