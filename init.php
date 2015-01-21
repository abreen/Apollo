<?php // 5.3.3

ini_set('display_errors', 1);
date_default_timezone_set("America/New_York");

define('APOLLO_VERSION', '1.0');

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
