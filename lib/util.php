<?php // 5.3.3

function plural($word, $n) {
    if ($n == 1)
        return $word;
    else
        return $word . 's';
}

/*
 * Produce a "safe" version of this string that can be used as
 * an HTML id or name value.
 */
function html_safe($str) {
    $replace_pairs = array('-' => '_',
                           ' ' => '_',
                           '.' => '_',
                           ',' => '_',
                           ':' => '_',
                           '&' => '_',
                           );

    return strtolower(strtr($str, $replace_pairs));
}

/*
 * Given a file name, return the file extension of the file name,
 * without the dot.
 */
function file_extension($name) {
    return pathinfo($name, PATHINFO_EXTENSION);
}

function is_dotfile($filename) {
    return strpos($filename, '.') === 0;
}

function html_ul($arr) {
    $s = '<ul>';

    foreach ($arr as $el)
        $s .= '<li>' . $el . '</li>';

    return $s . '</ul>';
}

function html_tt($s) {
    return '<tt>' . $s . '</tt>';
}

function html_admonition($body, $title = 'Note', $style = 'note') {
    $s = "<div class=\"admonition $style\">";
    $s .= "<p class=\"admonition-title\">$title</p>";
    $s .= "<p>$body</p>";
    $s .= '</div>';

    return $s;
}

function html_pre($s) {
    $pairs = array(
        '	' => '<span class="tab"></span>&nbsp;&nbsp;&nbsp;&nbsp;',
        ' ' => '<span class="sp"></span> ',
        "\r\n" => "\n",
        "\r" => "\n",
        "\n" => '<span class="nl"></span><br>',
    );

    if (version_compare(phpversion(), '5.4.0', '<'))
        // the old version of this function will leave in unknown chars
        $specialchars = htmlspecialchars($s);
    else
        // for >= 5.4.0, to retain unknown chars, we must pass in a flag
        $specialchars = htmlspecialchars($s, ENT_SUBSTITUTE | ENT_HTML5);

    return '<div class="pre">' .
           str_replace(array_keys($pairs), array_values($pairs),
                       $specialchars) .
           '</div>';
}
