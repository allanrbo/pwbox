<?php

$lockFilePath = getDataPath() . "/lockfile";
$lockFileFp = null;

if (!file_exists($lockFilePath)) {
    file_put_contents($lockFilePath, "");
}

function takeLock() {
    global $lockFilePath, $lockFileFp;

    $lockFileFp = fopen($lockFilePath, "r+");

    if (!flock($lockFileFp, LOCK_EX)) {
        echo json_encode(["status" => "error", "message" => "Failed to acquire lock."]);
        exit();
    }
}

register_shutdown_function(function() {
    global $lockFileFp;

    if ($lockFileFp) {
        flock($lockFileFp, LOCK_UN);
        fclose($lockFileFp);
    }
});
