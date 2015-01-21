<?php // 5.3.3

/*
 * login.php - handles user log in
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require '../init.php';

require '../accounts.php';
require '../util.php';

session_start();

if (isset($_SESSION['username'])) {
    // don't show this page if a user is already logged in
    header("Location: index.php");
    exit;
}

if (isset($_POST['submitted'])) {
    // form was submitted

    if (!isset($_POST['username'], $_POST['code']) || !$_POST['username'] ||
        !$_POST['code'])
    {
        // cannot log in: something's missing... (the turtles)

        $errors = array();

        if (!isset($_POST['username']) || !$_POST['username'])
            $errors[] = 'Please enter your Kerberos user name.';

        if (!isset($_POST['code']) || !$_POST['code'])
            $errors[] = 'Please enter your access code.';

        set_title('Error logging in');
        use_body_template('login_error');
        render_page(array('errorslist' => html_ul($errors)));

    } else {
        // authenticate the user
        $errors = array();

        switch (authenticate_user($_POST['username'], $_POST['code'])) {
            case AUTHENTICATED:
                $_SESSION['username'] = $_POST['username'];
                header("Location: index.php");
                exit;

            case NO_SUCH_USER:
                $errors[] = 'The user name you entered is not registered.';
                break;

            case WRONG_CODE:
                $errors[] = 'The access code you entered is incorrect.';
                break;
        }

        set_title('Error logging in');
        use_body_template('login_error');
        render_page(array('errorslist' => html_ul($errors)));
    }

    exit;
}

set_title('Log in');

use_body_template('login');

$vars = array();

if (isset($_GET['logged_out'])) {
    $vars['message'] = html_admonition('You have been logged out.');
} else if (isset($_GET['registered'])) {
    $msg = 'An access code has been sent to your BU e-mail address. ' .
           'If you do not receive it within 10 minutes, and the e-mail ' .
           'is not in your junk mail folder, contact the course staff.';

    $vars['message'] = html_admonition($msg);
} else {
    $vars['message'] = '';
}

render_page($vars);
