<?php

namespace Microthemer;

/*
 * Logic
 *
 * Evaluate a PHP syntax conditional expression as a text string without using eval()
 * Supports a handful of WP functions and: ||, or, &&, and, (, ), !, =
 * Test String: is_page('test') and is_page("some-slug") && ! has_category() or has_tag() || is_date() === 23 or is_date() !== 'My string'
 * Test Regex: (?:(!)?\s*([a-z_]+)\('?"?(.*?)'?"?\))|(and|&&)|(or|\|\|)|([=!]{2,3})|(\d+)|(?:['"]{1}(.+?)['"]{1})
 */

class Logic {

	protected $test = false;

	// we cache condition results at various levels of granularity for maximum performance
	public static $cache = array(
		'conditions' => array(),
		'statements' => array(),
		'functions' => array(),
	);
	protected $statementCount = 0;
	protected $settings = array();

	// parenthesis parsing variables
	protected $stack = null;
	protected $current = null;
	protected $string = null;
	protected $position = null;
	protected $buffer_start = null;
	protected $length;

	// Regex patterns for reading logic
	protected $patterns = array(
		"andOrSurrSpace" => '/\s+\b(and|AND|or|OR)\b(?=(?:[^"\']*(?:"[^"]*"|\'[^\']*\'))*[^"\']*$)\s+/', // exclude if inside double quotes
		"functionName" => "(!)?\s*[a-zA-Z_\\\\]+",
		"comparison" => "/\s*(?<comparison><=|<|>|>=|!==?|===?)\s*/",
		"expressions" => array(
			"(?:(?<negation>!)?\s*(?<functionName>[a-zA-Z_\\\\]+)\((?<parameter>.*?)\))",
			"(?:[$]_?(?<global>GET)\['?\"?(?<key>.*?)'?\"?\])",
			"(?<string>['\"].+?['\"])",
			"(?<boolean>true|false|null|TRUE|FALSE|NULL)",
			"(?<number>-?\d+)",

		)
	);

	// PHP functions the user is allowed to use in the logic
	protected $allowedFunctions = array(
		'get_post_type',
		'has_action',
		'has_block',
		'has_category',
		'has_filter',
		'has_meta',
		'has_post_format',
		'has_tag',
		'is_404',
		'is_admin',
		'is_archive',
		'is_author',
		'is_category',
		'is_date',
		'is_front_page',
		'is_home',
		'is_page',
		'is_post_type_archive',
		'is_search',
		'is_single',
		'is_singular',
		'is_super_admin',
		'is_tag',
		'is_tax',
		'is_login',
		'is_user_logged_in',

		// custom namespaced Microthemer functions
		'\\'.__NAMESPACE__.'\has_template',
		'\\'.__NAMESPACE__.'\is_active',
		'\\'.__NAMESPACE__.'\is_admin_page',
		'\\'.__NAMESPACE__.'\is_post_or_page',
		'\\'.__NAMESPACE__.'\is_public',
		'\\'.__NAMESPACE__.'\is_public_or_admin',
		'\\'.__NAMESPACE__.'\match_url_path',
		'\\'.__NAMESPACE__.'\query_admin_screen',
		'\\'.__NAMESPACE__.'\user_has_role',

		// native PHP
		'isset',
	);

	public function getAllowedPHPSyntax(){
		return array(
			'functions' => $this->allowedFunctions,
			'superglobals' => $this->allowedSuperglobals,
			'characters' => 'or | and & ( ) ! = > <'
		);
	}

	protected $allowedSuperglobals = array(
		'$_GET',
	);

	function __construct($settings = array()){

		Logic::$cache['settings'] = $settings;

		// maybe allow user defined whitelist of functions here
	}

	// normalise &&, || for simpler regex and logical comparisons
	protected function normaliseAndOr($string){

		return str_replace(
			array("&&", "||"),
			array("and", "or"),
			$string
		);
	}

	protected $quotedStringPlaceholders = array();

