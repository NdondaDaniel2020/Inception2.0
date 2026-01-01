<?php

/*
 * Common properties and methods for Admin and AssetAuth classes
 */

namespace Microthemer;

trait PluginTrait {

	var $version = '7.5.3.5';
	var $db_chg_in_ver = '7.5.2.8';
	var $minimum_wordpress = '5.6';
	var $preferencesName = 'preferences_themer_loader';
	var $autoloadPreferencesName = 'microthemer_autoload_preferences';
	var $microthemeruipage = 'tvr-microthemer.php';
	var $microthemespage = 'tvr-manage-micro-themes.php';
	var $managesinglepage = 'tvr-manage-single.php';
	var $preferencespage = 'tvr-microthemer-preferences.php';
	var $detachedpreviewpage = 'tvr-microthemer-preview-wrap.php';
	var $docspage = 'tvr-docs.php';
	var $fontspage = 'tvr-fonts.php';
	//var $preferences = array(); PHP warning when AssetLoad runs
	var $current_user_id = -1;
	var $wp_ajax_url = '';

	// Previously dynamic properties
	// @var strings dir/url paths
	var $thistmpdir;
	var $debug_dir;
	var $home_url;
	var $wp_admin_url;
	var $wp_blog_admin_url;
	var $country_codes;
	var $nth_formulas;
	var $fav_css_filters;
	var $css_filters;
	var $min_and_max_mqs;
	var $container_queries;
	var $default_dev_preferences;
	var $subscription_defaults;
	var $subscription_check_defaults;
	var $browser_events;
	var $browser_event_keys;
	var $system_fonts;
	var $enq_js_structure;
	var $mq_structure;
	var $menu;

	var $wp_content_url = '';
	var $wp_content_dir = '';
	var $wp_plugin_url = '';
	var $wp_plugin_dir = '';
	var $thispluginurl = '';
	var $thisplugindir = '';
	var $multisite_blog_id = false;
	var $micro_root_dir = '';
	var $micro_root_url = '';
	var $site_url = '';
	var $content;

	function loadTextDomain(){

		load_plugin_textdomain(
			'microthemer',
			false,
			dirname( plugin_basename(__FILE__) ) . '/languages/'
		);
	}

	// get/set cookie
	function pluginCookie($key, $value = false, $expiration = 0){ // 0 = expire when browser closes

		if ($value) {
			$this->deleteCookie($key);
			return setcookie($key, $value, $expiration, '/', COOKIE_DOMAIN);
		}

		return isset($_COOKIE[$key]) ? $_COOKIE[$key] : false;
	}

	// delete cookie
	/*function deleteCookie($key){
		unset($_COOKIE[$key]);
	}*/

	function deleteCookie($key, $expires = 0, $path = '/', $domain = COOKIE_DOMAIN){

		setcookie($key, "", $expires, $path, $domain);

		unset($_COOKIE[$key]);
	}


	function setAdminAjaxUrl(){
		$this->wp_ajax_url = $this->wp_blog_admin_url . 'admin-ajax.php' . '?action=mtui&mcth_simple_ajax=1&page='.$this->microthemeruipage . '&_wpnonce='.wp_create_nonce('mcth_simple_ajax');
	}

	function jsonResponse($data, $clean = true){

		if ($clean){
			// Clean all levels of output buffering - this fixes issues like <p> wrapped around JSON
			while (ob_get_level()) {
				ob_end_clean();
			}
		}

		header('Content-type: application/json');

		http_response_code(200);

		echo json_encode($data);
		exit;
	}



}