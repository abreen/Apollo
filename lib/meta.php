<?php // 5.3.3

/*
 * meta.php - tools for opening metafiles (see below)
 *
 * This file contains functions that allow a PHP script to load metafiles.
 * In the context of Apollo, a metafile is a file containing information
 * about other files, and Apollo metafiles contain the file names that
 * Apollo expects to be uploaded, and the due dates for each of those files.
 *
 * An Apollo metafile is a YAML file whose "top-level" document is a map
 * from file names to due date maps. A due date map is a mapping from a
 * due date string in ISO 8601 format (with time zone offset) to a
 * floating-point value between 0.0 and 1.0 (inclusive) indicating a late
 * penalty.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require_once 'files.php';
require_once 'spyc/Spyc.php';

/*
 * Constants
 */

define('ISO8601_TZ', 'Y-m-d\TH:i:sP');
define('FRIENDLY_DATE_FORMAT', 'F j, Y \a\t g:i A');
define('DATE_FORMAT_WITH_SECONDS', 'F j, Y \a\t g:i:s A');

// upload status values given to an assignment
define('TOO_EARLY', 0);
define('ACCEPTING', 1);
define('ACCEPTING_LATE', 2);
define('CLOSED', 3);

if (!is_readable(DROPBOX_DIR))
    trigger_error('failed to access dropbox: ' . DROPBOX_DIR);

if (!is_readable(METAFILE_DIR))
    trigger_error('failed to access metafile directory: ' . METAFILE_DIR);

if (!is_readable(SUBMISSIONS_DIR))
    trigger_error('failed to access submissions directory: ' .
                  SUBMISSIONS_DIR);


/*
 * If this is set to TRUE, submissions for all assignments are allowed,
 * even past deadlines. This essentially turns off the deadline-related
 * computation and turns this application into WebSubmit.
 */
define('ALLOW_ALL_SUBMISSIONS', FALSE);


/*
 * Used to determine whether a "view this file" link should be shown
 * on submissions.
 */
$viewable_file_types = array('py', 'txt', 'java', 'c', 'cpp');

/*
 * This is used by any code that needs the "current" time and is modifiable
 * here for testing purposes.
 */
$now = new DateTime();

/*
 * This interval is used to determine when uploads should be allowed
 * (i.e., how many hours/days before an assignment's first due date
 * should the upload forms be available).
 */
$pre_due_date_window = new DateInterval('P1W');         // 1 week

/*
 * This interval is used as padding at the end of all deadlines to
 * give students extra time to submit files.
 */
$grace_period_window = new DateInterval('PT10M');       // 10 minutes


/*
 * Assignments can be either problem sets or labs. These constants
 * are provided since functions may cause different effects for
 * different assignment types.
 */
define('PROBLEM_SET', 'ps');
define('LAB', 'lab');

$ps_names = array(0  => 'Problem Set 0',
                  1  => 'Problem Set 1',
                  2  => 'Problem Set 2',
                  3  => 'Problem Set 3',
                  4  => 'Problem Set 4',
                  5  => 'Problem Set 5',
                  6  => 'Problem Set 6',
                  7  => 'Problem Set 7',
                  8  => 'Problem Set 8',
                  9  => 'Problem Set 9',
                  10 => 'Problem Set 10',
                  11 => 'Problem Set 11',
                  12 => 'Final Project');

$lab_names = array(1  => 'Lab 1',
                   2  => 'Lab 2',
                   3  => 'Lab 3',
                   4  => 'Lab 4',
                   5  => 'Lab 5',
                   6  => 'Lab 6',
                   7  => 'Lab 7',
                   8  => 'Lab 8',
                   9  => 'Lab 9',
                   10 => 'Lab 10',
                   11 => 'Lab 11');

function ps_exists($key) {
    global $ps_names;

    return array_key_exists($key, $ps_names);
}

function lab_exists($key) {
    global $lab_names;

    return array_key_exists($key, $lab_names);
}

function check_assignment($key, $type) {
    switch ($type) {
        case PROBLEM_SET:
            if (!ps_exists($key))
                trigger_error("invalid problem set number: $key");
            break;

        case LAB:
            if (!lab_exists($key))
                trigger_error("invalid lab number: $key");
            break;

        default:
            return trigger_error("invalid assignment type: $type");
    }
}

function assignment_name($key, $type) {
    global $ps_names, $lab_names;

    switch ($type) {
        case PROBLEM_SET:
            return $ps_names[$key];
        case LAB:
            return $lab_names[$key];
        default:
            return trigger_error("invalid assignment type: $type");
    }
}