	// brackets inside quotes cause issues for regex, so we can temp remove quoted strings to simplify
	protected function removeQuotedStrings($string) {

		// Clear existing placeholders
		$this->quotedStringPlaceholders = array();

		$placeholderPrefix = 'QSP_';

		return preg_replace_callback(
			//'/"[^"]*"/',
		   //'([\'"])[^\'"]*\1',
			"/(['\"])(?:\\\\.|[^\\\\])*?\\1/",
			function ($matches) use ($placeholderPrefix) {
				$key = $placeholderPrefix . count($this->quotedStringPlaceholders);
				$this->quotedStringPlaceholders[$key] = $matches[0];
				return $key;
			},
			$string
		);
	}

	protected function restoreQuotedStrings($string, $quotedStringPlaceholders = false) {
		if (!$quotedStringPlaceholders){
			$quotedStringPlaceholders = $this->quotedStringPlaceholders;
		}
		return str_replace(array_keys($quotedStringPlaceholders), array_values($quotedStringPlaceholders), $string);
	}


	protected function addCarets($string) {

		// Step 1: Replace quoted strings with placeholders
		$modifiedString = $this->removeQuotedStrings($string);

		// Step 2: Replace brackets with carets
		$modifiedString = preg_replace(
			"/(".$this->patterns['functionName'].")\((.*?)\)/s",
			'$1^^$3^^',
			$modifiedString
		);

		// Step 3: Put the quoted strings back
		return $this->restoreQuotedStrings($modifiedString);
	}


	protected function removeCarets($string){

		return preg_replace(
			"/(".$this->patterns['functionName'].")\^\^(.*?)\^\^/s",
			'$1($3)',
			$string
		);
	}

	protected function splitStatements($value){

		return preg_split(
			$this->patterns["andOrSurrSpace"],
			trim($value),
			-1,
			PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE
		);
	}

	protected function push(){

		if ($this->buffer_start !== null) {

			// extract string from buffer start to current position
			$buffer = substr($this->string, $this->buffer_start, $this->position - $this->buffer_start);

			// clean buffer
			$this->buffer_start = null;

			// throw token into current scope
			$statementsArray = $this->splitStatements(
				$this->removeCarets(
					$buffer
				)
			);

			if (count($statementsArray)){
				$this->current = array_merge($this->current, $statementsArray);
			}
		}
	}

	// Tease apart parenthesis groups
	protected function parseStatements($string){
		return $this->parse($string);
	}

	// walk over a multidimensional array recursively, applying a callback on non-array values
	protected function traverseStatements(&$array, $callback, $level = 0){

		$result = false;

		foreach ($array as $index => &$value){

			// if we are on a parenthesis group, get the result of the group
			if (is_array($value)){

				$result = $this->traverseStatements($value, $callback, (++$level));

				if (Helper::$doDebug){
					Helper::debug('Group result ', array(
						'result' => $result,
						'group' => $value
					));
				}

			}

			// get the result of the individual statement
			else {

				// simply move onto the next statement if we're on and/or
				if ($value === 'and' || $value === 'or' || $value === 'AND' || $value === 'OR'){
					continue;
				}

				// check result
				$result = $this->evaluateStatement($value);
				$resultString = $result
					? 'true'
					: ($result === null ? 'null' : 'false');

				// now that we have processed the logical statement, add some debug info
				$array[$index].= ' ['.$resultString.']';

				if (Helper::$doDebug){
					Helper::debug('Statement result ('.$value.'): '.$result);
				}

			}

			// look for the following and/or and possibly return early
			$nextIndex = $index + 1;
			$nextStatement = isset($array[$nextIndex]) ? $array[$nextIndex] : false;

			if (
				!$nextStatement ||
				($result && ($nextStatement === 'or' || $nextStatement === 'OR')) ||
				(!$result && ($nextStatement === 'and' || $nextStatement === 'AND'))
			){

				// mark final result
				if (!is_array($array[$index])){
					$array[$index].= '[result]';
				}

				return $result;
			}

		}

		return $result;
	}

