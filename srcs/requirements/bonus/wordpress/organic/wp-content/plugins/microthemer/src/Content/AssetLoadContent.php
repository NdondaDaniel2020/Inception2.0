<?php

namespace Microthemer\Content;

use \Microthemer\TimerTrait;

/*
 * AssetLoadContent
 *
 * Manage server-side content amendments and asset loading
 */

class AssetLoadContent {

	use TimerTrait;

	var $assetClass;
	var $preferences = array();
	var $assetLoadingKey;
	var $isEditing;
	var $published;
	var $devMode = false;
	var $dynAmends = false;
	var $noAmends = false;
	var $timeAmends = false;
	var $modList = array();
	var $debugAmends = false;
	var $clientSide = array();

	private static $hasRun = false;

	public function __construct(&$assetClass, $devMode) {

		// save reference to calling class
		$this->assetClass = $assetClass;
		$this->preferences = &$this->assetClass->preferences;
		$this->assetLoadingKey = $this->assetClass->assetLoadingKey;
		$this->published = $this->assetClass->draft ? 0 : 1;
		$this->devMode = $devMode;

		// Only admins can view the page differently by passing params (mostly for editor review functionality)
		$this->isEditing = $this->assetClass->context === 'edit';
		if ($this->isEditing){
			$this->debugAmends = isset($_GET['debug_amends']);
			$this->noAmends = isset($_GET['no_amends']);
			$this->dynAmends = isset($_GET['dyn_amends']) || $this->debugAmends || $this->noAmends;
			$this->timeAmends = isset($_GET['time_amends']);
		}

		if ($this->doAmends()){
			$this->hookOutputBuffer();
		}

	}

	function hookOutputBuffer(){
		add_action('wp',  array(&$this, 'setupOutputBufferCallback'));
		add_action('shutdown', array(&$this, 'buffer_end')); // do I need this?
	}

	function setupOutputBufferCallback(){
		ob_start(array(&$this, 'filterOutputBuffer'));
	}

	function buffer_end() {
		if (ob_get_contents()){
			ob_end_flush();
		}
	}

	function returnServerTiming($total_time, &$html, $memoryProfiler = array()){

		header('Content-type: application/json');

		// Build simplified memory stats (avg delta + peak)
		$avgDelta = isset($memoryProfiler['all_server_side_html_changes']['avg_delta'])
			? $memoryProfiler['all_server_side_html_changes']['avg_delta']
			: 0;
		$maxPeak = isset($memoryProfiler['all_server_side_html_changes']['max_peak_window'])
			? $memoryProfiler['all_server_side_html_changes']['max_peak_window']
			: 0;

		$html = json_encode(array(
			'server_html_timing' => $total_time * 1000, // return ms for consistency with JS
			'server_html_memory' => array(
				'avg_delta' => $avgDelta,
				'max_peak'  => $maxPeak
			)
		));
	}

	function filterOutputBuffer($html){

		if (!$html || self::$hasRun){
			return $html;
		}

		self::$hasRun = true;

		// Init HTML modification
		$HTML = new HTML($this, $this->devMode);

		// run the modifications against the HTML string
		try {
			$HTML->iterateMods($this->modList, $html);
		} catch (\Exception $e) {
			$HTML->log('Error applying amender modifications: ' . $e->getMessage());
		}

		return $html;
	}

	// Early filtering can check for on/off presence of server-side amends
	function doAmends(){
		return (
		($this->isEditing || !empty($this->preferences[$this->assetClass->assetLoadingKey]['html_mods']['all']))
		);
	}

