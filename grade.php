<?php // 5.3.3

/*
 * grade.php - provides a detailed listing of grade files for an assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/socrates.php';
require 'lib/util.php';

// redirects to log in page if necessary
require 'auth.php';

if (!isset($_GET['type']) || !isset($_GET['num']))
    trigger_error('invalid or not enough parameters');

check_assignment($_GET['num'], $_GET['type']);

$num = $_GET['num'];
$type = $_GET['type'];
$assignment_name = assignment_name($num, $type);

set_title('Grade details for ' . $assignment_name);
use_body_template('grade');

$vars = array();
$vars['username'] = $_SESSION['username'];
$vars['assignment'] = $assignment_name;

$grade_files = get_grade_files($_SESSION['username'], $num, $type);

$str = '';
$incomplete = FALSE;

if (!$grade_files) {
    $str .= '<div class="row"><div class="left"></div><div class="right">';
    $str .= "No grade files found.";
    $str .= '</div></div>';
    $incomplete = TRUE;
} else {
    $crits = get_criteria_files($num, $type);
    $groups = get_groups($crits);
    $total_possible = get_total_points($crits);
    $total = 0;

    foreach ($groups as $group) {
        $str .= '<div class="row">';

        $str .= '<div class="left">Group ' . strtoupper($group) . '</div>';

        if (array_key_exists($group, $grade_files)) {
            $pair = $grade_files[$group];
            $contents = $pair[0];
            $earned = $pair[1];

            $total += $earned;

            $str .= '<div class="right">';
            $str .= '<pre>' . $contents . '</pre>';
            $str .= '</div>';
        } else {
            $incomplete = TRUE;
            $str .= '<div class="right">No grade file yet</div>';
        }

        $str .= '</div>';           // end .row
    }
}

// calculate summary
$str .= '<div class="row">';
$str .= '<div class="left">Summary</div>';

if ($incomplete) {
    $str .= '<div class="right"><span class="summary">Not completely ' .
            'graded</span><div class="caption">Your total will be shown ' .
            'when all groups have been graded.</div></div>';
} else {
    $str .= '<div class="right score">';
    $str .= '<span class="major">' . $total .'</span>';
    $str .= '/<span class="minor">' . $total_possible .'</span>';
    $str .= '</div>';
}

$str .= '</div>';

$vars['rows'] = $str;

render_page($vars);
