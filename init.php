<?php // 5.3.3

ini_set('display_errors', 1);
date_default_timezone_set("America/New_York");

define('APOLLO_VERSION', '1.2');

// path to the Apollo installation
// note: this will work as long as this file (init.php) is in
// Apollo's base directory
define('APOLLO_PATH', dirname(__FILE__));

// note: this will work as long as the INI file is in
// Apollo's base directory
define('APOLLO_INI', APOLLO_PATH . DIRECTORY_SEPARATOR . 'apollo.ini');

if (!is_file(APOLLO_INI))
    trigger_error('could not load INI path: ' . APOLLO_INI);

$vars = parse_ini_file(APOLLO_INI);

// define all necessary constants using values from INI file
define('MAINTENANCE_MODE', $vars['maintenance_mode']);
define('CODE_LENGTH', $vars['code_length']);
define('EMAIL_SUFFIX', $vars['email_suffix']);
define('ACCOUNTS_DIR', $vars['accounts_dir']);
define('CONCERNS_DIR', $vars['concerns_dir']);
define('DROPBOX_DIR', $vars['dropbox_dir']);
define('CRITERIA_DIR', $vars['criteria_dir']);
define('SUBMISSIONS_DIR', $vars['submissions_dir']);
define('PRESTO_TEMPLATE', $vars['presto_template']);
define('TEMPLATES_DIR', $vars['templates_dir']);

require_once 'template.php';

function http_error($num, $str, $file, $line) {
    use_body_template("500");
    header('X-PHP-Response-Code: 500', true, 500);

    $script = end(explode(DIRECTORY_SEPARATOR, $file));
    $str = "In " . $script . " on line " . $line . ":\n" . $str;

    render_page(array("title" => "Server error", "errorstr" => $str));

    die;
}

set_error_handler("http_error");
