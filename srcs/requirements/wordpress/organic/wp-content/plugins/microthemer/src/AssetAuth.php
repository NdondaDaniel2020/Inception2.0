<?php

/*
 * AssetAuth
 * 
 * For logged in administrators
 * Load asset editing resources on the frontend and admin area (if edit $context is passed into the construct method)
 * This class loads on the admin area even when not editing, so that a response can be given to MT from the admin area too
 */

namespace Microthemer;

class AssetAuth extends AssetLoad {

    use PluginTrait;

	var $context = 'assetAuth';

    var $builderBlockedEdit = false;
    var $assetLoadingKey = 'asset_loading';
	var $draft = true;
	var $globalStylesheetRequiredKey = "global_stylesheet_required";
	var $globalJSRequiredKey = "load_js";

    function __construct($context){

        $this->context = $context;

		// no need to run MT frontend script on Oxygen intermediate iframe
		// only one the actual site preview
		if (isset($_GET['ct_builder']) && !isset($_GET['oxygen_iframe'])){
			return;
		}

        // run common init with standalone asset loader
		parent::__construct();

        // initialise functionality for administrator
	    $this->initAuth();
	}

    // editing-specific functionality
    function initAuth(){

	    // get the directory paths
	    include dirname(__FILE__) .'/../get-dir-paths.inc.php';

	    if ($this->hasContentCapability()){
		    $this->contentClass = new Content\AssetAuthContent($this, TVR_DEV_MODE);
	    }

        // setup plugin text domain - not sure if this is needed as JS text strings run on parent window
        // but let's see when reviewing code
        $this->loadTextDomain();

        // determine if we're displaying draft or actively published content
        $this->getFileStub();

		// hook save post
	    $this->hookPostSaved();
        $this->hookAjaxUrlSetup();

        if ($this->isFrontend){
	        $this->hookRedirect();
			$this->nonLoggedInMode();
	        $this->hookAdminBarLink();
			$this->hookDequeue();
        }

        // hookJS doesn't run in the admin area so just hook MT JavaScript
        if ($this->isAdminArea){

            // don't show on Divi page - this can cause issues
            $exclude = isset($_GET['et_fb']);

            if (!$exclude){
	            $this->deferHookIfAdmin('current_screen', 'hookMTJS');
            }

        }

        // note, must come after hookMTJS as inline data is attached to the tvr_mcth_frontend handle
	    //$this->deferHookIfAdmin('current_screen', 'hookFrontendData');
    }

	// support viewing the frontend as a logged-out user
	function hookAjaxUrlSetup(){
		add_action('init',  array(&$this, 'setAdminAjaxUrl'), $this->defaultActionHookOrder);
	}

    // support redirection
	function hookRedirect(){
		add_action('wp',  array(&$this, 'redirect'), $this->defaultActionHookOrder);
	}

    // Add link to admin bar
    function hookAdminBarLink(){
	    if (!empty($this->preferences['admin_bar_shortcut'])) {
		    add_action( 'admin_bar_menu', array(&$this, 'adminBarLink'), $this->defaultActionHookOrder);
	    }
    }

	function hookDequeue(){
		add_action( 'wp_print_scripts', array(&$this, 'dequeueScripts'), $this->defaultActionHookOrder );
	}

	// Dequeue scripts that conflict with MT - this only happens for logged in admins
	function dequeueScripts(){

		// Swiper.js loaded by Woo Essential plugin makes mousewheel scroll very slow
		wp_dequeue_script( 'dnwoo_swiper_frontend' );
	}

	// add_action( 'save_post', 'set_private_categories' );
	function hookPostSaved(){
		add_action('save_post', array(&$this, 'postSaved'));
	}

