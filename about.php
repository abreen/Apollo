<?php

/*
 * about.php - about page for Apollo system
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';

// needed for $_SESSION['username']
require 'auth.php';

set_title('About Apollo');
use_body_template('about');
render_page(array('version' => APOLLO_VERSION, 'username' => $_SESSION['username']));
