<?php // 5.3.3

/*
 * concerns.php - allows for viewing and submitting grading concerns
 *
 * When a student creates a grading concern produced by this script, a
 * YAML file is created on the file system, so that graders can review
 * and update the grader's response there. See the concerns.php file in
 * the directory above this one for the helper functions.
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';
require 'lib/util.php';
require 'lib/concerns.php';

// redirects to log in page if necessary
require 'auth.php';

$vars = array();
$vars['username'] = $_SESSION['username'];

if (isset($_POST['submitted'])) {

    if (!isset($_POST['name'], $_POST['ps'],
               $_POST['issue'], $_POST['comments']))
    {
        $errors = array();

        if (!isset($_POST['name']))
            $errors[] = 'Please supply us with your full name.';

        if (!isset($_POST['ps']))
            $errors[] = 'Please specify which problem set this pertains to.';

        if (!isset($_POST['issue']))
            $errors[] = 'Please select the appropriate issue from the list.';

        if (!isset($_POST['comments']))
            $errors[] = 'Please describe the issue in the comments field.';

        set_title('Error submitting concern');
        use_body_template('concern_error');
        render_page(array('errorslist' => html_ul($errors)));
        exit;
    }

    // create the new concern file
    $name = $_POST['name'];
    $ps = $_POST['ps'];
    $issue = $_POST['issue'];
    $comments = $_POST['comments'];

    $concern = array('name'     => $name,
                     'ps'       => $ps,
                     'issue'    => $issue,
                     'comments' => $comments);

    if (!save_concern($_SESSION['username'], $concern))
        trigger_error('error saving new concern file');

    $vars['message'] = html_admonition('Your concern has been submitted.');
    unset($_POST['submitted']);

} else {
    // don't write an admonition
    $vars['message'] = '';
}

set_title("Grading concerns");

use_body_template("concerns");

$concerns = get_concerns($_SESSION['username']);

if (count($concerns) == 0) {
    $concerns_string = "None.";
} else {
    $concerns_string = '<ol>';

    foreach ($concerns as $c) {
        $ps = $c['ps'];
        $issue = $c['issue'];
        $comments = $c['comments'];

        if ($c['resolved'] == TRUE) {
            $status = '<span class="resolved">Resolved</span>';
        } else {
            $status = '<span class="unresolved">Unresolved</span>';
        }

        $table = '<table class="concern">';
        $table .= '<tr><th>status</th><td>' . $status . '</td></tr>' .
                  '<tr><th>problem set</th><td>' . $ps . '</td></tr>' .
                  '<tr><th>issue</th><td>' . $issue . '</td></tr>' .
                  '<tr><th>comments</th><td><tt>' .
                       $comments . '</tt></td></tr>';

        if (array_key_exists('response', $c) and $c['response'])
            $table .= '<tr><th>response</th><td><tt>' . $c['response'] .
                      '</tt></td></tr>';

        $table .= '</table>';

        $concerns_string .= "<li>" . $table . "</li>";
    }

    $concerns_string .= '</ol>';
}

$vars['concernslist'] = $concerns_string;

render_page($vars);
