<?php

namespace Microthemer\Content;

use \Microthemer\TimerTrait;

/*
 * Admin Content
 *
 * Manage content actions on the admin side
 */

class AdminContent {

	use TimerTrait;

	var $Admin;
	var $preferences = array();
	var $tailwindClassCacheDir;
	var $tailwindStyleCacheDir;

	var $content_table;

	var $features = array(
		'2025-03-26' => array(
			array(
				'name' => 'inspect',
				'title' => 'Inspect amendments',
				'desc' => 'Review all amendments applying to the current page'
			)
		)
	);

	public function __construct(&$Admin) {

		global $wpdb;

		// save reference to calling class
		$this->Admin = &$Admin;
		$this->preferences = &$Admin->preferences;
		$this->tailwindClassCacheDir = $Admin->micro_root_dir . 'mt/cache/tailwind/classes/';
		$this->tailwindStyleCacheDir = $Admin->micro_root_dir . 'mt/cache/tailwind/styles/';
		$this->content_table = $wpdb->prefix . "micro_content";
	}

	// Load TinyMCE scripts, but initialisation will be done on the client side
	function loadTinyMCE(){
		wp_enqueue_editor();
	}

	function getExistingMods($published){

		global $wpdb;
		$content_table = $wpdb->prefix . "micro_content";

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT slug FROM $content_table 
				WHERE type = %s AND published = %d",
				'folder_mod', $published
			),
			ARRAY_A
		);
	}

	function maybeUpdateHTMLTable($type, $insertOrUpdate, $staleMods = array(), $published = 0){

		//wp_die('maybeUpdateHTMLTable: <pre>' . print_r([$insertOrUpdate, $staleMods, count($insertOrUpdate), count($staleMods)], 1) . '</pre>');

		global $wpdb;
		$content_table = $wpdb->prefix . "micro_content";

		// check which syntax to use for MySQL (use an alias for 8+)
		$mysqlVersion = mysqli_get_client_version();

		// I get an error even with MySQL 8.2.0.0 so not implementing this
		// - live with the VALUES() deprecation for now
		// https://stackoverflow.com/questions/2714587/mysql-on-duplicate-key-update-for-multiple-rows-insert-in-single-query
		// $aliasSyntax = $mysqlVersion >= 80020;
		$aliasSyntax = false;

		if (count($insertOrUpdate)){

			// insert / update
			$insertString = '';
			$insertArray = array();
			$i = 0;
			foreach ($insertOrUpdate as $slug => $mod){

				$name = '';
				$aspect = '';
				$meta = '';
				$func_ref = '';

				if ($i > 0){
					$insertString.= ',';
				}

				if ($type === 'snippet'){
					$name = $mod['name'];
					$aspect = $mod['aspect'];
					$meta = isset($mod['meta'])
						? (is_array($mod['meta'])
							? json_encode($mod['meta'])
							: $mod['meta']
						)
						: '';
					$func_ref = isset($mod['func_ref'])
						? $mod['func_ref']
						: '';
					$mod = $mod['value'];
				}

				$insertString.= '(%d, %s, %s, %s, %s, %d, %s, %s, %d)';
				$insertArray[] = $i;
				$insertArray[] = $slug;
				$insertArray[] = $name;
				$insertArray[] = $type;
				$insertArray[] = $aspect;
				$insertArray[] = $published;
				$insertArray[] = $mod;
				$insertArray[] = $meta;
				$insertArray[] = $func_ref;

				$i++;
			}

			// alternative syntax for MySQL 8.0.19 (not in use right now)
			$as = $aliasSyntax ? 'as new' : '';
			$mods = $aliasSyntax ? 'new.content' : 'VALUES(content)';

			$preparedSql = $wpdb->prepare(
				"insert INTO $content_table(seq, slug, name, type, aspect, published, content, meta, func_ref) 
				   VALUES $insertString $as
				   ON DUPLICATE KEY UPDATE 
				       seq = VALUES(seq),
				       name = VALUES(name),
				       aspect = VALUES(aspect),
				       content = $mods, 
        			   modified_at = NOW(),
				       meta = VALUES(meta),
				       func_ref = VALUES(func_ref)",
				...$insertArray
			);

			$wpdb->query($preparedSql);

			/*wp_die('$preparedSql: <pre>' . print_r([
					$aliasSyntax,
				$mysqlVersion,
				$preparedSql,
				$staleMods
				], 1) . '</pre>');*/

		}

		// delete empty snippet
		if ($type === 'snippet'){
			// todo - if appropriate...
		}

		// remove any stale folders
		if ($type === 'folder_mod'){
			if (count($staleMods)){

				/*wp_die('$staleMods: <pre>' . print_r([
						$staleMods
					], 1) . '</pre>');*/

				$deleteString = '';
				$deleteArray = array($published);
				$i = 0;

				foreach ($staleMods as $slug => $one){

					if ($i > 0){
						$deleteString.= ' or ';
					}

					$deleteString.= 'slug = %s';
					$deleteArray[] = $slug;
					$i++;
				}


				$preparedSql = $wpdb->prepare(
					"DELETE FROM $content_table 
					WHERE type = 'folder_mod' AND published = %d and ( $deleteString )",
					...$deleteArray
				);

				$wpdb->query($preparedSql);

				//wp_die('$sql: <pre>' . print_r([$preparedSql, $staleMods], 1) . '</pre>');
				//wp_die('maybeUpdateHTMLTable: <pre>' . print_r([$insertOrUpdate, $staleMods, $content_table, $sql, $deleteArray], 1) . '</pre>');

				// clean stale folders
			}
		}


	}

	//
	function getSnippets(
		$num = 3, $published = 0, $output = OBJECT, $columns = null, $where = array()
	){

		global $wpdb;

		if (is_null($columns)){
			$columns = 'slug, name, aspect, content, modified_at, meta, func_ref';
		}

		$limit = '';
		$whereString = '';
		$values = array($published, 'snippet');

		// Where
		if (count($where)){
			
			// and/or where conditions
			foreach ($where as $type => $array){

				if (count($array)){
					$whereString.= ' AND ';
					$pieces = array();
					foreach ($array as $item){
						$col = $item[0];
						$val = $item[1];

						// Check if $val starts with '!'
						if (strpos($val, '!') === 0) {
							$val = substr($val, 1); // Strip the '!'
							$pieces[] = $col . ' != %s';
						} else {
							$pieces[] = $col . ' = %s';
						}

						$values[] = $val;
					}
					$whereString.= '( ' . implode(' '.$type.' ', $pieces) . ') ';
				}
			}
		}

		// Limit
		if ($num > 0){
			$limit = "LIMIT %d";
			$values[] = $num;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT $columns FROM $this->content_table 
			     WHERE published = %d AND type = %s $whereString 
			     ORDER BY aspect, modified_at DESC 
			     $limit",
				$values
			),
			$output
		);

	}

	function getSingleSnippet($slug, $published = 0){

		global $wpdb;

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name, content, aspect FROM $this->content_table 
			     WHERE type = %s AND published = %d AND slug = %s",
				array('snippet', $published, $slug)
			)
		);

		$this->Admin->jsonResponse($result);

	}

	/*function getAllSnippetNames(){

		$array = array();

		$results = $this->getSnippets(-1, 0, OBJECT, 'slug, name, aspect, modified_at');

		if ($results) {
			foreach ($results as $row) {

				$dateTime = new \DateTime($row->modified_at);

				$array[$row->slug] = array(
					'id' => $row->slug,
					'label' => $row->name,
					'mysql_date' => $row->modified_at,
					'category' => $row->aspect
					//'modified_at' => $dateTime->format('M j, y @ g:ia')
				);
			}
		}

		return $array;
	}*/

	function getSnippetCache($method, $or = array(), $and = array()){

		$cache = array();

		//return $cache;

		//$results = $this->getSnippets();
		$results = $this->Admin->contentMethod($method, array($or, $and));

		if ($results) {

			foreach ($results as $row) {

				//wp_die('$row: <pre>' . print_r(json_decode($row->content, true), 1). '</pre>');

				$cache[$row->slug] = array(
					'name' => $row->name,
					'value' => $row->content,
					'mysql_data' => $row->modified_at,
					//'category' => $row->aspect,
					'aspect' => $row->aspect,
					'meta' => json_decode($row->meta, true)
				);
			}

			//wp_die('here: <pre>' . print_r($cache, 1). '</pre>');
		}

		return $cache;

	}


	

	// Save snippets that have been added or edited
	function addUpdateOrDeleteSnippets($snippet_cache, $snippets_deleted = null){

		//wp_die('$snippet_cache: <pre>'. print_r($snippet_cache, 1). '</pre>');

		// Delete snippets marked for deletion (do this first in case user re-adds snippet content after deletion)
		if (is_array($snippets_deleted)){
			$this->deleteSnippets(array_keys($snippets_deleted));
		}

		// Snippet cache
		if (is_array($snippet_cache)){

			$insertOrUpdate = array();

			// get previous id/name from DB for file cleanup of renamed items
			/*$slugs = array();
			foreach ($snippet_cache as $slug => &$data){
				$slugs[] = array('slug', $slug);
			}*/
			$previousSnippets = $this->getSnippetsFromIds(array_keys($snippet_cache), array(array('aspect', 'js'))); // $this->getSnippetCache('getSnippetsOfType', $slugs);

			foreach ($snippet_cache as $slug => &$data){

				$insertOrUpdate[$slug] = $data; // json_encode($content);

				// Update js file on server
				if ($data['aspect'] === 'js'){
					$file_name = ContentHelper::getJsFileName($data, $slug);
					$prev_file_name = !empty($previousSnippets[$slug]) ? ContentHelper::getJsFileName($previousSnippets[$slug], $slug) : false;

					// cleanup previous file if renamed
					if ($file_name !== $prev_file_name){
						$prev_file = $this->Admin->micro_root_dir . 'mt/js/draft/'. $prev_file_name;
						if (file_exists($prev_file)){
							@unlink($prev_file);
						}
					}

					// Write new file
					$importStatements = '';
					if (!empty($data['meta'])){
						$importStatements = ContentHelper::getScriptDepsFromMeta($this->preferences['npm_dependencies'], $data['meta'], false, true);
						if ($importStatements){
							$importStatements.= "\n";
						}
					}

					$file = $this->Admin->micro_root_dir . 'mt/js/draft/'. $file_name;
					$this->Admin->write_file($file, $importStatements . $data['value'], 0, 'js');
				}

			}

			$this->maybeUpdateHTMLTable('snippet', $insertOrUpdate);

			// Create a cache file of snippets with TvrJS.func() dependencies
			$this->updateFunctionCacheFile();

		}

	}

	// To support getting all data from the content table in one DB query, we have a cache file listing the
	// TVR.func() references a snippet has - so those slugs can be included in the query
	// To support getting all data from the content table in one DB query, we have a cache file listing the
	// TVR.func() references a snippet has - so those slugs can be included in the query
	function updateFunctionCacheFile(){

		$snippets = array();
		$funcNames = array();

		// Get all slugs and meta from the content table where func_ref = 1
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT slug, meta FROM $this->content_table 
			 WHERE func_ref = %d",
				1
			)
		);

		if ($results) {
			// Loop through all snippets and extract tvrjs-(funcName) from meta string
			foreach ($results as $row) {
				$scriptDeps = ContentHelper::getScriptDepsFromMeta($this->preferences['npm_dependencies'], $row->meta, true);

				//$meta = !empty($row->meta) ? json_decode($row->meta) : array();
				//!empty($meta['auto_script_deps']) ? $meta['auto_script_deps'] : array();
				//$scriptDeps = explode(', ', $row->auto_script_deps); // Assuming auto_script_deps are comma-separated
				if ($scriptDeps){
					foreach ($scriptDeps as $dep => $importSyntax) {
						$dep = trim($dep);
						// Check if the dep contains a tvrjs function reference
						if (strpos($dep, 'tvrjs-') === 0) {
							// Extract the function name from the dep
							$funcName = trim(str_replace('tvrjs-', '', $dep));
							$snippets[$row->slug][$funcName] = 1; // Temporarily set to 1
							$funcNames[$funcName] = 1;
						}
					}
				}

			}
		}

		// Get slugs for all funcNames from another DB query
		if (!empty($funcNames)) {
			$funcNames = array_keys($funcNames); // Extract keys as an array

			// Create the placeholders for the query
			$placeholders = implode(',', array_fill(0, count($funcNames), '%s'));

			// Prepare and run the query
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT slug, name FROM $this->content_table 
				 WHERE name IN ($placeholders)",
					...$funcNames
				)
			);

			// Loop through the results and update the $snippets array
			if ($results) {
				foreach ($results as $row) {
					foreach ($snippets as $slug => &$funcs) {
						if (isset($funcs[$row->name])) {
							$funcs[$row->name] = $row->slug; // Update with the correct slug reference
						}
					}
				}
			}
		}

		// Save the data to a file as JSON
		$filePath = $this->Admin->micro_root_dir . 'mt/cache/content/function-deps.json';
		$this->Admin->write_file($filePath, json_encode($snippets));


	}

	function getSnippetsFromIds($snippetIds, $and = array()){
		$or = array();
		foreach ($snippetIds as $slug){
			$or[] = array('slug', $slug);
		}
		return $this->getSnippetCache('getSnippetsOfType', $or, array(array('aspect', 'js')));
	}

	// Snippets can be deleted via the "Select snippet" menu
	function deleteSnippets($snippetIds){

		global $wpdb;

		// Ensure the input is a non-empty array
		if (is_array($snippetIds) && !empty($snippetIds)) {

			// Get config for js files, which may need to be deleted from the server
			/*$slugs = array();
			foreach ($snippetIds as $slug){
				$slugs[] = array('slug', $slug);
			}*/
			$previousSnippets = $this->getSnippetsFromIds($snippetIds, array(array('aspect', 'js'))); // $this->getSnippetCache('getSnippetsOfType', $slugs, array(array('aspect', 'js')));

			// Create placeholders for the query
			$placeholders = implode(',', array_fill(0, count($snippetIds), '%s'));

			// Prepare and execute a delete query
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM $this->content_table 
                 WHERE type = %s AND published = %d AND slug IN ($placeholders)",
					array_merge(['snippet', 0], $snippetIds)
				)
			);

			// Clean up the files
			foreach ($previousSnippets as $snippet_id => $snippet){
				$file = $this->Admin->micro_root_dir . 'mt/js/draft/'. ContentHelper::getJsFileName($snippet, $snippet_id);
				if (file_exists($file)){
					@unlink($file);
				}
			}

		}
	}

	// Duplicate unpublished rows, making them published and updating any existing published rows
	function publishHTMLTable() {
		global $wpdb;

		try {
			// Start transaction
			$wpdb->query("START TRANSACTION");

			// Insert new published rows only if they don't exist
			$wpdb->query(
				"INSERT IGNORE INTO $this->content_table 
            (seq, slug, name, type, published, content, modified_at, aspect, meta, func_ref)
            SELECT seq, slug, name, type, 1, content, modified_at, aspect, meta, func_ref
            FROM $this->content_table
            WHERE published = 0"
			);

			// Update existing published rows with new content
			$wpdb->query(
				"UPDATE $this->content_table t1
            JOIN $this->content_table t2
                ON t1.slug = t2.slug 
                AND t1.type = t2.type
                AND t1.published = 1
                AND t2.published = 0
            SET 
                t1.name = t2.name,
                t1.content = t2.content,
                t1.modified_at = t2.modified_at,
                t1.aspect = t2.aspect,
                t1.meta = t2.meta,
                t1.func_ref = t2.func_ref"
			);

			// Delete published rows that have no matching draft rows
			$wpdb->query(
				"DELETE t1 FROM $this->content_table t1
            LEFT JOIN $this->content_table t2
                ON t1.slug = t2.slug 
                AND t1.type = t2.type
                AND t2.published = 0
            WHERE t1.published = 1
              AND t2.slug IS NULL"
			);

			// Commit if everything worked
			$wpdb->query("COMMIT");

		} catch (\Exception $e) {
			// Rollback on error
			$wpdb->query("ROLLBACK");
			$this->Admin->log('Publish Amender error', '<p>' . $e->getMessage() . '</p>');
		}
	}

	/*function publishHTMLTable() {
		global $wpdb;

		// Insert new published rows only if they don't exist
		$wpdb->query(
			"INSERT IGNORE INTO $this->content_table 
            (seq, slug, name, type, published, content, modified_at, aspect, meta, func_ref)
         SELECT seq, slug, name, type, 1, content, modified_at, aspect, meta, func_ref
         FROM $this->content_table
         WHERE published = 0"
		);

		// Update existing published rows with new content
		$wpdb->query(
			"UPDATE $this->content_table t1
         JOIN $this->content_table t2
         ON t1.slug = t2.slug 
         AND t1.type = t2.type
         AND t1.published = 1
         AND t2.published = 0
         SET 
            t1.name = t2.name,
            t1.content = t2.content,
            t1.modified_at = t2.modified_at,
            t1.aspect = t2.aspect,
            t1.meta = t2.meta,
            t1.func_ref = t2.func_ref"
		);
	}*/

	// Copy and minify user and npm JS
	function publishJavaScript(){

		$draftFolder = $this->Admin->micro_root_dir . 'mt/js/draft/';
		$activeFolder = $this->Admin->micro_root_dir . 'mt/js/active/';
		$origActiveFiles = $this->Admin->getDirectoryFileList($activeFolder);
		$message = '';

		// copy and minify user JS files (non-recursive)
		$this->Admin->copyFolder($draftFolder, $activeFolder, 0, true, true, 0);

		// Just copy already minified npm folders recursively
		$this->Admin->copyFolder($draftFolder.'npm/', $activeFolder.'npm/');

		// Clean up files that are not present in draft folder
		foreach ($origActiveFiles as $relativePath){
			if (!file_exists($draftFolder . $relativePath)){
				@unlink($activeFolder . $relativePath);
				$message = $this->removeEmptyDirectories($activeFolder, $relativePath, $message);
			}
		}

	}

	function deleteDraftSnippets(){

		global $wpdb;

		// Delete all existing snippets from the content table
		$wpdb->query(
			"DELETE FROM $this->content_table WHERE published = 0"
		);
	}

	/**
	 * @method void restoreSnippets(array &$snippets)
	 */
	/**
	 * @method void restoreSnippets(array &$snippets)
	 */
	function restoreSnippets($snippets, $isSerialised = true, $mergeWithExisting = false) {
		global $wpdb;

		if ($isSerialised) {
			$snippets = unserialize($snippets);
		}

		if (is_array($snippets) && !empty($snippets)) {

			$wpdb->query('START TRANSACTION');

			try {
				if (!$mergeWithExisting) {
					$this->deleteDraftSnippets();
				}

				$insertPlaceholders = [];
				$insertValues = [];

				foreach ($snippets as $data) {
					$insertPlaceholders[] = '(%d, %s, %s, %s, %s, %d, %s, %s, %s)';

					array_push($insertValues,
						0, // seq
						isset($data['slug']) ? $data['slug'] : '',
						isset($data['name']) ? $data['name'] : '',
						'snippet',
						isset($data['aspect']) ? $data['aspect'] : '',
						0, // published
						isset($data['content']) ? $data['content'] : '',
						isset($data['meta']) ? $data['meta'] : '',
						isset($data['func_ref']) ? $data['func_ref'] : ''
					);
				}

				if (!empty($insertPlaceholders)) {
					$sql = "
					INSERT INTO $this->content_table 
					(seq, slug, name, type, aspect, published, content, meta, func_ref)
					VALUES " . implode(', ', $insertPlaceholders);

					if ($mergeWithExisting) {
						$sql .= "
						ON DUPLICATE KEY UPDATE 
							name = VALUES(name),
							aspect = VALUES(aspect),
							content = VALUES(content),
							meta = VALUES(meta),
							func_ref = VALUES(func_ref),
							published = VALUES(published)";
					}

					$wpdb->query($wpdb->prepare($sql, ...$insertValues));
				}

				$wpdb->query('COMMIT');

			} catch (\Exception $e) {
				$wpdb->query('ROLLBACK');
				throw $e;
			}
		}
	}





	/*function restoreSnippets($snippets, $isSerialised = true, $mergeWithExisting = false) {

		global $wpdb;

		// Deserialize the provided snippets
		if ($isSerialised){
			$snippets = unserialize($snippets);
		}

		if (is_array($snippets) && !empty($snippets)) {
			// Start a transaction to ensure atomicity
			$wpdb->query('START TRANSACTION');

			try {

				// Clear out the existing draft snippets
				$this->deleteDraftSnippets();

				// Prepare data for insertion
				$insertValues = array();
				$insertPlaceholders = array();

				foreach ($snippets as $data) {
					$insertPlaceholders[] = '(%s, %s, %s, %s, %s, %d, %s, %s, %s)';
					$insertValues = array_merge($insertValues, [
						0,
						$data['slug'],
						$data['name'],
						'snippet',
						$data['aspect'],
						0, // we only backup/restore unpublished snippets (published ones are copied from unpublished)
						$data['content'],
						isset($data['meta']) ? $data['meta'] : '',
						isset($data['func_ref']) ? $data['func_ref'] : ''
					]);
				}

				if (!empty($insertPlaceholders)) {
					// Insert the restored snippets into the database
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO $this->content_table 
                        (seq, slug, name, type, aspect, published, content, meta, func_ref) 
                        VALUES " . implode(', ', $insertPlaceholders),
							...$insertValues
						)
					);
				}

				// Commit the transaction
				$wpdb->query('COMMIT');

			} catch (\Exception $e) {
				// Rollback the transaction on error
				$wpdb->query('ROLLBACK');
				throw $e; // Re-throw the exception after rollback
			}
		}
	}*/


	function ajaxActions(){

		// get a code snippet
		if (isset($_GET['get_single_snippet'])) {
			$this->getSingleSnippet($_GET['snippet_id']);
		}

		// update an install
		elseif (isset($_GET['update_npm_install'])) {
			echo $this->adjustNPMInstall();
			$this->Admin->jsonResponse(array('message' => 'done'));
		}

		// Update dependencies
		elseif (isset($_GET['update_npm_dependencies'])) {
			$this->updateNPMDependencies();
			$this->Admin->jsonResponse(array('message' => 'done'));
		}

		elseif (isset($_POST['tailwind_actions'])) {
			$this->tailwindAjaxActions();
		}

		// update mt_rich_text preference
		$binaryPreferences = array(
			'mt_rich_text',
			'mt_rich_text_code',
			'show_snippet_adv'
		);
		foreach ($binaryPreferences as $key){
			if (isset($_GET[$key])) {
				$this->Admin->savePreferences(array(
					$key => intval($_GET[$key])
				));
				wp_die();
			}
		}

	}

	function updateNPMDependencies(){

		$npm_dependencies = isset($_POST['npm_dependencies'])
			? $_POST['npm_dependencies']
			: array();
		$npm_dependencies_in_use = isset($_POST['npm_dependencies_in_use'])
			? $_POST['npm_dependencies_in_use']
			: array();

		$this->Admin->savePreferences(
			array(
				'npm_dependencies' => (object) $npm_dependencies,
				'npm_dependencies_in_use' => (object) $npm_dependencies_in_use
			)
		);
	}

	function removeEmptyDirectories($vendorDir, $addonDepPath, $message){

		// check directories going backwards from the file, deleting any empty one
		$pathParts = explode('/', $addonDepPath);
		array_pop($pathParts); // remove the filename

		while (!empty($pathParts)) {
			$dirPath = $vendorDir . implode('/', $pathParts);
			if (is_dir($dirPath) && count(scandir($dirPath)) <= 2) { // only . and .. remain
				if (@rmdir($dirPath)) {
					$message .= '<p>Empty directory removed: ' . implode('/', $pathParts) . '</p>';
				} else {
					$message .= '<p>Could not remove directory: ' . implode('/', $pathParts) . '</p>';
					break; // if we can't remove this directory, we won't be able to remove parents
				}
			} else {
				break; // directory not empty or doesn't exist, stop checking parents
				//$message .= '<p>directory not empty or does not exist:' . implode('/', $pathParts) . '</p>';
			}
			array_pop($pathParts); // move up to parent directory
		}

		return $message;
	}

	function adjustNPMInstall(){

		$vendorDir = $this->Admin->micro_root_dir . 'mt/js/draft/npm/';
		$message = '';
		$results = array();
		$addonFiles = &$this->preferences['npm_addon_files'];
		$saveAddonFiles = false;

		if (!empty($_POST['packages']) && is_array($_POST['packages'])){
			foreach($_POST['packages'] as $item){

				if (empty($item['package'])){
					continue;
				}

				$package = $item['package'];

				// Uninstall
				if (empty($item['install']) && !empty($item['local'])){
					$local = $item['local'];
					$file = $vendorDir . $local;
					if (file_exists($file)){
						if (!@unlink($file)){
							$message.= '<p>Package could not be deleted: ' . $package . '</p>';
						} else {
							$message.= '<p>Package uninstalled: ' . $package . '</p>';
							$results[$package]['isInstalled'] = 0;

							// Some non-addon packages will be installed in a subdirectory
							// e.g.@react-three/drei
							$message = $this->removeEmptyDirectories(
								$vendorDir, $local, $message
							);

							// Find all addon dependent file
							foreach ($addonFiles as $addonDepPath => $addonArray){

								//echo '$addonDepPath: <pre>'.print_r($addonArray, 1).'</pre>';

								// If the addon references it
								if (!empty($addonArray[$local])){

									//echo 'Is there: <pre>'.$package.'</pre>';

									// unset the entry
									unset($addonFiles[$addonDepPath][$local]);

									// If the file is not used by any other dependencies
									if (empty($addonFiles[$addonDepPath])){

										$addonFile = $vendorDir . $addonDepPath;

										if (file_exists($addonFile)){
											if (!@unlink($addonFile)){
												$message.= '<p>Addon dep could not be deleted: ' . $addonDepPath . '</p>';
											} else {
												$message.= '<p>Addon dep successfully deleted: ' . $addonDepPath . '</p>';
											}
										}

										// clean up any empty directories
										$message = $this->removeEmptyDirectories(
											$vendorDir, $addonDepPath, $message
										);

										unset($addonFiles[$addonDepPath]);

									}

									$saveAddonFiles = true;
								} else {
									//echo 'NOT there: <pre>'.$package.'</pre>';
								}
							}

							/*if (!empty($item['isAddon'])){

							}*/


						}
					} else {
						$message.= '<p>Package already uninstalled: ' . $package . '</p>';
						$results[$package]['isInstalled'] = 0;
					}
				}

				// Install
				else {
					if (!empty($item['cdn']) && !empty($item['package'])){

						// Use content if provided
						if (isset($item['content'])){
							$content = stripslashes($item['content']);
						}

						// Else copy from CDN
						else {
							$cdnUrl = 'https://cdn.jsdelivr.net/npm/' . $item['cdn'];
							$response = wp_remote_get($cdnUrl, [
								'timeout' => 15,
								'redirection' => 5,
								'headers' => [
									'User-Agent' => 'WordPress/' . get_bloginfo('version'),
								],
							]);

							if (is_wp_error($response)) {
								error_log('CDN fetch error: ' . $response->get_error_message());
								$message.= '<p>Error fetching package from CDN: ' . $cdnUrl . '</p>';
							} else {
								$content = wp_remote_retrieve_body($response);
							}
						}

						// Write to file if we have content
						if (!empty($content)) {
							$local = $item['local']; // str_replace('@', '', $item['package']) . '.js';
							$this->Admin->write_file($vendorDir . $local, $content);
							$message.= '<p>File installed locally: ' . $local . '</p>';
							$results[$package]['isInstalled'] = 1;
							$results[$package]['local'] = $local;

							// Log files used for addon
							if (!empty($item['isAddon'])){
								$addonFiles[$local][$item['rootName'] . '.js'] = 1;
								$saveAddonFiles = true;
							}

						} else {
							$message.= '<p>Content was empty.</p>';
						}

					}
				}
			}
		}

		if ($saveAddonFiles){
			$this->Admin->savePreferences(array('npm_addon_files' => $addonFiles));
		}

		return json_encode(array(
			'addonFiles' => $addonFiles,
			'results' => $results,
			'message' => $message,
			//'packages' => $_POST['packages']
		));
	}
	
	
	// handle Tailwind ajax actions
	function tailwindAjaxActions(){

		//wp_die( '<pre> we got actions' . print_r($GLOBALS, 1 ) . '</pre>');

		// update single page class cache
		if (isset($_POST['update_single_page_classes'])) {
			$this->updateTailwindClasses(
				$_POST['single_page_slug'],
				stripslashes($_POST['single_page_classes'])
			);
		}

		// update single page style cache
		if (isset($_POST['update_single_page_styles'])) {
			$this->updateTailwindStyles(
				$_POST['single_page_slug'],
				stripslashes($_POST['single_page_styles'])
			);
		}

		// update site-wide styles cache
		if (isset($_POST['update_site_wide_styles'])) {
			$this->updateTailwindStyles(
				$_POST['site_wide_slug'],
				stripslashes($_POST['site_wide_styles'])
			);
		}
		
		// get a list of side-wide tailwind classes
		if (isset($_POST['get_site_wide_classes'])) {

			$this->Admin->jsonResponse(
				$this->getTailwindClasses(
					$_POST['site_wide_slug'],
					isset($_POST['from_cache']),
				),
				false
			);
		}

		wp_die();
		
	}

	// update the Tailwind CSS styles 
	function updateTailwindStyles($slug, $content = ''){

		$this->Admin->write_file(
			$this->tailwindStyleCacheDir . $slug . '.css', $content, true
		);

		$this->Admin->savePreferences(array(
			'tailwind_num_saves' => ($this->preferences['tailwind_num_saves'] + 1),
		));
	}

	// Update the tailwind classes and styles for a specific page and / or site-wide
	function updateTailwindClasses($slug, $content = '', $rebuildCache = false){
		
		$dir = $this->tailwindClassCacheDir;
		$siteWide = $slug === 'site-wide';
		$path = $dir . $slug . '.json';
		
		if ($siteWide && $rebuildCache){
			$this->refreshSiteWideTailwindCache($path);
		}
		
		else {

			//wp_die( '<pre>' . print_r([$path, $content], 1 ) . '</pre>');

			$this->Admin->write_file($path, $content);
		}
		
	}

	function refreshSiteWideTailwindCache($path = null){
		
		$dir = $this->tailwindClassCacheDir;
		$data = array();
		
		foreach (new \DirectoryIterator($dir) as $fileInfo) {

			if (!$fileInfo->isDot() && $fileInfo->getFilename() !== 'site-wide.json' ) {

				$classArray = json_decode(file_get_contents($dir . $fileInfo), true);

				//echo "Test all <pre>" . print_r([$dir . $fileInfo, $classArray], 1) . "\n</pre>";

				if (is_array($classArray) && count($classArray)){

					foreach ($classArray as $className){

						/*$value = isset($data[$className]) ? $data[$className]+1 : 1;
						$data[$className] = $value;*/
						// for now, we're not using counts for a perf boost, and have 1 means string comp is easier
						$data[$className] = 1;
					}
				}
			};
		}

		$this->Admin->write_file($path, json_encode($data));
		
		return $data;
	}

	function getTailwindClasses($slug, $fromCache = true){

		$dir = $this->tailwindClassCacheDir;
		$siteWide = $slug === 'site-wide';
		$path = $dir . $slug . '.json';
		$data = '';

		//wp_die('file_exists <pre>' . print_r([$path], 1) . '</pre>');

		if ($siteWide && !$fromCache) {
			$data = $this->refreshSiteWideTailwindCache($path);
		} elseif (file_exists($path)){
			$data = json_decode(file_get_contents($path), true);
		}

		return $data;

	}

	function groupContextMenu(){

		$html = '';
		$types = array(
			'current' => 'Current Tabs',
			'default' => 'Default Tabs',
		);

		$html.= '
		<div id="group-management-tabs" class="query-tabs">';

		foreach($types as $key => $title){
			$active = $key === 'default' ? ' active' : '';
			$html.= '<span class="mt-tab group-management-tab group-management-tab-'.$key.$active.'" rel="'.$key.'">'.$title.'</span>';
		}

		$html.= '
		</div>';

		foreach($types as $key => $title){
			$show= $key === 'default' ? ' show' : '';
			$addTab = esc_attr__('Add tab', 'microthemer');
			$html.= '
			<div class="group-management-field group-management-field-'.$key.$show.' hidden">
			    <ul class="html-tabs-list-'.$key.'"></ul>
			    <div class="add-tab-wrap">
				     '.$this->Admin->iconFont('add', array(
						'class' => 'group-management-add-tab group-tab-action-icon',
						'adjacentText' => array(
							'text' => $addTab,
							'class' => 'mti-text group-management-add-tab group-tab-action-icon'
						),
					)).
			        '<span class="mt-management-spacer"></span>'.
			        $this->Admin->iconFont('undo', array(
				        'class' => 'group-management-reset-tabs group-tab-action-icon',
						'data-group-tab-action' => 'reset',
				        'adjacentText' => array(
					        'text' => esc_html__('Reset default tabs', 'microthemer'),
					        'class' => 'mti-text group-management-reset-tabs group-tab-action-icon',
					        'data-group-tab-action' => 'reset',
				        ),
			        )).'
				</div>
			   
			</div>';
		}

		echo $this->Admin->context_menu_content(array(
			'base_key' => 'group-html',
			'title' => esc_html__('Amender tabs', 'microthemer'),
			'sections' => array(
				$html
			)
		));

	}

	function getSnippetsOfType($or = array(), $and = array(), $columns = null){
		return $this->getSnippets(
			-1, 0, OBJECT, $columns, array(
				'OR' => $or,
				'AND' => $and
			)
		);
	}

	/*function getLoadingHTMLSnippets(){
		return $this->getSnippetsOfType(array(), array(
			array('aspect', 'html'),
		), 'slug, content');
	}*/

	function storeCSSSnippets(&$asset){
		$snippets = $this->getSnippetsOfType(array(
			array('aspect', 'css'),
		));
		foreach ($snippets as $item){
			$asset['snippets'][$item->aspect][$item->slug] = $item->content;
		}
	}

}