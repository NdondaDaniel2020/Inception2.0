<?php
/*
Plugin Name: Microthemer
Plugin URI: https://themeover.com/microthemer
Text Domain: microthemer
Domain Path: /languages
Description: Microthemer is a powerful AI & visual CSS editor that cares about site performance.
Version: 7.5.3.5
Author: Themeover
Author URI: https://themeover.com
*/

// Copyright 2025 by Sebastian Webb @ Themeover

// Stop direct call
if ( !defined( 'ABSPATH') ) exit;

// Include a simple autoload function
require dirname(__FILE__) . '/src/autoload.php';

// Plugin constants
if (!defined('TVR_DEV_MODE')) define('TVR_DEV_MODE', false);
if (!defined('TVR_DEBUG_DATA')) define('TVR_DEBUG_DATA', false);
if (!defined('TVR_CSS_EDITOR')) define('TVR_CSS_EDITOR', true);
if (!defined('MT_IS_ACTIVE')) define('MT_IS_ACTIVE', true);

// Initiate Microthemer functionality
if (!function_exists('initiateMicrothemer')){
	function initiateMicrothemer() {

		// Admin dashboard
		if (is_admin()) {

			// Only run admin code for a logged in Administrator
			if (current_user_can('manage_options')){
				new \Microthemer\Admin();
			}

			// Public ajax
			new \Microthemer\AjaxPublic();

		}

		// Site frontend
		else {

			// logged in Administrator viewing the frontend - include editing assets
			if (current_user_can('manage_options')){
				new \Microthemer\AssetAuth('edit');
			}

			// Non-admin viewing the site - just load minimal assets
			else {
				new \Microthemer\AssetLoad();
			}

		}
	}

	add_action('plugins_loaded', 'initiateMicrothemer');
}

// Explain how admins should set up both Amender and Microthemer correctly
elseif (is_admin()) {
	add_action(
		'admin_notices',
		function() {
			if (current_user_can('manage_options')){
				echo '
				<div class="notice notice-error">
					<p>' .
					     '<strong>You cannot run Microthemer and Amender as two separate plugins</strong>. Remove one, then enable Amender or Microthemer as an addon via <i>Settings > General > Preferences</i>.' .
					'</p>
				</div>';
			}

		}
	);
}

