<?php

namespace Microthemer;

trait PackTrait {

	/**
	 * Handles the 'Manage Micro Themes' admin page.
	 */
	function manage_micro_themes_page() {

		// only run code if it's the manage themes page
		if ( $_GET['page'] == $this->microthemespage ) {

			if (!current_user_can('manage_options')){
				wp_die('Access denied');
			}

			// handle zip upload
			if (isset($_POST['tvr_upload_micro_submit'])) {
				check_admin_referer('tvr_upload_micro_submit');
				$this->process_uploaded_zip();
			}


			// notify that design pack was successfully deleted (operation done via ajax on single pack page)
			if (!empty($_GET['mt_action']) and $_GET['mt_action'] == 'tvr_delete_ok') {
				check_admin_referer('tvr_delete_ok');
				$this->log(
					esc_html__('Design pack deleted', 'microthemer'),
					'<p>' . esc_html__('The design pack was successfully deleted.', 'microthemer') . '</p>',
					'notice'
				);
			}

			// handle edit micro selection
			if (isset($_POST['tvr_edit_micro_submit'])) {
				check_admin_referer('tvr_edit_micro_submit');
				$pref_array = array();
				$pref_array['theme_in_focus'] = $_POST['preferences']['theme_in_focus'];
				$this->savePreferences($pref_array);
			}

			// activate theme
			if (
				!empty($_GET['mt_action']) and
				$_GET['mt_action'] == 'tvr_activate_micro_theme') {
				check_admin_referer('tvr_activate_micro_theme');
				$theme_name = $this->preferences['theme_in_focus'];
				$json_file = $this->micro_root_dir . $theme_name . '/config.json';
				$this->load_json_file($json_file, $theme_name);
				// update the revisions DB field
				$user_action = sprintf(
					esc_html__('%s Activated', 'microthemer'),
					'<i>' . $this->readable_name($theme_name) . '</i>'
				);
				if (!$this->updateRevisions($this->options, $user_action)) {
					$this->log('', '', 'error', 'revisions');
				}
			}
			// deactivate theme
			if (
				!empty($_GET['mt_action']) and
				$_GET['mt_action'] == 'tvr_deactivate_micro_theme') {
				check_admin_referer('tvr_deactivate_micro_theme');
				$pref_array = array();
				$pref_array['active_theme'] = '';
				if ($this->savePreferences($pref_array)) {
					$this->log(
						esc_html__('Item deactivated', 'microthemer'),
						'<p>' .
						sprintf(
							esc_html__('%s was deactivated.', 'microthemer'),
							'<i>'.$this->readable_name($this->preferences['theme_in_focus']).'</i>' )
						. '</p>',
						'notice'
					);
				}
			}

			// include manage micro interface (both loader and themer plugins need this)
			include $this->thisplugindir . 'includes/tvr-manage-micro-themes.php';
		}
	}

    // Check a file path matches up against known server-path
    function checkExpectedPath($constructedPath, $serverPath){

	    if (strpos($constructedPath, '..') !== false) {
		    return false;
	    }

	    $realBase = realpath($serverPath);
	    $realTarget = realpath($constructedPath);

	    return $realBase !== false
	           && $realTarget !== false
	           && strpos($realTarget, $realBase) === 0;
    }

	/**
	 * Handles the 'Manage Single Pack' admin page.
	 */
	function manage_single_page() {
		// only run code on preferences page
		if( $_GET['page'] == $this->managesinglepage ) {

			if (!current_user_can('manage_options')){
				wp_die('Access denied');
			}

			// handle zip upload
			if (isset($_POST['tvr_upload_micro_submit'])) {
				check_admin_referer('tvr_upload_micro_submit');
				$this->process_uploaded_zip();
			}

			// update meta.txt
			if (isset($_POST['tvr_edit_meta_submit'])) {
				check_admin_referer('tvr_edit_meta_submit');
				$this->update_meta_file($this->micro_root_dir . $this->preferences['theme_in_focus'] . '/meta.txt');
			}

			// update readme.txt
			if (isset($_POST['tvr_edit_readme_submit'])) {
				check_admin_referer('tvr_edit_readme_submit');
				$this->update_readme_file($this->micro_root_dir . $this->preferences['theme_in_focus'] . '/readme.txt');
			}

			// upload a file
			if (isset($_POST['tvr_upload_file_submit'])) {
				check_admin_referer('tvr_upload_file_submit');
				$this->handle_file_upload();
			}

			// delete a file
			if (
				!empty($_GET['mt_action']) and
				$_GET['mt_action'] == 'tvr_delete_micro_file') {
				check_admin_referer('tvr_delete_micro_file');

				$file_to_delete = basename(stripslashes($_GET['file']));
				$base_dir = $this->micro_root_dir . $this->preferences['theme_in_focus'] . '/';
				$full_path = $base_dir . $file_to_delete;

				if (!$this->checkExpectedPath($full_path, $base_dir)) {
					return;
				}

				$delete_ok = true;
				// remove the file from the media library
				if ($_GET['location'] == 'library'){
					// This logic remains the same, as it uses WordPress's secure media deletion functions
					// which operate on post IDs, not file paths from user input.
					global $wpdb;
					$img_path = $_GET['file'];
					$query = $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'", esc_url($img_path));
					$results = $wpdb->get_results($query);
					foreach ( $results as $row ) {
						if ( false === wp_delete_attachment( $row->ID )) {
							$delete_ok = false;
						}
					}
				}
				// Regular delete of a pack file
				else {
					// It's now safe to perform the unlink operation on the validated path.
					if ( !is_file($full_path) || !unlink($full_path) ) {
						$delete_ok = false;
					} else {
						// remove from file_structure array
						if (!$this->is_screenshot($file_to_delete)){
							$key = $file_to_delete;
						} else {
							$key = 'screenshot';
							// delete the screenshot-small too
							$thumb_path = str_replace('screenshot', 'screenshot-small', $full_path);
							if (is_file($thumb_path)){
								unlink($thumb_path);
								unset($this->file_structure[$this->preferences['theme_in_focus']][basename($thumb_path)]);
							}
						}
						unset($this->file_structure[$this->preferences['theme_in_focus']][$key]);
					}
				}

				if ($delete_ok){
					$this->log(
						esc_html__('File deleted', 'microthemer'),
						'<p>' . sprintf( esc_html__('%s was successfully deleted.', 'microthemer'), '<b>' . esc_html($file_to_delete) . '</b>' ) . '</p>',
						'notice'
					);
					// update paths in json file
					$json_config_file = $this->micro_root_dir . $this->preferences['theme_in_focus'] . '/config.json';
					// We need the relative path for replacement.
					$root_rel_path = $this->root_rel($this->micro_root_url . $this->preferences['theme_in_focus'] . '/' . $file_to_delete, false, true);
					$this->replace_json_paths($json_config_file, array($root_rel_path => ''));
				} else {
					$this->log(
						esc_html__('File delete failed', 'microthemer'),
						'<p>' . sprintf( esc_html__('%s was not deleted.', 'microthemer'), '<b>' . esc_html($file_to_delete) . '</b>' ) . '</p>'
					);
				}
			}

			// include manage file
			include $this->thisplugindir . 'includes/tvr-manage-single.php';
		}
	}