	// Content modification
	function initContentAmendments(){
		
		if ($this->doAmends()){

			// bail if no active mods, or testing without
			$this->profiler['all_amender_changes_time'] = 0 . 'ms';
			$this->startT('preparing_client_side_assets');

			$activeSlugs = $this->getActiveSlugs();

			if (!$this->isEditing && !count($activeSlugs)){
				$this->endT('preparing_client_side_assets');
				return false;
			}

			// Get the mods from the database or bail if none
			$rows = count($activeSlugs)
				? $this->getActiveModsAndSnippets($activeSlugs, $this->published)
				: null;

			if (!$this->isEditing && !$rows){
				$this->endT('preparing_client_side_assets');
				return false;
			}

			// Data stores
			$extracted = array(

				// client side mods and snippets
				'clientSide' => array(
					'mods' => array(),
					'import_map' => array(),
					'function_deps' => array(),
					'funcNameMap' => array(),
					'js' => array(),
					'jsFunction' => array(),
					'css' => array(),
					'snippets' => array(),
					'asset_size' => array(
						'user_css' => 0,
						'user_js' => 0,
						'user_packages' => 0,
						'amender_packages' => 0,
						'amender_inline' => 0,

						// For subtracting from modifications
						'debug_only_data' => 0
					),

					// Populated if debugging
					'inline_functions' => '',
					'serverModsCount' => 0,
					'clientModsCount' => 0
				),

				// Build mods and snippet data for HTML parser to iterate through efficiently
				'serverSide' => array(
					'modList' => array(),
				)
			);

			// separate the client / server-side modifications
			if ($rows){
				$this->extractModsAndScripts($extracted, $rows);
			}

			// Client-side modifications / base settings for editing
			$this->clientSideSetup($extracted);

			if ($this->debugAmends){
				$this->clientSide = &$extracted['clientSide'];
			}

			// store serverSide mods on class
			if (count($extracted['serverSide']['modList'])){
				$this->modList = &$extracted['serverSide']['modList'];
			}

			//wp_die('$this->modList <pre>'.print_r([$this->isEditing, $this->modList], 1).'</pre>');

			// Free memory
			unset($rows);
			ContentHelper::cleanupMemory();

			$this->endT('preparing_client_side_assets');


		}
	}

	public function accumulateSizeKB($key, &$clientSide, &$str = null, $file = null) {
		if ($this->debugAmends){
			$str = $str !== null ? $str : file_get_contents($file);
			$kb = strlen($str) / 1024;
			$clientSide['asset_size'][$key]+= $kb;
		}
	}

