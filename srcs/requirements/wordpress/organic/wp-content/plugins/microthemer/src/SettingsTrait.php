<?php

namespace Microthemer;

trait SettingsTrait {

	/*function getRevisionForIndexedDB($revision_id, $withSettings = false, $jsonResponse = false, $recent = null){
		global $wpdb;
		$table_name = $wpdb->prefix . "micro_revisions";
		$columns = 'user_action, preferences, snippets'; // , meta
		if ($withSettings){
			$columns.= ', settings';
		}
		$rev = $wpdb->get_row( $wpdb->prepare("SELECT $columns FROM $table_name WHERE id = %d", $revision_id) );

		$data = $rev ? array(
			'revision_id' => $revision_id,
			'user_action' => $rev->user_action ? json_decode($rev->user_action) : '', // should be json_decode
			'preferences' => $rev->preferences ? unserialize($rev->preferences) : '',
			'snippets' => $rev->snippets ? unserialize($rev->snippets) : '',
			'settings' => $withSettings ? unserialize($rev->settings) : '',
			//'meta' => $rev->meta,
		) : null;

		if (!$jsonResponse){
			return $data;
		}

		$this->jsonResponse($data);
	}*/

	function getRevisionForIndexedDB($revision_id, $withSettings = false, $jsonResponse = false, $recent = null) {
		global $wpdb;
		$table_name = $wpdb->prefix . "micro_revisions";
		$columns = 'id, user_action, preferences, snippets';
		if ($withSettings) {
			$columns .= ', settings';
		}

		// Helper to normalize a DB row into an array
		$normalizeRevision = function($rev) use ($withSettings) {
			return array(
				'revision_id' => isset($rev->id) ? (int) $rev->id : null,
				'user_action' => !empty($rev->user_action) ? json_decode($rev->user_action) : '',
				'preferences' => !empty($rev->preferences) ? unserialize($rev->preferences) : '',
				'snippets'    => !empty($rev->snippets) ? unserialize($rev->snippets) : '',
				'settings'    => $withSettings && isset($rev->settings) ? unserialize($rev->settings) : '',
			);
		};

		if ($recent !== null) {
			// Fetch multiple recent revisions
			$recent = (int) $recent;
			$results = $wpdb->get_results(
				$wpdb->prepare("SELECT $columns FROM $table_name ORDER BY id DESC LIMIT %d", $recent)
			);

			$data = array();
			if ($results) {
				foreach ($results as $rev) {
					$data[] = $normalizeRevision($rev);
				}
			}
		} else {
			// Fetch a single revision by ID
			$rev = $wpdb->get_row(
				$wpdb->prepare("SELECT $columns FROM $table_name WHERE id = %d", $revision_id)
			);

			$data = $rev ? $normalizeRevision($rev) : null;
		}

		if (!$jsonResponse) {
			return $data;
		}

		$this->jsonResponse($data);
	}


	function actionSaveInterface(){

		// remove slashes and custom escaping so that DB data is clean
		$this->serialised_post =
			$this->deep_unescape($_POST, 1, 1, 1);

		if (!empty($this->serialised_post['serialise'])){
			$this->serialised_post['tvr_mcth'] = $this->json('decode', $this->serialised_post['tvr_mcth']);
			//json_decode($this->serialised_post['tvr_mcth'], true);
			/*echo 'show_me from tvr_mcth: <pre> ';
					print_r($_POST);
					echo '</pre>';*/
		}

		// bail if no save data was successfully decoded
		if (empty($this->serialised_post['tvr_mcth'])) {
			return false;
		}

		// strange Kinsta error prompted this but might have been a fleeting issue
		$partial = !empty($this->serialised_post['partial_data'])
			? $this->serialised_post['partial_data']
			: false;
		$last_save_time = !empty($this->serialised_post['last_save_time'])
			? $this->serialised_post['last_save_time']
			: false;
		$new_select_option = '';
		$revisionData = array();

		//$revision_id = null;
		//$revision = null;


		/*$debug = true;
		if ($debug){
			echo 'show_me from ajax save (before): <pre>';
			echo print_r($this->serialised_post, 1);
			echo '</pre>';
		}*/

		// save settings in DB
		if (!$this->saveUiOptions2(
			$this->serialised_post['tvr_mcth'],
			$partial,
			$last_save_time
		)) {

			// save error
			$this->log(
				esc_html__('Settings failed to save', 'microthemer'),
				'<p>' . esc_html__('Saving your settings to the database failed.', 'microthemer') . '</p>'
			);
		}

		// save successful
		else {

			$saveOk = empty($this->preferences['auto_publish_mode'])
				? esc_html__('Draft saved', 'microthemer')
				: esc_html__('Settings saved and published', 'microthemer');
			$skip_revision = !empty($this->serialised_post['skip_revision']);

			$this->log(
				$saveOk,
				'<p>' . esc_html__('The UI interface settings were successfully saved.', 'microthemer') . '</p>',
				'notice'
			);

			// check if settings need to be exported to a design pack
			if (!empty($this->serialised_post['export_to_pack']) && $this->serialised_post['export_to_pack'] == 1) {

				$theme = htmlentities($this->serialised_post['export_pack_name']);
				$context = 'existing';
				$do_option_delete = false;

				if ($this->serialised_post['new_pack'] == 1){
					$context = 'new';
					$do_option_delete = true;
				}

				// function return sanitised theme name
				$theme = $this->update_json_file($theme, $context);

				// save new sanitised theme in span for updating select menu via jQuery
				if ($do_option_delete) {
					$meta_file = $this->micro_root_dir.$theme.'/meta.txt';
					if (file_exists($meta_file)){
						$meta_info = $this->read_meta_file($meta_file);
						$new_select_option = $meta_info['Name'];
					} else {
						$new_select_option = $this->readable_name($theme);
					}

				}
				//$user_action.= sprintf( esc_html__(' & Export to %s', 'microthemer'), '<i>'. $this->readable_name($theme). '</i>');
			}

			// else its a standard save of custom settings
			else {
				$theme = 'customised';
				//$user_action.= esc_html__(' (regular)', 'microthemer');
			}

			// Restore snippets stored in indexedDB
			if (!empty($this->serialised_post['restoreSnippets'])){
				$this->contentMethod('restoreSnippets', array(
					&$this->serialised_post['restoreSnippets'],
					false
				));
			}

			else {
				// Save snippets in DB if set
				if (!empty($this->serialised_post['snippet_cache']) || !empty($this->serialised_post['snippets_deleted'])){
					$this->contentMethod('addUpdateOrDeleteSnippets', array(
						&$this->serialised_post['snippet_cache'],
						&$this->serialised_post['snippets_deleted']
					));
				}
			}



			// update active-styles.css
			$this->update_assets($theme);

			// update the revisions DB field
			if (!$skip_revision){
				$user_action = !empty($this->serialised_post['user_action'])
					? json_encode($this->serialised_post['user_action'])
					: null;
				$revision_id = $this->updateRevisions(
					$this->options, $user_action, 1, 0, 0,
					$this->supportContent()
				);
				if (!$revision_id) {
					$this->log('','','error', 'revisions');
				} else {
					$revisionData = $this->getRevisionData($revision_id);
				}
			}

		}


		//echo 'carrots!';
		//wp_die();

		// return the globalmessage and then kill the program - this action is always requested via ajax
		// also fullUIData as an interim way to keep JS ui data up to date (post V5 will have new system with less http)
		$html = '<div id="microthemer-notice">' . $this->display_log() . '</div>';

		/*<span id="outdated-tab-issue">'.$this->outdatedTabIssue.'</span>
							<span id="returned-save-time">'.$this->options['non_section']['last_save_time'].'</span>*/

		// we're returning a JSON obejct here, the HTML is added as a property of the object
		$response = array_merge(array(
			'html'=> $html,
			'outdatedTab'=> $this->outdatedTabIssue,
			'outdatedTabDebug'=> $this->outdatedTabDebug,
			'returnedSaveTime'=> !empty($this->options['non_section']['last_save_time'])
				? $this->options['non_section']['last_save_time']
				: time(),
			'returnedRevisions' => $this->getRevisions(true),
			'exportName' => $new_select_option,
			'num_saves' => $this->preferences['num_saves'],
			'asset_loading' => $this->preferences['asset_loading'],
			'asset_loading_change' => $this->asset_loading_change,
			'adjusted_logic' => !empty($this->serialised_post['adjusted_logic']),
			'recent_logic' => $this->preferences['recent_logic'],
			'num_unpublished_saves' => $this->preferences['num_unpublished_saves'],
			//'revision_id' => $revision_id !== 'bail' ? $revision_id : null,
			//'revision' => $revision
			//'uiData'=> $this->options
			//'uiData'=> array()
		), $revisionData);

		echo json_encode($response); //$html;
	}