	protected function parseStatement($string, $doReplacement = false){

		// temp remove quoted strings
		if ($doReplacement){
			$string = $this->removeQuotedStrings($string);
		}

		preg_match(
			"/" . implode('|', $this->patterns['expressions']) . "/s",
			$string,
			$matches
		);

		/*if (Helper::$doDebug){
			Helper::debug('Parse Statement pattern / string', array(
				'pattern' => $pattern,
				'string' => $string,
				'$matches' => $matches
			));
		}*/

		// restore quoted strings
		if ($doReplacement && !empty($matches['parameter'])) {
			$matches[0] = $this->restoreQuotedStrings($matches[0]);
			$matches[3] = $matches['parameter'] = $this->restoreQuotedStrings($matches['parameter']);
		}

		return $matches;
	}

	protected function statementResult($parsedStatement){

		if (Helper::$doDebug){
			Helper::debug('Statement parsed in callback', $parsedStatement);
		}
		
		$result = false;

		// query any GET values
		$global = isset($parsedStatement['global']) ? $parsedStatement['global'] : false;
		if ($global){

			$key = $parsedStatement['key'];

			if (!$key){
				return false;
			}

			if ($global == 'GET'){
				$result = isset($_GET[$key]) ? $_GET[$key] : false;
			}

		}

		// query any allowed function results
		$functionName = isset($parsedStatement['functionName']) ? $parsedStatement['functionName'] : false;
		if ($functionName){

			// bail if the function isn't allowed, or doesn't exist
			if (
				!in_array($functionName, $this->allowedFunctions) || !function_exists($functionName)
			    //(!function_exists($functionName) && !function_exists( 'Microthemer\\' .$functionName))
			){
				if (Helper::$doDebug){
					Helper::debug('Disallowed or does not exist:', [
						'$functionName' => $functionName,
						'not allowed' => !in_array($functionName, $this->allowedFunctions),
						'does not exist' => !function_exists($functionName)
					]);
				}

				return null;
			}

			$parameter = isset($parsedStatement['parameter']) ? $parsedStatement['parameter'] : '';
			$parameters = $parameter
				? preg_split("/\s*,\s*/", $parameter)
				: array();

			if (Helper::$doDebug){
				Helper::debug('Parameter Strings', $parameters);
			}

			// native PHP functions cannot be called with call_user_func_array (as not user function)
			if ($functionName === 'isset'){

				// we have a parameter
				if (isset($parameters[0])){

					$parsedParameter = $this->parseStatement($parameters[0]);
					$globalParameter = isset($parsedParameter['global']) ? $parsedParameter['global'] : false;

					// we have a global parameter
					if ($globalParameter){

						$key = $parsedParameter['key'];

						if (!$key){
							return false;
						}

						if ($globalParameter == 'GET'){
							$result = isset($_GET[$key]);
						}
					}
				}

				// no parameter, so false
				else {
					$result = null;
				}
			}

			// run function
			else {

				$cacheKey = $functionName . '('.$parameter.')';

				// draw from function call cache if available
				if (isset(Logic::$cache['functions'][$cacheKey])){

					$result = Logic::$cache['functions'][$cacheKey];

					if (Helper::$doDebug){
						Helper::debug('Pulling function result from cache:', array(
							'function' => $cacheKey,
							'result' => $result,
						));
					}

				}

				else {

					// convert parameter strings to PHP result
					foreach ($parameters as $i => $parameterString){

						$parsedParameter = $this->parseStatement($parameterString);

						if (!$parsedParameter){
							if (Helper::$doDebug){
								Helper::debug('Cannot parse $parameterString: ' . $parameterString);
							}
						} else {
							$parameters[$i] = $this->statementResult($parsedParameter);
						}
					}

					if (Helper::$doDebug){
						Helper::debug('Parameters converted', $parameters);
					}

					$result = call_user_func_array(
						$functionName,
						$parameters
					);

					Logic::$cache['functions'][$cacheKey] = $result;
				}

			}

			// reverse result if negation has been used e.g. !is_page(20)
			$negation = isset($parsedStatement['negation']) && $parsedStatement['negation'];

			if ($negation){
				$result = !$result;
			}

		}

		// boolean
		$boolean = isset($parsedStatement['boolean']) ? $parsedStatement['boolean'] : false;
		if ($boolean){
			$result = $boolean === 'true';
		}

		// number
		$number = isset($parsedStatement['number']) ? $parsedStatement['number'] : false;
		if ($number){
			$result = strpos($number, '.') === false ? intval($number) : floatval($number);
		}

		// string
		$string = isset($parsedStatement['string']) ? $parsedStatement['string'] : false;
		if ($string){
			$result = str_replace(array('"', "'"), '', $string);
		}

		return $result;
	}

