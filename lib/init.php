<?php

require_once 'globals.php';

// "unregistering" globals
// http://php.net/manual/en/faq.misc.php#faq.misc.registerglobals
if (ini_get('register_globals')) {
    if (isset($_REQUEST['GLOBALS']) || isset($_FILES['GLOBALS'])) {
        exit;
    }

    $no_unset = array(
        'GLOBALS', '_GET', '_POST', '_COOKIE', '_REQUEST', '_SERVER',
        '_ENV', '_FILES'
    );

    $input = array_merge(
        $_GET, $_POST, $_COOKIE, $_SERVER, $_ENV, $_FILES,
        isset($_SESSION) && is_array($_SESSION) ? $_SESSION : array()
    );

    foreach ($input as $k => $v) {
        if (!in_array($k, $no_unset) && isset($GLOBALS[$k])) {
            unset($GLOBALS[$k]);
        }
    }
}

/*
 * Used *only* for serious configuration-related errors that occur in this
 * initialization script. It should be used before set_error_handler() is
 * used, and will display a fatal error message to the page.
 */
function crash($msg) {
    // need this, otherwise the app just shows a blank page
    ini_set('display_errors', true);
    trigger_error($msg, E_USER_ERROR);
}

// note: this will work as long as this file (init.php) is in the lib directory
define('APOLLO_LIB_PATH', dirname(__FILE__));

define('TEMPLATES_DIR', dirname(APOLLO_LIB_PATH) . SEP . 'templates');

// note: this will work as long as the INI file is in the lib directory
define('APOLLO_INI', APOLLO_LIB_PATH . SEP . 'apollo.ini');

if (!is_file(APOLLO_INI))
    crash('could not load configuration file: ' . APOLLO_INI);

$vars = parse_ini_file(APOLLO_INI);

foreach ($vars as $name => $value)
    define(strtoupper($name), $value);

$required_vars = array(
    'TIMEZONE',
    'DROPBOX_DIR',
    'SUBMISSIONS_DIR',
    'PRESTO_TEMPLATE',
    'METAFILE_DIR',
    'LOGS_DIR'
);

// check that required variables are set
foreach ($required_vars as $name)
    if (!defined($name) || strlen(constant($name)) === 0)
        crash("required configuration variable $name is not set or is empty");

// verify the time zone is correct & set the time zone
if (!in_array(TIMEZONE, timezone_identifiers_list()))
    crash('TIMEZONE configuration variable is invalid: ' . TIMEZONE);
else
    date_default_timezone_set(TIMEZONE);

// verify the required directory paths
foreach (
    array('DROPBOX_DIR', 'SUBMISSIONS_DIR', 'METAFILE_DIR', 'LOGS_DIR')
    as $dir)
{
    $path = constant($dir);

    if (!is_dir($path) || !is_readable($path))
        crash("required directory $dir missing or unreadable: $path");
}

/*
 * Verify that we have write permission on the directories under which we may
 * need to create more files or directories.
 */
foreach (array('SUBMISSIONS_DIR', 'LOGS_DIR') as $dir) {
    $path = constant($dir);

    if (!is_writable($path))
        crash("need write permissions for $dir: $path", E_USER_ERROR);
}

/*
 * Set up default values for the other configuration variables, if needed.
 */
$optional_vars = array(
    'DEBUG_MODE' => false,
    'MAINTENANCE_MODE' => false,
    'NEW_FILE_MODE' => '0666',
    'NEW_DIR_MODE' => '0777',
    'LOG_ERRORS' => false
);

foreach ($optional_vars as $name => $default)
    if (!defined($name))
        define($name, $default);

// NEW_FILE_MODE and NEW_DIR_MODE come in as strings; need ints for chmod()
define('NEW_FILE_MODE_INT', intval(NEW_FILE_MODE, 0));
define('NEW_DIR_MODE_INT', intval(NEW_DIR_MODE, 0));

ini_set('display_errors', DEBUG_MODE);

/*
 * Set up friendly runtime error handling.
 */

require_once 'template.php';

/*
 * Given details about a PHP error, this function returns a compact string
 * containing the error details and the values of relevant superglobals,
 * ideal for logging to a file.
 */
function get_error_string($num, $error_string, $file, $line) {
    switch ($num) {
        case E_ERROR: $level_str = 'E_ERROR'; break;
        case E_WARNING: $level_str = 'E_WARNING'; break;
        case E_PARSE: $level_str = 'E_PARSE'; break;
        case E_NOTICE: $level_str = 'E_NOTICE'; break;
        case E_CORE_ERROR: $level_str = 'E_CORE_ERROR'; break;
        case E_CORE_WARNING: $level_str = 'E_CORE_WARNING'; break;
        case E_COMPILE_ERROR: $level_str = 'E_COMPILE_ERROR'; break;
        case E_COMPILE_WARNING: $level_str = 'E_COMPILE_WARNING'; break;
        case E_USER_ERROR: $level_str = 'E_USER_ERROR'; break;
        case E_USER_WARNING: $level_str = 'E_USER_WARNING'; break;
        case E_USER_NOTICE: $level_str = 'E_USER_NOTICE'; break;
        case E_STRICT: $level_str = 'E_STRICT'; break;
        case E_RECOVERABLE_ERROR: $level_str = 'E_RECOVERABLE_ERROR'; break;
        case E_DEPRECATED: $level_str = 'E_DEPRECATED'; break;
        case E_USER_DEPRECATED: $level_str = 'E_USER_DEPRECATED'; break;
        case E_ALL: $level_str = 'E_ALL'; break;
        default: $level_str = 'E_UNKNOWN'; break;
    }

    $err = date(DATE_RFC2822) . "\n" .
           "\ttype=$level_str\n" .
           "\tmessage=$error_string\n" .
           "\tfile=$file:$line\n";

    if (isset($_SESSION['username']))
        $err .= "\t\$_SESSION['username']=" . $_SESSION['username'] . "\n";

    $err .= "\t\$_GET=" . json_encode($_GET) . "\n";
    $err .= "\t\$_POST=" . json_encode($_POST) . "\n";

    return $err;
}

/*
 * This function is called when an error is triggered. If Apollo is in debug
 * mode, details about the error, running script, and line number are actually
 * displayed to the user.
 */
function http_error($num, $str, $file, $line) {
    header('X-PHP-Response-Code: 500', true, 500);

    $vars = array();
    $vars['title'] = 'Server error';

    $error_string = get_error_string($num, $str, $file, $line);
    $error_details = '';

    if (LOG_ERRORS) {
        $error_log_path = LOGS_DIR . SEP . 'errors.log';
        file_put_contents($error_log_path, $error_string, FILE_APPEND);

        $error_details = 'The following information was appended to ' .
          'the Apollo error log at <tt>' . htmlspecialchars($error_log_path) .
          "</tt>:\n";
    }

    $error_details .= '<pre>' . htmlspecialchars($error_string) . '</pre>';

    if (DEBUG_MODE) {
        use_body_template('500debug');
        $vars['errordetails'] = $error_details;
    } else {
        use_body_template('500');
    }

    render_page($vars);

    exit;
}

set_error_handler('http_error');

if (MAINTENANCE_MODE) {
    set_title('Maintenance mode');
    use_body_template('maintenance');
    render_page();
    exit;
}