	function getRevisionData($revision_id){
		$revisionData = array();
		if ($revision_id && is_numeric($revision_id)) {
			$revision = $this->getRevisionForIndexedDB($revision_id);
			if ($revision){
				$revision['settings'] = $this->options;
				$revisionData['revision_id'] = $revision_id;
				$revisionData['revision'] = &$revision;
			}
		}
		return $revisionData;
	}

	function getRelevantPreferences(&$addTo, &$addFrom) {
		$prefKeys = [
			'mq_device_focus',
			'pg_focus'
		];

		foreach ($prefKeys as $key) {
			if (array_key_exists($key, $addFrom)) {
				$addTo[$key] = $addFrom[$key];
			}
		}

		//return $addTo;
	}


	function publishSettings(){

		$microthemerActive = $this->supportGUICSS();

		// They cannot publish Amender if no subscription
		$unableToPublishAmender = $this->supportContent() && !$this->hasContentSubscription();

		// They cannot publish anything if they are just using Amender free trial without Microthemer
		$unableToPublishAnything = $unableToPublishAmender && !$microthemerActive;

		$canPublishSomething = !$unableToPublishAnything;

		// We can publish all settings if they have an amender subscription or they are just using Microthemer
		$canPublishEverything = $this->hasContentSubscription() || !$this->supportContent();

		$response = array(
			'num_unpublished_saves' => $canPublishEverything
				? 0
				: $this->preferences['num_unpublished_saves'],
			'notify_amender_subscription_needed' => $unableToPublishAmender
				? 1
				: 0,
		);

		// If they can publish Microthemer or Amender changes, update assets
		if ($canPublishSomething){

			$this->update_assets('customised', '', array('active' => 1));

			$pref_array = array(
				'num_unpublished_saves' => $response['num_unpublished_saves'],
				'asset_loading_published' => &$this->preferences['asset_loading'],
				'global_stylesheet_required_published' => &$this->preferences['global_stylesheet_required'],
				'load_js_published' => $this->preferences['load_js'],
			);

			// Publish content modifications if allowed
			if ($this->hasContentSubscription()){

				// Update the database
				$this->contentMethod('publishHTMLTable', array(), true);

				// Copy (and minify user JS and NPM)
				$this->contentMethod('publishJavaScript', array(), true);

				// Copy the draft npm dependencies
				$pref_array['npm_dependencies_published'] = &$this->preferences['npm_dependencies'];
			}

			// update the published preferences
			$this->savePreferences($pref_array);

		}

		//$this->log('Test error', 'An issue occurred!');

		/*$response = array(
			'num_unpublished_saves' => 0,
			'notify_amender_subscription_needed' => $this->supportContent() && !$this->hasContentSubscription()
				? 1
				: 0,
		);*/

		if (!empty($this->globalmessage)){
			$response['html'] = '<div id="microthemer-notice">' . $this->display_log() . '</div>';
		}

		return json_encode($response);

	}

	// Reset the options.
	function resetUiOptions(){

		// Delete the main UI settings
		delete_option($this->optionsName);

		// Reset the defaults
		$this->getOptions();

		// Reset certain preferences
		$pref_array = array();
		$pref_array['active_theme'] = 'customised';
		$pref_array['theme_in_focus'] = '';
		$pref_array['num_saves'] = 0;
		$pref_array['g_fonts_used'] = false;
		$pref_array['g_url'] = '';
		$pref_array['g_url_with_subsets'] = '';
		$pref_array['hover_inspect'] = 1;
		$pref_array['lastMultiTab']['html'] = array(
			'index' => 0,
			'action' => 'replace',
			'aspect' => 'text',
		);
		$pref_array['npm_dependencies_in_use'] = (object) array();
		$this->savePreferences($pref_array);

		// Reset the snippets (folder_mods will be overwritten on next save)
		$this->contentMethod('deleteDraftSnippets');

		return true;
	}

	// Save the UI styles to the database - from full or partial save package
	function saveUiOptions2($savePackage, $partial = false, $last_save_time = false){

		// check last save time
		if (!$this->check_last_save_time($last_save_time)){
			return false;
		}

		// plain save if no save package
		if (!$partial){
			$this->options = $savePackage;
		}

		// loop through update items making adjustments to $this->options
		else {
			$this->apply_save_package($savePackage, $this->options);
		}

		// tag version the settings were saved at so e.g. css units can be imported correctly for legacy data
		$this->options['non_section']['mt_version'] = $this->version;

		// we don't want to store the snippets cache twice. It gets added when getting the $options.
		unset($this->options['non_section']['meta']['snippets_cache']);

		// update DB
		update_option($this->optionsName, $this->options);

		return true;

	}

