<?php // 5.3.3

/*
 * view_file.php - displays the contents of a submitted file
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
    trigger_error('the specified file does not exist: ' . $_GET['file']);

$vars = array();

$vars['filename'] = $_GET['file'];
$vars['file'] = htmlspecialchars(file_get_contents($path));

$vars['ps'] = $_GET['ps'];
$vars['assignment'] = $assignment_names[$_GET['ps']];

$ct = get_change_time($_GET['ps'], $_SESSION['username'], $_GET['file']);
$vars['ctime'] = $ct;

set_title("Viewing " . $_GET['file']);
use_body_template("view_file");
render_page($vars);
