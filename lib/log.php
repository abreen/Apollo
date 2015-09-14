<?php // 5.3.3

/*
 * log.php - tools for generating log files
 *
 * This file contains functions that allow log files to be updated when
 * students make submissions. A log file is kept for each assignment,
 * and contains a list of mappings containing information about a single
 * submission. An example mapping is the following:
 *
 *  files: ['ps0pr0.py', 'ps0pr1.py']
 *  date: 2015-09-11T10:48:10-04:00
 *  ip: 155.41.54.138
 *
 * The mapping contains the names of the files submitted, the submission
 * date and time (in ISO 8601 format with timezone), and the IP address
 * from which the submission was made.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require_once 'meta.php';
require_once 'spyc/Spyc.php';

/*
 * Constants
 */

if (!is_readable(LOGS_DIR))
    trigger_error('failed to access logs directory: ' . LOGS_DIR);

/*
 * Functions
 */

function log_submission($num, $type, $username, $filenames, $ip) {
    $dt = new DateTime();
    $date = $dt->format(ISO8601_TZ);

    //$yaml = Spyc::YAMLDump(array($arr), false, false, true);

    $str = "- files:\n";
    foreach ($filenames as $filename)
        $str .= "    - $filename\n";

    $str .= "  date: $date\n";
    $str .= "  ip: $ip\n";

    $log_dir = log_dir_path($username);

    if (!is_dir($log_dir))
        apollo_new_directory($log_dir);

    $log_file = log_file_path($num, $type, $username);

    file_put_contents($log_file, $str, FILE_APPEND);
}

/*
 * Helper functions
 */

// form a path to a log file
function log_file_path($num, $type, $username) {
    return log_dir_path($username) . DIRECTORY_SEPARATOR .
           $type . $num . '.yml';
}

// form a path to a student's log directory for an assignment
function log_dir_path($username) {
    return LOGS_DIR . DIRECTORY_SEPARATOR . $username;
}
