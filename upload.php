<?php

/*
 * upload.php - provides upload fields for files in an assignment
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/meta.php';
require 'lib/log.php';
require 'lib/util.php';

// redirects to log in page if necessary
require 'auth.php';

if (!isset($_GET['type']) || !isset($_GET['num'])) {
    header('Location: index.php');
    exit;
}

check_assignment($_GET['num'], $_GET['type']);

$num = $_GET['num'];
$type = $_GET['type'];
$assignment_name = htmlspecialchars(assignment_name($num, $type));

$expected_files = get_files_and_dates($num, $type);

if (!$expected_files)
    trigger_error('invalid assignment number: ' . $num);

$vars = array();
$vars['username'] = $_SESSION['username'];
$vars['assignment'] = $assignment_name;

if (isset($_POST['submitted'])) {
    $failures = array();
    $warnings = array();
    $successes = array();

    $valid_file_names = array_keys($expected_files);

    $html_names_map = array();
    foreach ($valid_file_names as $name)
        $html_names_map[html_safe($name)] = $name;

    foreach ($_FILES as $fieldname => $data) {
        $filename = $html_names_map[$fieldname];
        $status = upload_status($expected_files[$filename]);
        $extension = file_extension($filename);

        if ($data['error'] == UPLOAD_ERR_NO_FILE)
            continue;

        if ($data['error'] != UPLOAD_ERR_OK) {
            if ($data['error'] == UPLOAD_ERR_INI_SIZE ||
                $data['error'] == UPLOAD_ERR_FORM_SIZE)
            {
                $failures[$filename] = 'The file you tried to upload is ' .
                                       'too large.';
            } else {
                $failures[$filename] = 'An error occurred uploading this file.';
            }

            continue;
        }

        if ($data['size'] == 0) {
            $failures[$filename] = 'The file you tried to upload is empty.';
            continue;
        }

        if ($data['name'] != $filename) {
            $failures[$filename] = 'The file you tried to upload has the ' .
                                   'wrong name. You uploaded a file with ' .
                                   'the name ' . html_tt($data['name']) . ' ' .
                                   'in the upload area for ' .
                                   html_tt($filename) . '. Please check that ' .
                                   'you are uploading the correct ' .
                                   'file.';

            if ($extension == 'java') {
                $failures[$filename] .= ' If you need to rename your Java ' .
                                        'file, <strong>make sure you change ' .
                                        'the class name to match</strong>.';
            }

            continue;
        }

        if ($status != ACCEPTING && $status != ACCEPTING_LATE) {
            $failures[$filename] = 'This file is not being accepted yet or ' .
                                   'its deadline has already passed.';
            continue;
        }

        $dest_path = save_file(
            $num, $type, $_SESSION['username'], $data['tmp_name'], $filename
        );

        if ($dest_path === false) {
            $failures[$filename] = 'An error occurred saving this file.';
            continue;
        }

        if ($extension == 'py') {
            // check Python syntax with the helper check.py script
            $output = array();
            $status = -1;

            exec("python lib/check.py $dest_path", $output, $status);

            if ($status === -1) {
                // something went wrong with the helper script; do nothing
                $successes[$filename] = 'File uploaded successfully.';
            } elseif ($status != 0 && count($output) == 4) {
                // if count($output) != 4, then there must have been
                // an error running the script
                $error_type = $output[0];
                $error_line = $output[1];
                $error_msg = $output[2];
                $error_code = $output[3];

                if (in_array($error_type[0], array('a', 'e', 'i', 'o', 'u')))
                    $kind = 'an ' . $error_type;
                else
                    $kind = 'a ' . $error_type;

                if ($error_line == -1) {
                    $warnings[$filename] = "Your file was uploaded, but the " .
                        "Python interpreter reported $kind (" .
                        html_tt(htmlspecialchars($error_msg)) . ').';
                } else {
                    $warnings[$filename] = "Your file was uploaded, but $kind " .
                        "was found near line $error_line: <pre>" .
                        htmlspecialchars($error_code) . '</pre>' .
                        'The Python interpreter reported ' .
                        html_tt(htmlspecialchars($error_msg)) . '.';
                }

                $warnings[$filename] .= ' <strong>Please review this code ' .
                        'and re-upload it, if necessary.</strong>';

            } else {
                $successes[$filename] = 'File uploaded successfully.';
            }
        } else {
            $successes[$filename] = 'File uploaded successfully.';
        }
    }

    log_submission($num, $type, $_SESSION['username'], array_keys($successes),
                   $_SERVER['REMOTE_ADDR']);

    $str = '';

    if (count($failures) == 0) {
        $str = 'None.';
    } else {
        $str = '<ul>';
        foreach ($failures as $file => $result)
            $str .= '<li><tt>' . $file . '</tt><br>' . $result . '</li>';
        $str .= '</ul>';
    }

    $vars['failures'] = $str;

    if (count($warnings) == 0) {
        $str = 'None.';
    } else {
        $str = '<ul>';
        foreach ($warnings as $file => $result)
            $str .= '<li><tt>' . $file . '</tt><br>' . $result . '</li>';
        $str .= '</ul>';
    }

    $vars['warnings'] = $str;

    if (count($successes) == 0) {
        $str = 'None.';
    } else {
        $str = '<ul>';
        foreach ($successes as $file => $result)
            $str .= '<li><tt>' . $file . '</tt><br>' . $result . '</li>';
        $str .= '</ul>';
    }

    $vars['successes'] = $str;

    $c = count($successes) + count($warnings);
    if ($c == 1)
        $str = 'One file was uploaded successfully.';
    else
        $str = $c . ' files were uploaded successfully.';

    $vars['summary'] = $str;

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
        $time = get_receipt_time($num, $type, $_SESSION['username'], $file);

        if ($time) {
            $str .= '<tt>' . $file . '</tt> was uploaded on ' .
                    $time->format(DATE_FORMAT_WITH_SECONDS) . '.<br>';
        }

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

$vars['formtag'] = '<form enctype="multipart/form-data" method="post" class="upload" '  .
                   'action="upload.php?' . http_build_query($_GET) . '">';
render_page($vars);
