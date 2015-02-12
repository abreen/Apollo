<?php // 5.3.3

/*
 * upload.php - provides upload fields for files in an assignment
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
$assignment_name = htmlspecialchars(assignment_name($num, $type));

$expected_files = get_files_and_dates($num, $type);

$vars = array();
$vars['username'] = $_SESSION['username'];
$vars['assignment'] = $assignment_name;

if (isset($_POST['submitted'])) {
    $errors = array();
    $successes = array();

    $valid_file_names = array_keys($expected_files);

    $html_names_map = array();
    foreach ($valid_file_names as $name)
        $html_names_map[html_safe($name)] = $name;

    foreach ($_FILES as $fieldname => $data) {
        $filename = $html_names_map[$fieldname];
        $status = upload_status($expected_files[$filename]);

        if ($data['error'] == UPLOAD_ERR_NO_FILE)
            continue;

        if ($data['error'] != UPLOAD_ERR_OK) {
            if ($data['error'] == UPLOAD_ERR_INI_SIZE ||
                $data['error'] == UPLOAD_ERR_FORM_SIZE)
            {
                $errors[$filename] = 'The file you tried to upload is ' .
                                     'too large.';
            } else {
                $errors[$filename] = 'An error occurred uploading this file.';
            }

            continue;
        }

        if ($data['size'] == 0) {
            $errors[$filename] = 'The file you tried to upload is empty.';
            continue;
        }

        if ($data['name'] != $filename) {
            $errors[$filename] = 'The file you tried to upload has the ' .
                                 'wrong name. Please check the file name.';
            continue;
        }

        if ($status != ACCEPTING && $status != ACCEPTING_LATE) {
            $errors[$filename] = 'This file is not being accepted yet or ' .
                                 'its deadline has already passed.';
            continue;
        }

        if (!save_file($num, $type, $_SESSION['username'],
                       $data['tmp_name'], $filename))
        {
            $errors[$filename] = 'An error occurred saving this file.';
            continue;
        }

        $successes[$filename] = 'File uploaded successfully.';
        // TODO do post-upload tests
    }

    $str = "";

    if (count($successes) == 0) {
        $str = "None.";
    } else {
        $str = '<ul>';
        foreach ($successes as $file => $result)
            $str .= '<li><tt>' . $file . '</tt><br>' . $result . '</li>';
        $str .= '</ul>';
    }

    $vars['successes'] = $str;

    if (count($errors) == 0) {
        $str = "None.";
    } else {
        $str = '<ul>';
        foreach ($errors as $file => $result)
            $str .= '<li><tt>' . $file . '</tt><br>' . $result . '</li>';
        $str .= '</ul>';
    }

    $vars['failures'] = $str;

    $c = count($successes);
    if ($c == 1)
        $vars['summary'] = 'One file was uploaded successfully.';
    else
        $vars['summary'] = $c . ' files were uploaded successfully.';

    if (count($errors) == 1) {
        $vars['message'] = html_admonition('There was a problem with your ' .
                                           'upload.', 'Error', 'error');
    } else if (count($errors) > 1) {
        $vars['message'] = html_admonition('There were problems with your ' .
                                           'upload.', 'Error', 'error');
    } else {
        $vars['message'] = '';
    }

    $vars['url'] = "upload.php?type=$type&num=$num";

    set_title('Upload results for ' . $assignment_name);
    use_body_template('upload_results');
    render_page($vars);
    exit;
}

set_title('Upload files for ' . $assignment_name);
use_body_template('upload');

$str = '';

foreach ($expected_files as $file => $dates) {
    $html_safe = html_safe($file);
    $extension = file_extension($file);

    $status = upload_status($dates);
    $allowed = $status == ACCEPTING || $status == ACCEPTING_LATE;

    if ($allowed) {
        if ($status == ACCEPTING_LATE) {
            $str .= '<div class="row late">';
        } else {
            $str .= '<div class="row">';
        }
    } else {
        $str .= '<div class="row disabled">';
    }

    // left part: file name
    $str .= '<div class="left"><tt>' . $file . '</tt></div>';

    // right part: info. and upload field
    $str .= '<div class="right">';

    if (has_submitted($num, $type, $_SESSION['username'], $file)) {
        $url = '?type=' . $type . '&num=' . $num . '&file=' . $file;
        $ctime = get_modification_time($num, $type,
                                       $_SESSION['username'], $file);

        $str .= '<tt>' . $file . '</tt> was uploaded on ' .
                $ctime . '.<br>';

        $str .= '<ul>';
        $str .= '<li><a class="php" href="download.php' . $url .
                '">Download this file</a></li>';

        if (viewable_file_type($extension)) {
            $str .= '<li><a class="php" href="view_file.php' . $url .
                    '">View this file</a></li>';
        }

        $str .= '</ul>';
    }

    if ($allowed) {
        $str .= '<input type="file" name="' . $html_safe .
                '" accept=".' . $extension . '">';

        if ($status == ACCEPTING_LATE)
            $str .= '<div class="caption latecaption">Accepting late submissions</div>';
        else
            $str .= '<div class="caption acceptingcaption">Accepting submissions</div>';

    } else {
        $str .= '<input type="file" name="' . $html_safe .
                '" disabled="disabled">';

        $str .= '<div class="caption">Not accepting submissions</div>';
    }

    $str .= '<div class="caption"><ul>';
    foreach ($dates as $pair) {
        $d = $pair[0];
        $m = $pair[1];      // multiplier

        $str .= '<li>After ';
        $str .= $d->format(FRIENDLY_DATE_FORMAT) . ', ';

        if ($m < 1.00) {
            $str .= 'this file will be accepted with a ' . $m * 100 .
                    '% penalty';
        } else {
            $str .= 'this file will not be accepted';
        }

        $str .= '</li>';
    }

    $str .= '</ul></div>';       // end .caption

    $str .= '</div>';       // end .right

    $str .= '</div>';       // end .row
}

$vars['rows'] = $str;

render_page($vars);