	// add frontend JS data (inc the login page)
	/*function hookFrontendData(){

        $p = &$this->preferences;

		$action_hook = $this->getCSSActionHook($p);

        // determine the action execution order
		$action_order = $this->getCSSActionOrder($p);

		//wp_die('$action_hook: ' . $action_hook);

		// load on login page too
		if ($this->isFrontend){
			add_action( 'login_head', array(&$this, 'addFrontendData'), $action_order);
		}

        // add the frontend data script
        add_action( $action_hook, array(&$this, 'addFrontendData'), $action_order);

	}*/

	// action hook when a post is saved
	// we need to update the theme template map as the arrangement of patterns and template parts may have changed
	function postSaved($post_id){
		Common::maybeUpdateTemplateCache($this->micro_root_dir, null, true);
	}

	function addMTPlaceholder(){

        // this interferes with the logic test by echoing output, and isn't needed then
        if (!isset($_GET['test_logic'])){

			//wp_die('basty_current_filter: ' .  current_filter());

            $this->enqueueOrAdd(
		        true,
		        'mt-placeholder', // id must not start with 'microthemer' or it will be removed on browser tab sync
		        '',
		        array(
			        'inline' => true,
			        'code' => $this->supportAdminAssets() ? '.wp-block {}' : '',
			        'doNotDoItem' => true
		        )
	        );
        }

    }

	function addMTCSS(){

		// use the $wp_styles->add() method rather than enqueue if order is specified
		// or loading stylesheets in footer which only works if using $wp_styles->add()
		$add = $this->addInsteadOfEnqueue();

		// dev vs production stylesheet
		$min = !TVR_DEV_MODE ? '.min' : '';

		// load file
		$this->enqueueOrAdd(
			$add,
			'microthemer-overlay',
			$this->thispluginurl.'css/frontend'.$min.'.css?v='.$this->version
		);

	}

	function hookMTJS(){

        $action_hook = $this->checkBlockEditorScreen()
	        ? 'enqueue_block_assets'
	        : $this->hooks['enqueue_scripts'];

	   add_action($action_hook, array(&$this, 'addMTJS'), $this->defaultActionHookOrder);
	}

	function addMTJS(){

		$p = $this->preferences;
        $min = !TVR_DEV_MODE ? '-min' : '/page';
        $jsPath = $this->thispluginurl.'js'.$min;

		// Common dependencies
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-tooltip');

        // load mt-block.js on block editor pages
        if ($this->isBlockEditorScreen){

            $js_path = 'js' . (TVR_DEV_MODE ? '/mod/' : '-min/') . 'mt-block.js';

            wp_enqueue_script(
		        'tvr_block_classes',
		        $this->thispluginurl . $js_path,
		        array( 'wp-blocks', 'wp-element', 'wp-compose', 'lodash' ),
		        filemtime($this->thisplugindir . $js_path),
		        false // Set it to true if you want it to be loaded in the footer
	        );
        }

		// MT preview script
		// For editing styles on the frontend and the admin area
		// But also for the Microthemer interface to receive a response from the admin area without editing
		// e.g. Frontend loaded, Folder loading config
		wp_register_script(
			'tvr_mcth_frontend',
			$jsPath.'/frontend.js?v='.$this->version,
			array('jquery', 'jquery-ui-tooltip')
		);

		wp_enqueue_script( 'tvr_mcth_frontend');

		// Print theme variables to inline JSON object for Tailwind and AI usage
		$this->contentMethod('renderThemeVariablesConfig');

		// Load tailwind JIT if enabled
		$this->contentMethod('maybeLoadTailwindProcessor', array(&$p, $jsPath));

		// the previous system of hooking this separately did not work on the wp-login.php page
		// And I think it added unnecessary complexity too
		$this->addFrontendData();
	}