	protected function evaluateStatement($value){

		// split the statement on the comparison (e.g. ===)
		$results = preg_split(
			$this->patterns["comparison"],
			trim($value),
			-1,
			PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE
		);

		$comparison = false;

		if (Helper::$doDebug){
			Helper::debug('Split on any comparison', $results);
		}

		foreach ($results as $index => $part){

			if ($index === 1){
				$comparison = $part;
			}

			// process the result of the statement
			else {

				// draw from statement cache if available
				if (isset(Logic::$cache['statements'][$part])){

					$results[$index] = Logic::$cache['statements'][$part];

					if (Helper::$doDebug){
						Helper::debug('Pulling statement result from cache:', array(
							'statement' => $part,
							'result' => $results[$index],
						));
					}

				}

				// statement needs to be run
				else {
					$parsedStatement = $this->parseStatement($part, true);

					if (!$parsedStatement) {
						if (Helper::$doDebug){
							Helper::debug( 'Cannot parse statement: ' . $part);
						}

						$results[$index] = null;
					} else {
						if (Helper::$doDebug){
							Helper::debug( 'Could parse statement: ' . $part, $parsedStatement);
						}

						$results[$index] = $this->statementResult($parsedStatement);
					}

					// cache result so evaluation of the same statement only happens once
					Logic::$cache['statements'][$part] = $results[$index];
				}

			}
		}

		if (Helper::$doDebug){
			Helper::debug('Processed statement results:', $results);
		}

		// return comparison if defined and we have two values
		if ($comparison && count($results) > 2){

			$a = $results[0];
			$b = $results[2];

			switch ($comparison) {
				case '==':
					return $a == $b;
				case '===':
					return $a === $b;
				case '!=':
					return $a != $b;
				case '!==':
					return $a !== $b;
				case '>':
					return $a > $b;
				case '<':
					return $a < $b;
				case '>=':
					return $a >= $b;
				case '<=':
					return $a <= $b;
				default:
					return false;
			}

		}

		// otherwise simply return the first result
		return isset($results[0])
					? $results[0]
					: null;

	}

	public function parse($string){

		if (!$string) {
			return array();
		}

		$this->current = array();
		$this->stack = array();
		$quotesOpen = array();

		// use caret ^^ placeholder for function parenthesis we don't create an extra group
		// and replace && with and for simpler regex/logic
		$string = $this->normaliseAndOr(
			$this->addCarets(
				trim($string)
			)
		);

		$this->string = $string;
		$this->length = strlen($this->string);

		if (Helper::$doDebug){
			Helper::debug('About to parse string', array('string' => $this->string, 'length' => $this->length));
		}

		// look at each character
		for ($this->position=0; $this->position < $this->length; $this->position++) {

			$char = $this->string[$this->position];
			$isInsideQuotes = count($quotesOpen);

			switch ($char) {
				case '(':
					if (!$isInsideQuotes){
						$this->push();
						// push current scope to the stack and begin a new scope
						$this->stack[] = $this->current;
						$this->current = array();
					}
					break;

				case ')':
					if (!$isInsideQuotes){
						$this->push();
						// save current scope
						$t = $this->current;
						$this->current = array_pop($this->stack);

						// add just saved scope to current scope
						if (count($t)){

							// get the last scope from stack
							$this->current[] = $t;
							break;
						}
					}

					if (Helper::$doDebug){
						Helper::debug('It is )', array('$isInsideQuotes' => $isInsideQuotes, '$char' => $char, '$this->position', $this->position, '$this->current', $this->current));
					}

					break;

				default:
					// remember the offset to do a string capture later
					// could've also done $buffer .= $string[$position]
					// but that would just be wasting resources…
					if ($this->buffer_start === null) {
						$this->buffer_start = $this->position;
					}

					// flag if we are inside quotes
					if ($char === "'" || $char === '"'){
						$altQuote = $char === '"' ? "'" : '"';
						if (isset($quotesOpen[$char])){
							unset($quotesOpen[$char]);
						} else {
							// if we are not inside the other type of quote (which would escape it)
							if (!isset($quotesOpen[$altQuote])){
								$quotesOpen[$char] = 1;
							}
						}
					}

			}
		}

		// catch any trailing text
		if ($this->buffer_start <= $this->position) {
			$this->push();
		}

		return $this->current;
	}

