<?php

function formatException($e) {
    // Mimic PHP's built-in exception formatter, but don't print parameters, as they could potentially contain sensitive data
    $s = "exception '" . get_class($e) . "' with message '" . $e->getMessage() . "' in " . $e->getFile() . ":" . $e->getLine() . "\n";
    $s .= "Stack trace:\n";
    $i = 0;
    foreach ($e->getTrace() as $call) {
        $class = "";
        if(isset($call["class"])) {
            $class = $call["class"] . "->";
        }

        $file = "";
        if(isset($call["file"])) {
            $file = $call["file"];
        }

        $line = "";
        if(isset($call["line"])) {
            $line = "(" . $call["line"] . "): ";
        }

        $s .= "#" . $i++ . " " . $file . $line . $class . $call["function"] . "\n";
    }

    return $s;
}

function errorHandler($errno, $errstr, $errfile, $errline) {
    if($errno & error_reporting()) {
        throw new Exception("Error $errno: $errfile:$errline\n$errstr");
    }
}

function exceptionHandler($e) {
    writelog(formatException($e));
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal error. Correlation ID: " . getLogTxId() . "."]);
}

set_error_handler("errorHandler");
set_exception_handler("exceptionHandler");