	function addFrontendData($returnData = false) {

		if ( is_user_logged_in() || isset($_GET['mt_nonlog']) ) {

			global $wp_version;
			$p = &$this->preferences;
            $min = !TVR_DEV_MODE ? '-min' : '/mod';
			$asset_loading = !empty($p[$this->assetLoadingKey])
				? $p[$this->assetLoadingKey]
				: array();

			// ensure that folderLoading config has been set
			// it won't be if stylesheet_order has a value
			if (!$this->folderLoadingChecked){

				//echo 'getCondAssets ';
				if (isset($asset_loading['logic'])){
					$this->conditionalAssets($asset_loading['logic'], false, true);
				}

				// Now we have folderLoading config, queue scripts and maybe hook HTML mods
				if (!$this->isFrontendBuilder()){
					$this->contentMethod('initContentAmendments');
				}



			}

            // Get the folder loading status of any draft folder too
			$eligibleForLoading = !$this->isAdminArea || $this->supportAdminAssets();
			$draftFolder = isset($_COOKIE['microthemer_draft_folder'])
				? json_decode(stripslashes($_COOKIE['microthemer_draft_folder']), true)
				: false;

            // if we need to test draft folder logic that hasn't been saved
			if ($draftFolder && $eligibleForLoading){
				$logic = new Logic($this->logicSettings);
				$this->folderLoading[$draftFolder['slug']] = $logic->result($draftFolder['expr'])
                    ? 'empty'
                    : 0;
			}

			$MTDynFrontData = array_merge(
				array(
                    'draftFolder' => $draftFolder,
					'iframe-url' => rawurlencode(
						esc_url(
							Common::strip_page_builder_and_other_params($this->currentPageURL()),
							null,
							'read'
						)
					),
					'mt-show-admin-bar' => !empty($p['admin_bar_preview'])
						? intval($p['admin_bar_preview'])
						: 1,

					// note: folderLoading may need to hook this data to wp_footer if stylesheet_in_footer
					'folderLoading' => $this->folderLoading,
					'assetLoadingLogic' => !empty($p['asset_loading']['logic'])
						? $p['asset_loading']['logic']
						: array(),
					'builderBlockedEdit' => $this->builderBlockedEdit,
					'broadcast' => !empty($p['sync_browser_tabs'])
						? $this->thispluginurl . 'js'.$min.'/mt-broadcast.js?v='.$this->version
						: false,
					'isAdminArea' => $this->isAdminArea,

                    'add_block_classes_all' => !empty($p['add_block_classes_all']),

					// Flag to the frontend script that asset editing is / isn't supported
					'interactions' => $this->context === 'edit',

					// flag if bricks builder is active
					'bricksBuilderActive' => $this->isBricksUi(),

                    // Ajax URL for saving data after monitoring the frontend e.g. Tailwind class usage
                    'wp_ajax_url' => $this->wp_ajax_url,

					// WordPress info
                    'wp_version' => $wp_version,
                    'theme' => get_stylesheet(),
                    'template' => Helper::getCurrentTemplateSlug(),
					'home_url' => $this->home_url

				),
                $this->pageMeta()
            );

			// get Oxygen page width
			if ( function_exists('oxygen_vsb_get_page_width') ){

				$MTDynFrontData['oxygen'] = array(
					'page-width' => intval( oxygen_vsb_get_page_width() )
				);
			}

			wp_add_inline_script(
				'tvr_mcth_frontend',
				'window.MTDynFrontData = '. json_encode( $MTDynFrontData ) .';',
				'before'
			);

			if ($returnData){
				return $MTDynFrontData;
			}

			//wp_die('$returnData: <pre>'.print_r(['$MTDynFrontData' => wp_scripts()], 1).'</pre>' );

		}
	}

	function authOnlyData($min){

		// pageHasMods, what mods the folder has...

		return array(
			'jQueryScript' => includes_url().'js/jquery/jquery.min.js?v='.$this->version,
		    'MTFscript' => $this->thispluginurl.'js'.$min.'/frontend.js?v='.$this->version
		);
	}


