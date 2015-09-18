<?php // 5.3.3

define('APOLLO_VERSION', '2.1');

// path to the Apollo installation
// note: this will work as long as this file (init.php) is in the lib directory
define('APOLLO_LIB_PATH', dirname(__FILE__));

define('ERROR_LOG_PATH', APOLLO_LIB_PATH . DIRECTORY_SEPARATOR . 'errors.log');

// note: this will work as long as the INI file is in the lib directory
define('APOLLO_INI', APOLLO_LIB_PATH . DIRECTORY_SEPARATOR . 'apollo.ini');

if (!is_file(APOLLO_INI)) {
    echo 'could not load configuration file';
    exit;
}

$vars = parse_ini_file(APOLLO_INI);

// define all necessary constants using values from INI file
define('DEBUG_MODE', $vars['debug_mode']);
define('MAINTENANCE_MODE', $vars['maintenance_mode']);
define('CHECK_NEW_USER_EXISTS', $vars['check_new_user_exists']);

define('TIMEZONE', $vars['timezone']);
define('CODE_LENGTH', $vars['code_length']);
define('SHOW_ACCESS_CODE', $vars['show_access_code']);
define('EMAIL_SUFFIX', $vars['email_suffix']);
define('SUPPORT_EMAIL', $vars['support_email']);

define('NEW_FILE_MODE', intval($vars['new_file_mode'], 0));
define('NEW_DIR_MODE', intval($vars['new_dir_mode'], 0));

define('ACCOUNTS_DIR', $vars['accounts_dir']);
define('CONCERNS_DIR', $vars['concerns_dir']);
define('DROPBOX_DIR', $vars['dropbox_dir']);
define('SUBMISSIONS_DIR', $vars['submissions_dir']);
define('PRESTO_TEMPLATE', $vars['presto_template']);
define('TEMPLATES_DIR', $vars['templates_dir']);
define('METAFILE_DIR', $vars['metafile_dir']);
define('LOGS_DIR', $vars['logs_dir']);

// perform intialization
date_default_timezone_set(TIMEZONE);

if (DEBUG_MODE)
    ini_set('display_errors', 1);
else
    ini_set('display_errors', 0);

require_once 'template.php';

/*
 * This function is called when an error is triggered. If Apollo is
 * in debug mode, details about the error, running script, and line
 * number are actually displayed to the user. Otherwise, the error
 * number is printed.
 */
function http_error($num, $str, $file, $line) {
    $err = date(DATE_RFC2822) . ": error #$num: $str: $file: $line\n";
    file_put_contents(ERROR_LOG_PATH, $err, FILE_APPEND);

    use_body_template('500');
    header('X-PHP-Response-Code: 500', true, 500);

    $vars = array();
    $vars['title'] = 'Server error';

    if (DEBUG_MODE) {
        $script = end(explode(DIRECTORY_SEPARATOR, $file));
        $str = "In $script on line $line:\n" . $str;
        $vars['errorstr'] = $str;
    } else {
        $vars['errorstr'] = "Error $num";
    }

    render_page($vars);

    die;
}

set_error_handler('http_error');

if (MAINTENANCE_MODE) {
    set_title('Maintenance mode');
    use_body_template('maintenance');
    render_page();
    exit;
}