	function extractModsAndScripts(&$extracted, &$rows){

		$allSnippets = array();

		foreach ($rows as $row){

			// if snippet reference, make easy to reference
			if ($row->type === 'snippet'){

				$allSnippets[$row->slug] = array(
					'meta' => $row->meta,
					'content' => $row->content
				);
				$aspect = trim($row->aspect);

				// Function imports need to be printed inline
				$snippet_deps = ContentHelper::getScriptDepsFromMeta($this->preferences['npm_dependencies'], $row->meta, true, false, true);

				if ($snippet_deps){
					foreach ($snippet_deps as $packageName => $config){

						// Internal function deps are handled with initial DB query and function map - include others though
						if (strpos($packageName, 'tvrjs-') === false){

							if ($aspect === 'jsFunction'){
								$extracted['clientSide']['function_deps'][$packageName] = $config['importSyntax'];
							}

							// We need an import map for all JS dependencies
							$extracted['clientSide']['import_map'][$packageName] = 1;
						}
					}
				}

			}

			// with folder mods, we need to parse the json and take action
			elseif ($row->type === 'folder_mod'){

				$data = json_decode($row->content, true);

				foreach ($data as $sectionSlug => $mqArray){

					foreach ($mqArray as $mq_key => $array){

						$mq_query = $array['mq_query'];
						if (!$mq_query){
							$mq_query = 'all-devices';
						}

						foreach ($array['selectors'] as $selectorSlug => $selectorData){

							$modArr = $selectorData['mods'];

							foreach ($modArr as $index => $mod){

								// Get aspect and snippet
								$action = isset($mod['action']) ? trim($mod['action']) : '';
								$aspect = isset($mod['aspect']) ? trim($mod['aspect']) : 'html';
								$snippet_id = !empty($mod['snippet_id']) ? trim($mod['snippet_id']) : false;

								// extract juncture info
								$wasExtracted = false;
								$juncture = !empty($mod['juncture'])
									? $mod['juncture']
									: ($aspect === 'jsFunction'
										? 'DOMContentLoaded'
										: ($action === 'lazyLoad'
											? 'serverHTMLReady'
											: $this->preferences['default_amender_event']
										)
									);
								$junctureMeta = $this->extractJunctureInfo($juncture);
								$juncture = $junctureMeta['native'];
								unset($junctureMeta['native']);

								// resolve new value - use a snippet if an id is provided - otherwise plain text
								$newValue = !empty($mod['snippet_id']) && !empty($allSnippets[$snippet_id])
									? $allSnippets[$snippet_id]['content']
									: (isset($mod['text'])
										? $mod['text']
										: '');

								// for css/js, we add the combined code inside one style/script tag
								// The CSS selector is ignored. But if they choose "html" they can insert anywhere
								// If the content should be extracted for specific placement in the DOM
								// such as a style or script tag
								if ($snippet_id && isset($extracted['clientSide'][$aspect])){

									$meta = !empty($allSnippets[$snippet_id]['meta'])
										? (
											is_array($allSnippets[$snippet_id]['meta'])
												? $allSnippets[$snippet_id]['meta']
												: json_decode($allSnippets[$snippet_id]['meta'], true)
										)
										: array();

									// We only need the file name for JavaScript
									if ($aspect === 'js'){
										$metaWrapper = array('meta' => $meta);
										$newValue = ContentHelper::getJsFileName(
											$metaWrapper,
											$snippet_id,
											false
										);
									}

									// We need to keep track of function name / snippet_id relationship
									if ($aspect === 'jsFunction'){
										$extracted['clientSide']['funcNameMap'][$snippet_id] = !empty($meta['funcName'])
											? $meta['funcName']
											: '';
									}

									$extracted['clientSide'][$aspect][$snippet_id] = $newValue;
									$wasExtracted = true;
								}

								// If we're running the mod on server-side HTML
								// (and we're not deferring them to the client side for MT instant "undo")
								if (!$this->dynAmends && $juncture === 'serverHTMLReady'){

									if (!$wasExtracted){

										$xpathSelector = !empty($mod['xpath'])
											? $mod['xpath']
											: $selectorData['xpath'];

										// Store the server-side mod value for processing later
										$extracted['serverSide']['modList'][] = array(
											$xpathSelector, $mod, $aspect, $newValue,
											$sectionSlug, $selectorData['selectorCode'], $mq_query
										);
									}
								}

								// It's a client-side modification (or dynAmends is active)
								else {

									// Log count for server-side, even if we are using dynAmends
									if ($juncture === 'serverHTMLReady'){
										$extracted['clientSide']['serverModsCount']++;
									} else {
										$extracted['clientSide']['clientModsCount']++;
									}

									// add the snippet to an array if defined
									if ($snippet_id && !$wasExtracted){
										$extracted['clientSide']['snippets'][$snippet_id] = $newValue;
									}

									// remove redundant info for non-logged
									unset($mod['xpath']);

									// (juncture is needed for change comparison when logged in)
									if ($this->assetClass->context !== 'edit'){
										unset($mod['juncture']); // this has info about orig event (may need)
									}

									$item = array( // [] ensures numeric array
										'undo' => array(),
										'meta' => (object) $junctureMeta,
										'mod' => $mod,
									);

									if ($this->debugAmends){
										$item['debugValue'] = $newValue;
										$this->accumulateSizeKB(
											'debug_only_data',
											$extracted['clientSide'],
											$newValue
										);
									}

									$selectorId = $sectionSlug.'-'.$selectorSlug;

									$extracted['clientSide']['mods'][$juncture][$mq_query][$selectorId]['selectorCode'] = $selectorData['selectorCode'];

									// Apply mod, note we need to ensure mods is an array on the client side
									// The first index might be e.g. "5" which can make it an object.
									$extracted['clientSide']['mods'][$juncture][$mq_query][$selectorId]['mods'][$index] = $item;

								}
							}
						}
					}
				}
			}
		}
	}
	