	// process posted zip file
	function process_uploaded_zip() {
		if ($_FILES['upload_micro']['error'] == 0) {
			$this->handle_zip_package();
		}
		// there was an error - save in global message
		else {
			$this->log_file_upload_error($_FILES['upload_micro']['error']);
		}
	}

	// write settings to .json file
	function update_json_file($theme, $context = '', $export_full = false, $preferences = false) {

		$theme = sanitize_file_name(sanitize_title($theme));

		// create micro theme of 'new' has been requested
		if ($context == 'new') {
			// Check for micro theme with same name
			if ($alt_name = $this->rename_if_required($this->micro_root_dir, $theme)) {
				$theme = $alt_name; // $alt_name is false if no rename was required
			}
			if (!$this->create_micro_theme($theme, 'export', ''))
				return false;
		}

		// json file
		$json_file = $this->micro_root_dir.$theme.'/config.json';
		$task = file_exists($json_file) ? 'updated' : 'created';

		// simple test - the json file was not being overwritten for one user, so delete if it exists
		if ($task === 'updated'){
			unlink($json_file);
		}

		// Create new file if it doesn't already exist
		if (!file_exists($json_file)) {

			// create directory if it doesn't exist - not doing so caused fopen issues after pack delete,
			// unless page refreshed
			$dir = dirname($json_file);
			if (!is_dir($dir)){
				if ( !wp_mkdir_p($dir) ) {
					$this->log(
						esc_html__('/micro-themes/'.$theme.' create directory error', 'microthemer'),
						'<p>' . sprintf(
							esc_html__('WordPress was not able to create the directory: %s', 'microthemer'),
							$this->root_rel($dir)
						) . $this->permissionshelp . '</p>'
					);
					return false;
				}
			}

			if (!$write_file = @fopen($json_file, 'w')) { // this creates a blank file for writing
				$this->log(
					esc_html__('Create json error', 'microthemer'),
					'<p>' . esc_html__('WordPress does not have permission to create: ', 'microthemer')
					. $this->root_rel($json_file) . $this->permissionshelp.'</p>'
				);
				return false;
			}
		}

		// check if json file is writable
		if (!is_writable($json_file)){
			$this->log(
				esc_html__('Write json error', 'microthemer'),
				'<p>' . esc_html__('WordPress does not have "write" permission for: ', 'microthemer')
				. $this->root_rel($json_file) . '. '.$this->permissionshelp.'</p>'
			);
			return false;
		}

		// copy full options to var for filtering
		$json_data = $this->options;

		// include the user's current media queries form importing back
		$json_data['non_section']['active_queries'] = $this->preferences['m_queries'];


		// Include snippets and npm dependencies
		if ($this->supportContent()){

			if (!empty($this->serialised_post['export_sections']['active_snippets'])){

				$json_data['non_section']['active_snippets'] = $this->contentMethod('getSnippets', array(-1, 0, ARRAY_A));

				// We want to combine npm packages with existing instead of overwriting
				$json_data['non_section']['combine_preferences']['npm_dependencies'] = $this->preferences['npm_dependencies'];

			}
		}


		// unless full, loop through full options - removing sections
		if (!$export_full){

			foreach ($this->options as $section_name => $array) {

				// if the section wasn't selected, remove it from json data var (along with the view_state var)
				if ( empty($this->serialised_post['export_sections'])
				     or (!array_key_exists($section_name, $this->serialised_post['export_sections']) )
				        and $section_name != 'non_section') {

					// remove the regular section data and view states
					unset($json_data[$section_name]);
					unset($json_data['non_section']['view_state'][$section_name]);

					// need to remove all media query settings for unchecked sections too
					if (!empty($json_data['non_section']['m_query']) and
					    is_array($json_data['non_section']['m_query'])) {
						foreach ($json_data['non_section']['m_query'] as $m_key => $array) {
							unset($json_data['non_section']['m_query'][$m_key][$section_name]);
						}
					}

					// and all of the important values
					if (!empty($json_data['non_section']['important']['m_query']) and
					    is_array($json_data['non_section']['important']['m_query'])) {
						foreach ($json_data['non_section']['important']['m_query'] as $m_key => $array) {
							unset($json_data['non_section']['important']['m_query'][$m_key][$section_name]);
						}
					}
				}
			}
		}

		// include preferences in export if passed in
		if ($preferences){
			$json_data['non_section']['exported_preferences'] = $preferences;
		}

		// set hand-coded css to nothing if not marked for export
		if ( empty($this->serialised_post['export_sections']['hand_coded_css'])) {
			$json_data['non_section']['hand_coded_css'] = '';
		}

		// set js to nothing if not marked for export
		if ( empty($this->serialised_post['export_sections']['js'])) {
			$json_data['non_section']['js'] = '';
		}

		// create debug selective export file if specified at top of script
		if ($this->debug_selective_export) {
			$data = '';
			$debug_file = $this->debug_dir . 'debug-selective-export.txt';
			$write_file = @fopen($debug_file, 'w');
			$data.= esc_html__('The Selectively Exported Options', 'microthemer') . "\n\n";
			$data.= print_r($json_data, true);
			$data.= "\n\n" . esc_html__('The Full Options', 'microthemer') . "\n\n";
			$data.= print_r($this->options, true);
			fwrite($write_file, $data);
			fclose($write_file);
		}

		// write data to json file
		if ($data = json_encode($json_data)) {
			// the file will be created if it doesn't exist. otherwise it is overwritten.
			$write_file = @fopen($json_file, 'w');
			fwrite($write_file, $data);
			fclose($write_file);
			// report
			if ($task == 'updated'){
				$this->log(
					esc_html__('Settings exported', 'microthemer'),
					'<p>' . esc_html__('Your settings were successfully exported to: ',
						'microthemer') . '<b>'.$theme.'</b></p>',
					'notice'
				);
			}
		}
		else {
			$this->log(
				esc_html__('Encode json error', 'microthemer'),
				'<p>' . esc_html__('WordPress failed to convert your settings into json.', 'microthemer') . '</p>'
			);
		}

		return $theme; // sanitised theme name
	}

	// load .json file - or json data if already got
	function load_json_file($json_file, $theme_name, $context = '', $data = false) {

		$isMerge = $context == __('Merge', 'microthemer');

		// if json data wasn't passed in to function, get it
		if ( !$data ){

			// bail if file is missing or cannot read
			if ( !$data = $this->get_file_data( $json_file ) ) {
				return false;
			}
		}

		// convert to array
		if (!$json_array = $this->json('decode', $data)) {
			return false;
		}

		// json decode was successful

		// if the export included workspace settings, save preferences and remove from data
		// this is insurance agaist upgrade problems
		if (!empty($json_array['non_section']['exported_preferences'])){
			update_option($this->preferencesName, $json_array['non_section']['exported_preferences']);
			unset($json_array['non_section']['exported_preferences']);
		}

		// replace mq keys, add new to the UI, add css units if necessary.
		$filtered_json = $this->filter_incoming_data('import', $json_array, $isMerge);

		// merge the arrays if merge (must come after key analysis/replacements)
		if ($isMerge or $context == esc_attr__('Raw CSS', 'microthemer')) {
			$filtered_json = $this->merge($this->options, $filtered_json);
		} else {
			// Only update theme_in_focus if it's not a merge
			$pref_array['theme_in_focus'] = $theme_name;
			$this->savePreferences($pref_array);
		}

		// updates options var, save settings, and update stylesheet
		$this->options = $filtered_json;
		$this->saveUiOptions2($this->options);
		$this->update_assets($theme_name, $context);

		// import success
		$this->log(
			esc_html__('Settings were imported', 'microthemer'),
			'<p>' . esc_html__('The design pack settings were successfully imported.', 'microthemer') . '</p>',
			'notice'
		);
	}