	function adminBarLink($wp_admin_bar) {

        if (!current_user_can('manage_options')){
            return false;
        }

        $parent = !empty($this->preferences['top_level_shortcut']) ? false : 'site-name';
        $currentPageURL = Common::strip_page_builder_and_other_params($this->currentPageURL());
        $post = $this->pageMeta(); //$this->getCurrentPostData();

        // format URL
        $href = $this->wp_blog_admin_url . 'admin.php?page=' . $this->microthemeruipage .
                '&mt_preview_url=' . rawurlencode(esc_url($currentPageURL))
                . '&mt_item_id=' . rawurlencode($post['post_id'])
                . '&mt_path_label=' . rawurlencode($post['post_title'])
                . '&_wpnonce=' . wp_create_nonce( 'mt-preview-nonce' );

        // add menu item
        $wp_admin_bar->add_node(array(
            'id' => 'wp-mcr-shortcut',
            'title' => $this->appNameFull,
            'parent' => $parent,
            'href' => $href,
            'meta' => array(
                'class' => 'wp-mcr-shortcut',
                'title' => sprintf(__('Edit with %s', 'microthemer'), $this->appName)
            )
        ));
	}

	function getFileStub(){

        $user_id = get_current_user_id();

		if (!empty($this->preferences['draft_mode'])
            && !empty($this->preferences['draft_mode_uids'])
		    && in_array($user_id, $this->preferences['draft_mode_uids'])
        ) {
			$this->fileStub = 'draft';
        }
	}

	// perform redirect for e.g. Oxygen edit URL params for the current post
	// this is more performant than getting the edit links in the quick edit menu
	function redirect(){

		// redirect to Oxygen edit page
		if ( isset($_GET['mto2_edit_link']) && function_exists('oxygen_add_posts_quick_action_link') ){

			$nonce = !empty($_GET['_wpnonce']) ? $_GET['_wpnonce'] : false;

			if (current_user_can('manage_options') && wp_verify_nonce( $nonce, 'mt_builder_redirect_check' )) {

				global $post;

				// try to get link
				$edit_link = \oxygen_add_posts_quick_action_link(array(), $post);

				// we have a valid URL
				if (!empty($edit_link['oxy_edit'])){

					preg_match('/href="(.+?)"/', $edit_link['oxy_edit'], $matches);

					if (!empty($matches[1])){

						$edit_url = $matches[1];

						wp_redirect( esc_url($edit_url) );
					}
				}

				else {

					$reason = 'unknown';

					// warn that oxygen did not allow edit screen
					if (!oxygen_vsb_current_user_can_access()) {
						$reason = 'user-privileges';
					} if (get_option("oxygen_vsb_ignore_post_type_{$post->post_type}") == 'true') {
						$reason = 'post-type';
					} if (is_oxygen_edit_post_locked()) {
						$reason = 'edit-lock';
					}

					$this->builderBlockedEdit = array(
						'builder' => 'oxygen',
						'reason' => $reason
					);
				}
			}

            else {
				die('Permission denied');
			}
		}
	}

	function nonLoggedInMode(){

        if (isset($_GET['mt_nonlog'])) {

			$nonce = !empty($_GET['_wpnonce']) ? $_GET['_wpnonce'] : false;

            if (current_user_can('manage_options') and wp_verify_nonce( $nonce, 'mt_nonlog_check' ) ) {
	            wp_set_current_user(-1);
			}

			else {
				die('Permission denied');
			}
		}
	}

	function getCacheParam(){
		return 'nomtcache=' . time();
	}

