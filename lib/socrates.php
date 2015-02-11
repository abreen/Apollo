<?php // 5.3.3

/*
 * socrates.php - PHP interface to socrates' criteria and grade files
 *
 * This file contains functions that allow a PHP script to load criteria
 * and grade files from a socrates installation. The criteria files are
 * read and parsed to produce information about assignments, their due dates,
 * and the files required. The grade files can be retrieved (the contents
 * of their TAR files read) to obtain information about students' grades.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require_once 'files.php';
require_once 'spyc/Spyc.php';

/*
 * Constants
 */
define('DATE_FORMAT', 'F j, Y g:i A');
define('FRIENDLY_DATE_FORMAT', 'F j, Y \a\t g:i A');
define('DATE_FORMAT_WITH_SECONDS', 'F j, Y \a\t g:i:s A');

// upload status values given to an assignment
define('TOO_EARLY', 0);
define('ACCEPTING', 1);
define('ACCEPTING_LATE', 2);
define('CLOSED', 3);

if (!is_readable(DROPBOX_DIR))
    trigger_error('failed to access dropbox: ' . DROPBOX_DIR);

if (!is_readable(CRITERIA_DIR))
    trigger_error('failed to access criteria directory: ' . CRITERIA_DIR);

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
$viewable_file_types = array('py', 'txt');

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
 * mapping groups (e.g., "a") to an array pair (a, b), where a is the
 * contents of the grade file for that group, and b is the total points
 * earned by the student. This function returns FALSE if the assignment
 * has not been graded at all.
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
        $pattern = '/ps' . $num. '([a-z])\-grade\.txt/';
    else
        $pattern = '/lab' . $num . '([a-z])\-grade\.txt/';

    $files = scandir($dir_path);
    foreach ($files as $filename) {
        if ($filename == '.' || $filename == '..' || is_dotfile($filename))
            continue;

        $matches = array();
        if (preg_match($pattern, $filename, $matches) !== 1)
            // this grade file is not for this assignment
            continue;

        $group = $matches[1];

        $contents = file_get_contents($dir_path . DIRECTORY_SEPARATOR .
                                      $filename);

        if (preg_match('/Total:\s*(\d+)/', $contents, $matches) !== 1)
            trigger_error("malformed grade file: $filename");

        $earned_points = $matches[1];

        $grade_files[$group] = array($contents, $earned_points);
    }

    if (count($grade_files) == 0)
        return FALSE;
    else
        return $grade_files;
}

/*
 * Given an assignment number (e.g., "10") and an assignment type
 * (e.g., LAB or PROBLEM_SET), return an associative array
 * mapping file names (e.g., "ps10pr2.py") to an associative array
 * containing mappings from DateTime objects to late deduction
 * multipliers. The DateTime objects are the due dates parsed from the
 * socrates criteria files (all groups). If there are no criteria files
 * for the assignment, this function returns NULL.
 */
function get_files_and_dates($num, $type) {
    check_assignment($num, $type);

    $groups = get_criteria_files($num, $type);

    if ($groups === NULL)
        return NULL;

    $info = array();

    foreach ($groups as $parsed) {
        $dates = array();

        // parse the string dates into PHP date objects
        foreach ($parsed['due'] as $multiplier => $date) {
            $d = DateTime::createFromFormat(DATE_FORMAT, $date);

            if ($d === FALSE)
                trigger_error("could not parse date: $date");

            $pair = array($d, $multiplier);

            $dates[] = $pair;
        }

        // for each file, associate its file name with the array of dates
        foreach ($parsed['files'] as $file)
            if (array_key_exists($file['path'], $info))
                $info[$file['path']] =
                    array_merge($info[$file['path']], $dates);
            else
                $info[$file['path']] = $dates;
    }

    return $info;
}

/*
 * Given an assignment number (e.g., "10"), return an array of parsed
 * YAML criteria files for that assignment, drawn from the directory
 * CRITERIA_DIR. If there are no criteria files for the specified
 * assignment, this function returns NULL.
 */