	// manage packs UI header
	function manage_packs_header($page){
		?>
		<ul class="pack-manage-options">
			<li class="upload">
				<form name='upload_micro_form' id="upload-micro-form" method="post" enctype="multipart/form-data"
				      action="<?php echo 'admin.php?page='. $page;?>" >
					<?php wp_nonce_field('tvr_upload_micro_submit'); ?>
					<input id="upload_pack_input" type="file" name="upload_micro" />
					<input class="tvr-button upload-pack" type="submit" name="tvr_upload_micro_submit"
					       value="<?php esc_attr_e('Upload design pack', 'microthemer'); ?>" title="<?php esc_attr_e('Upload a new design pack', 'microthemer'); ?>" />
				</form>
			</li>
		</ul>
		<?php
	}

	// Get design packs directories and count
	function get_design_packs($packs){
		$count = 0;
		$valid_packs = array();
		$exclude = array('sass', 'scss');
		foreach($packs as $name => $item){
			if (is_array($item) && !in_array($name, $exclude)){
				++$count;
				$valid_packs[$name] = $item;
			}
		}
		return array(
			'count' => $count,
			'directories' => $valid_packs
		);
	}

	// output meta spans and logs tmpl for manage pages
	function manage_packs_meta(){
		?>
		<span id="ajaxUrl" rel="<?php echo $this->wp_ajax_url; ?>"></span>
		<span id="delete-ok" rel='admin.php?page=<?php echo $this->microthemespage;?>&mt_action=tvr_delete_ok&_wpnonce=<?php echo wp_create_nonce('tvr_delete_ok'); ?>'></span>
		<span id="zip-folder" rel="<?php echo $this->thispluginurl.'zip-exports/'; ?>"></span>
		<?php
	}

	// Pack pagination UI
	function pack_pagination($page, $total_pages, $total_packs, $start, $end) {

		?>
		<ul class="tvr-pagination">
			<?php
			$i = $total_pages;
			while ($i >= 1){
				echo '
						<li class="page-item">';
				if ($i == $page) {
					echo '<span>'.$i.'</span>';
				} else {
					echo '<a href="admin.php?page='. $this->microthemespage . '&packs_page='.$i.'">'.$i.'</a>';
				}
				echo '
						</li>';
				--$i;
			}

			if ($end < 1) {
				$start = 0;
			}

			echo '<li class="displaying-x">' .
			     sprintf(esc_html__('Displaying %s - %s of %s', 'microthemer'), $start, $end, $total_packs) . '</li>';

			if (!empty($this->preferences['theme_in_focus']) and $total_packs > 0){
				$url = 'admin.php?page=' . $this->managesinglepage . '&design_pack=' . $this->preferences['theme_in_focus'];
				$name = $this->readable_name($this->preferences['theme_in_focus']);
				?>
				<li class="last-modified" rel="<?php echo $this->preferences['theme_in_focus']; ?>">
					<?php esc_html_e('Last modified design pack: ', 'microthemer'); ?><a title="<?php printf(esc_attr__('Edit %s', 'microthemer'), $name); ?>"
					                                                                     href="<?php echo $url; ?>"><?php echo esc_html($name); ?>
					</a>
				</li>
				<?php
			}
			?>
		</ul>
		<?php
	}

	// create micro theme/pack directory
	function create_micro_theme($micro_name, $action, $temp_zipfile) {
		// sanitize dir name
		$name = sanitize_file_name( $micro_name );
		$error = false;
		// extra bit need for zip uploads (removes .zip)
		if ($action == 'unzip') {
			$name = substr($name, 0, -4);
		}
		// check for micro-themes folder and create if doesn't exist
		$error = !$this->setup_micro_themes_dir() ? true : false;

		// check if the micro-themes folder is writable
		if ( !is_writeable( $this->micro_root_dir ) ) {
			$this->log(
				esc_html__('/micro-themes write error', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('The directory %s is not writable.', 'microthemer'),
					$this->root_rel($this->micro_root_dir)
				) . $this->permissionshelp . '</p>'
			);
			$error = true;
		}
		// Check for micro theme with same name
		if ($alt_name = $this->rename_if_required($this->micro_root_dir, $name)) {
			$name = $alt_name; // $alt_name is false if no rename was required
		}
		// abs path
		$this_micro_abs = $this->micro_root_dir . $name;
		// Create new micro theme folder
		if ( !wp_mkdir_p ( $this_micro_abs ) ) {
			$this->log(
				esc_html__('design pack create error', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('WordPress was not able to create the %s directory.', 'microthemer'), $this->root_rel($this_micro_abs)
				). '</p>'
			);
			$error = true;
		}
		// Check folder permission
		if ( !is_writeable( $this_micro_abs ) ) {
			$this->log(
				esc_html__('design pack write error', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('The directory %s is not writable.', 'microthemer'), $this->root_rel($this_micro_abs)
				) . $this->permissionshelp . '</p>'
			);
			$error = true;
		}

		// unzip if required
		if ($action == 'unzip') {
			// extract the files
			$this->extract_files($this_micro_abs, $temp_zipfile);
			// get the final name of the design pack from the meta file
			$name = $this->rename_from_meta($this_micro_abs . '/meta.txt', $name);
			if ($name){
				// import bg images to media library and update paths if any are found
				$json_config_file = $this->micro_root_dir . $name . '/config.json';
				$this->import_pack_images_to_library($json_config_file, $name);
			}
			// add the dir to the file structure array
			$this->file_structure[$name] = $this->dir_loop($this->micro_root_dir . $name);
			ksort($this->file_structure);
		}

		// if creating blank shell or exporting UI settings, need to create meta.txt and readme.txt
		if ($action == 'export') {
			// set the theme name value
			$_POST['theme_meta']['Name'] = $this->readable_name($name);
			$this->update_meta_file($this_micro_abs . '/meta.txt');
			$this->update_readme_file($this_micro_abs . '/readme.txt');

		}
		// update the theme_in_focus value in the preferences table
		$this->savePreferences(
			array(
				'theme_in_focus' => $name,
			)
		);

		// if still no error, the action worked
		if ($error != true) {
			if ($action == 'create') {
				$this->log(
					esc_html__('Design pack created', 'microthemer'),
					'<p>' . esc_html__('The design pack directory was successfully created on the server.', 'microthemer') . '</p>',
					'notice'
				);
			}
			if ($action == 'unzip') {
				$this->log(
					esc_html__('Design pack installed', 'microthemer'),
					'<p>' . esc_html__('The design pack was successfully uploaded and extracted. You can import it into your workspace any time using the') .
					' <span class="show-parent-dialog link" rel="import-from-pack">' . esc_html__('import option', 'microthemer') . '</span>'.
					'<span id="update-packs-list" rel="' . $this->readable_name($name) . '"></span>.</p>',
					'notice'
				);
			}
			if ($action == 'export') {
				$this->log(
					esc_html__('Settings exported', 'microthemer'),
					'<p>' . esc_html__('Your settings were successfully exported as a design pack directory on the server.', 'microthemer') . '</p>',
					'notice'
				);
			}
		}
		return true;
	}