/*
 * Functions
 */

/*
 * Given a student's Kerberos username (e.g., "abreen") and an
 * assignment number (e.g., "3"), return an associative array
 * mapping group names to an array "triple" (a, b, c), where a is the
 * contents of the grade file for that group, b is the points earned by
 * the student, and c is the total number of possible points. The group
 * name is the alphabetic portion of the grade file's name between the
 * assignment name and the "-grade.txt" part. If the grade file has no
 * such alphabetic portion, NULL is used for the group name. This function
 * returns FALSE if the assignment has not been graded at all.
 */
function get_grade_files($username, $num, $type) {
    check_assignment($num, $type);

    $dir_path = DROPBOX_DIR . DIRECTORY_SEPARATOR . $username;

    if (!is_dir($dir_path))
        // if this directory doesn't exist, no grades are present
        return FALSE;

    if (!is_readable($dir_path))
        trigger_error("grade directory not readable: $dir_path");

    $grade_files = array();

    if ($type == PROBLEM_SET)
        $pattern = '/ps' . $num. '([a-zA-Z]+)?\-grade\.txt/';
    else
        $pattern = '/lab' . $num . '([a-zA-z]+)?\-grade\.txt/';

    $files = scandir($dir_path);
    foreach ($files as $filename) {
        if ($filename == '.' || $filename == '..' || is_dotfile($filename))
            continue;

        $matches = array();
        if (preg_match($pattern, $filename, $matches) !== 1)
            // this grade file is not for this assignment
            continue;

        if (count($matches) > 1) {
            $group = $matches[1];
        } else {
            $group = NULL;
        }

        $contents = file_get_contents($dir_path . DIRECTORY_SEPARATOR .
                                      $filename);

        if (preg_match('%[Tt]otal:\s*(\d+(?:\.\d+)?)/(\d+)(?:\.\d+)?%',
                       $contents, $matches) !== 1)
        {
            trigger_error("malformed grade file: $filename");
        }

        $earned_points = $matches[1];
        $total_points = $matches[2];

        $grade_files[$group] = array($contents, $earned_points, $total_points);
    }

    if (count($grade_files) === 0)
        return FALSE;
    else
        return $grade_files;
}

/*
 * Given an assignment number (e.g., "10") and an assignment type
 * (e.g., LAB or PROBLEM_SET), return an associative array
 * mapping file names (e.g., "ps10pr2.py") to an array "pair"
 * of DateTime objects and late deduction multipliers. The DateTime
 * objects are the due dates parsed from a metafile. If there is no
 * metafile for the assignment, this function returns NULL.
 */
function get_files_and_dates($num, $type) {
    check_assignment($num, $type);

    $meta = get_metafile($num, $type);

    if ($meta === NULL)
        return NULL;

    $info = array();

    foreach ($meta as $file => $due_dates) {
        $dates = array();

        foreach ($due_dates as $date => $penalty) {
            $d = DateTime::createFromFormat(ISO8601_TZ, $date);

            if ($d === FALSE)
                trigger_error("could not parse date: $date");

            if ($penalty < 0 || $penalty > 1)
                trigger_error("invalid late multiplier: $penalty");

            $pair = array($d, $penalty);

            $dates[] = $pair;
        }

        $info[$file] = $dates;
    }

    return $info;
}

/*
 * Given an assignment number (e.g., "10") and an assignment type
 * (e.g., LAB or PROBLEM_SET), return an associative array representing
 * the metafile for that assignment, drawn from the directory
 * METAFILE_DIR. If there is no metafile for the specified assignment, this
 * function returns NULL.
 */
function get_metafile($num, $type) {
    check_assignment($num, $type);

    $path = METAFILE_DIR . DIRECTORY_SEPARATOR;
    if ($type == PROBLEM_SET)
        $path .= 'ps' . $num . '.yml';
    else
        $path .= 'lab' . $num . '.yml';

    if (file_exists($path))
        return Spyc::YAMLLoad($path);
    else
        return NULL;
}

/*
 * Given an array of pairs (a, b) where a is a DateTime object
 * representing an assignment's due date and b is a late deduction
 * multiplier (such an array of pairs might be created by
 * get_files_and_dates()), return the upload status of the file
 * (i.e., whether an upload would be too early, would be accepted,
 * accepted late, or if the deadline has passed). The integer
 * returned by this function will be one of the following constants:
 * TOO_EARLY, ACCEPTING, ACCEPTING_LATE, CLOSED.
 */
