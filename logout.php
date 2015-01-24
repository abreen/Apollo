<?php // 5.3.3

/*
 * logout.php - destroys a session and redirects to log in page
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';

require 'auth.php';

if (!isset($_SESSION['username']))
    session_start();

session_destroy();

header("Location: login.php?logged_out");