	// rename zip from meta.txt name value
	function rename_from_meta($meta_file, $name){
		$orig_name = $name;
		if (is_file($meta_file) and is_readable($meta_file)) {
			$meta_info = $this->read_meta_file($meta_file);
			$name = strtolower(sanitize_file_name( $meta_info['Name'] ));
			// rename the directory if it doesn't already have the correct name
			if ($orig_name != $name){
				if ($alt_name = $this->rename_if_required($this->micro_root_dir, $name)) {
					$name = $alt_name; // $alt_name is false if no rename was required
				}
				rename($this->micro_root_dir . $orig_name, $this->micro_root_dir . $name);
			}
			return $name;
		} else {
			// no meta file error
			$this->log(
				esc_html__('Missing meta file', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('The zip file doesn\'t contain a necessary %s file or it could not be read.', 'microthemer'),
					$this->root_rel($meta_file)
				) . '</p>'
			);
			return false;
		}
	}

	// read the data from a file into a string
	function get_file_data($file){
		if (!is_file($file)){
			$this->log(
				esc_html__('File doesn\'t exist', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('%s does not exist on the server.', 'microthemer'),
					$this->root_rel($file)
				) . '</p>'
			);
			return false;
		}
		if (!is_readable($file)){
			$this->log(
				esc_html__('File not readable', 'microthemer'),
				'<p>' . sprintf(
					esc_html__(' %s could not be read.', 'microthemer'),
					$this->root_rel($file)
				) . '</p>'
				. $this->permissionshelp
			);
			return false;
		}
		$fh = @fopen($file, 'r');
		$data = fread($fh, filesize($file));
		fclose($fh);
		return $data;
	}

	// get image paths from the config.json file
	function get_image_paths($data){
		$img_array = array();

		// look for images
		preg_match_all('/"(background_image|list_style_image|border_image_src|mask_image)":"([^none][A-Za-z0-9 _\-\.\\/&\(\)\[\]!\{\}\?:=]+)"/',
			$data,
			$img_array,
			PREG_PATTERN_ORDER);

		// ensure $img_array only contains unique images
		foreach ($img_array[2] as $key => $config_img_path) {

			// if it's not unique, remove
			if (!empty($already_got[$config_img_path])){
				unset($img_array[2][$key]);
			}
			$already_got[$config_img_path] = 1;
		}

		if (count($img_array[2]) > 0) {
			return $img_array;
		} else {
			return false;
		}
	}

	// get media library images linked to from the config.json file
	function get_linked_library_images($json_config_file){
		// get config data
		if (!$data = $this->get_file_data($json_config_file)) {
			return false;
		}

		// get images from the config file that should be imported
		if (!$img_array = $this->get_image_paths($data)) {
			return false;
		}

		// loop through the image array, remove any images not in the media library
		foreach ($img_array[2] as $key => $config_img_path) {
			// has uploads path and doesn't also exist in pack dir (yet to be moved) - may be an unnecessary check
			if (strpos($config_img_path, '/uploads/')!== false and !is_file($this->micro_root_dir . $config_img_path)){
				$library_images[] = $config_img_path;
			}
		}
		if (is_array($library_images)){
			return $library_images;
		} else {
			return false;
		}
	}

	// import images in a design pack to the media library and update image paths in config.json
	function import_pack_images_to_library($json_config_file, $name, $data = false, $remote_images = false){

		// reset imported images
		$this->imported_images = array();

		// get config data if not passed in
		if (!$data) {
			if (!$data = $this->get_file_data($json_config_file)) {
				return false;
			}
		}

		// get images from the config file if not passed in
		if (!$remote_images) {
			if (!$img_array = $this->get_image_paths($data)) {
				return false;
			}
			$img_array = $img_array[2];
		} else {
			$img_array = $remote_images;
		}


		// loop through the image array
		foreach ($img_array as $key => $img_path) {

			$just_image_name = basename($img_path);

			// if remote image found in stylesheet downloaded to /tmp dir
			if ($remote_images){
				$tmp_image = $img_path; // C:/
				$orig_config_path = $key; // url
			} else {
				// else pack image found in zip
				$tmp_image = $this->micro_root_dir . $name . '/' . $just_image_name; // C:/
				$orig_config_path = $img_path; // url
			}

			// import the file to the media library if it exists
			if (file_exists($tmp_image)) {
				$this->imported_images[$just_image_name]['orig_config_path'] = $orig_config_path;

				// note import_image_to_library() updates 'success' and 'new_config_path'
				$id = $this->import_image_to_library($tmp_image, $just_image_name);

				// report wp error if problem
				if ( $id === 0 or is_wp_error($id) ) {
					if (is_wp_error($id)){
						$wp_error = '<p>'. $id->get_error_message() . '</p>';
					} else {
						$wp_error = '';
					}
					$this->log(
						esc_html__('Move to media library failed', 'microthemer'),
						'<p>' . sprintf(
							esc_html__('%s was not imported due to an error.', 'microthemer'),
							$this->root_rel($tmp_image)
						) . '</p>'
						. $wp_error
					);
				}
			}
		}

		// first report successfully moved images
		$moved_list =
			'<ul>';
		$moved = false;
		foreach ($this->imported_images as $just_image_name => $array){
			if (!empty($array['success'])){
				$moved_list.= '
						<li>
							'.$just_image_name.'
						</li>';
				$moved = true;
				// also update the json data string
				$replacements[$array['orig_config_path']] = $array['new_config_path'];
			}
		}
		$moved_list.=
			'</ul>';

		// move was successful, update paths
		if ($moved){
			$this->log(
				esc_html__('Images transferred to media library', 'microthemer'),
				'<p>' . esc_html__('The following images were transferred from the design pack to your WordPress media library:', 'microthemer') . '</p>'
				. $moved_list,
				'notice'
			);
			// update paths in json file
			return $this->replace_json_paths($json_config_file, $replacements, $data, $remote_images);
		}
	}

	// update paths in json file
	function replace_json_paths($json_config_file, $replacements, $data = false, $remote_images = false){

		if (!$data){
			if (!$data = $this->get_file_data($json_config_file)) {
				return false;
			}
		}

		// replace paths in string
		$replacement_occurred = false;
		foreach ($replacements as $orig => $new){
			if (strpos($data, $orig) !== false){
				$replacement_occurred = true;
				$data = str_replace($orig, $new, $data);
			}
		}
		if (!$replacement_occurred){
			return false;
		}

		// just return updated json data if loading css stylesheet
		if ($remote_images){
			$this->log(
				esc_html__('Image paths updated', 'microthemer'),
				'<p>' . esc_html__('Images paths were successfully updated to reflect the new location or deletion of an image(s).', 'microthemer') . '</p>',
				'notice'
			);
			return $data;
		}

		// update the config.json image paths for images successfully moved to the library
		if (is_writable($json_config_file)) {
			if ($write_file = @fopen($json_config_file, 'w')) {
				if (fwrite($write_file, $data)) {
					fclose($write_file);
					$this->log(
						esc_html__('Images paths updated', 'microthemer'),
						'<p>' . esc_html__('Images paths were successfully updated to reflect the new location or deletion of an image(s).', 'microthemer') . '</p>',
						'notice'
					);
					return true;
				}
				else {
					$this->log(
						esc_html__('Image paths failed to update.', 'microthemer'),
						'<p>' . sprintf(esc_html__('Images paths could not be updated to reflect the new location of the images transferred to your media library. This happened because %s could not rewrite the config.json file.', 'microthemer'), $this->appName) . '</p>' . $this->permissionshelp
					);
					return false;
				}
			}
		}
	}