function upload_status($dates) {
    global $now, $pre_due_date_window, $grace_period_window;

    if (ALLOW_ALL_SUBMISSIONS)
        return ACCEPTING;

    $now_plus_window = clone $now;
    $now_plus_window->add($pre_due_date_window);

    /*
     * We need to sort the dates, but we can't sort the array of pairs
     * passed in. We'll create an associative array mapping of i => d,
     * where d is a DateTime object from $dates and i is its index in
     * $dates. Then we sort the map by values.
     */
    $map = array();
    foreach ($dates as $i => $pair) {
        $dt = clone $pair[0];
        $dt->add($grace_period_window);
        $map[$i] = $dt;
    }

    asort($map);

    /*
     * We'll consider the dates from earliest to latest and stop when
     * $d contains the first date later than "now". $m will also hold
     * the corresponding multiplier for $d.
     */
    $m = 1.00;
    foreach ($map as $i => $date) {
        $last_was_late = $m < 1.00;

        $d = $date;
        $m = $dates[$i][1];

        if ($d > $now)
            break;
    }

    if ($m < 1.00) {
        // $d holds a date after which late submissions will be accepted

        if ($now_plus_window < $d) {

            /*
             * Even adding the pre-due date window didn't get us to the
             * assignment's late due date. It's too early to allow uploads.
             */
            return TOO_EARLY;

        } else { // $now_plus_window >= $d

            // we're before the assignment's first due date, in the window
            return ACCEPTING;
        }

    } else {
        // $d holds a date after which no submissions will be accepted

        if ($now < $d) {

            /*
             * This is a tricky case. The date that's coming next ($d) is
             * a date after which no submissions will be accepted. This
             * can mean that we're accepting late submissions right now;
             * this would be the case if there were a date before this
             * date that specified some multiplier < 1.00. If, for example,
             * there was only one date specified, and its multiplier is
             * 1.00, then we'll assume there never was a late window.
             */
            if ($last_was_late)
                return ACCEPTING_LATE;
            else
                return ACCEPTING;

        } else { // $now >= $d

            /*
             * We've passed a date after which no submissions will
             * be accepted.
             */
            return CLOSED;
        }
    }

    // should not get here
}

/*
 * Given an associative array mapping file names to dates and multipliers
 * (e.g., one obtained by calling get_files_and_dates()), return the
 * upload status of the assignment (i.e., whether uploads would be too
 * early, would be accepted, accepted late, or if the deadline has passed).
 * The integer returned by this function will be one of the following
 * constants: TOO_EARLY, ACCEPTING, ACCEPTING_LATE, CLOSED.
 *
 * This function delegates its work to upload_status() for each file in
 * the passed-in array, and chooses the most permissive status among those
 * produced by upload_status() on each file.
 */
function assignment_upload_status($info) {
    $statuses = array_map('upload_status', $info);

    if (in_array(ACCEPTING, $statuses))
        return ACCEPTING;
    else if (in_array(ACCEPTING_LATE, $statuses))
        return ACCEPTING_LATE;
    else if (in_array(TOO_EARLY, $statuses))
        return TOO_EARLY;
    else
        return CLOSED;
}

/*
 * Given an associative array mapping file names to dates and multipliers
 * (e.g., one obtained by calling get_files_and_dates()), return an array
 * of distinct pairs (a, b) where a is a DateTime object representing a
 * due date and b is a late deduction multiplier. For more information
 * about these pairs, see the get_files_and_dates() function, which
 * produces them.
 */
function due_dates($info) {
    $due_dates = array();
    foreach ($info as $file => $ds) {
        foreach ($ds as $pair) {
            $d = $pair[0];
            $m = $pair[1];      // multiplier

            if (in_array($pair, $due_dates))
                continue;

            $due_dates[] = $pair;
        }
    }

    return $due_dates;
}

/*
 * Given an assignment number (e.g., "6"), a student username, the
 * path to a temporary file holding a student's upload, and the desired
 * "destination" file name, move the temporary file into SUBMISSIONS_DIR.
 * The file will be placed in the student's subdirectory for the assignment.
 * If the student's subdirectory does not exist, this function creates it.
 * If the subdirectory for the assignment doesn't exist, this function
 * will create it. This function returns TRUE if the file was moved
 * correctly, or FALSE if anything goes wrong.
 */
