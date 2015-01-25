<?php // 5.3.3

/*
 * index.php - provides a summary of upload/grade links for each assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/socrates.php';
require 'lib/util.php';

// redirects to log in page if necessary
require 'auth.php';

$nums = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

$status_map =
    array(TOO_EARLY => array('early', 'Not accepting submissions'),
          ACCEPTING => array('accepting', 'Accepting submissions'),
          ACCEPTING_LATE => array('late', 'Accepting late submissions'));

function create_row($num, $type) {
    global $status_map;

    $str = '';

    $parts = get_grade_files($_SESSION['username'], $num, $type);
    $info = get_files_and_dates($num, $type);

    if ($info === NULL)
        return '';

    $status = assignment_upload_status($info);

    $str .= '<div class="row">';

    $str .= '<div class="left">' .
            htmlspecialchars(assignment_name($num, $type)) .
            '</div>';

    $str .= '<div class="right">';

    if ($status != CLOSED) {
        $status_class = $status_map[$status][0];
        $status_str = $status_map[$status][1];
        $str .= '<span class="status ' . $status_class .
                '">' . $status_str . '</span>';
    } else if ($parts) {
        $str .= '<span class="status graded">Graded</span>';
    } else {
        $str .= '<span class="status notgraded">Not yet graded</span>';
    }

    $str .= '<br>';

    $str .= '<ul>';

    if ($status == CLOSED or $status == TOO_EARLY) {
        if (anything_submitted($num, $type, $_SESSION['username'])) {
            $str .= '<li><a href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">View uploaded files</a></li>';
        }
    } else if ($status != CLOSED) {
        if (anything_submitted($num, $type, $_SESSION['username'])) {
            $str .= '<li><a href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">Upload/view uploaded files</a></li>';
        } else {
            $str .= '<li><a href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">Upload files</a></li>';
        }
    }

    if ($parts) {
        $str .= '<li><a href="grade.php?type=' . $type .
                '&num=' . $num .
                '">View grade</a></li>';
    }

    $str .= '</ul>';
    $str .= '</div>';       // end .right
    $str .= '</div>';       // end .row

    return $str;
}

set_title('Homework submissions');
use_body_template('submissions');

$vars = array();
$vars['username'] = $_SESSION['username'];

$str = '';

foreach ($nums as $num) {
    if (ps_exists($num))
        $str .= create_row($num, PROBLEM_SET);

    if (lab_exists($num))
        $str .= create_row($num, LAB);
}

$vars['rows'] = $str;

render_page($vars);