	// check the last save time
	function check_last_save_time($last_save_time){

		// if we have no last_save_time to compare, set it for future reference
		if (!isset($this->options['non_section']['last_save_time'])){
			$this->options['non_section']['last_save_time'] = time();
		}

		// else we do have a time in the DB and a passed save time to compare
		else if ($last_save_time){

			// do safety check to make sure newer settings haven't been applied in another tab
			// allow passed last save time to be 15 seconds out due to quirk of resave I haven't fully understood
			if ( intval($last_save_time + 15) < intval($this->options['non_section']['last_save_time']) ){

				$this->log(
					esc_html__('Multiple tabs/users issue', 'microthemer'),
					'<p>' . esc_html__('MT settings were updated more recently by another user or browser tab. Saving from this outdated tab could cause data loss. Please reload the page instead of saving from this tab (to get the latest changes).', 'microthemer') . '</p>'
				);

				$this->outdatedTabDebug = 'Last save time: '.intval($last_save_time). ", \n" .
				                          'Stored save time: '.intval($this->options['non_section']['last_save_time'])  . ", \n" .
				                          'Difference: ' . (intval($last_save_time) - intval($this->options['non_section']['last_save_time']));

				$this->outdatedTabIssue = 1;

				return false;
			}

			else {

				$this->outdatedTabDebug = 'Last save time: '.$last_save_time. ", \n" .
				                          'Stored save time: '.$this->options['non_section']['last_save_time'] . ", \n" .
				                          'Difference: ' . (intval($last_save_time) - intval($this->options['non_section']['last_save_time']));

				// update last save time
				$this->options['non_section']['last_save_time'] = time();



			}



		}

		return true;
	}

	// update the ui options using & reference to behave like JS object
	function apply_save_package($savePackage, &$data){

		$before_after = array('### Save Package Before and After ###');

		foreach($savePackage as $update){

			if ($update['action'] === 'debug'){
				if ($this->debug_save_package) {
					$before_after[] = $update['data'];
				}
				continue;
			} elseif ($update['action'] === 'no_new_data'){
				continue;
			}

			$before = false;
			if ($this->debug_save_package) {
				$before                 = $this->get_or_update_item($data, array_merge($update, array('action' => 'get')));
				$update[ 'callerFunc' ] = !empty($update[ 'callerFunc' ]) ? $update[ 'callerFunc' ] : '';
			}

			$data_item = &$this->get_or_update_item($data, $update, 0);

			if ($this->debug_save_package) {
				$before_after[] = array(
					'before '.$update['callerFunc'].' (' .$update['action'].')' => $before,
					'after '.$update['callerFunc'].' (' .$update['action'].')' => $data_item,
					'update_package '.$update['callerFunc'].' (' .$update['action'].')' => $update
				);
			}
		}

		if ($this->debug_save_package) {
			$before_after[] = array(
				'Full options:' => $this->options
			);
			$write_file = @fopen($this->debug_dir . 'save-package.txt', 'w');
			fwrite($write_file, print_r($before_after, true));
			fclose($write_file);
		}

	}


	// (optionally) update a multidimensional array item using array trail e.g. ['non_section', 'meta'].
	// Returns a reference to the target item. Note '&' must proceed function call for ref rather than copy.
	function &get_or_update_item(&$data, $config, $startIndex = 0){

		$item = &$data;
		$trail = !empty($config['trail']) ? $config['trail'] : array();
		$trail_length = count($trail);

		// to get round PHP error: Only variable references should be returned by reference
		$false = false;

		for ($x = $startIndex; $x < $trail_length; $x++) {
			$key = $trail[$x];

			// if item doesn't exist
			if (!isset($data[$key])){

				// bail if we're trying to get an item that doesn't exist
				if ($config['action'] === 'get'){

					/* $this->log(
								 esc_html__('Trail lead to undefined item: '.$key, 'microthemer'),
								 '<pre>parent: '  . print_r($data, true) . '</pre>'
								 //'notice'
							 );*/

					return $false;
				}

				// create trail is we're trying to perform an action on a non_existant item
				else {

					$data[$key] = array();

					/*$this->log(
								esc_html__('Previously undefined item added: '.$key, 'microthemer'),
								'<pre>parent: '  . print_r($data, true) . '</pre>'
								//'notice'
							);*/
				}

			}

			$item = &$data[$key];
			$next_index = $x+1;

			//$this->show_me.= '<pre>loop key: '.$key. ' $x: '.$x. ' $trail_length: '.$trail_length. ' $item: '.$item.'</pre>';

			if ($next_index < $trail_length){
				return $this->get_or_update_item($item, $config, $next_index);
			}
		}

		// optionally update item
		switch($config['action']){
			case 'get':
				return $item;
			case 'replace':

				/*$this->log(
							esc_html__('The replace item: ', 'microthemer'),
							'<pre>parent: '  . print_r($item, true) . '</pre>'
						//'notice'
						);*/

				$item = $config['data'];
				break;
			case 'delete':
				unset($item[$config['key']]);
				break;
			case 'rename':
				$this->order_item_properties($item, $config['order'], $config['key'], $config['new_key']);
				break;
			case 'reorder':
				$this->order_item_properties($item, $config['order']);
				break;
			case 'append':
				$item[$config['key']] = $config['data'];
				break;
			case 'array_merge':
				$item = array_merge($item, $config['data']);
				break;
			case 'array_merge_recursive_distinct':

				// tip for myself, this causes 500 error otherwise
				if (!is_array($item) || !is_array($config['data'])){
					$this->log(
						esc_html__('Merge data or item is not an array: ', 'microthemer'),
						'<pre>Update package: '  . print_r(array(
							'item' => $item,
							'data' => $data,
							'config' => $config
						), true) . '</pre>'
					);
					return $false;
				}

				$item = $this->array_merge_recursive_distinct($item, $config['data']);
				break;
			/* unlikely to ned this
					 * array_merge_recursive is a bit weird http://php.net/manual/en/function.array-merge-recursive.php
                     * see explaination of diff with array_merge_recursive_distinct on above PHP page
					 * case 'array_merge_recursive':
						$item = array_merge_recursive($item, $config['data']);
						break;*/
		}

		// return the updated item
		return $item;
	}

	function order_item_properties(&$item, $order, $old_key = false, $new_key = false){
		$new_item = array();
		foreach ($order as $i => $key){

			// don't add undefined keys
			if (isset($item[$key])){
				$new_item[(($key == $old_key) ? $new_key : $key)] = $item[$key];
			} else {
				/* for debugging
						 * $this->log(
							esc_html__('Order key was undefined: '.$key, 'microthemer'),
							'<pre>parent: '  . print_r($item, true) . '</pre>'
						);*/
			}
		}
		$item = $new_item;
	}

	// circumvent max_input_vars by passing one serialised input that can be unpacked with this function
	function my_parse_str($string, &$result) {
		if($string==='') return false;
		$result = array();
		// find the pairs "name=value"
		$pairs = explode('&', $string);
		foreach ($pairs as $pair) {
			// use the original parse_str() on each element
			parse_str($pair, $params);
			$k=key($params);
			if(!isset($result[$k])) {
				$result+=$params;
			}
			else {
				if (is_array($result[$k])){
					//echo '<pre>key:'. $k . "\n";
					//echo 'params:';
					//print_r($params);
					//$result[$k]+=$params[$k];
					$result[$k] = $this->array_merge_recursive_distinct($result[$k], $params[$k]);
					// 'result:';
					//print_r($result);
					//echo '</pre>';
				}
			} //
			//else $result[$k]+=$params[$k];
		}
		return true;
	}

	// better recursive array merge function listed on the function's PHP page
	function array_merge_recursive_distinct ( array &$array1, array &$array2 ){
		$merged = $array1;
		foreach ( $array2 as $key => &$value )
		{
			if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
			{
				$merged [$key] = $this->array_merge_recursive_distinct ( $merged [$key], $value );
			}
			else
			{
				$merged [$key] = $value;
			}
		}

		return $merged;
	}