function get_criteria_files($num, $type) {
    check_assignment($num, $type);

    $crit_files_path = CRITERIA_DIR . DIRECTORY_SEPARATOR;
    if ($type == PROBLEM_SET)
        $crit_files_path .= 'ps' . $num;
    else
        $crit_files_path .= 'lab' . $num;

    if (!is_dir($crit_files_path))
        return NULL;

    $groups = scandir($crit_files_path);

    $files = array();
    foreach ($groups as $yml_file) {
        if ($yml_file == '.' || $yml_file == '..' || !is_yml_file($yml_file))
            continue;

        $path = $crit_files_path . DIRECTORY_SEPARATOR . $yml_file;
        $files[] = Spyc::YAMLLoad($path);
    }

    return $files;
}

/*
 * Given an array of parsed YAML criteria files (e.g., one obtained by
 * calling get_criteria_files()), return an array of the grading groups
 * present in the criteria files for the assignment.
 */
function get_groups($criteria_files) {
    $groups = array();
    foreach ($criteria_files as $file)
        $groups[] = $file['group'];

    return $groups;
}

/*
 * Given an array of parsed YAML criteria files (e.g., one obtained by
 * calling get_criteria_files()), return the total number of points the
 * assignment is worth. This is obtained by summing the point values for
 * all files across all grading groups.
 */
function get_total_points($criteria_files) {
    $sum = 0;
    foreach ($criteria_files as $file)
        foreach ($file['files'] as $required_file)
            $sum += $required_file['point_value'];

    return $sum;
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
 * "destination" file name, move the temporary file into the student's
 * submission directory (the one under SUBMISSIONS_DIR). If the student's
 * submission directory does not exist, this function creates it. If the
 * subdirectory for the assignment also doesn't exist, this function will
 * create it. This function returns TRUE if the file was moved correctly.
 */
function save_file($num, $type, $username, $tmp_path, $dest_name) {
    check_assignment($num, $type);

    $dest_path = SUBMISSIONS_DIR . DIRECTORY_SEPARATOR . $username;

    if (!file_exists($dest_path))
        // create new directory for this user
        apollo_new_directory($dest_path);

    if ($type == PROBLEM_SET)
        $dest_path .= DIRECTORY_SEPARATOR . 'ps' . $num;
    else
        $dest_path .= DIRECTORY_SEPARATOR . 'lab' . $num;

    if (!file_exists($dest_path))
        // create new subdirectory for this assignment 
        apollo_new_directory($dest_path);

    $dest_path .= DIRECTORY_SEPARATOR . $dest_name;

    if (move_uploaded_file($tmp_path, $dest_path) === FALSE)
        trigger_error("uploaded file could not be moved: $tmp_path");

    if (chmod($dest_path, NEW_FILE_MODE) === FALSE)
        trigger_error("error setting mode of uploaded file: $dest_path");

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
function submission_path($ps, $type, $username, $file) {
    return submission_dir_path($ps, $type, $username) .
           DIRECTORY_SEPARATOR . $file;
}

// form a path to a student's submission directory
function submission_dir_path($ps, $type, $username) {
    return SUBMISSIONS_DIR . DIRECTORY_SEPARATOR . $username .
           DIRECTORY_SEPARATOR . $type . $ps;
}

// return TRUE if a student has submitted a particular file
function has_submitted($ps, $type, $username, $file) {
    return is_file(submission_path($ps, $type, $username, $file));
}

// return TRUE if a student has submitted anything for an assignment
function anything_submitted($ps, $type, $username) {
    $path = submission_dir_path($ps, $type, $username);

    if (!is_dir($path))
        return FALSE;

    return !is_dir_empty($path);
}

// return a date (in DATE_FORMAT_WITH_SECONDS) of a submitted file's ctime
function get_change_time($ps, $type, $username, $file) {
    return date(DATE_FORMAT_WITH_SECONDS,
                filectime(submission_path($ps, $type, $username, $file)));
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
