<?php // 5.3.3

/*
 * register.php - sends an access code to a student
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require '../init.php';

require '../util.php';
require '../accounts.php';

if (isset($_POST['submitted'])) {
    // the form was submitted

    if (!isset($_POST['username']) || !$_POST['username']) {
        $errors[] = 'Please enter your Kerberos user name.';
        set_title('Error registering');
        use_body_template('register_error');
        render_page(array('errorslist' => html_ul($errors)));

    } else {
        $code = generate_access_code();
        $return_val = register_user($_POST['username'], $code);

        $errors = array();
        switch ($return_val) {
            case BAD_USERNAME:
                $errors[] = 'The user name you entered contains invalid ' .
                            'characters. Please check that you typed your ' .
                            'Kerberos user name correctly.';
                break;

            case UNKNOWN_USER:
                $errors[] = 'The user name you entered does not seem to ' .
                            'be a valid Kerberos user name with a CS ' .
                            'account. If you are certain you set up a CS ' .
                            'account, send an e-mail to the course staff.';
                break;

            case ALREADY_REGISTERED:
                $errors[] = 'The user name you entered is already ' .
                            'registered. If you have lost your access ' .
                            'code, send an e-mail to the course staff.';
                break;
        }

        if (!empty($errors)) {
            set_title('Error registering');
            use_body_template('register_error');
            render_page(array('errorslist' => html_ul($errors)));
            exit;
        }

        // send the access code via e-mail
        send_registration_email($_POST['username'], $code);

        header("Location: login.php?registered");
    }

    exit;
}

set_title('Register');
use_body_template('register');
render_page();