	// run data structure updates
	function maybe_do_data_conversions_for_update(){

		// a few minor data format changes were made for the speed version. This runs once.
		if (empty($this->preferences['speed_conversion_done'])){

			// create backup
			//$this->pre_upgrade_backup(); - this happens on every update

			$non_section = &$this->options['non_section'];
			$keys = array(
				'adv_wizard_focus', 'css_focus', 'device_focus', // just pref now
				'last_save_time' // move to meta
			);

			// remove keys that were hack for non-queued settings save
			foreach ($keys as $key){
				$item = &$this->get_or_update_item($non_section, array('trail' => array($key), 'action' => 'get'));
				//$this->show_me.= '<pre>key '.$key. ' $item: '.$item.'</pre>';
				if ($item){

					// move certain key values to meta
					if ($key === 'last_save_time'){
						$this->get_or_update_item($non_section, array(
							'action' => 'append',
							'trail' => array('meta'),
							'key' => $key,
							'data' => $item
						));
					}

					unset($non_section[$key]);
				}
			}

			// we don't need to track view state outside of regular sel
			if (!empty($non_section['view_state'])){
				unset($non_section['view_state']);
			}

			// we only use active_queries for import/revision restore now
			if (!empty($non_section['active_queries'])){
				unset($non_section['active_queries']);
			}

			// remove recent sug for background_image and list_style_image which will be basename - invalid
			$image_props = array('list_style_image', 'background_image', 'url');
			$types = array('recent', 'copiedSrc');
			foreach ($image_props as $image_prop){
				foreach ($types as $type){
					$this->get_or_update_item(
						$this->preferences['my_props']['sug_values'],
						array(
							'trail' => array($image_prop, $type),
							'action' => 'replace',
							'data' => array()
						)
					);
				}

			}

			// update preferences
			$this->savePreferences(
				array(
					'speed_conversion_done' => true,
					'my_props' =>  $this->preferences['my_props']
				)
			);

			// update DB
			update_option($this->optionsName, $this->options);

			//$this->show_me.= '<pre>modified non_section what '.$this->options['css_focus'].'</pre>';

		}

		// transition to more solid system for connecting MT tabs with page builder device views
		if (empty($this->preferences['builder_site_preview_width_conversion_done'])

		    // run this again if Gutenberg site device preview sync has not been setup
		    || $this->preferences['builder_site_preview_width_conversion_done'] !== 2
		)
		{

			$m_queries = $this->preferences['m_queries'];

			$map = array(
				//"bbxl" => "builder.FLBuilder.xl",
				"bb0" => "builder.FLBuilder.large",
				"bb1" => "builder.FLBuilder.medium",
				"bb2" => "builder.FLBuilder.small",
				"elem2" => "builder.elementor.tablet",
				"elem3" => "builder.elementor.mobile",
				"oxy_page_width" => "builder.oxygen.page-width",
				"oxy_tablet" => "builder.oxygen.tablet",
				"oxy_phone_landscape" => "builder.oxygen.phone-landscape",
				"oxy_phone_portrait" => "builder.oxygen.phone-portrait",
				"gutenberg2" => "builder.gutenberg.tablet",
				"gutenberg3" => "builder.gutenberg.mobile",
			);

			// remove keys that were hack for non-queued settings save
			foreach ($m_queries as $mq_key => $m_query){

				foreach ($map as $key_suffix => $site_preview_width){

					$keySuffixMatch = preg_match('/'.$key_suffix.'$/', $mq_key);
					$defaultMobOrTablet = !isset($m_queries[$mq_key]['site_preview_width']) &&
					                      isset($m_query['max']) &&
					                      ($key_suffix === 'gutenberg2' && $m_query['max'] == '767') ||
					                      ($key_suffix === 'gutenberg3' && $m_query['max'] == '479');

					if ( $keySuffixMatch || $defaultMobOrTablet ){
						$m_queries[$mq_key]['site_preview_width'] = $site_preview_width;
					}
				}
			}

			/*wp_die('Old: <pre>$media_queries_list: '.print_r($this->preferences['m_queries'], true). '</pre>'
					. 'New: <pre>$media_queries_list: '.print_r($m_queries, true). '</pre>');*/

			// update preferences
			$this->savePreferences(
				array(
					'builder_site_preview_width_conversion_done' => true,
					'm_queries' => $m_queries
				)
			);

		}

		// there were some errors with recently viewed pages being badly formatted
		// including an Oxygen issue that could cause data loss, so reset custom_paths if not done already
		// custom paths also needed to be reset for the logic feature, so that logic values are included too
		if (empty($this->preferences['custom_paths_reset']) || $this->preferences['custom_paths_reset'] < 3){
			$this->savePreferences(
				array(
					'custom_paths_reset' => 3,
					'custom_paths' =>  array('/')
				)
			);
		}

		// we previously had dock_styles_left which did both styles and editor
		if (empty($this->preferences['dock_left_conversion_done'])){
			$this->savePreferences(
				array(
					'dock_editor_left' => 1,
					'dock_styles_left' => 1,
					'dock_left_conversion_done' =>  1
				)
			);
		}


		// min_widths for resizable panels are stored alongside user custom sizes
		// but this may need to be refined at various intervals
		$layout_adjust_version = 8;// increase this number when
		if (
			empty($this->preferences['layout_adjust_version'])
			|| $this->preferences['layout_adjust_version'] !== $layout_adjust_version
		){

			// set new value - note array_merge had strange affect, so be careful with that
			// simpler to just set new values
			$this->preferences['layout']['inspection_columns']['min_column_sizes'] = array(300, 310);

			// update minimum size for left columns
			$min_left = array(282, 282, 282);
			$this->preferences['layout']['left']['min_column_sizes'] = $min_left;
			foreach ($min_left as $index => $min_size){
				if ($this->preferences['layout']['left']['column_sizes'][$index] < $min_size){
					$this->preferences['layout']['left']['column_sizes'][$index] = $min_size;
				}
			}

			// introduce new ai panel
			unset($this->preferences['layout']['right']['items']['wizard']);
			$this->preferences['layout']['right']['items']['ai'] = array(
				'size' => 252,
				'size_category' => 'sm'
			);

			// update minimum size for right columns
			$min_right = array(252, 252);
			$this->preferences['layout']['right']['min_column_sizes'] = $min_right;
			foreach ($min_right as $index => $min_size){
				if ($this->preferences['layout']['right']['column_sizes'][$index] < $min_size){
					$this->preferences['layout']['right']['column_sizes'][$index] = $min_size;
				}
			}

			//wp_die('We convert: ' . $layout_adjust_version);

			$this->savePreferences(
				array(
					'layout' => $this->preferences['layout'],
					'layout_adjust_version' =>  $layout_adjust_version
				)
			);
		}

		// ensure draft mode is always on, and wizard dock right setting is off
		if (empty($this->preferences['always_draft_conversion_done'])){
			$this->savePreferences(
				array(
					'draft_mode' => 1,
					'dock_wizard_right' => 0, // this is never docked right in 7.0 release (may be supported again)
					'always_draft_conversion_done' =>  1
				)
			);
		}

	}


