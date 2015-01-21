<?php // 5.3.3

/*
 * auth.php - causes users not logged in to be redirected to login
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

session_start();

if (!isset($_SESSION['username']) || !$_SESSION['username']) {
    header("Location: login.php");
    die;
}