	function getDependencyInfo($packageName){
		return !empty($this->preferences['npm_dependencies']->$packageName)
			? $this->preferences['npm_dependencies']->$packageName
			: null;
	}

	function deliveryUrl($dep, $packageName, $vendorDir, $relative = false, $vendor = true){

		$version = $this->assetClass->mts;
		$localUrl = $this->getLocalJsFile($packageName, $relative, $vendor);
		$dep = $dep ?: $this->getDependencyInfo($packageName);

		if (!$dep){
			return $localUrl . '?v=' . $version;
		}

		$version = $dep['version'];
		$localPath = $vendorDir . $dep['local'];
		if (empty($dep['cdn']) || (!empty($dep['isInstalled']) && file_exists($localPath))){
			return $localUrl . '?v=' . $version;
		}

		// return CDN URL if no local url
		return 'https://cdn.jsdelivr.net/npm/' . $dep['cdn'];
	}

	// build list of assets to load on the page inline, and initiate
	function popuplateInlineDeps($packageName, $dep, $pointTo, &$inline){

		if (str_contains($packageName, 'alpinejs')){

			$importSyntax = $dep['importSyntax'];
			$item = array(
				'import' => 'import ' . $importSyntax . ' from "'.$packageName.'";' . "\n",
			);

			// Ensure the core library comes first
			if ($packageName === 'alpinejs'){
				$item['init'] = "window.Alpine = Alpine;\nAlpine.start();\n";
				array_unshift($inline['statements'], $item);
			} else {
				$item['register'] = "Alpine.plugin(".$importSyntax.");\n";
				$inline['statements'][] = $item;

				// Plugins like x-mask require a second scan to work
				$inline['post_init']['alpinejs'] = 'Alpine.initTree(document.body);' . "\n";
			}

		}
	}

	function applyInlineDeps(&$inline){

		$imports = '';
		$register = '';
		$init = '';
		$init_ready_used = false;

		// In case this is needed to fix any issues - alpine may not always work
		$init_ready = 'addEventListener("load", (event) => {' . "\n";

		foreach($inline['statements'] as $statement){
			if (!empty($statement['import'])){
				$imports.= $statement['import'];
			} if (!empty($statement['register'])){
				$register.= $statement['register'];
			} if (!empty($statement['init'])){
				$init.= $statement['init'];
			} if (!empty($statement['init_ready'])){
				$init_ready.= $statement['init_ready'];
				$init_ready_used = true;
			}
		}

		// Post init things, like alpine 2nd scan for x-mask
		$init.= implode('', $inline['post_init']);

		if ($init_ready_used){
			$init_ready.= "\n});\n";
		}

		$inline_content = $imports . $register . $init . ($init_ready_used ? $init_ready : '');

		if ($inline_content){
			$this->hookScript(
				'amender-init-deps',
				'',
				'inline_module',
				$inline_content
			);
		}

	}