	// update active-styles.css
	function update_assets($activated_from, $context = '', $assetTypes = array('draft' => 1)) {

		// as an interim, MT will continue to load dependencies in the global stylesheet
		// e.g. key frames, GFonts, event JS - this will take some time but can be supported
		$globalStylesheetRequired = 0;
		$contentTrial = $this->supportContent() && !$this->hasContentSubscription();
		$doPublish = !empty($assetTypes['active']) || !empty($this->preferences['auto_publish_mode']);

		// Ensure that we publish at the same time if auto-publish is set
		if ($doPublish){
			$assetTypes['active'] = 1;
		}

		// cache previous asset loading for change analysis
		$prev_asset_loading = $this->preferences['asset_loading'];
		if (!isset($prev_asset_loading['html_mods'])){
			$prev_asset_loading['html_mods'] = array();
		}

		// check for micro-themes folder and create if it doesn't exist
		$this->setup_micro_themes_dir();

		// setup vars
		$asset = array(
			'global' => array(
				'data' => '',
				'data_active' => '',
				'scss_data' => '',
				'scss_data_active' => '',
				//'js_data' => '',
				//'js_data_active' => '',
				'html' => array(),
				'html_mods' => array(),
			),
			'conditional' => array(),

			// when the settings are saved, conditional folders are checked and only those with
			// styles are given stylesheets. This make this Server side script a good candidate as the
			// central source of truth building the asset_loading logic value (rather than with JS)
			'preference' => array(

				// log when an asset (global or conditional) has been added or removed
				// so that browser tab syncing can add/remove these assets on other tabs
				'global_css' => 0,
				'global_g_fonts' => 0,
				'conditional' => array(),

				// ordered folder logic
				'logic' => array(),

				// HTML modifications
				'html_mods' => array(), // all mod junctures (global or conditional)
			),
			'assetTypes' => $assetTypes,
			'separatePublishData' => $contentTrial && $doPublish,
			'contentTrial' => $contentTrial,
			'doPublish' => $doPublish,
		);

		// Create store of CSS / JS snippets for insertion
		if ($this->supportContent()){
			$this->contentMethod('storeCSSSnippets', array(&$asset));
		}

		$title = '/*  MICROTHEMER STYLES  */' . "\n\n";

		// check if hand coded have been set - output before other css
		$scss_custom_code = '';
		$custom_code = '';
		if ( !empty($this->options['non_section']['hand_coded_css']) &&
		     !empty(trim($this->options['non_section']['hand_coded_css'])) ){

			$globalStylesheetRequired = 1;

			// format comment
			$name = esc_attr_x('Full Code Editor CSS', 'CSS comment', 'microthemer');
			$eq_str = $this->eq_str($name);
			$custom_code_comment = "/*= $name $eq_str */\n\n";

			// if the scss compiles in the browser
			if ($this->client_scss()){

				// log raw SCSS for writing to active-styles.scss
				$scss_custom_code.= $custom_code_comment . $this->options['non_section']['hand_coded_css'] ."\n";

				// include already compiled CSS
				if (!empty($this->options['non_section']['hand_coded_css_compiled'])){
					$custom_code.= $custom_code_comment . $this->options['non_section']['hand_coded_css_compiled'] ."\n";
				}
			}

			// No scss support
			else {
				$custom_code.= $custom_code_comment . $this->options['non_section']['hand_coded_css'] ."\n";
			}

		}

		// convert ui data to regular css output
		$this->convert_ui_data($this->options, $asset, 'regular');

		// convert ui data to media query css output
		if (!empty($this->options['non_section']['m_query']) and is_array($this->options['non_section']['m_query'])) {
			foreach ($this->preferences['m_queries'] as $key => $m_query) {
				// process media query if it has been in use at all
				if (!empty($this->options['non_section']['m_query'][$key]) and
				    is_array($this->options['non_section']['m_query'][$key])){
					$this->convert_ui_data($this->options['non_section']['m_query'][$key], $asset, 'mq', $key);
				}
			}
		}

		//$this->log('The total config', json_encode($asset['preference']['conditional']));

		// flag that some CSS will be output to the global stylesheet
		if ($asset['global']['data']){
			$globalStylesheetRequired = 1;
		}

		//wp_die('Styles: <pre>' . print_r($asset, 1) . '</pre>');

		// any animations have been found after iterating GUI options, include if necessary
		$anim_keyframes = '';

		if ( !empty($this->options['non_section']['meta']['animations']['names']) and
		     count($this->options['non_section']['meta']['animations']['names']) ){

			$globalStylesheetRequired = 1;

			// flag section with CSS comment
			$name = esc_attr_x('Animations', 'CSS comment', 'microthemer');
			$eq_str = $this->eq_str($name);
			$anim_keyframes.= "/*= $name $eq_str */\n\n";

			// get array of animation code
			$animations = array();
			include $this->thisplugindir . 'includes/animation/animation-code.inc.php';

			foreach ($this->options['non_section']['meta']['animations']['names'] as $animation_name => $one){

				// if we recognise the animation name, include the keyframe code
				if (!empty($animations[$animation_name])){
					$anim_keyframes.= $animations[$animation_name]['code'];
				}

			}
		}

		// join title, animations, custom code and GUI output in correct order
		$asset['global']['data'] = $title . $anim_keyframes . $custom_code . $asset['global']['data'];
		$asset['global']['scss_data'] = $title . $anim_keyframes . $scss_custom_code . $asset['global']['scss_data'];

		/** UPDATE PREFERENCES */

		// flag if global JS file should load
		$js_data = !empty($this->options['non_section']['js'])
			? trim($this->options['non_section']['js'])
			: '';
		$load_js = !empty($js_data);

		// save the google font values
		$g_fonts = $this->get_item(
			$this->options,
			array('non_section', 'meta', 'g_fonts')
		);

		// copy loading of global CSS/JS to asset_loading preference for ease of lookup
		$asset['preference']['global_g_fonts'] = !empty($g_fonts['g_fonts_used']) ? 1: 0;
		$asset['preference']['global_css'] = $globalStylesheetRequired ? 1: 0;
		$asset['preference']['global_js'] = $load_js ? 1: 0;

		$asset['global']['js_data'] = $js_data;
		$this->asset_loading_change = $this->checkAssetLoadingChange($asset['preference'], $prev_asset_loading);

		// not sure if I need this
		$this->html_mods_changed = $asset['preference']['html_mods'] != $prev_asset_loading['html_mods'];

		// core preference values
		$pref_array = array(
			'global_stylesheet_required' => $globalStylesheetRequired,
			'asset_loading' => $asset['preference'],
			'load_js' => $load_js,
			'active_events' => !empty($this->options['non_section']['active_events'])
				? $this->options['non_section']['active_events']
				: array(),
			'num_saves' => (++$this->preferences['num_saves'])
		);

		// google fonts
		$gf_keys = array('g_fonts_used', 'g_url', 'g_url_with_subsets', 'found_gf_subsets');
		foreach($gf_keys as $index => $key){
			$pref_array[$key] = !empty($g_fonts[$key]) ? $g_fonts[$key] : '';
		}

		// track number of unpublished saves
		if (!$doPublish){
			$pref_array['num_unpublished_saves'] = ++$this->preferences['num_unpublished_saves'];
		}

		// update the recent logic array
		if (!empty($this->serialised_post['adjusted_logic']['update_recent_logic'])){

			// update recent logic data
			if (!empty($this->serialised_post['adjusted_logic']['expr'])){
				$pref_array['recent_logic'] = $this->updateRecentLogic(
					$this->serialised_post['adjusted_logic'],
					$this->preferences['recent_logic']
				);
			}
		}
		
		// set theme in focus (legacy)
		if ($activated_from != 'customised' and $context != __('Merge', 'microthemer')) {
			$pref_array['theme_in_focus'] = $activated_from;
			$pref_array['active_theme'] = $activated_from;
		}

		if ($context == __('Merge', 'microthemer') or $activated_from == 'customised') {
			$pref_array['active_theme'] = 'customised'; // a merge means a new custom configuration
		}

		if ($this->savePreferences($pref_array) and $activated_from != 'customised') {
			$this->log(
				esc_html__('Design pack activated', 'microthemer'),
				'<p>' . esc_html__('The design pack was successfully activated.', 'microthemer') . '</p>',
				'dev-notice'
			);
		}

		$this->updateAssetFiles($asset, $assetTypes, $globalStylesheetRequired);

	}

