<?php

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
