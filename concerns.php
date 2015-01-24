<?php // 5.3.3

/*
 * concerns.php - simple PHP library for reading/writing YAML concern files
 *
 * This file contains functions that allow a PHP script to create grading
 * concern files (in YAML format) and save them to the file system, under
 * a directory for a particular user. Functions are also provided for
 * reading the files in again and parsing them into associative arrays.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require_once 'spyc/Spyc.php';

if (!is_readable(CONCERNS_DIR))
    trigger_error('failed to access concerns directory: ' . CONCERNS_DIR);

/*
 * Given a student's Kerberos username (e.g., "abreen") this function
 * returns an array of parsed YAML files corresponding to the concern
 * files present in the file system. If there is no directory for this
 * user in the file system, this function attempts to create it.
 */
function get_concerns($username) {
    $dir_path = check_and_get_subdirectory($username);

    $concern_files = scandir($dir_path);
    $list = array();
    foreach ($concern_files as $concern) {
        if ($concern == '.' || $concern == '..' || !is_yml_file($concern))
            continue;

        $yml_path = $dir_path . DIRECTORY_SEPARATOR . $concern;

        if (!is_readable($yml_path))
            trigger_error("error reading a concerns file: $yml_path");

        $parsed = Spyc::YAMLLoad($yml_path);

        if (!is_valid_concern($parsed))
            continue;

        $list[] = $parsed;
    }

    return $list;
}

/*
 * Given a student's Kerberos username (e.g., "abreen") and values from
 * the HTML form ("name", "ps", "issue", and "comments"), add a "resolved"
 * value of FALSE, an empty "response" field, and write the concern
 * to the file system.
 */
function save_concern($username, $data) {
    $dir_path = check_and_get_subdirectory($username);

    $data['resolved'] = FALSE;
    $data['response'] = '';
    $yaml = Spyc::YAMLDump($data, 2, 80, TRUE);

    $concern_path = $dir_path . DIRECTORY_SEPARATOR .
                    concern_filename($data);

    if (is_file($concern_path))
        trigger_error('this concern has already been submitted');

    umask(0000);
    file_put_contents($concern_path, $yaml);
    return TRUE;
}

/*
 * Given a valid concern as an associative array, generate a UNIX-friendly
 * filename with a ".yml" extension.
 */
function concern_filename($data) {
    return clean($data['ps']) . '-' .
           sprintf('%u', crc32(date('U'))) . '.yml';
}

/*
 * Returns TRUE if this associative array (parsed from a YAML file) has
 * the necessary key-value pairs, and FALSE otherwise.
 */
function is_valid_concern($parsed) {
    $necessary_keys = array('ps', 'resolved', 'issue', 'comments');
    foreach ($necessary_keys as $v)
        if (!array_key_exists($v, $parsed))
            return FALSE;
    return TRUE;
}

function clean($str) {
    return strtolower(strtr($str, array(' ' => '_')));
}

function check_and_get_subdirectory($username) {
    $dir_path = CONCERNS_DIR . DIRECTORY_SEPARATOR . $username;

    if (!file_exists($dir_path)) {
        umask(0000);
        if (!mkdir($dir_path)) {
            trigger_error('failed to create new concerns subdirectory: ' .
                          $dir_path);
        }
    }

    if (!is_readable($dir_path))
        trigger_error("failed to read concerns subdirectory: $dir_path");

    return $dir_path;
}

function is_yml_file($string) {
    return stripos(strrev($string), 'lmy.') === 0;
}