	function updateRecentLogic($adjusted_logic, $prev_recent_logic){

		$existingLabelUpdated = false;
		$adjusted_expr = trim($adjusted_logic['expr']);
		$adjusted_label = !empty($adjusted_logic['label'])
			? trim($adjusted_logic['label'])
			: $adjusted_expr;
		$recent_logic = array();
		$max = 8; // let there be lots

		//$this->log('recent logic issue', 'updateRecentLogic: <pre>'.print_r($adjusted_logic, 1).'</pre>');

		foreach ($prev_recent_logic as $i => $array){

			//echo 'compare: ' . trim($array['logic']) . ' with: ' . $adjusted_expr . "\n";

			// update existing logic label if expressions match
			if (trim($array['logic']) === $adjusted_expr && !empty($adjusted_logic['label'])){
				$array['label'] = $adjusted_label;
				$array['value'] = $adjusted_label;
				$existingLabelUpdated = true;
			}

			// ensure we don't exceed max and that labels are unique
			if ($i < $max && ($existingLabelUpdated || trim($array['label']) !== $adjusted_label)){
				$recent_logic[] = $array;
			}
		}

		// prepend the new logic if an existing item wasn't updated
		if (!$existingLabelUpdated){
			array_unshift($recent_logic, array(
				'logic' => $adjusted_expr,
				'label' => $adjusted_label,
				'value' => $adjusted_label
			));
		}
		
		return $recent_logic;
	}

	function checkAssetLoadingChange($asset_loading, $prev_asset_loading){

		$change = array();

		// log any change in the Google font loading
		if ($asset_loading['global_g_fonts'] !== $prev_asset_loading['global_g_fonts']){
			$change[] = array(
				'key' => 'global_g_fonts',
				'action' => ($asset_loading['global_g_fonts'] ? 'added' : 'removed'),
			);
		}

		// log any change in the global CSS loading
		if ($asset_loading['global_css'] !== $prev_asset_loading['global_css']){
			$change[] = array(
				'key' => 'global_css',
				'action' => ($asset_loading['global_css'] ? 'added' : 'removed'),
			);
		}

		// log newly added conditional folders
		foreach ($asset_loading['conditional'] as $key => $on){

			if (empty($prev_asset_loading['conditional'][$key])){
				$change[] = array(
					'key' => $key,
					'action' => 'added',
				);
			}

			// they are both on, remove from previous asset loading array so any leftover have been removed
			else {
				unset($prev_asset_loading['conditional'][$key]);
			}
		}

		// log removed conditional folders
		if (count($prev_asset_loading['conditional'])) {
			foreach ( $prev_asset_loading['conditional'] as $key => $on) {
				$change[] = array(
					'key'    => $key,
					'action' => 'removed'
				);
			}
		}

		return count($change) ? $change : false;
	}

	function getAssetDataKey($defaultKey, $assetType, $asset){
		$activeDataKey = $defaultKey . '_active';
		return $assetType === 'active' && $asset['separatePublishData']
			? $activeDataKey
			: $defaultKey;
	}

	function updateAssetFiles($asset, $assetTypes, $globalStylesheetRequired){

		// HTML modification

		$staleMods = array();
		$insertOrUpdate = array();

		// We only clean up mods if draft is being checked - otherwise we delete erroneously
		if (!empty($assetTypes['draft'])){
			$existingMods = $this->contentMethod('getExistingMods', array(0));
			if ($existingMods){
				foreach ($existingMods as $row){
					$staleMods[$row['slug']] = 1;
				}
			}
		}

		// prepare any Global HTML for DB delete
		if (count($asset['global']['html'])){
			$slug = 'mt_collective_global';
			$insertOrUpdate[$slug] = json_encode($asset['global']['html']);
			unset($staleMods[$slug]);
		}

		// CSS paths
		$root = $this->micro_root_dir;
		$minifyCSS = !empty($this->preferences['minify_css']);
		$minifyJS = !empty($this->preferences['minify_js']);

		// The Gutenberg FSE editor will not load CSS in the iframe if the file does not contain
		// .wp-block. So ensure all MT CSS has this.
		$fseFix = $this->supportAdminAssets()
			?  '.wp-block {} /*  (.wp-block {} ensures MT files load in Gutenberg editor) */' . "\n\n"
			: '';
		$globalAssets = array(
			'scss' => array(
				'file' => 'styles.scss',
				'key' => 'scss_data',
				'write' => !empty($asset['global']['scss_data']),
				'minify' => false
			),
			'css' => array(
				'file' => 'styles.css',
				'key' => 'data',
				'write' => $globalStylesheetRequired,
				'minify' => $minifyCSS
			),
			'js' => array(
				'file' => 'scripts.js',
				'key' => 'js_data',
				'write' => !empty($asset['global']['js_data']),
				'minify' => $minifyJS
			)
		);

		// write any global Sass, CSS, or JS for draft/active
		foreach ($assetTypes as $assetType => $array){
			foreach ($globalAssets as $dataType => $config){
				$file = $root . $assetType . '-' . $config['file'];
				$prefix = $dataType === 'css' ? $fseFix : '';
				$minify = $assetType === 'active' && $config['minify'];
				$dataKey = $this->getAssetDataKey($config['key'], $assetType, $asset);

				// Write to or remove file
				if ( $config['write']){
					$this->write_file(
						$file, $prefix . $asset['global'][$dataKey], $minify, $dataType
					);
				} elseif (file_exists($file)) {
					unlink($file);
				}
			}
		}

		// write any conditional styles
		if (count($asset['conditional'])){

			foreach ($assetTypes as $assetType => $array){

				$conditionalDir = $root . 'mt/conditional/'.$assetType.'/';

				// list of existing files to compare with current files, and possibly cleaned
				$previousFiles = $this->getDirectoryFileList($conditionalDir);
				$currentFiles = array();

				foreach ($asset['conditional'] as $folderSlug => $condSty){

					// prepare any HTML mods for DB delete
					if ($assetType === 'draft'){
						if (!empty($condSty['html']) && is_array($condSty['html']) && count($condSty['html'])){
							$insertOrUpdate[$folderSlug] = json_encode($condSty['html']);
							unset($staleMods[$folderSlug]);
						}
					}

					// CSS
					$dataKey = $this->getAssetDataKey('data', $assetType, $asset);
					if (!empty($condSty[$dataKey])){
						$name = $folderSlug . '.css';
						$file = $conditionalDir . $name;
						$currentFiles[] = $name;
						$minify = $minifyCSS && $assetType === 'active';
						/*$this->log($folderSlug . ': '.$assetType, '<pre>'.print_r([
								$asset['separatePublishData'], $folderSlug, $assetType, $dataKey, $condSty, $asset
							], 1).'</pre>');*/

						$this->write_file($file, $fseFix . $condSty[$dataKey], $minify, 'css');
					}

				}

				// clean up any files that are no longer conditional / renamed
				$redundantFiles = array_diff($previousFiles, $currentFiles);
				foreach ($redundantFiles as $fileName){
					unlink($conditionalDir . $fileName);
				}

			}

		}

		// maybe update the mods in the database
		if (!empty($assetTypes['draft'])){
			$this->contentMethod('maybeUpdateHTMLTable', array('folder_mod', $insertOrUpdate, $staleMods));
		}


		//$this->maybeUpdateHTMLTable($insertOrUpdate, $staleMods, 0);

		// publish settings if auto-publish is on
		/*if (!empty($this->preferences['auto_publish_mode'])){
			$this->publishSettings();
		}*/

		/* debug
		 * $this->log('An update', json_encode([
			'$globalStylesheetRequired' => $globalStylesheetRequired,
			'file exists' => file_exists($global_css),
			'file' => $global_css
		]));*/

	}



