<?php // 5.3.3

/*
 * grade.php - provides a detailed listing of grade files for an assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require '../init.php';
require '../socrates.php';

// redirects to log in page if necessary
require 'auth.php';

require '../util.php';

if (!isset($_GET['ps']))
    trigger_error('no assignment number specified');

$ps = $_GET['ps'];

check_assignment($ps);

$assignment_name = $assignment_names[$ps];

set_title("Grade details for " . $assignment_name);
use_body_template("grade");

$vars = array();
$vars['username'] = $_SESSION['username'];
$vars['assignment'] = $assignment_name;

$grade_files = get_grade_files($_SESSION['username'], $ps);

$str = "";
$incomplete = FALSE;

if (!$grade_files) {
    $str .= '<div class="row"><div class="left"></div><div class="right">';
    $str .= "No grade files found.";
    $str .= '</div></div>';
    $incomplete = TRUE;
} else {
    $crits = get_criteria_files($ps);
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
            $str .= '<div class="right">No grade file this group yet.</div>';
        }

        $str .= '</div>';           // end .row
    }
}

// calculate summary
$str .= '<div class="row">';
$str .= '<div class="left">Summary</div>';

if ($incomplete) {
    $str .= '<div class="right"><span class="summary">Incomplete</span></div>';
} else {
    $str .= '<div class="right score">';
    $str .= '<span class="major">' . $total .'</span>';
    $str .= '/<span class="minor">' . $total_possible .'</span>';
    $str .= '</div>';
}

$str .= '</div>';

$vars['rows'] = $str;

render_page($vars);
