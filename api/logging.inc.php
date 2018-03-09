<?php

function getLogTxId() {
    static $txId = null;
    if($txId == null) {
        $txId = uniqid("tx");
    }
    return $txId;
}

function writelog($message) {
    $txId = getLogTxId();
    $utcNow = time() - date("Z");
    $timeStamp = date("c", $utcNow);

    $r = @file_put_contents(getDataPath() . "/logs/api.log", "[$timeStamp] [$txId] $message\n", FILE_APPEND);
    if($r === false) {
        http_response_code(500);
        echo json_encode(["error" => "Internal error. Failed to write to log file."]);
        exit();
    }
}