	// transform MT form settings into stylesheet data
	function convert_ui_data(&$ui_data, &$asset, $con, $mq_key = '1') {

		$tab = $sec_breaks = $mq_line = "";
		$sassToo = $this->client_scss();

		if ($con == 'mq') {

			// don't output media query if no values inside
			if (!$this->ui_data_has_values($ui_data, false)){
				return false;
			}

			// reset tracker that opening MQ has been added
			$asset['global']['mq_opened'] = 0;

			$mq_label = $this->preferences['m_queries'][$mq_key]['label'];
			$mq_query = $this->preferences['m_queries'][$mq_key]['query'];
			$tab = "\t";
			$sec_breaks = "";
			$mq_line = "\n/*( $mq_label )*/\n$mq_query {\n";
		}

		// loop through the sections
		foreach ($ui_data as $section_name => $array) {

			// check if the folder is empty but don't skip straight away, we need to assess folder logic
			$emptyFolder = !$this->section_has_values($section_name, $array, false);

			//$this->log('Empty folder ('.$section_name.'): '.$emptyFolder, 'More info');

			//echo 'empty folder ('.$section_name.'): '.$emptyFolder . "\n";

			// skip non_section stuff or empty sections
			if ($section_name == 'non_section'){ // || !$this->section_has_values($section_name, $array, false)
				continue;
			}

			// Get folder name
			$display_section_name = $this->get_folder_name_inc_legacy($section_name, $array);

			// we either update the global styles data or conditional styles
			$sectionLoading = !empty($this->options[$section_name]['this']['logic']['expr'])
				? 'conditional'
				: 'global';

			// update conditional folder stylesheet
			if ($sectionLoading === 'conditional'){

				// ensure data store is set
				if (!isset($asset['conditional'][$section_name])){

					// update the asset_loading preference data
					$asset['preference']['conditional'][$section_name] = $emptyFolder ? 'empty' : 1;

					// Prepare asset data keys if the folder is not empty
					if (!$emptyFolder){

						$asset['preference']['logic'][] = array_merge(
							$this->options[$section_name]['this']['logic'],
							array(
								'slug' => $section_name,
							)
						);

						$asset['conditional'][$section_name] = array(
							'data' => '',
							'data_active' => '',
							'scss_data' => '',
							'scss_data_active' => '',
							//'js_data' => '',
							//'js_data_active' => '',
							'mq_opened' => 0,
							'html' => array(),
							"html_mods" => array(),
							'snippets' => array(),
							//'auto_script_deps' => array(),
						);
					}
				}

				$dataToUpdate = &$asset['conditional'][$section_name];

				// flag that the folder is conditional
				//$display_section_name.= ' (conditional folder)'; // make this a clickable link in the ACE editor
			}

			// update the global stylesheet
			else {
				$dataToUpdate = &$asset['global'];
			}

			// Now that a potentially conditional folder has been flagged, we can continue if it's empty
			if ($emptyFolder){
				continue;
			}

			// start the opening media query bracket if not created yet
			if ($con == 'mq' && empty($dataToUpdate['mq_opened'])) {

				$this->updateStyleData($sassToo, $asset, $dataToUpdate, $mq_line);

				$dataToUpdate['mq_opened'] = 1;
			}

			// flag if the folder is disabled
			$sectionIsDisabled = !empty($this->options[$section_name]['this']['disabled']);
			if ($sectionIsDisabled){
				$display_section_name.= ' ('.$this->dis_text.')';
			}

			// make sections same width by adding extra = and accounting for char length
			$eq_str = $this->eq_str($display_section_name);
			$section_comment = $sec_breaks."\n$tab/*= $display_section_name $eq_str */\n\n";

			// Add the section folder name comment regardless of whether styles are omitted
			// not for media queries as that's too many unused folder comments
			if ($con !== 'mq') {
				$this->updateStyleData($sassToo, $asset, $dataToUpdate, $section_comment);
			}

			// if section disabled, continue
			if ($sectionIsDisabled) {
				continue;
			}

			// loop the CSS selectors - section_has_values() already tells us array is good
			foreach ( $array as $css_selector => $sub_array ) {

				// skip this or empty selectors
				if ($css_selector == 'this' or
				    !$this->selector_has_values($section_name, $css_selector, $sub_array, false)) {
					continue;
				}

				// sort out the css selector - need to get css label/code from regular ui array
				if ($con == 'mq') {
					$sub_array['label'] = $this->options[$section_name][$css_selector]['label'];
				}
				$label_array = explode('|', $sub_array['label']);
				$css_label = $label_array[0];
				$selectorCode = !empty($label_array[1]) ? $label_array[1] : null;
				$xpath = !empty($this->options[$section_name][$css_selector]['xpath'])
					? $this->options[$section_name][$css_selector]['xpath']
					: '';

				$sel_disabled = false;
				if (!empty($sub_array['tab']['disabled']) ||
				    !empty($this->options[$section_name][$css_selector]['disabled'])) {
					$sel_disabled = true;
					$css_label.= ' ('.$this->dis_text.')';
				}

				// output sel comment
				$selector_comment = "$tab/** $display_section_name >> $css_label **/\n$tab";
				$this->updateStyleData($sassToo, $asset, $dataToUpdate, $selector_comment);

				// move on if sel disabled
				if ($sel_disabled) {
					continue;
				}

				// Format HTML modification instruction
				$html = null;
				if (!empty($sub_array['styles']['html'])){

					// get the juncture
					/*$juncture = isset($sub_array['styles']['html']['juncture']['value'])
						? $sub_array['styles']['html']['juncture']['value']
						: 'serverHTMLReady';*/

					// register that some Mods have been applied on the site (if none, we skip HTML parsing)
					$asset['preference']['html_mods']['all'] = 1; // all mod junctures

					// Flag that the Folder is applying mods
					if ($sectionLoading === 'conditional'){
						$asset['preference']['html_mods']['conditional'][$section_name]['folder'] = 1;
					} else {
						$asset['preference']['html_mods']['global']['folder'] = 1;
					}

					// Flag that the folder references snippets
					$snippetCollection = array(
						'css' => '',
					);
					if (isset($sub_array['styles']['html']['snippet_id']['value'])){

						//$this->log('cssSnippets', '<pre>'.print_r([$sub_array['styles']['html']['snippet_id']['value'], $asset['snippets']['css']], 1).'</pre>');

						foreach($sub_array['styles']['html']['snippet_id']['value'] as $snippet_id){
							if ($sectionLoading === 'conditional'){
								$asset['preference']['html_mods']['conditional'][$section_name]['snippets'][$snippet_id] = 1;
							} else {
								$asset['preference']['html_mods']['global']['snippets'][$snippet_id] = 1;
							}

							// Generate Snippet CSS string
							foreach ($snippetCollection as $type => $value){
								if (!empty($asset['snippets'][$type][$snippet_id])){
									$snippetCollection[$type].= "$tab/*^^ $type snippet ($snippet_id) ^^*/\n$tab"
									               . $asset['snippets'][$type][$snippet_id] . "\n\n";
								}
							}

						}
					}

					// prepare data for updateStyleData()
					$html = array(
						'mq_key' => $mq_key === '1' ? 'all-devices' : $mq_key,
						'mq_query' => !empty($mq_query) ? $mq_query : '',
						'sectionSlug' => $section_name,
						'selectorSlug' => $css_selector,
						'selectorCode' => $this->stripStateFromSelector($selectorCode, false),
						'xpath' => $xpath,
						'mod' => $this->convertModArrayFormat($sub_array['styles']['html'], $sub_array),
						'snippetCollection' => $snippetCollection
					);

				}


				$string = !empty($sub_array['compiled_css'])
					? $this->normalise_tabs(
						$this->normalise_line_breaks($sub_array['compiled_css']), $tab
					)
					: false;
				$string2 = $sassToo && !empty($sub_array['raw_scss'])
					? $this->normalise_tabs(
						$this->normalise_line_breaks($sub_array['raw_scss']), $tab, true
					)
					: false;

				// add selector data
				$this->updateStyleData(
					$sassToo,
					$asset,
					$dataToUpdate,
					$string,
					$string2,
					$html
				);

			}
		}

		// Close media query bracket
		if ($con == 'mq') {

			$close_mq = "}\n\n";

			// add for global stylesheet if opened
			if (!empty($asset['global']['mq_opened'])){
				$this->updateStyleData($sassToo, $asset, $asset['global'], $close_mq);
			}

			// add for any conditional stylesheets
			foreach ($asset['conditional'] as &$dataToUpdate){

				if (!empty($dataToUpdate['mq_opened'])){

					$this->updateStyleData($sassToo, $asset, $dataToUpdate, $close_mq);

					// reset for next MQ that calls this method
					$dataToUpdate['mq_opened'] = 0;
				}
			}

		}



		//return $asset;
	}