	public function evaluate($statementsArray, $string, $test, $fileExists = null){

		$result = $this->traverseStatements($statementsArray, 'evaluateStatement');

		if (Helper::$doDebug){
			Helper::debug('Debug', array(
				'result' => $result,
				'load' => $result ? 'Yes' : 'No',
				'logic' => $string,
				'num_statements' => count($statementsArray, COUNT_RECURSIVE),
				'analysis' => '<pre>'.print_r($statementsArray, 1).'</pre>',
				//'cache' => Logic::$cache
			), false);
		}


		return !$test
			? $result
			: array(
				'fileExists' => $fileExists,
				'empty' => !$fileExists,
				'blocksOnly' => $result === 'blocksOnly',
				'resultIsString' => is_string($result) ? $result : false, // e.g. blocksOnly
				'result' => $result,
				'resultString' => $result
					? 'true'
					: ($result === null ? 'null' : 'false'),
				'load' => $result ? 'Yes' : 'No',
				'logic' => $string,
				'num_statements' => $this->countNumStatements($statementsArray),
				'analysis' => '<pre>'.print_r($statementsArray, 1).'</pre>'
			);

	}

	public function countNumStatements($array){

		foreach ($array as $value) {

			if ( is_array( $value ) ) {
				$this->countNumStatements($value);
			} else {
				if ($value === 'and' || $value === 'or' || $value === 'AND' || $value === 'OR'){
					continue;
				}
				++$this->statementCount;
			}
		}

		return $this->statementCount;
	}

	public function result($string, $test = false, $fileExists = null){

		if (Helper::$doDebug){
			Helper::debug('String received: ' . $string);
		}

		$result = null;
		$error = false;
		$statementsArray = $this->parseStatements($string);

		if (Helper::$doDebug){
			Helper::debug('statementsArray', $statementsArray);
		}

		// draw from full conditional statements string cache if available
		if (isset(Logic::$cache['conditions'][$string])){

			$result = Logic::$cache['conditions'][$string];

			if (Helper::$doDebug){
				Helper::debug('Pulling condition result from cache:', array(
					'condition' => $string,
					'result' => $result,
				));
			}

		}

		else {

			// Running a function could result in an error which we should capture but suppress
			try {
				$result = $this->evaluate($statementsArray, $string, $test, $fileExists);
			}

			// 'Throwable' is executed in PHP 7+, but ignored in lower PHP versions
			catch (\Throwable $t) {
				$error = $t->getMessage();
			}

			// 'Exception' is executed in PHP 5, this will not be reached in PHP 7+
			catch (\Exception $e) {
				$error = $e->getMessage();
			}
		}


		// return error result if a PHP exception occurs - this should fail silently
		if ($error){

			if ($test){

				$result = array(
					'error' => $error,
					'result' => null,
					'resultString' => 'null',
					'load' => 'No',
					'logic' => $string,
					'num_statements' => 0,
					'analysis' => 'Your condition generated a PHP error. The folder will not load until you fix it: ' . '<br /><br /><b><pre>' . $error . '</pre></b>'
				);
			}

			// the folder just won't load, but no errors will display on the frontend
			else {
				$result = null;
			}

		}

		// cache result in case same condition is used in another folder
		Logic::$cache['conditions'][$string] = $result;

		return $result;

	}

