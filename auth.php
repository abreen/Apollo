<?php

/*
 * auth.php - verifies that Kerberos login has occurred
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

session_start();

if (!isset($_SERVER['REMOTE_USER'])) {
    trigger_error('could not authenticate using Kerberos', E_USER_ERROR);
} else {
    $_SESSION['username'] = $_SERVER['REMOTE_USER'];
}
