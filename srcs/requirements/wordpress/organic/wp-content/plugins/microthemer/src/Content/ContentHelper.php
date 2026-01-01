<?php

namespace Microthemer\Content;

class ContentHelper {

	/**
	 * Perform safe memory/DOM cleanup after heavy XML/DOM operations.
	 * Compatible with PHP 5.1+ (skips unavailable functions).
	 */
	public static function cleanupMemory()
	{
		// Clear libxml internal error buffer
		if (function_exists('libxml_clear_errors')) {
			libxml_clear_errors();
		}

		// Trigger garbage collection for cyclic references (PHP 5.3+)
		if (function_exists('gc_collect_cycles')) {
			gc_collect_cycles();
		}

		// Free unused memory from Zend allocator (PHP 7.0+)
		if (function_exists('gc_mem_caches')) {
			gc_mem_caches();
		}
	}

	/**
	 * Merges any number of arrays recursively, preserving keys.
	 *
	 * For non-array values at the same key, the value from the later array will overwrite the earlier one.
	 * If a key exists in both arrays and both values are arrays, the two arrays will be merged recursively.
	 *
	 * @param array ...$arrays The arrays to merge.
	 * @return array The merged array.
	 */
	public static function array_merge_distinct(...$arrays){

		// If no arrays are provided, return an empty array.
		if (empty($arrays)) {
			return [];
		}

		// Get the first array as the base.
		$merged = array_shift($arrays);

		// Loop through the remaining arrays.
		foreach ($arrays as $array) {

			// Iterate over the current array to merge it into the base.
			foreach ($array as $key => $value) {

				// If the key exists in the merged array and both values are arrays, recurse.
				if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
					$merged[$key] = ContentHelper::array_merge_distinct($merged[$key], $value);
				} else {
					// Otherwise, just overwrite the value.
					$merged[$key] = $value;
				}
			}
		}

		return $merged;
	}

	public static function getScriptDepsFromMeta(&$npm_dependencies, $meta, $isString = true, $justStatements = false, $incImportedInline = false){

		if ($isString){
			$meta = !empty($meta) ? json_decode($meta, true) : array();
		}

		$statements = '';
		$auto = !empty($meta['auto_script_deps']) ? $meta['auto_script_deps'] : array();
		$manual = !empty($meta['manual_script_deps']) ? $meta['manual_script_deps'] : array();
		$imported = $incImportedInline && !empty($meta['imported_script_deps']) ? $meta['imported_script_deps'] : array();

		//return $meta;

		// exclude auto dependencies that have been manually disabled
		foreach ($manual as $packName => $config) {
			if (!empty($config['autoDisabled'])){
				unset($auto[$packName]);
				unset($manual[$packName]['autoDisabled']);
				if (empty($manual[$packName])){
					unset($manual[$packName]);
				}
			}
		}

		// Combine deps
		$totalDeps = ContentHelper::array_merge_distinct($auto, $manual, $imported);

		// Ensure importSyntax is set
		foreach ($totalDeps as $packName => $config) {
			if (empty($config['importSyntax'])){

				// Fall back to the import statement set at the package level
				if (!empty($npm_dependencies->$packName['importSyntax'] )){
					$config['importSyntax'] = $npm_dependencies->$packName['importSyntax'];
				}

				// we do not have a valid option to choose from - do not add dependency
				// perhaps we could default to the pack name...
				else {
					unset($totalDeps[$packName]);
				}
			}
		}

		//echo('$totalDeps: <pre>'.print_r($totalDeps, 1).'</pre>' . $statements);
		if ($justStatements){
			$register = array();
			foreach ($totalDeps as $packageName => $config){
				$module = !empty($config['module']) ? $config['module'] : $packageName;
				$statements.= "import " . $config['importSyntax'] . " from '".$module."';\n";
				ContentHelper::populateRegistered($packageName, $config['importSyntax'], $register);
			}
			ContentHelper::applyRegistered($register, $statements);
			//wp_die('getScriptDepsFromMeta: <pre>'.print_r($totalDeps, 1).'</pre>' . $statements);
		}

		/*if (count($totalDeps)){
			wp_die('$totalDeps: <pre>'.print_r([
				'auto' => $auto,
				'import' => $import,
				'manual' => $manual,
				'total' => $totalDeps,
				], 1).'</pre>' . $statements);
		}*/

		return count($totalDeps)
			? ($justStatements ? $statements : $totalDeps)
			: '';
	}

	public static function populateRegistered($packageName, $importSyntax, &$register){
		if (str_contains($packageName, 'gsap') && $packageName !== 'gsap'){
			$register['gsap']['registerSyntax'] = "gsap.registerPlugin({addons});\n";
			$register['gsap']['addons'][] = $importSyntax;
		}
	}

	public static function applyRegistered($register, &$statements){
		foreach($register as $array){
			$statements.= str_replace(
				'{addons}', implode(', ', $array['addons']), $array['registerSyntax']
			);
		}
	}


	public static function getJsFileName(&$snippet, $snippet_id, $withExtension = true){

		//wp_die('getJsFileName: <pre>'.print_r($snippet, true).'</pre>');

		$baseName = !empty($snippet['meta']['manual_name'])
			? pathinfo(trim($snippet['meta']['manual_name']), PATHINFO_FILENAME)
			: $snippet_id;

		return sanitize_file_name($baseName) . ($withExtension ? '.js' : '');
	}
}