	// Integrations

	// Bricks Templates
	public static function getBricksTemplateIds($template_id, &$template_ids, $content_type = 'nested'){

		if (is_numeric($template_id) && $template_id > 0
		    && !isset($template_ids[$template_id])
		    && !\Bricks\Database::is_template_disabled($content_type)) {

			$template_ids[intval($template_id)] = $content_type;
			$meta_key = $content_type === 'header'
				? BRICKS_DB_PAGE_HEADER
				: ($content_type === 'footer'
					? BRICKS_DB_PAGE_FOOTER
					: BRICKS_DB_PAGE_CONTENT);
			$bricks_data = get_post_meta( $template_id, $meta_key, true );

			if (is_array($bricks_data)){
				foreach($bricks_data as $item){
					if (!empty($item['settings']['template'])){
						Logic::getBricksTemplateIds($item['settings']['template'], $template_ids);
					}
				}
			}
		}

	}

	public static function getGutenbergTemplateIds($source, &$id, &$template_ids) {

		global $post, $pagenow;

		$map = null;
		$startingPoints = array();
		$isFSE = $pagenow === 'site-editor.php';
		$themeSlug = get_stylesheet();
		$id = Helper::removeRedundantThemePrefix($id, $themeSlug);
		$currentTemplate = '';
		$urlParams = Helper::extractUrlParams($themeSlug);

		// Get parameters in case we are in the FSE view
		extract($urlParams);

		// Determine the starting points and their trails
		if ($isFSE) {
			// FSE falls back to the home page if no $postId is set (for overview pages)
			// So we need to grab the post for the single page if set
			$homePageFallback = false;
			if (!$postId){

				// single page assigned to the front
				if (get_option('show_on_front') === 'page'){
					$postId = get_option('page_on_front');
					$post = get_post($postId);
					$homePageFallback = true;
					Helper::debug('FSE fallback to home page (single page): '.$currentTemplate);
				}

				// Recent posts page
				else {
					$currentTemplate = 'home';
					Helper::debug('FSE fallback to blog home: '.$currentTemplate);
				}

			}

			// single template and page views
			if ($fseType === $source && $postId == $id){ // wp_navigation, wp_template_part, wp_pattern
				$template_ids[$id] = 'blocksOnly';
			} if ($fseType === 'wp_template'){
				$currentTemplate = $postId;
			} elseif($fseType === 'page' || ($postId && $homePageFallback)){
				if (!$homePageFallback){
					$post = get_post($postId);
				}
				$currentTemplate = Helper::getTemplateFromPostId($postId, $post);
				Helper::debug('FSE page template: '.$currentTemplate);
			}

		}

		// non FSE
		else {
			// regular Gutenberg editor
			if ($pagenow === 'post.php' && isset($_GET['post'])){
				$postId = $_GET['post'];
				$post = get_post($postId);
				$currentTemplate = Helper::getTemplateFromPostId($postId, $post);
			}

			// any other front or admin page
			else {
				$currentTemplate = Helper::getCurrentTemplateSlug();
			}

		}

		if ($source === 'wp_template') {
			if ($currentTemplate == $id){ // loose, so user can use quotes e.g. "404"
				if (Helper::$doDebug){
					Helper::debug('Found wp_template: ' . $id);
				}
				$template_ids[$id] = 'blocksOnly';
			}
		} else {
			// we need to check the cached map
			$map = Helper::getTemplateMap($themeSlug, Logic::$cache);

			// use URL param as starting point for FSE nav/template/part/etc
			if ($isFSE && $map && $fseType && $postId
			    && isset($map[$fseType]) && isset($map[$fseType][$postId]) ){
				$startingPoints[] = [
					'dataArray' => $map[$fseType][$postId],
					'trail' => $fseType . '.' . $postId
				];
			}

			// we should extract any synced pattern references from the post content,
			// so they can be checked in the map too
			if ($post instanceof \WP_Post) {
				$content = $post->post_content;

				// allow for the live post override
				if (Helper::isLiveContentTest()) {
					$content = Common::$live_post_content;
				}

				$matches = Helper::extractSyncedPatterns($content);
				$types = $matches[1];
				$syncedPatternIds = $matches[2];

				if ($syncedPatternIds && count($syncedPatternIds)){
					foreach ($syncedPatternIds as $i => $syncedPatternId) {
						$type = $types[$i];
						$key = $type === 'block' ? 'wp_pattern' : 'wp_' . $type;

						if (isset($map[$key][$syncedPatternId])) {
							$startingPoints[] = [
								'dataArray' => $map[$key][$syncedPatternId],
								'trail' => $key . '.' . $syncedPatternId
							];

							if ($source === $key && $id == $syncedPatternId) {
								$template_ids[$syncedPatternId] = 'blocksOnly';
							}
						}
					}
				}

			}

			if ($currentTemplate) {
				if (isset($map['wp_template'][$currentTemplate])) {
					$startingPoints[] = [
						'dataArray' => $map['wp_template'][$currentTemplate],
						'trail' => 'wp_template.' . $currentTemplate,
					];
				}
			}

			if (Helper::$doDebug){
				Helper::debug('$startingPoints', array(
					'id' => $id,
					'postId' => $postId,
					'$startingPoints' => $startingPoints,
					'currentTemplate' => $currentTemplate,
					'urlParams' => $urlParams
				));
			}

			foreach ($startingPoints as $startingPoint) {
				$visited = array();
				Logic::checkGutenbergMap(
					$startingPoint['dataArray'], $map, $id, $template_ids, $source, $visited, 0, 50, $startingPoint['trail']
				);

				if (!empty($template_ids[$id])) {
					break;
				}
			}
		}
	}

