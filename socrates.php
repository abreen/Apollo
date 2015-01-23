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
    trigger_error('failed to access dropbox');

if (!is_readable(CRITERIA_DIR))
    trigger_error('failed to access criteria directory');

if (!is_readable(SUBMISSIONS_DIR))
    trigger_error('failed to access submissions directory');

if (MAINTENANCE_MODE)
    trigger_error("This application has been put in maintenance mode.\n" .
                  'We expect to restore service soon.');


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

$assignment_names = array(0  => 'Problem Set 0',
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

// if the specified assignment number is incorrect, trigger an error
function check_assignment($key) {
    global $assignment_names;

    if (array_key_exists($key, $assignment_names) === FALSE)
        trigger_error("invalid assignment number: $key");
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
function get_grade_files($username, $assignment) {
    check_assignment($assignment);

    $dir_path = DROPBOX_DIR . DIRECTORY_SEPARATOR . $username;

    if (!is_dir($dir_path))
        // if this directory doesn't exist, no grades are present
        return FALSE;

    if (!is_readable($dir_path))
        trigger_error("grade directory not readable: $username");

    $grade_files = array();
    $pattern = '/ps' . $assignment . '([a-z])\-grade\.txt/';

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
 * Given an assignment number (e.g., "10"), return an associative array
 * mapping file names (e.g., "ps10pr2.py") to an associative array
 * containing mappings from DateTime objects to late deduction
 * multipliers. The DateTime objects are the due dates parsed from the
 * socrates criteria files (all groups).
 */
function get_files_and_dates($assignment) {
    check_assignment($assignment);

    $groups = get_criteria_files($assignment);
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
 * CRITERIA_DIR.
 */
function get_criteria_files($assignment) {
    check_assignment($assignment);

    $crit_files_path = CRITERIA_DIR . DIRECTORY_SEPARATOR .
                       'ps' . $assignment;

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
function save_file($ps, $username, $tmp_path, $dest_name) {
    check_assignment($ps);

    $dest_path = SUBMISSIONS_DIR . DIRECTORY_SEPARATOR . $username;

    if (!file_exists($dest_path)) {
        umask(0000);
        if (!mkdir($dest_path))
            trigger_error('failed to create new submissions user directory');
    }

    $dest_path .= DIRECTORY_SEPARATOR . 'ps' . $ps;

    if (!file_exists($dest_path)) {
        umask(0000);
        if (!mkdir($dest_path))
            trigger_error('failed to create new submissions subdirectory');
    }

    $dest_path .= DIRECTORY_SEPARATOR . $dest_name;

    return move_uploaded_file($tmp_path, $dest_path);
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
function submission_path($ps, $username, $file) {
    return submission_dir_path($ps, $username) .
           DIRECTORY_SEPARATOR . $file;
}

// form a path to a student's submission directory
function submission_dir_path($ps, $username) {
    return SUBMISSIONS_DIR . DIRECTORY_SEPARATOR . $username .
           DIRECTORY_SEPARATOR . 'ps' . $ps;
}

// return TRUE if a student has submitted a particular file
function has_submitted($ps, $username, $file) {
    return is_file(submission_path($ps, $username, $file));
}

// return TRUE if a student has submitted anything for an assignment
function anything_submitted($ps, $username) {
    $path = submission_dir_path($ps, $username);

    if (!is_dir($path))
        return FALSE;

    return !is_dir_empty($path);
}

// return a date (in DATE_FORMAT_WITH_SECONDS) of a submitted file's ctime
function get_change_time($ps, $username, $file) {
    return date(DATE_FORMAT_WITH_SECONDS,
                filectime(submission_path($ps, $username, $file)));
}

// unceremoniously delete a student's submission file
function delete_file($ps, $username, $file) {
    return unlink(submission_path($ps, $username, $file));
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
