<?php // 5.3.3

/*
 * accounts.php - simple PHP library for storing user access codes
 *
 * This file contains functions that allow users with BU user names
 * to register and obtain an access code. When a user wants to register,
 * an access code is generated, a hash stored in the file system, and the
 * code is sent to their e-mail inbox.
 *
 * This file contains functions for generating an access code,
 * saving a code to the file system, and sending an e-mail with the
 * access code.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require_once 'files.php';

// valid characters in an access code
$valid_code_chars = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
                          'k', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u',
                          'v', 'w', 'x', 'y', 'z', '1', '2', '3', '4', '5',
                          '6', '7', '8', '9');

// used to check user names (only word characters and numbers allowed)
define('VALID_USERNAME_REGEX', '/\w+/');

// constants returned by authenticate_user()
define('AUTHENTICATED', 1);
define('NO_SUCH_USER', 2);
define('BAD_CODE', 3);
define('WRONG_CODE', 4);

// constants returned by register_user()
define('UNKNOWN_USER', 11);
define('ALREADY_REGISTERED', 12);

// returned by either authenticate_user() or register_user()
define('BAD_USERNAME', 21);


if (!is_readable(ACCOUNTS_DIR))
    trigger_error('failed to access accounts directory: ' .
                  ACCOUNTS_DIR);

function generate_access_code() {
    global $valid_code_chars;

    $chars_len = count($valid_code_chars);

    $code = '';
    for ($i = 0; $i < CODE_LENGTH; $i++)
        $code .= $valid_code_chars[mt_rand(0, $chars_len - 1)];

    return $code;
}

function is_valid_access_code($code) {
    global $valid_code_chars;

    $len = strlen($code);
    for ($i = 0; $i < $len; $i++)
        if (!in_array($code[$i], $valid_code_chars))
            return FALSE;

    return TRUE;
}

function is_valid_username($username) {
    $matches = array();
    preg_match(VALID_USERNAME_REGEX, $username, $matches);
    return count($matches) > 0 && $matches[0] === $username;
}

function hash_file_path($username) {
    return ACCOUNTS_DIR . DIRECTORY_SEPARATOR . $username . '.hash';
}

function hash_access_code($code) {
    return hash('sha256', $code);
}

/*
 * Given a user name and an access code, this function associates the
 * user name to an access code, hashes the code, and saves the hash in
 * the file system.
 *
 * This function triggers an error if the user already has an
 * access code.
 */
function register_user($username, $code) {
    if (!is_valid_username($username))
        return BAD_USERNAME;

    $path = hash_file_path($username);

    if (CHECK_NEW_USER_EXISTS) {
        // check if user has an account
        $safe_cmd = escapeshellcmd('id ' . escapeshellarg($username));

        $cmd_output = array();
        $cmd_return_val = -1;

        exec($safe_cmd, $cmd_output, $cmd_return_val);

        if ($cmd_return_val !== 0)
            return UNKNOWN_USER;
    }

    // check if user already has a code
    if (is_file($path))
        return ALREADY_REGISTERED;

    $hash = hash_access_code($code);

    apollo_new_file($path, $hash);
}

/*
 * Given a user name and an access code, hash the code and check the file
 * system for a matching hash. This function returns one of the following
 * constants: AUTHENTICATED, NO_SUCH_USER, WRONG_CODE.
 */
function authenticate_user($username, $code) {
    if (!is_valid_username($username))
        return BAD_USERNAME;

    if (!is_valid_access_code($code))
        return BAD_CODE;

    $path = hash_file_path($username);

    if (!is_file($path))
        return NO_SUCH_USER;

    $hash = hash_access_code($code);

    $hash_on_fs = file_get_contents($path);

    if ($hash !== $hash_on_fs)
        return WRONG_CODE;
    else
        return AUTHENTICATED;
}

/*
 * Given a user name and an access code, send the user an e-mail
 * containing the access code.
 */
function send_registration_email($username, $code) {
    $support_email = SUPPORT_EMAIL;
    $body = <<<BODY
Dear $username,

Our records show that you requested an access code for Apollo, the homework
submission and grade review system. Your access code is the following:

$code

Please keep this code safe. It is often convenient for students to retain
this e-mail until the end of the semester. Do not share your access code
with others.

If you did not request an access code, no action is required on your
part --- it is possible that a user misspelled their user name.

Note: this is an automated e-mail. If you have issues with Apollo, please
contact the course staff ($support_email).
BODY;

    $to = $username . EMAIL_SUFFIX;
    $subject = 'Your Apollo access code';
    $headers = "From: Apollo <$support_email>";

    if (!mail($to, $subject, $body, $headers))
        trigger_error('error sending registration e-mail');
}
