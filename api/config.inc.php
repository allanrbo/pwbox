<?php

function getconfig() {
    static $config = null;
    if ($config == null) {
        $configPath = dirname(__FILE__) . "/../config.json";
        $config = json_decode(file_get_contents($configPath), true);
        if ($config == null) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Internal error. Failed to load config."]);
            exit();
        }

        ensurePermissions($config);
    }

    return $config;
}


function getDataPath() {
    return getconfig()["dataPath"];
}


function ensurePermissions($config) {
    $uid = posix_geteuid();

    if (!file_exists($config["dataPath"])) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Internal error. Data directory does not exist."]);
        exit();
    }

    if (fileowner($config["dataPath"]) != $uid) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Internal error. Not owner of data directory."]);
        exit();
    }

    $dataSubDirs = [
        "/logs",
        "/gpghome",
        "/secrets",
        "/groups",
        "/userprofiles",
        "/backuptokens"
    ];

    $dataDirs = [];
    foreach ($dataSubDirs as $dataSubDir) {
        $dataDirs[] = $config["dataPath"] . $dataSubDir;
    }

    $dataDirs[] = $config["tokenPath"];

    foreach ($dataDirs as $path) {
        if (!file_exists($path)) {
            $r = @mkdir($path, 0700, true);
            if ($r === false) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Internal error. Failed to create $path."]);
                exit();
            }
        }
    }
}