	function stripStateFromSelector($selector, $forXPath = true)
	{
		// Always remove pseudo-elements
		$selector = preg_replace(
			'/::[a-zA-Z0-9_-]+(\([^)]*\))?/i',
			'',
			$selector
		);

		// Base pseudos supported by BOTH DOM and XPath
		$baseWhitelist = [
			'empty',
			'first-child',
			'last-child',
			'only-child',
			'first-of-type',
			'last-of-type',
			'only-of-type',
			'nth-child',
			'nth-last-child',
			'nth-of-type',
			'nth-last-of-type',
			'not',
			'root'
		];

		// Pseudos supported ONLY in the DOM
		$domOnly = [
			'has',
			'scope'
		];

		$allowed = $forXPath
			? $baseWhitelist
			: array_merge($baseWhitelist, $domOnly);

		$allowedPattern = implode('|', $allowed);

		// Strip everything not explicitly allowed
		$selector = preg_replace(
			'/:(?!' . $allowedPattern . ')([a-zA-Z0-9_-]+)(\([^)]*\))?/i',
			'',
			$selector
		);

		return trim($selector);
	}


	function convertModArrayFormat($modData, $sub_array){
		$mods = array();
		foreach ($modData as $prop => $data){
			foreach ($data as $key => $values){
				foreach ($values as $index => $value){

					// Check if tab is disabled
					$isDisabled = !empty($sub_array['pg_disabled']['changehtml'][$index]);

					// don't use $key so we remove value final nested level
					if (!$isDisabled && !is_null($value)){
						$mods[$index][$prop] = $prop === 'selector'
							? $this->stripStateFromSelector($value)
							: $value;
					}

				}
			}
		}
		return $mods;
	}

	// update the css and possible sass data strings
	function updateStyleData($sassToo, &$asset, &$dataToUpdate, $string, $string2 = false, $html = null){
		
		if ($string !== false){

			$dataToUpdate['data'].= $string;

			if ($asset['separatePublishData']){
				$dataToUpdate['data_active'].= $string;
			}
		}

		// for now, we still add all SCSS to a single global file.
		// This kind of makes sense because the Sass is processed in a single global scope,
		// Even if the output CSS is distributed between separate files
		if ($sassToo){
			$resolvedString = ($string2 ?: $string);
			if ($resolvedString !== false){
				$asset['global']['scss_data'].= $resolvedString;
				if ($asset['separatePublishData']){
					$dataToUpdate['scss_data_active'].= $resolvedString;
				}
				/*if (!empty($html['snippetCollection']['css'])) {
					$dataToUpdate['scss_data'].= $html['snippetCollection']['css'];
				}*/
			}
		}

		// Add any HTML data
		if ($html){
			$dataToUpdate['html'][$html['sectionSlug']][$html['mq_key']]['mq_query'] = $html['mq_query'];
			$dataToUpdate['html'][$html['sectionSlug']][$html['mq_key']]['selectors'][$html['selectorSlug']] = array(
				'selectorCode' => $html['selectorCode'],
				'xpath' => $html['xpath'],
				'mods' => $html['mod']
			);

			foreach ($html['snippetCollection'] as $type => $content){
				if ($content){
					if ($type === 'css'){
						$dataToUpdate['data'].= $content;
					}
				}
			}



			/*else {
				$this->log('cssSnippets NOT added', $html['cssSnippets']);
			}*/
		}


	}


}