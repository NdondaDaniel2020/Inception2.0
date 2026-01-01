<?php

namespace Microthemer\Content;

use \Microthemer\TimerTrait;

/*
 * AssetAuthContent
 *
 * Manage content amendments on the frontend for a logged in administrator
 */

class AssetAuthContent extends AssetLoadContent {

	//use TimerTrait;

	public function __construct(&$assetClass, $devMode) {
		parent::__construct($assetClass, $devMode);
	}

	public function renderThemeVariablesConfig() {

		$theme_settings = wp_get_global_settings();

		$vars = [
			'colors' => [],
			'fontFamily' => [],
			'fontSize' => [],
			'spacing' => [],
		];

		// --- Map Colors with Fallbacks ---
		if (!empty($theme_settings['color']['palette']['theme'])) {
			foreach ($theme_settings['color']['palette']['theme'] as $color) {
				$slug = $color['slug'];
				$value = $color['color']; // The original hex/rgb value
				// Create the var() string with the original value as a fallback
				$vars['colors'][$slug] = "var(--wp--preset--color--{$slug}, {$value})";
			}
		}

		// --- Map Font Families with Fallbacks ---
		if (!empty($theme_settings['typography']['fontFamilies']['theme'])) {
			foreach ($theme_settings['typography']['fontFamilies']['theme'] as $font_family) {
				$slug = $font_family['slug'];
				$value = $font_family['fontFamily']; // The original font stack string
				$vars['fontFamily'][$slug] = "var(--wp--preset--font-family--{$slug}, {$value})";
			}
		}

		// --- Map Font Sizes with Fallbacks ---
		if (!empty($theme_settings['typography']['fontSizes']['theme'])) {
			foreach ($theme_settings['typography']['fontSizes']['theme'] as $font_size) {
				$slug = $font_size['slug'];
				$value = $font_size['size']; // The original rem/px value
				$vars['fontSize'][$slug] = "var(--wp--preset--font-size--{$slug}, {$value})";
			}
		}

		// --- Map Spacing with Fallbacks ---
		if (!empty($theme_settings['spacing']['spacingSizes']['theme'])) {
			foreach ($theme_settings['spacing']['spacingSizes']['theme'] as $spacing) {
				$slug = $spacing['slug'];
				$value = $spacing['size']; // The original rem/px value
				$vars['spacing'][$slug] = "var(--wp--preset--spacing--{$slug}, {$value})";
			}
		}

		// Theme vars
		$json_vars = json_encode($vars, JSON_PRETTY_PRINT);
		$this->hookScript(
			'amender-theme-vars',
			null,
			'inline_module',
			"window.amenderThemeVars = {$json_vars};"
		);
	}

	// load Tailwind and MT processor script if support has been enabled
	public function maybeLoadTailwindProcessor(&$p, $jsPath){
		
		if (!empty($p['tailwind']) && $this->runTailwindOnPage()){

			// Tailwind scripts
			$scripts = array('mt-tailwind-main');
			foreach ($scripts as $scriptName){

				$this->hookScript(
					'amender-script-tailwind',
					$jsPath.'/'.$scriptName.'.js?v='.$this->assetClass->version,
					'module',
				);
			}
			
		}
	}

	function getFrontendDataTailwindCache($type, $id, &$tailwind){

		if (!empty($this->preferences['tailwind'])){

			$caches = array(
				'classCache' => $type . '-' . $id,
				'siteWideCache' => 'site-wide'
			);

			foreach ($caches as $cacheKey => $fileName){
				$classCache = $this->assetClass->micro_root_dir . 'mt/cache/tailwind/classes/'. $fileName . '.json';
				if (file_exists($classCache)){
					$tailwind[$cacheKey] = file_get_contents($classCache);
				}
			}

		}
	}


}