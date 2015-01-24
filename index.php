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

set_title('Homework submissions');
use_body_template('submissions');

$vars = array();
$vars['username'] = $_SESSION['username'];

$nums = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

$status_map =
    array(TOO_EARLY => array('early', 'Not accepting submissions'),
          ACCEPTING => array('accepting', 'Accepting submissions'),
          ACCEPTING_LATE => array('late', 'Accepting late submissions'));

$str = "";

foreach ($nums as $ps) {
    $parts = get_grade_files($_SESSION['username'], $ps);
    $info = get_files_and_dates($ps);
    $status = assignment_upload_status($info);

    $str .= '<div class="row">';

    $str .= '<div class="left">' . $assignment_names[$ps] .
            "</div>\n";

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
        if (anything_submitted($ps, $_SESSION['username'])) {
            $str .= '<li><a href="upload.php?ps=' . $ps .
                    '">View uploaded files</a></li>';
        }
    } else if ($status != CLOSED) {
        if (anything_submitted($ps, $_SESSION['username'])) {
            $str .= '<li><a href="upload.php?ps=' . $ps .
                    '">Upload/view uploaded files</a></li>';
        } else {
            $str .= '<li><a href="upload.php?ps=' . $ps .
                    '">Upload files</a></li>';
        }
    }

    if ($parts) {
        $str .= '<li><a href="grade.php?ps=' . $ps .
                '">See grade</a></li>';
    }

    $str .= '</ul>';

    $id = 'ps' . $ps . '-dates';

    $str .= '<div class="caption">';
    $str .= '<a class="js" onclick="';

    $str .= <<<JS
e = document.getElementById('$id');
if (e.style.display != 'none')
    e.style.display = 'none';
else
    e.style.display = 'inherit';
JS;

    $str .= '">Display due dates</a><br>';
    $str .= '<ul id="' . $id . '" style="display: none;">';

    $due_dates = due_dates($info);
    foreach ($due_dates as $pair) {
        $d = $pair[0];
        $m = $pair[1];      // multiplier

        $str .= '<li>After ';
        $str .= $d->format(FRIENDLY_DATE_FORMAT) . ', ';

        if ($m < 1.00) {
            $str .= 'these files will be accepted with a ' . $m * 100 .
                    '% penalty:';
        } else {
            $str .= 'these files will not be accepted:';
        }

        $files = files_with_due_date($info, $pair);
        $str .= html_ul(array_map('html_tt', $files));

        $str .= '</li>';
    }

    $str .= '</ul></div>';       // end .caption

    $str .= '</div>';       // end .right

    $str .= '</div>';       // end .row
}

$vars['rows'] = $str;

render_page($vars);
