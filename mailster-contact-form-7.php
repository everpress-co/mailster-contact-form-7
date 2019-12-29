<?php
/*
Plugin Name: Mailster Contact Form 7
Plugin URI: https://mailster.co/?utm_campaign=wporg&utm_source=Contact+Form+7+for+Mailster&utm_medium=plugin
Description: Create your Signup Forms with Contact Form 7 and allow users to signup to your newsletter
Version: 1.4
Author: EverPress
Author URI: https://mailster.co
License: GPLv2 or later
Text Domain: mailster-cf7
*/

define( 'MAILSTER_CF7_VERSION', '1.4' );
define( 'MAILSTER_CF7_REQUIRED_VERSION', '2.2.10' );
define( 'MAILSTER_CF7_FILE', __FILE__ );

require_once dirname( __FILE__ ) . '/classes/contactform.class.php';
new MailsterCF7();

