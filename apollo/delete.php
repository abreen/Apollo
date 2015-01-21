<?php // 5.3.3

/*
 * delete.php - deletes an uploaded file from a user's subdirectory
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

set_title("Deleted");
use_body_template("delete");

delete_file($_GET['ps'], $_SESSION['username'], $_GET['file']);

$vars = array();
$vars['filename'] = $_GET['file'];
$vars['ps'] = $_GET['ps'];
$vars['assignment'] = $assignment_names[$_GET['ps']];

render_page($vars);