	public static function checkGutenbergMap(
		$array, $map, $id, &$template_ids, $source, &$visited = array(), $depth = 0, $maxDepth = 50, $trail = ''
	) {
		Helper::debug("Run checkGutenbergMap (" . $depth . "): " . $id . " Trail: " . $trail);

		// Stop if maximum recursion depth is reached
		if ($depth > $maxDepth) {
			if (Helper::$doDebug) {
				Helper::debug("Maximum recursion depth reached in checkGutenbergMap");
			}
			return;
		}

		// Use trail as the unique identifier for the current node
		if (isset($visited[$trail])) {
			if (Helper::$doDebug) {
				Helper::debug("Already visited node: " . $trail, $array);
			}
			return;
		}

		// Mark the current node as visited
		$visited[$trail] = true;
		if (Helper::$doDebug) {
			Helper::debug("Mark as visited: " . $trail, $array);
		}

		// Sources to process
		$sources = ['wp_template_part', 'wp_pattern', 'wp_navigation'];

		foreach ($sources as $itemSource) {
			if (!empty($array[$itemSource])) {
				$subArray = $array[$itemSource];

				if (is_array($subArray)) {
					foreach ($subArray as $itemId => $enabled) {
						$newTrail = $trail . ($trail ? '.' : '') . $itemSource . '.' . $itemId;

						if (Helper::$doDebug) {
							Helper::debug("Check: " . Helper::maybeMakeNumber($itemId) . ' = ' . $id, $itemSource);
						}

						// Match the current source and ID
						if ($itemSource === $source && Helper::maybeMakeNumber($itemId) == $id) {
							$template_ids[$id] = 'blocksOnly';
						}

						// Recursively check nested structures
						elseif (!empty($map[$itemSource][$itemId])) {
							self::checkGutenbergMap(
								$map[$itemSource][$itemId],
								$map,
								$id,
								$template_ids,
								$source,
								$visited,
								$depth + 1,
								$maxDepth,
								$newTrail
							);
						}
					}
				}
			}
		}
	}



}

/*
 * Custom (namespaced) microthemer functions for use with logical conditions
 * These fill gaps in WordPress API and can support integrations with other plugins
 * IMPORTANT - all params must be optional to prevent user from generating a fatal error (extra params OK it seems)
 */

