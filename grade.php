<?php // 5.3.3

/*
 * grade.php - provides a detailed listing of grade files for an assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/meta.php';
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

$total = 0;
$total_possible = 0;

if (!$grade_files) {
    $str .= '<div class="row"><div class="left"></div><div class="right">';
    $str .= "No grade files found.";
    $str .= '</div></div>';
    $incomplete = TRUE;
} else {
    $i = 0;
    foreach ($grade_files as $group => $triple) {
        $i++;
        $contents = $triple[0];
        $earned_points = $triple[1];
        $total_points = $triple[2];

        $total += $earned_points;
        $total_possible += $total_points;

        $str .= '<div class="row">';

        if ($group) {
            $str .= '<div class="left">Group ' . strtoupper($group) . '</div>';
        } else {
            $str .= '<div class="left"></div>';
        }

        $str .= '<div class="right">';
        $str .= html_pre($contents);
        $str .= '</div>';

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