	// get the current page for iframe-meta and loading WP page after clicking WP admin MT option
	function currentPageURL() {
		return Common::get_protocol() . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	function getCurrentPostData($id = 0){

		$post = get_post($id);

		return array(
			'post_title' => isset( $post->post_title ) ? $post->post_title : '',
			'post_id' => isset( $post->ID ) ? $post->ID : 0
		);
	}

    // we support the logic test if this file (for administrators only) is in use and GET param is set
	function supportLogicTest(){
		return isset($_GET['test_logic']);
	}

	function doLogicTest($folders, $logic, $forceAll = false){

		$testFolder = isset($_GET['test_logic'])
			? $_GET['test_logic']
			: null;
		$testAll = isset($_GET['test_all']) || $forceAll;
		$getStylesheets = isset($_GET['get_simple_stylesheets']);
		$stylesheets = '';
		$getFrontData = isset($_GET['get_front_data']);
        $defaultResponse = array(
	        'result' => 1,
	        'resultString' => 'true',
	        'logic' => 'Not set',
	        'analysis' => 'Either no logic or no folder settings have been defined, so this folder will load globally (on the frontend)',
	        'num_statements' => 0,
	        'load' => 'Yes'
        );
        $adminSupportedResponse = array_merge($defaultResponse, array(
	        'result' => 0,
	        'resultString' => 'false',
	        'load' => 'No',
	        'analysis' => 'No logic has been defined, so this folder will load on the frontend only',
        ));
		$adminUnsupportedResponse = array_merge($adminSupportedResponse, array(
			'analysis' => 'CSS in the admin area has not been enabled, optionally do this via Settings > Preferences.',
		));

		// if Microthemer has provided live gutenberg HTML with new template-parts/patterns/navigation
		// we need to update the map and possibly the post_content
		if (Helper::isLiveContentTest()){
			Common::updateLiveTemplateData($this->micro_root_dir);
		}

        // set default evaluation response
		$evaluation = $this->isAdminArea
            ? (
			    $this->supportAdminAssets()
                    ? $adminSupportedResponse
                    : $adminUnsupportedResponse
			)
            : $defaultResponse;

        $eligibleForLoading = !$this->isAdminArea || $this->supportAdminAssets();

        // 
		foreach ($folders as $folder){

			$slug = $folder['slug'];
			$file_exists = file_exists($this->rootDir . 'mt/conditional/draft/' . $slug . '.css');

			// if a condition has been set
			if (isset($folder['expr'])){

				// log all folders that load on the current page
				if ($testAll){
					$result = $logic->result($folder['expr']);
					$this->folderLoading[$slug] = $eligibleForLoading && $result
                        ? ($file_exists
							? (is_string($result) ? $result : 1) // preserve string result like 'blocksOnly'
							: 'empty')
                        : 0;

					// For FSE page changes (which only replaces inner content) we need to replace MT assets
					if ($getStylesheets && $file_exists && $this->folderLoading[$slug]){
						$stylesheets.= '<link rel="stylesheet" href="'.$this->micro_root_url.'mt/conditional/draft/'.$slug.'.css?'.$this->cacheParam.'" id="microthemer-'.$slug.'-css">' . "\n";
					}
				}

				// test a single folder, and provide debug info
				else {

					// bail if we have the result for a test folder
					if ($eligibleForLoading && $testFolder === $folder['slug']){

						$evaluation = $logic->result(
							$folder['expr'],
							true,
							$file_exists
						);

						break;
					}
				}
			}

		}

		$dataToReturn = $testAll
			? ($getFrontData
				? $this->addFrontendData(true)
				: ($getStylesheets
					// as array (not a string), so we can tease apart from any leading HTML before the json
					? array('stylesheets' => $stylesheets)
					: $this->folderLoading)
			)
			: $evaluation;

		//$dataToReturn['debugOutput'] = Helper::$debugOutput;
		//wp_die('Test all <pre>' . print_r($this->folderLoading, 1) . '</pre>');
		//echo 'Helper::$debugOutput <pre>' . Helper::$debugOutput . '</pre>';

		// return test folder result - unless we are just running this to set folderLoading
		if (!$forceAll){
			$this->testResultResponse($dataToReturn);
		}

	}

	function testResultResponse($testEvaluation){
        $this->jsonResponse($testEvaluation);
	}

}

