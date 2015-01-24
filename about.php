<?php // 5.3.3

/*
 * about.php - about page for Apollo system
 *
 * Author: Alexander Breen (alexander.breen@gmail.com)
 */

require 'lib/init.php';

set_title('About Apollo');
use_body_template('about');
render_page(array('version' => APOLLO_VERSION));
