<?php // 5.3.3

define('APOLLO_VERSION', '1.3');

// path to the Apollo installation
// note: this will work as long as this file (init.php) is in the lib directory
define('APOLLO_LIB_PATH', dirname(__FILE__));

// note: this will work as long as the INI file is in the lib directory
define('APOLLO_INI', APOLLO_LIB_PATH . DIRECTORY_SEPARATOR . 'apollo.ini');

if (!is_file(APOLLO_INI))
    trigger_error('could not load configuration file: ' . APOLLO_INI);

$vars = parse_ini_file(APOLLO_INI);

// define all necessary constants using values from INI file
define('DEBUG_MODE', $vars['debug_mode']);
define('MAINTENANCE_MODE', $vars['maintenance_mode']);

define('TIMEZONE', $vars['timezone']);
define('CODE_LENGTH', $vars['code_length']);
define('EMAIL_SUFFIX', $vars['email_suffix']);
define('SUPPORT_EMAIL', $vars['support_email']);

define('ACCOUNTS_DIR', $vars['accounts_dir']);
define('CONCERNS_DIR', $vars['concerns_dir']);
define('DROPBOX_DIR', $vars['dropbox_dir']);
define('CRITERIA_DIR', $vars['criteria_dir']);
define('SUBMISSIONS_DIR', $vars['submissions_dir']);
define('PRESTO_TEMPLATE', $vars['presto_template']);
define('TEMPLATES_DIR', $vars['templates_dir']);

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
    use_body_template('500');
    header('X-PHP-Response-Code: 500', true, 500);

    $vars = array();
    $vars['title'] = 'Server error';

    if (DEBUG_MODE) {
        $script = end(explode(DIRECTORY_SEPARATOR, $file));
        $str = "In " . $script . " on line " . $line . ":\n" . $str;

        $vars['errorstr'] = $str;
    } else {
        $vars['errorstr'] = "Error $num";
    }

    render_page($vars);

    die;
}

set_error_handler('http_error');
