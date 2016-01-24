<?php

/*
 * files.php - special Apollo wrapper functions around file operations
 *
 * This file contains functions to create new files and directories,
 * which simply call PHP's file functions, but ensuring that the
 * correct permissions are maintained.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

function apollo_new_file($path, $contents) {
    if (file_exists($path))
        trigger_error("file already exists: $path");

    if (file_put_contents($path, $contents) === FALSE)
        trigger_error("failure writing file: $path");

    chmod($path, NEW_FILE_MODE_INT);
}

function apollo_new_directory($path) {
    if (file_exists($path))
        trigger_error("directory already exists: $path");

    if (mkdir($path) === FALSE)
        trigger_error("failure making directory: $path");

    chmod($path, NEW_DIR_MODE_INT);
}