	function clientSideSetup(&$extracted){

		$clientSide = &$extracted['clientSide'];
		$hasClientSideMods = count($clientSide['mods']) || $this->isEditing;
		$vendorDir = $this->assetClass->rootDir . 'mt/js/'.$this->assetClass->fileStub.'/npm/';

		if (!$hasClientSideMods){
			return false;
		}

		/*
		 * Main amender window object data - set outside of a module
		 */
		$inline_data = json_encode(array(
			'isEditing' => $this->isEditing,
			'noAmends' => $this->noAmends,
			'debugAmends' => $this->debugAmends,
			'snippets' => $clientSide['snippets'],
			'mods' => $clientSide['mods'],
			// Map snippet_ids to function names
			'funcNameMap' => $clientSide['funcNameMap']
		));

		$this->hookScript(
			'amender-data',
			'',
			'inline',
			'window.amender = ' . $inline_data . ";\n\n" // . "console.log('Amender config', window.amender);"
		);

		if ($this->debugAmends){
			$this->accumulateSizeKB(
				'amender_inline',
				$extracted['clientSide'],
				$inline_data
			);
		}

		/*
		 * Dynamically constructed AF functions object
		 */

		// create import map entries - WP manages an import map
		$imports = array();

		// We also need to manage certain assets inline - like Apline.js
		$inline = array(
			'statements' => array(),
			'post_init' => array()
		);

		foreach ($clientSide['import_map'] as $packageName => $one){

			$dep = $this->getDependencyInfo($packageName);

			if ($dep){

				// register in import map
				$pointTo = $this->deliveryUrl($dep, $packageName, $vendorDir);
				$imports[] = $packageName;

				wp_register_script_module($packageName, $pointTo);

				// Support deps that need to load inline, rather than being imported in JS
				$this->popuplateInlineDeps($packageName, $dep, $pointTo, $inline);

				if ($this->debugAmends && !empty($dep['size'])){
					$extracted['clientSide']['asset_size']['user_packages'] += $dep['size'];
				}
			}

		}

		// Register items in the import map - use a dummy file for now, better solution later
		if (count($imports)){
			wp_enqueue_script_module(
				'register-in-map',
				$this->getFrontDepsUrl('mt-register-import-map-entries'),
				$imports
			);
		}

		// Import statements for functions
		$functionDeps = '';
		if (count($clientSide['function_deps'])){
			$register = array();
			foreach ($clientSide['function_deps'] as $packageName => $importSyntax){
				if (!empty($importSyntax)){
					$functionDeps.= "import $importSyntax from '$packageName';\n";
					ContentHelper::populateRegistered($packageName, $importSyntax, $register);
				}
			}
			ContentHelper::applyRegistered($register, $functionDeps);
			$functionDeps.= "\n";
		}

		// Dynamically prepared functions
		$functionObject = '{' . "\n";
		foreach ($clientSide['jsFunction'] as $jsFunction) {
			if ($jsFunction) {
				try {
					$functionObject .= preg_replace('/^function\s*/', '', $jsFunction, 1) . ",\n";
				} catch (\Exception $e) {
					// $this->log('Error with function', $e->getMessage());
				}
			}
		}

		$functionObject.= '};';
		$inlineFunctions = $functionDeps . 'var AF = window.AF = ' . $functionObject . "\n\n";

		// Alpine is not instantiated by JS, but should apply to HTML
		// So it needs to be initiated manually
		$this->applyInlineDeps($inline);

		$this->hookScript(
			'amender-functions',
			'',
			'inline_module',
			$inlineFunctions //. " console.log('Amender functions', window.AF);"
		);

		if ($this->debugAmends){
			$extracted['clientSide']['inline_functions'] = $inlineFunctions;
		}

		// User JS scripts
		$time = time();
		foreach ($clientSide['js'] as $snippet_id => $file_slug){
			$this->hookScript(
				'amender-script-'.$snippet_id.'',
				$this->getLocalJsFile($file_slug, 0, 0, 0, '?v=' . $time),
				'module',
			);
		}

		// MT scripts
		$mtScripts = array('mt-events');
		foreach ($mtScripts as $slug){
			$this->hookScript(
				'amender-'.$slug,
				$this->getFrontDepsUrl($slug) . '?v=' . $this->assetClass->pluginVersion,
				'module'
			);
		}

		if ($this->debugAmends){
			$allAmenderPackages = array('mt-events', 'mt-apply-mod', 'mt-visibility-observer');
			$str = null;
			foreach ($allAmenderPackages as $handle){
				$this->accumulateSizeKB(
					'amender_packages',
					$clientSide,
					$str,
					$this->getFrontDepsUrl($handle, true)
				);
			}
		}

	}