	//Handle an individual file import.
	function import_image_to_library($file, $just_image_name, $post_id = 0, $import_date = false) {
		set_time_limit(60);
		// Initially, Base it on the -current- time.
		$time = current_time('mysql', 1);
		// A writable uploads dir will pass this test. Again, there's no point overriding this one.
		if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) ) {
			$this->log(
				esc_html__('Uploads folder error', 'microthemer'),
				$uploads['error']
			);
			return 0;
		}

		$wp_filetype = wp_check_filetype( $file, null );
		$type = $ext = false;
		extract( $wp_filetype );
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
			$this->log(
				esc_html__('Wrong file type', 'microthemer'),
				'<p>' . esc_html__('Sorry, this file type is not permitted for security reasons.', 'microthemer') . '</p>'
			);
			return 0;
		}

		//Is the file already in the uploads folder?
		if ( preg_match('|^' . preg_quote(str_replace('\\', '/', $uploads['basedir'])) . '(.*)$|i', $file, $mat) ) {
			$filename = basename($file);
			$new_file = $file;

			$url = $uploads['baseurl'] . $mat[1];

			$attachment = get_posts(array( 'post_type' => 'attachment', 'meta_key' => '_wp_attached_file', 'meta_value' => ltrim($mat[1], '/') ));
			if ( !empty($attachment) ) {
				$this->log(
					esc_html__('Image already in library', 'microthemer'),
					'<p>' . sprintf(
						esc_html__('%s already exists in the WordPress media library and was therefore not moved', 'microthemer'),
						$filename
					) . '</p>',
					'warning'
				);
				return 0;
			}
			//OK, Its in the uploads folder, But NOT in WordPress's media library.
		} else {
			$filename = wp_unique_filename( $uploads['path'], basename($file));

			// copy the file to the uploads dir
			$new_file = $uploads['path'] . '/' . $filename;
			if ( false === @rename( $file, $new_file ) ) {
				$this->log(
					esc_html__('Move to library failed', 'microthemer'),
					'<p>' . sprintf(
						esc_html__('%s could not be moved to %s', 'microthemer'),
						$filename,
						$uploads['path']
					) . '</p>',
					'warning'
				);
				return 0;
			}


			// Set correct file permissions
			$stat = stat( dirname( $new_file ));
			$perms = $stat['mode'] & 0000666;
			@ chmod( $new_file, $perms );
			// Compute the URL
			$url = $uploads['url'] . '/' . $filename;
		}

		//Apply upload filters
		$return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => $type ) );
		$new_file = $return['file'];
		$url = $return['url'];
		$type = $return['type'];

		$title = preg_replace('!\.[^.]+$!', '', basename($new_file));
		$content = '';

		// update the array for replacing paths in config.json
		$this->imported_images[$just_image_name]['success'] = true;
		$this->imported_images[$just_image_name]['new_config_path'] = $this->root_rel($url, false, true);

		// use image exif/iptc data for title and caption defaults if possible
		if ( $image_meta = @wp_read_image_metadata($new_file) ) {
			if ( '' != trim($image_meta['caption']) )
				$content = trim($image_meta['caption']);
		}

		if ( $time ) {
			$post_date_gmt = $time;
			$post_date = $time;
		} else {
			$post_date = current_time('mysql');
			$post_date_gmt = current_time('mysql', 1);
		}

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => $post_id,
			'post_title' => $title,
			'post_name' => $title,
			'post_content' => $content,
			'post_date' => $post_date,
			'post_date_gmt' => $post_date_gmt
		);

		$attachment = apply_filters('afs-import_details', $attachment, $file, $post_id, $import_date);

		//Win32 fix:
		$new_file = str_replace( strtolower(str_replace('\\', '/', $uploads['basedir'])), $uploads['basedir'], $new_file);

		// Save the data
		$id = wp_insert_attachment($attachment, $new_file, $post_id);
		if ( !is_wp_error($id) ) {
			$data = wp_generate_attachment_metadata( $id, $new_file );
			wp_update_attachment_metadata( $id, $data );
		}

		return $id;
	}

	// handle zip package
	function handle_zip_package() {
		$temp_zipfile = $_FILES['upload_micro']['tmp_name'];
		$filename = $_FILES['upload_micro']['name']; // it won't be this name for long
		// Chrome return a empty content-type : http://code.google.com/p/chromium/issues/detail?id=6800
		if ( !preg_match('/chrome/i', $_SERVER['HTTP_USER_AGENT']) ) {
			// check if file is a zip file
			if ( !preg_match('/(zip|download|octet-stream)/i', $_FILES['upload_micro']['type']) ) {
				@unlink($temp_zipfile); // del temp file
				$this->log(
					esc_html__('Faulty zip file', 'microthemer'),
					'<p>' . esc_html__('The uploaded file was faulty or was not a zip file.', 'microthemer') . '</p>
						<p>' . esc_html__('The server recognised this file type: ', 'microthemer') . $_FILES['upload_micro']['type'].'</p>'
				);
				return false;
			}
		}
		$this->create_micro_theme($filename, 'unzip', $temp_zipfile);
	}

	// read meta data from file
	function read_meta_file($meta_file) {
		// create default meta.txt file if it doesn't exist
		if (!is_file($meta_file)) {
			$_POST['theme_meta']['Name'] = $this->readable_name($this->preferences['theme_in_focus']);
			$this->update_meta_file($this->micro_root_dir . $this->preferences['theme_in_focus'].'/meta.txt');
		}
		if (is_file($meta_file)) {
			// check if it's readable
			if ( is_readable($meta_file) ) {
				//disable wptexturize
				remove_filter('get_theme_data', 'wptexturize');
				return $this->flx_get_theme_data( $meta_file );
			}
			else {
				$abs_meta_path = $this->micro_root_dir . $this->preferences['theme_in_focus'].'/meta.txt';

				$this->log(
					esc_html__('Read meta.txt error', 'microthemer'),
					'<p>' . esc_html__('WordPress does not have permission to read: ', 'microthemer') .
					$this->root_rel($abs_meta_path) . '. '.$this->permissionshelp.'</p>'
				);
				return false;
			}
		}
	}

	// read readme.txt data from file
	function read_readme_file($readme_file) {

		// create default readme file if it doesn't exist
		if (!is_file($readme_file)) {
			$this->update_readme_file($this->micro_root_dir . $this->preferences['theme_in_focus'].'/readme.txt');
		}
		if (is_file($readme_file)) {
			// check if it's readable
			if ( is_readable($readme_file) ) {
				$fh = @fopen($readme_file, 'r');
				$length = filesize($readme_file);
				if ($length == 0) {
					$length = 1;
				}
				$data = fread($fh, $length);
				fclose($fh);
				return $data;
			}
			else {
				$abs_readme_path = $this->micro_root_dir . $this->preferences['theme_in_focus'].'/readme.txt';
				$this->log(
					esc_html__('Read readme.txt error', 'microthemer'),
					'<p>' . esc_html__('WordPress does not have permission to read: ', 'microthemer'),
					$this->root_rel($abs_readme_path) . '. '.$this->permissionshelp.'</p>'
				);
				return false;
			}
		}
	}

	// adapted WordPress function for reading and formattings a template file
	function flx_get_theme_data( $theme_file ) {
		$default_headers = array(
			'Name' => 'Theme Name',
			'PackType' => 'Pack Type',
			'URI' => 'Theme URI',
			'Description' => 'Description',
			'Author' => 'Author',
			'AuthorURI' => 'Author URI',
			'Version' => 'Version',
			'Template' => 'Template',
			'Status' => 'Status',
			'Tags' => 'Tags'
		);
		// define allowed tags
		$themes_allowed_tags = array(
			'a' => array(
				'href' => array(),'title' => array()
			),
			'abbr' => array(
				'title' => array()
			),
			'acronym' => array(
				'title' => array()
			),
			'code' => array(),
			'em' => array(),
			'strong' => array()
		);
		// get_file_data() - WP 2.8 compatibility function created for this
		$theme_data = get_file_data( $theme_file, $default_headers, 'theme' );
		$theme_data['Name'] = $theme_data['Title'] = wp_kses( $theme_data['Name'], $themes_allowed_tags );
		$theme_data['PackType'] = wp_kses( $theme_data['PackType'], $themes_allowed_tags );
		$theme_data['URI'] = esc_url( $theme_data['URI'] );
		$theme_data['Description'] = wp_kses( $theme_data['Description'], $themes_allowed_tags );
		$theme_data['AuthorURI'] = esc_url( $theme_data['AuthorURI'] );
		$theme_data['Template'] = wp_kses( $theme_data['Template'], $themes_allowed_tags );
		$theme_data['Version'] = wp_kses( $theme_data['Version'], $themes_allowed_tags );
		if ( empty($theme_data['Status']) )
			$theme_data['Status'] = 'publish';
		else
			$theme_data['Status'] = wp_kses( $theme_data['Status'], $themes_allowed_tags );

		if ( empty($theme_data['Tags']) )
			$theme_data['Tags'] = array();
		else
			$theme_data['Tags'] = array_map( 'trim', explode( ',', wp_kses( $theme_data['Tags'], array() ) ) );

		if ( empty($theme_data['Author']) ) {
			$theme_data['Author'] = $theme_data['AuthorName'] = __('Anonymous');
		} else {
			$theme_data['AuthorName'] = wp_kses( $theme_data['Author'], $themes_allowed_tags );
			if ( empty( $theme_data['AuthorURI'] ) ) {
				$theme_data['Author'] = $theme_data['AuthorName'];
			} else {
				$theme_data['Author'] = sprintf( '<a href="%s" title="%s">%s</a>', $theme_data['AuthorURI'], esc_html__( 'Visit author homepage' ), $theme_data['AuthorName'] );
			}
		}
		return $theme_data;
	}

	// delete theme/pack
	function tvr_delete_micro_theme($dir_name) {
		$error = false;

        // loop through files if they exist
		if (isset($this->file_structure[$dir_name]) && is_array($this->file_structure[$dir_name])) {

			$serverPath = $this->micro_root_dir;
			$constructedPath = $serverPath . $dir_name;
			if (!$this->checkExpectedPath($constructedPath, $serverPath)){
				return false;
			}

			foreach ($this->file_structure[$dir_name] as $file => $oneOrFileName) {

				// there is an odd inconsistency with screenshot key referring to a filename
				// rather than the key being the file name
				$file = $oneOrFileName == 1 ? $file : $oneOrFileName;

				if (!unlink($this->micro_root_dir . $dir_name.'/'.$file)) {
					$this->log(
						esc_html__('File delete error', 'microthemer'),
						'<p>' . esc_html__('Unable to delete: ', 'microthemer') .
						$this->root_rel($this->micro_root_dir .
						                $dir_name.'/'.$file) . print_r($this->file_structure, true). '</p>'
					);
					$error = true;
				}
			}
		}
		if ($error != true) {
			$this->log(
				'Files successfully deleted',
				'<p>' . sprintf(
					esc_html__('All files within %s were successfully deleted.', 'microthemer'),
					$this->readable_name($dir_name)
				) . '</p>',
				'dev-notice'
			);
			// attempt to delete empty directory
			if (!rmdir($this->micro_root_dir . $dir_name)) {
				$this->log(
					esc_html__('Delete directory error', 'microthemer'),
					'<p>' . sprintf(
						esc_html__('The empty directory: %s could not be deleted.', 'microthemer'),
						$this->readable_name($dir_name)
					) . '</p>'
				);
				$error = true;
			}
			else {
				$this->log(
					esc_html__('Directory successfully deleted', 'microthemer'),
					'<p>' . sprintf(
						esc_html__('%s was successfully deleted.', 'microthemer'),
						$this->readable_name($dir_name)
					) . '</p>',
					'notice'
				);

				// reset the theme_in_focus value in the preferences table
				$pref_array['theme_in_focus'] = '';
				if (!$this->savePreferences($pref_array)) {
					// not much cause for a message
				}


				if ($error){
					return false;
				} else {
					return true;
				}
			}
		}
	}

	// update the meta file
	function update_meta_file($meta_file) {

		// check if the micro theme dir needs to be renamed
		if (isset($_POST['prev_micro_name']) and ($_POST['prev_micro_name'] != $_POST['theme_meta']['Name'])) {
			$orig_name = $this->micro_root_dir . $this->preferences['theme_in_focus'];
			$new_theme_in_focus = sanitize_file_name(sanitize_title($_POST['theme_meta']['Name']));
			// need to do unique dir check here too
			// Check for micro theme with same name
			if ($alt_name = $this->rename_if_required($this->micro_root_dir, $new_theme_in_focus)) {
				$new_theme_in_focus = $alt_name;
				// The dir had to be automatically renamed so update the visible name
				$_POST['theme_meta']['Name'] = $this->readable_name($new_theme_in_focus);
			}
			$new_name = $this->micro_root_dir . $new_theme_in_focus;
			// if the directory is writable
			if (is_writable($orig_name)) {
				if (rename($orig_name, $new_name)) {
					// if rename is successful...

					// the meta file will have a different location now
					$meta_file = str_replace($this->preferences['theme_in_focus'], $new_theme_in_focus, $meta_file);

					// update the files array directory key
					$cache = $this->file_structure[$this->preferences['theme_in_focus']];
					$this->file_structure[$new_theme_in_focus] = $cache;
					unset($this->file_structure[$this->preferences['theme_in_focus']]);

					// update the value in the preferences table
					$pref_array = array();
					$pref_array['theme_in_focus'] = $new_theme_in_focus;
					if ($this->savePreferences($pref_array)) {
						$this->log(
							esc_html__('Design pack renamed', 'microthemer'),
							'<p>' . esc_html__('The design pack directory was successfully renamed on the server.', 'microthemer') . '</p>',
							'notice'
						);
					}
				}
				else {
					$this->log(
						esc_html__('Directory rename error', 'microthemer'),
						'<p>' . sprintf(
							esc_html__('The directory %s could not be renamed for some reason.', 'microthemer'),
							$this->root_rel($orig_name)
						) . '</p>'
					);
				}
			}
			else {
				$this->log(
					esc_html__('Directory rename error', 'microthemer'),
					'<p>' . sprintf(
						esc_html__('WordPress does not have permission to rename the directory %s to match your new theme name "%s".', 'microthemer'),
						$this->root_rel($orig_name),
						htmlentities($this->readable_name($_POST['theme_meta']['Name']))
					) . $this->permissionshelp.'.</p>'
				);
			}
		}


		// Create new file if it doesn't already exist
		if (!file_exists($meta_file)) {
			if (!$write_file = @fopen($meta_file, 'w')) {
				$this->log(
					sprintf( esc_html__('Create %s error', 'microthemer'), 'meta.txt' ),
					'<p>' . sprintf(esc_html__('WordPress does not have permission to create: %s', 'microthemer'), $this->root_rel($meta_file) . '. '.$this->permissionshelp ) . '</p>'
				);
			}
			else {
				fclose($write_file);
			}
			$task = 'created';
			// set post variables if undefined (might be following initial export)

			if (!isset($_POST['theme_meta']['Description'])) {

				$current_user = wp_get_current_user();
				$_POST['theme_meta']['Description'] = "";
				$_POST['theme_meta']['PackType'] ='';
				$_POST['theme_meta']['Author'] = $current_user->display_name;
				$_POST['theme_meta']['AuthorURI'] = '';
				$_POST['theme_meta']['Template'] = '';
				$_POST['theme_meta']['Version'] = '1.0';
				$_POST['theme_meta']['Tags'] = '';

			}
		}
		else {
			$task = 'updated';
		}

		// check if it's writable - // need to remove carriage returns
		if ( is_writable($meta_file) ) {

			$Name = !empty($_POST['theme_meta']['Name']) ? $_POST['theme_meta']['Name'] : '';
			$PackType = !empty($_POST['theme_meta']['PackType']) ? $_POST['theme_meta']['PackType'] : '';
			$Description = !empty($_POST['theme_meta']['Description']) ? $_POST['theme_meta']['Description'] : '';
			$Author = !empty($_POST['theme_meta']['Author']) ? $_POST['theme_meta']['Author'] : '';
			$AuthorURI = !empty($_POST['theme_meta']['AuthorURI']) ? $_POST['theme_meta']['AuthorURI'] : '';
			$Template = !empty($_POST['theme_meta']['Template']) ? $_POST['theme_meta']['Template'] : '';
			$Version = !empty($_POST['theme_meta']['Version']) ? $_POST['theme_meta']['Version'] : '';
			$Tags = !empty($_POST['theme_meta']['Tags']) ? $_POST['theme_meta']['Tags'] : '';

			$data = '/*
Theme Name: '.strip_tags(stripslashes($Name)).'
Pack Type: '.strip_tags(stripslashes($PackType)).'
Description: '.strip_tags(stripslashes(str_replace(array("\n", "\r"), array(" ", ""), $Description))).'
Author: '.strip_tags(stripslashes($Author)).'
Author URI: '.strip_tags(stripslashes($AuthorURI)).'
Template: '.strip_tags(stripslashes($Template)).'
Version: '.strip_tags(stripslashes($Version)).'
Tags: '.strip_tags(stripslashes($Tags)).'
DateCreated: '.date('Y-m-d').'
*/';

			// the file will be created if it doesn't exist. otherwise it is overwritten.
			$write_file = @fopen($meta_file, 'w');
			fwrite($write_file, $data);
			fclose($write_file);
			// success message
			$this->log(
				'meta.txt '.$task,
				'<p>' . sprintf( esc_html__('The %s file for the design pack was %s', 'microthemer'), 'meta.txt', $task ) . '</p>',
				'dev-notice'
			);
		}
		else {
			$this->log(
				sprintf( esc_html__('Write %s error', 'microthemer'), 'meta.txt'),
				'<p>' . esc_html__('WordPress does not have "write" permission for: ', 'microthemer') .
				$this->root_rel($meta_file) . '. '.$this->permissionshelp.'</p>'
			);
		}
	}

	// update the readme file
	function update_readme_file($readme_file) {
		// Create new file if it doesn't already exist
		if (!file_exists($readme_file)) {
			if (!$write_file = @fopen($readme_file, 'w')) {
				$this->log(
					sprintf( esc_html__('Create %s error', 'microthemer'), 'readme.txt'),
					'<p>' . sprintf(
						esc_html__('WordPress does not have permission to create: %s', 'microthemer'),
						$this->root_rel($readme_file) . '. '.$this->permissionshelp
					) . '</p>'
				);
			}
			else {
				fclose($write_file);
			}
			$task = 'created';
			// set post variable if undefined (might be defined if theme dir has been
			// created manually and then user is submitting readme info for the first time)
			if (!isset($_POST['tvr_theme_readme'])) {
				$_POST['tvr_theme_readme'] = '';
			}
		}
		else {
			$task = 'updated';
		}
		// check if it's writable
		if ( is_writable($readme_file) ) {
			$data = stripslashes($_POST['tvr_theme_readme']); // don't use striptags so html code can be added
			// the file will be created if it doesn't exist. otherwise it is overwritten.
			$write_file = @fopen($readme_file, 'w');
			fwrite($write_file, $data);
			fclose($write_file);
			// success message
			$this->log(
				'readme.txt '.$task,
				'<p>' . sprintf(
					esc_html__('The %s file for the design pack was %s', 'microthemer'),
					'readme.txt', $task
				) . '</p>',
				'dev-notice'
			);
		}
		else {
			$this->log(
				sprintf( esc_html__('Write %s error', 'microthemer'), 'readme.txt'),
				'<p>' . esc_html__('WordPress does not have "write" permission for: ', 'microthemer') .
				$this->root_rel($readme_file) . '. '.$this->permissionshelp.'</p>'
			);
		}
	}

	// handle file upload to a pack
	function handle_file_upload() {

		// if no error
		if ($_FILES['upload_file']['error'] == 0) {
			$file = $_FILES['upload_file']['name'];
			// check if the file has a valid extension
			if ($this->is_acceptable($file)) {
				$dest_dir = $this->micro_root_dir . $this->preferences['theme_in_focus'].'/';
				// check if the directory is writable
				if (is_writeable($dest_dir) ) {
					// copy file if safe
					if (is_uploaded_file($_FILES['upload_file']['tmp_name'])
					    and copy($_FILES['upload_file']['tmp_name'], $dest_dir . $file)) {
						$this->log(
							esc_html__('File successfully uploaded', 'microthemer'),
							'<p>' . wp_kses(
								sprintf(
									__('<b>%s</b> was successfully uploaded.', 'microthemer'),
									htmlentities($file)
								),
								array( 'b' => array() )
							) . '</p>',
							'notice'
						);
						// update the file_structure array
						$this->file_structure[$this->preferences['theme_in_focus']][$file] = 1;

						// resize file if it's a screeshot
						if ($this->is_screenshot($file)) {
							$img_full_path = $dest_dir . $file;
							// get the screenshot size, resize if too big
							list($width, $height) = getimagesize($img_full_path);
							if ($width > 896 or $height > 513){
								$this->wp_resize(
									$img_full_path,
									896,
									513,
									$img_full_path);
							}
							// now do thumbnail
							$thumbnail = $dest_dir . 'screenshot-small.'. $this->get_extension($file);
							$root_rel_thumb = $this->root_rel($thumbnail);
							if (!$final_dimensions = $this->wp_resize(
								$img_full_path,
								145,
								83,
								$thumbnail)) {
								$this->log(
									esc_html__('Screenshot thumbnail error', 'microthemer'),
									'<p>' . wp_kses(
										sprintf(
											__('Could not resize <b>%s</b> to thumbnail proportions.', 'microthemer'),
											$root_rel_thumb
										),
										array( 'b' => array() )
									) . $img_full_path .
									esc_html__(' thumb: ', 'microthemer') .$thumbnail.'</p>'
								);
							}
							else {
								// update the file_structure array
								$file = basename($thumbnail);
								$this->file_structure[$this->preferences['theme_in_focus']][$file] = 1;
								$this->log(
									esc_html__('Screenshot thumbnail successfully created', 'microthemer'),
									'<p>' . sprintf(
										esc_html__('%s was successfully created.', 'microthemer'),
										$root_rel_thumb
									) . '</p>',
									'notice'
								);
							}
						}


					}
				}
				// it's not writable
				else {
					$this->log(
						esc_html__('Write to directory error', 'microthemer'),
						'<p>'. esc_html__('WordPress does not have "Write" permission to the directory: ', 'microthemer') .
						$this->root_rel($dest_dir) . '. '.$this->permissionshelp.'.</p>'
					);
				}
			}
			else {
				$this->log(
					esc_html__('Invalid file type', 'microthemer'),
					'<p>' . esc_html__('You have uploaded a file type that is not allowed.', 'microthemer') . '</p>'
				);

			}
		}
		// there was an error - save in global message
		else {
			$this->log_file_upload_error($_FILES['upload_file']['error']);
		}
	}

	// log file upload problem
	function log_file_upload_error($error){
		switch ($error) {
			case 1:
				$this->log(
					esc_html__('File upload limit reached', 'microthemer'),
					'<p>' . esc_html__('The file you uploaded reached your "upload_max_filesize" limit. This is a PHP setting on your server.', 'microthemer') . '</p>'
				);
				break;
			case 2:
				$this->log(
					esc_html__('File size too big', 'microthemer'),
					'<p>' . esc_html__('The file you uploaded reached your "max_file_size" limit. This is a PHP setting on your server.', 'microthemer') . '</p>'
				);
				break;
			case 3:
				$this->log(
					esc_html__('Partial upload', 'microthemer'),
					'<p>' . esc_html__('The file you uploaded only partially uploaded.', 'microthemer') . '</p>'
				);
				break;
			case 4:
				$this->log(
					esc_html__('No file uploaded', 'microthemer'),
					'<p>' . esc_html__('No file was detected for upload.', 'microthemer') . '</p>'
				);
				break;
		}
	}

	// resize image using wordpress functions
	function wp_resize($path, $w, $h, $dest, $crop = true){
		// ... (rest of the function is the same)
		$image = wp_get_image_editor( $path );
		if ( ! is_wp_error( $image ) ) {
			$image->resize( $w, $h, $crop );
			$image->save( $dest );
			return true;
		} else {
			return false;
		}
	}

	// resize image (legacy/fallback)
	function resize($img, $max_width, $max_height, $newfilename) {
		//Check if GD extension is loaded
		if (!extension_loaded('gd') && !extension_loaded('gd2')) {
			$this->log(
				esc_html__('GD not loaded', 'microthemer'),
				'<p>' . esc_html__('The PHP extension GD is not loaded.', 'microthemer') . '</p>'
			);
			return false;
		}
		//Get Image size info
		$imgInfo = getimagesize($img);
		switch ($imgInfo[2]) {
			case 1: $im = imagecreatefromgif($img); break;
			case 2: $im = imagecreatefromjpeg($img); break;
			case 3: $im = imagecreatefrompng($img); break;
			default:
				$this->log(
					esc_html__('File type error', 'microthemer'),
					'<p>' . esc_html__('Unsuported file type. Are you sure you uploaded an image?', 'microthemer') . '</p>'
				);

				return false; break;
		}
		// orig dimensions
		$width = $imgInfo[0];
		$height = $imgInfo[1];
		// set proportional max_width and max_height if one or the other isn't specified
		if ( empty($max_width)) {
			$max_width = round($width/($height/$max_height));
		}
		if ( empty($max_height)) {
			$max_height = round($height/($width/$max_width));
		}
		// abort if user tries to enlarge a pic
		if (($max_width > $width) or ($max_height > $height)) {
			$this->log(
				esc_html__('Dimensions too big', 'microthemer'),
				'<p>' . sprintf(
					esc_html__('The resize dimensions you specified (%s x %s) are bigger than the original image (%s x %s). This is not allowed.', 'microthemer'),
					$max_width, $max_height, $width, $height
				) . '</p>'
			);
			return false;
		}

		// proportional resizing
		$x_ratio = $max_width / $width;
		$y_ratio = $max_height / $height;
		if (($width <= $max_width) && ($height <= $max_height)) {
			$tn_width = $width;
			$tn_height = $height;
		}
		else if (($x_ratio * $height) < $max_height) {
			$tn_height = ceil($x_ratio * $height);
			$tn_width = $max_width;
		}
		else {
			$tn_width = ceil($y_ratio * $width);
			$tn_height = $max_height;
		}
		// for compatibility
		$nWidth = $tn_width;
		$nHeight = $tn_height;
		$final_dimensions['w'] = $nWidth;
		$final_dimensions['h'] = $nHeight;
		$newImg = imagecreatetruecolor($nWidth, $nHeight);
		/* Check if this image is PNG or GIF, then set if Transparent*/
		if(($imgInfo[2] == 1) or ($imgInfo[2]==3)) {
			imagealphablending($newImg, false);
			imagesavealpha($newImg,true);
			$transparent = imagecolorallocatealpha($newImg, 255, 255, 255, 127);
			imagefilledrectangle($newImg, 0, 0, $nWidth, $nHeight, $transparent);
		}
		imagecopyresampled($newImg, $im, 0, 0, 0, 0, $nWidth, $nHeight, $imgInfo[0], $imgInfo[1]);
		// Generate the file, and rename it to $newfilename
		switch ($imgInfo[2]) {
			case 1: imagegif($newImg,$newfilename); break;
			case 2: imagejpeg($newImg,$newfilename); break;
			case 3: imagepng($newImg,$newfilename); break;
			default:
				$this->log(
					esc_html__('Image resize failed', 'microthemer'),
					'<p>' . esc_html__('Your image could not be resized.', 'microthemer') . '</p>'
				);
				return false;
				break;
		}
		return $final_dimensions;
	}

}