function save_file($num, $type, $username, $tmp_path, $dest_name) {
    check_assignment($num, $type);

    $dest_path = SUBMISSIONS_DIR;

    if ($type == PROBLEM_SET)
        $dest_path .= DIRECTORY_SEPARATOR . 'ps' . $num;
    else
        $dest_path .= DIRECTORY_SEPARATOR . 'lab' . $num;

    if (!file_exists($dest_path))
        // create new directory for this assignment
        apollo_new_directory($dest_path);

    $dest_path .= DIRECTORY_SEPARATOR . $username;

    if (!file_exists($dest_path))
        // create new subdirectory for this student
        apollo_new_directory($dest_path);

    $dest_path .= DIRECTORY_SEPARATOR . $dest_name;

    if (move_uploaded_file($tmp_path, $dest_path) === FALSE)
        return FALSE;

    /*
     * Because PHP was written by monkeys and no part of it may be trusted,
     * we must make sure the file was actually saved. (For example, if there
     * is no more space on the file system for the file, move_uploaded_file()
     * will not return an error at all. This interesting fact was discovered
     * by painful experience.)
     */
    if (!file_exists($dest_path))
        // file could not be saved for some reason
        return FALSE;

    $size = filesize($dest_path);
    if ($size === FALSE)
        // file exists, but size cannot be obtained (permissions?)
        return FALSE;

    else if ($size === 0)
        // no more space on the file system (?)
        return FALSE;

    if (chmod($dest_path, NEW_FILE_MODE) === FALSE)
        trigger_error("error setting mode of uploaded file: $dest_path");

    /*
     * Only if the move was successful, we attempt to save a receipt file
     * with the current date and time.
     */
    $dt = new DateTime();
    $now = $dt->format(ISO8601_TZ);
    $receipt_path = $dest_path . '.receipt';
    if (file_put_contents($receipt_path, $now, FILE_APPEND) !== FALSE) {
        if (chmod($receipt_path, NEW_FILE_MODE) === FALSE)
            trigger_error("error setting mode of receipt: $receipt_path");
    }

    return TRUE;
}

function files_with_due_date($info, $pair) {
    $files = array();
    foreach ($info as $file => $dates)
        foreach ($dates as $other_pair)
            if ($other_pair == $pair && !in_array($file, $files))
                $files[] = $file;

    return $files;
}

/*
 * Helper functions and other miscellany
 */

// form a path to a file in a student submission directory
function submission_path($num, $type, $username, $file) {
    return submission_dir_path($num, $type, $username) .
           DIRECTORY_SEPARATOR . $file;
}

// form a path to a student's submission directory
function submission_dir_path($num, $type, $username) {
    return SUBMISSIONS_DIR . DIRECTORY_SEPARATOR . $type . $num .
           DIRECTORY_SEPARATOR . $username;
}

// return TRUE if a student has submitted a particular file
function has_submitted($num, $type, $username, $file) {
    return is_file(submission_path($num, $type, $username, $file));
}

// return TRUE if a student has submitted anything for an assignment
function anything_submitted($num, $type, $username) {
    $path = submission_dir_path($num, $type, $username);

    if (!is_dir($path))
        return FALSE;

    return !is_dir_empty($path);
}

// get a DateTime object for the submission time from a receipt, or NULL
function get_receipt_time($num, $type, $username, $filename) {
    $path = submission_path($num, $type, $username, $filename) . '.receipt';
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === FALSE) return NULL;
    $d = DateTime::createFromFormat(ISO8601_TZ, $lines[count($lines) - 1]);
    if ($d === FALSE) return NULL;
    return $d;
}

// unceremoniously delete a student's submission file
function delete_file($ps, $type, $username, $file) {
    return unlink(submission_path($ps, $type, $username, $file));
}

// given a path, return TRUE if the file ends in '.yml'
function is_yml_file($string) {
    return stripos(strrev($string), 'lmy.') === 0;
}

function any_value($array, $key) {
    if (!$array)
        return NULL;

    if (!is_array($array))
        return NULL;

    if (array_key_exists($key, $array))
        return $array[$key];

    foreach ($array as $v)
        if (is_array($v))
            return any_value($v, $key);
}

// given a path to a directory, return TRUE if it is empty
function is_dir_empty($path) {
    if (!is_readable($path))
        trigger_error("path is not readable: $path");

    $d = opendir($path);
    while ($entry = readdir($d))
        if ($entry != '.' && $entry != '..')
            return FALSE;

    return TRUE;
}

function viewable_file_type($extension) {
    global $viewable_file_types;
    return in_array($extension, $viewable_file_types);
}