	// Regular enqueue system did not set HTML attribute type=module
	// Debug further if needed
	function hookScript($handle, $src = '', $type = 'plain', $inline_content = '', $action = null, $order = null){

		if ($action === null){
			$action = $this->assetClass->hooks['footer'];

			// importmap is printed at 'admin_print_footer_scripts' in the admin area
			// So scripts need to come after that
			if ($this->assetClass->isAdminArea) {
				$action = 'admin_print_footer_scripts';
			}
		}

		if ($order === null){
			$order = $this->assetClass->defaultActionHookOrder;
		}
		
		$typeAttribute = $type === 'module' || $type === 'inline_module'
			? ' type="module"'
			: ($type === 'importmap' || $type === 'inline_importmap'
				? ' type="importmap"'
				: '');
		$srcAttribute = $src ? ' src="'.$src.'"' : '';

		add_action($action, function () use ($srcAttribute, $handle, $typeAttribute, $inline_content) {
			printf(
				"<script id='$handle-js'%s%s>%s</script>\n",
				$srcAttribute,
				$typeAttribute,
				$inline_content
			);
		}, $order);

	}

	function getActiveSlugs() {

		$activeSlugs = array();
		$asset_loading = $this->preferences[$this->assetLoadingKey];
		$deps = $this->getFunctionDeps();
		$html_mods = $asset_loading['html_mods'];

		// fetch global folder collective mods (frontend only)
		if (!$this->assetClass->isAdminArea){

			if (!empty($html_mods['global']['folder'])){
				$activeSlugs['mt_collective_global'] = 1;
			}

			// include global snippets
			if (!empty($html_mods['global']['snippets'])){
				foreach ($html_mods['global']['snippets'] as $snippet_id => $one){
					$this->addSnippetAndDepIds($snippet_id, $deps, $activeSlugs);
				}
			}
		}

		foreach($this->assetClass->folderLoading as $folderSlug => $on){

			// if the folder is loading
			if (intval($on)){

				// if folder should run it's mods, add slug
				if (!empty($html_mods['conditional'][$folderSlug]['folder'])){
					$activeSlugs[$folderSlug] = 1;
				}

				// fetch snippet_ids used by the folder too 
				if (!empty($html_mods['conditional'][$folderSlug]['snippets'])){
					foreach ($html_mods['conditional'][$folderSlug]['snippets'] as $snippet_id => $one){
						$this->addSnippetAndDepIds($snippet_id, $deps, $activeSlugs);
					}
				}
			}
		}

		return $activeSlugs;
	}

	function getFunctionDeps(){

		$deps = null;
		$depsFile = $this->assetClass->rootDir . 'mt/cache/content/function-deps.json';

		if (file_exists($depsFile)){

			$json = file_get_contents($depsFile);

			if ($json){
				$deps = json_decode($json, true);
			}
		}

		return $deps;
	}

	// add snippet if not already logged, and recursively check deps from cached deps config
	function addSnippetAndDepIds($snippet_id, &$deps, &$activeSlugs){

		// if we have a valid id and the snippet hasn't already been checked for deps
		if ($snippet_id && empty($activeSlugs[$snippet_id])){

			$activeSlugs[$snippet_id] = 1;

			// check deps too
			if ($deps && !empty($deps[$snippet_id])){
				foreach ($deps[$snippet_id] as $dep_id){
					$this->addSnippetAndDepIds($dep_id, $deps, $activeSlugs);
				}
			}
		}

	}

	function getActiveModsAndSnippets($activeSlugs, $published) {

		global $wpdb;

		$mods_table = $wpdb->prefix . "micro_content";

		// Bail early if there are no slugs
		if (empty($activeSlugs)) {
			return [];
		}

		// Create a comma-separated placeholder list for the IN clause
		$placeholders = implode(',', array_fill(0, count($activeSlugs), '%s'));

		// Build the SQL query using IN, which utilises indexes better
		$sql = $wpdb->prepare(
			"SELECT * FROM $mods_table 
        WHERE published = %d 
        AND slug IN ($placeholders) 
        ORDER BY type DESC, seq",
			array_merge([$published], array_keys($activeSlugs))
		);

		return $wpdb->get_results($sql);
	}


