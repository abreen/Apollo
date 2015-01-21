<?php // 5.3.3

/*
 * download.php - downloads an uploaded file in a user's subdirectory
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require '../init.php';
require '../socrates.php';

// redirects to log in page if necessary
require 'auth.php';

if (!isset($_GET['ps']))
    trigger_error('no assignment number specified');

if (!isset($_GET['file']))
    trigger_error('no file specified');

check_assignment($_GET['ps']);

$path = submission_path($_GET['ps'], $_SESSION['username'], $_GET['file']);

if (!file_exists($path))
    trigger_error('file does not exist: ' . $_GET['file']);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename=' . basename($path));
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));

readfile($path);
