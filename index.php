<?php // 5.3.3

/*
 * index.php - provides a summary of upload/grade links for each assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/meta.php';
require 'lib/util.php';

// redirects to log in page if necessary
require 'auth.php';

$status_map =
    array(TOO_EARLY => array('early', 'Not accepting submissions'),
          ACCEPTING => array('accepting', 'Accepting submissions'),
          ACCEPTING_LATE => array('late', 'Accepting late submissions'));

function create_row($num, $type) {
    global $status_map;

    $info = get_files_and_dates($num, $type);
    if ($info === NULL)
        // no metafile for this assignment
        return '';

    $str = '';

    $parts = get_grade_files($_SESSION['username'], $num, $type);

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
            $str .= '<li><a class="php" href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">View uploaded files</a></li>';
        }
    } else if ($status != CLOSED) {
        if (anything_submitted($num, $type, $_SESSION['username'])) {
            $str .= '<li><a class="php" href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">Upload/view uploaded files</a></li>';
        } else {
            $str .= '<li><a class="php" href="upload.php?type=' . $type .
                    '&num=' . $num .
                    '">Upload files</a></li>';
        }
    }

    if ($parts) {
        $str .= '<li><a class="php" href="grade.php?type=' . $type .
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

// note: $ps_names comes from lib/meta.php
foreach ($ps_names as $num => $ps_name)
    $str .= create_row($num, PROBLEM_SET);

$vars['psrows'] = $str;
$str = '';

// note: $lab_names comes from lib/meta.php
foreach ($lab_names as $num => $lab_name)
    $str .= create_row($num, LAB);

$vars['labrows'] = $str;

render_page($vars);
