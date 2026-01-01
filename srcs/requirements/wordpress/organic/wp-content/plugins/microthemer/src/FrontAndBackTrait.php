<?php

// Data and functions needed on both the admin, auth, and non-auth frontend
// Perhaps this could replace Common static functions, then we leave remaining in Plugin trait

namespace Microthemer;

trait FrontAndBackTrait {

	var $appName = 'Microthemer';
	var $appNameFull = 'Microthemer A+';

	function getAppName($shorthand = true){
		return $this->hasCSSCapability() ?
			($this->hasContentCapability() && !$shorthand
				? 'Microthemer A+'
				: 'Microthemer')
			: 'Amender';
	}

	function setAppName(){
		$this->appName = $this->getAppName();
		$this->appNameFull = $this->getAppName(0);
	}

	function isCSSEditor(){
		return defined('TVR_CSS_EDITOR') && !empty(constant('TVR_CSS_EDITOR'));
	}

	function isContentEditor(){
		return defined('TVR_CONTENT_EDITOR') && !empty(constant('TVR_CONTENT_EDITOR'));
	}

	// We want to return true when neither constant is defined - when Microloader is running
	function isFullEditor(){
		return !defined('TVR_CSS_EDITOR') && !defined('TVR_CONTENT_EDITOR');
	}

	function hasContentDir(){
		return is_dir(dirname(__FILE__) . '/Content');
	}

	function hasCSSDir(){
		return is_dir(dirname(__FILE__) . '/CSS');
	}

	function hasContentCapability(){
		return $this->hasContentDir() && ($this->isFullEditor() || $this->isContentEditor() || !empty($this->preferences['content_addon']));
	}

	function hasCSSCapability(){
		return $this->hasCSSDir() && ($this->isFullEditor() || $this->isCSSEditor() || !empty($this->preferences['css_addon']));
	}

	function contentRequiresSetup(){

		global $wpdb;

		if ($this->hasContentCapability()){
			$table = $wpdb->prefix . "micro_content";
			return $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table;
		}

		return false;
	}

	function hasContentSubscription(){
		return $this->contentClass && !empty($this->preferences['amender_buyer_validated']);
	}

	function hasCSSSubscription(){
		return !empty($this->preferences['buyer_validated']);
	}

	function supportContent($requiresSubscription = false){
		return $this->contentClass
			&& (!$requiresSubscription || $this->hasContentSubscription());
	}

	function supportGUICSS($requiresSubscription = false){
		return $this->hasCSSCapability()
		       && (!$requiresSubscription || $this->hasCSSSubscription());
	}

	// Conditionally run a content method
	function contentMethod($method, $args = array(), $requiresSubscription = false){

		if ($this->contentClass && method_exists($this->contentClass, $method)
		    && (!$requiresSubscription || $this->hasContentSubscription())){
			return call_user_func_array(array(&$this->contentClass, $method), $args);
		}

		return false;
	}

	// Conditionally run a CSS method
	function cssMethod($method, $args = array(), $requiresSubscription = false){

		if ($this->cssClass && method_exists($this->cssClass, $method)
		    && (!$requiresSubscription || $this->hasCSSSubscription())){
			return call_user_func_array(array(&$this->cssClass, $method), $args);
		}

		return false;
	}

	function getDefaultNPM(){

	}

}