// check what admin page the user is on - allow the page name or an id
function is_admin_page($pageNameOrId = false){

	global $post;

	return is_admin() && !$pageNameOrId

	       // e.g. edit.php
	       || (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === $pageNameOrId)

	       // e.g. 123
	       || (is_numeric($pageNameOrId) && isset($_GET['post']) && intval($_GET['post']) === intval($pageNameOrId))

	       // e.g. my-post-slug
	       || (!is_numeric($pageNameOrId) && isset($post->post_name) && $post->post_name === $pageNameOrId);
}

// check what page the user is on (frontend or admin)
function is_post_or_page($id = null){

	$globalOrFrontMatch = ($id === 'front' && Helper::isFrontOrFallback()) ||
	                      ($id === 'global' && (is_public() || Helper::isBlockAdminPage('global')));
	
	return is_public() && (is_page($id) || is_single($id) || $globalOrFrontMatch)
		? true
		: (is_admin() && (Helper::isBlockAdminPage($id) || $globalOrFrontMatch )
			? 'blocksOnly'
			: false);
}

// check what admin page the user is on
function is_public(){
	return !is_admin();
}

// check what admin page the user is on
function is_public_or_admin($postOrPageId = null){
	return !$postOrPageId
	       || ( !is_admin() && is_post_or_page($postOrPageId) )
	       || is_admin_page($postOrPageId);
}

function query_admin_screen($key = null, $value = null){

	if (!function_exists('get_current_screen')){
		return false;
	}

	$current_screen = get_current_screen();

	return ($key === null || isset($current_screen->$key))
	       && ($value === null || $current_screen->$key === $value);
}

// check if the user has a particular role or user id
function user_has_role($roleOrUserId = null){
	return is_user_logged_in() && $roleOrUserId === null ||
	       wp_get_current_user()->roles[0] === $roleOrUserId ||
	       (is_numeric($roleOrUserId) && intval($roleOrUserId) === get_current_user_id());
}

// check if a theme or plugin is active, slug is the directory slug e.g. 'microthemer' or 'divi'
function is_active($item = null, $slug = null){
	switch ($item) {
		case 'plugin':
			$active_plugins = get_option('active_plugins', array());
			foreach($active_plugins as $path){
				if (strpos($path, $slug) !== false){
					return true;
				}
			}
			return is_plugin_active_for_network($slug);
		case 'theme':
			$theme = wp_get_theme();
			return $theme->get_stylesheet() === $slug;
		default:
			return false;
	}
}

// check if the current url matches a path
function match_url_path($value = null, $regex = false){
	$urlPath = $_SERVER['REQUEST_URI'];
	return $regex
		? preg_match('/'.$value.'/', $urlPath)
		: strpos($urlPath, $value) !== false;
}

function has_template($source = null, $id = null, $label = null){

	global $post;

	// todo maybe this would work if Logic::$cache[$source][$id]['template_ids'] - try later
	/*$cache = !empty(Logic::$cache[$source]['template_ids'])
		? Logic::$cache[$source]['template_ids']
		: false;*/
	$template_ids = array(); // $cache ?: array();
	$returnType = true;

	if (!$source || !$id){
		return false;
	} /*if ($cache){
		return !empty($cache[$id]) ? $cache[$id] : false;
	}*/

	// gather template_ids
	switch ($source) {

		case 'bricks':
			if ($post && method_exists('\Bricks\Helpers', 'render_with_bricks')){
				if ( \Bricks\Helpers::render_with_bricks($post->ID) ) {
					foreach (\Bricks\Database::$active_templates as $content_type => $template_id){
						Logic::getBricksTemplateIds($template_id, $template_ids, $content_type);
					}
				}
			}
			break;

		case 'wp_template':
		case 'wp_template_part':
		case 'wp_pattern':
		case 'wp_navigation':
			$returnType = 'blocksOnly';
			//echo 'gothere' . $source . $id . '<br/>';
			Logic::getGutenbergTemplateIds($source, $id,$template_ids);
			break;
	}

	// cache template analysis for the source - no this does not work.
	//Logic::$cache[$source]['template_ids'] = $template_ids;

	return !empty($template_ids[$id]) ? $returnType : false;
}