	function extractJunctureInfo($input) {

		// Initialize the array to capture the matches
		$result = array();

		// Check if '_once' is in the string
		if (strpos($input, '_once') !== false) {
			$result['once'] = 1;
			$input = str_replace('_once', '', $input);
		}

		// Check if 'delay' is in the string
		if (strpos($input, '_delay') !== false) {
			$input = preg_replace_callback('/(\w+)_delay(?:_(\d+))?/', function ($matches) use (&$result) {
				$result['delay'] = isset($matches[2]) ? (int)$matches[2] : 18;
				return $matches[1];
			}, $input);
		}

		// Check if '_lazy' is in the string
		if (strpos($input, '_lazy') !== false) {
			$result['lazy'] = 1;
			$input = str_replace('_lazy', '', $input);
		}

		$result['native'] = $input;

		// Return the modified string and the matches
		return $result;
	}

	function getLocalJsFile($slug, $relative = false, $npm = false, $dirPath = false, $v = ''){
		$npmPath = $npm ? 'npm/' : '';
		$relativePath = $npmPath . str_replace('@', '', $slug) . '.js' . $v;
		$path = $dirPath ? $this->assetClass->rootDir: $this->assetClass->rootUrl;
		return $relative
			? './' . $relativePath
			: $path . 'mt/js/'.$this->assetClass->fileStub.'/' . $relativePath;
	}


	function getFrontDepsUrl($slug, $dirPath = false){
		return $this->devMode && !$dirPath
			? plugins_url() . '/microthemer/js/front-deps/amender/' . $slug . '.js'
			: ($dirPath
				? $this->assetClass->rootDir
				: $this->assetClass->rootUrl)
			  . 'mt/js/amender/' . $slug . '.js';
	}

	function runTailwindOnPage(){

		$doLoad = true;

		// The WP plugins screen has some compatibility issues, with display of upgrades notice
		// There may be other places - so take a white-listing approach - or let user define logic
		// Maybe we have a special tailwind folder with JS / CSS customisation and some default logic
		if ($this->assetClass->isAdminArea){
			$screen = get_current_screen();
			$doLoad = $screen->is_block_editor() ? 'forBlocks' : false;
		}

		return $doLoad;
	}

	function maybeLoadTailwind(&$p, $add){

		$doLoad = $this->runTailwindOnPage();

		if (!empty($p['tailwind']) && $doLoad){

			$cacheParam = !empty($p['tailwind_num_saves'])
				? $p['tailwind_num_saves']
				: $this->assetClass->mts;

			$pageMeta = $this->assetClass->pageMeta();
			$type = $pageMeta['type'];
			$page = 'mt/cache/tailwind/styles/'.$type.'-'.$pageMeta['id'].'.css';
			$siteWide = 'mt/cache/tailwind/styles/site-wide.css';
			$pagePath = $type !== null && file_exists($this->assetClass->rootDir . $page)
				? $this->assetClass->rootDir . $page
				: null;
			$siteWidePath = file_exists($this->assetClass->rootDir . $siteWide)
				? $this->assetClass->rootDir . $siteWide
				: null;

			// use single page tailwind if available, otherwise use site-wide
			$tailwindPath = $pagePath ?: ($siteWidePath ?: null);
			
			if (!$tailwindPath){
				return;
			}

			$inline = !!$pagePath;
			$content = file_get_contents($tailwindPath);

			if ($content){
				$tailwindUrl = $this->assetClass->rootUrl . ($pagePath ? $page : $siteWide);
				$this->assetClass->enqueueOrAdd(
					($add || $inline),
					'mt-tailwind', // use mt- instead of microthemer- so it isn't removed when MT inits
					$tailwindUrl . '?' . $cacheParam,
					array(
						'inline' => $inline,
						'code' => $inline ? $content : false
					)
				);
			}

		}
	}


}