<?php

namespace Microthemer\Content;

use \Microthemer\TimerTrait;

/*
 * HTML
 *
 * HTML editor that works on the final DOM string before being served to the browser
 */

class HTML {

	use TimerTrait;

	var $contentClass;
	var $assetClass;
	var $published;
	var $logs = array();
	var $doc;
	var $xpath;
	var $devMode = false;

	public static $attributes = array(
		'accept', 'accept-charset', 'accesskey', 'action', 'allow', 'alpha', 'alt', 'as', 'async', 'autocapitalize', 'autocomplete', 'autoplay', 'capture', 'charset', 'checked', 'cite', 'class', 'colorspace', 'cols', 'colspan', 'content', 'contenteditable', 'controls', 'coords', 'crossorigin', 'csp', 'datetime', 'decoding', 'default', 'defer', 'dir', 'dirname', 'disabled', 'download', 'draggable', 'enctype', 'enterkeyhint', 'elementtiming', 'for', 'form', 'formaction', 'formenctype', 'formmethod', 'formnovalidate', 'formtarget', 'headers', 'height', 'hidden', 'high', 'href', 'hreflang', 'http-equiv', 'id', 'integrity', 'inputmode', 'ismap', 'itemprop', 'kind', 'label', 'lang', 'loading', 'list', 'loop', 'low', 'max', 'maxlength', 'media', 'method', 'min', 'minlength', 'multiple', 'muted', 'name', 'novalidate', 'open', 'optimum', 'pattern', 'ping', 'placeholder', 'playsinline', 'poster', 'preload', 'readonly', 'referrerpolicy', 'rel', 'required', 'reversed', 'role', 'rows', 'rowspan', 'sandbox', 'scope', 'selected', 'shape', 'size', 'sizes', 'slot', 'span', 'spellcheck', 'src', 'srcdoc', 'srclang', 'srcset', 'start', 'step', 'style', 'tabindex', 'target', 'title', 'translate', 'type', 'usemap', 'value', 'width', 'wrap'
	);

	public function __construct(&$contentClass, $devMode = false) {
		$this->contentClass = $contentClass;
		$this->assetClass = $this->contentClass->assetClass;
		$this->published = $this->assetClass->draft ? 0 : 1;
		$this->devMode = $devMode;
	}

	function log($message, $data = null, $type = 'error'){
		$this->logs[] = array_merge(
			array(
				'message' => $message,
				'type' => $type,
				'data' => $data
			),
		);
	}

	// max-width < 980 (exclude desktop) min-width >= 980 (exclude mobile)
	function excludeMediaQuery($mediaQuery, $userHasMobile){

		if (empty($this->assetClass->preferences['m_queries'])){
			return false;
		}

		// Look up the media query config array
		// (we may have a different system in future that will make more use of User MQ config)
		$mQueries = $this->assetClass->preferences['m_queries'];

		foreach ($mQueries as $mq) {

			if (isset($mq['query']) && $mq['query'] === $mediaQuery) {

				$min = isset($mq['min']) ? (int)$mq['min'] : 0;
				$max = isset($mq['max']) ? (int)$mq['max'] : 0;

				// Exclude desktop-only media queries when on mobile
				if ($userHasMobile && $min >= 980) {
					return true;
				}

				// Exclude mobile-only media queries when on desktop
				if (!$userHasMobile && $max > 0 && $max < 980) {
					return true;
				}

				// Don't exclude if the query spans both (e.g., 768-1199)
				break;
			}
		}

		return false;
	}
	// Iterate mods, if they are present
	// Only now will $modList be populated by the extraction script
	// As the class property passed by reference has now been updated
	function iterateMods(&$modList, &$html){

		// If there are no server-side mods, and we do not need to print debug info
		if (!count($modList) && !$this->contentClass->debugAmends){

			// Return zero, if timing
			if ($this->contentClass->timeAmends){
				$this->contentClass->returnServerTiming(0, $html);
			}

			return;
		}

		$this->contentClass->startT('all_server_side_html_changes');

		// Parse the document
		$this->doc = new \DOMDocument();
		$this->doc->loadHTML($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$this->xpath = new \DOMXPath($this->doc);

		// pull lazy loaded content out of DOM and store in DB
		$lazy = array(
			'slugs' => array(),
			'store' => array()
		);

		// cache user's device type for adaptive resource loading / changes
		$userHasMobile = wp_is_mobile();

		// Iterate each mod
		foreach ($modList as $data){

			$xpathSelector = $data[0];
			$mod = $data[1];
			$aspect = $data[2];
			$newValue = $data[3];
			$sectionSlug = $data[4];
			$selectorCode = $data[5];
			$mediaQuery = $data[6];

			// Skip if media query does not match < 980px (mobile) or >= 980 (desktop)
			if ($this->excludeMediaQuery($mediaQuery, $userHasMobile)){
				continue;
			}

			$nodes = $this->xpath->query($xpathSelector);

			try {
				$this->applyMod(
					$nodes, $mod, $aspect, $newValue, $lazy
				);
			} catch (\Exception $e) {

				if ($this->contentClass->isEditing){
					$this->log(
						'Error with modification: ' . $e->getMessage(),
						array(
							'folder' => $sectionSlug,
							'selector' => $selectorCode,
							'modification' => $mod,
						)
					);
				}
			}
		}

		// Save extracted lazyLoad content in the Database and add data for deferred loading
		$footerJS = '';
		if ($this->maybeStoreLazyContent($lazy)){
			$footerJS.= '
			<script class="fetch-lazy-content">
			    window.amender._ajax_url = "' . admin_url('admin-ajax.php') . '";
				window.amender._ajax_nonce = "' . wp_create_nonce('tvra_request') . '";
				window.amender.lazy = ' . json_encode(array_keys($lazy['store'])) . ';
				'.file_get_contents($this->assetClass->rootDir . 'mt/js/amender/mt-lazy.js').'
			</script>'. "\n";
		}
		$body = $this->xpath->query('.//body');
		if ($footerJS){
			$this->applyMod($body, array('action' => 'append'), 'html', $footerJS, $lazy);
		}

		// Stop server-side timer
		$this->contentClass->endT('all_server_side_html_changes');

		// If we are timing server-side changes, present json
		if ($this->contentClass->timeAmends){
			$this->contentClass->returnServerTiming(
				$this->contentClass->profiler['all_server_side_html_changes']['total_time'],
				$html,
				$this->contentClass->memoryProfiler
			);
		}

		// Regular page view
		else {
			// Display debug data / error logs to administrators
			if ($this->contentClass->isEditing && ($this->contentClass->debugAmends || count($this->logs))){

				$debug = new Debug($this->contentClass);
				$display = $debug->generateHTML(
					$this->contentClass->debugAmends,
					$modList,
					$this->contentClass->profiler,
					$this->contentClass->memoryProfiler,
					$this->contentClass->clientSide,
					$this->logs,
					$lazy
				);

				if ($display){
					$body = $this->xpath->query('.//body');
					$this->applyMod($body, array('action' => 'append'), 'html', $display, $lazy);
				}

			}

			// save the object back to an HTML string
			$html = $this->doc->saveHTML();

			// Free memory
			unset($this->xpath, $this->doc);
			ContentHelper::cleanupMemory();

		}

	}

	function maybeStoreLazyContent(&$lazy) {

		if (count($lazy['store'])) {

			global $wpdb;

			// Define the table name
			$content_table = $wpdb->prefix . "micro_content";

			// Initialize an array to hold the values for the query
			$values = array();
			$placeholders = array();

			// Loop through each lazy_id in the store
			foreach ($lazy['store'] as $lazy_id => $content_array) {

				// JSON encode the content
				$json_content = json_encode($content_array);

				// Collect the placeholders and values for each row
				$placeholders[] = "(%s, %s, %s, %d)";
				$values[] = $lazy_id;          // Use lazy_id as the slug
				$values[] = $json_content;     // JSON-encoded content
				$values[] = 'lazy';            // Type is 'lazy'
				$values[] = 0;                 // Published is 0 (since it's part of the primary key)
			}

			// Combine the placeholders into a single query
			$query = "INSERT INTO $content_table (slug, content, type, published) 
			VALUES " . implode(", ", $placeholders) .
			         " ON DUPLICATE KEY UPDATE 
			            content = VALUES(content), 
			            modified_at = NOW(),
			            published = VALUES(published)";

			// Execute the query with all the values
			$wpdb->query(
				$wpdb->prepare($query, ...$values)
			);

			return true;
		}

		return false;
	}
	
	function aspectIsAttribute($value){

		if ($value === 'attributesString' || in_array($value, HTML::$attributes, true)) {
			return true;
		}

		// Prefix-based pattern match
		return preg_match('/^(data|x|hx|ng|v|aria)-[^\s"\'<>\/=]+$/', $value) === 1;
	}

	// Check if the HTML tag has no leading or trailing text
	function isPureTag($content) {
		return preg_match('/^\s*</', $content) && preg_match('/>\s*$/', $content);
	}

	// To make undo operations manageable (on client side), wrap leading/trailing loose text with a tag
	// This is done here for parity with frontend editing
	function maybeWrapWithTag($content) {

		// Allow for possibility $content is a node.
		if (!is_string($content)) {
			return $content;
		}

		$inlineElements = [
			"span", "a", "strong", "em", "img", "br", "i", "b", "u", "small", "mark",
			"q", "cite", "code", "kbd", "var", "abbr", "time", "sub", "sup",
			"button", "label", "input", "textarea", "select", "option"
		];

		$pattern = '/^\s*(<\s*([a-zA-Z0-9]+)[^>]*>(?:([\s\S]*?)<\s*\/\s*\2\s*>|\s*)?)\s*$/';

		preg_match_all($pattern, $content, $tagMatch);

		// Check if there were any matches
		$hasMatches = !empty($tagMatch[0]);

		// Use the updated logic for $requiresWrapper
		$requiresWrapper = !$hasMatches || !$this->isPureTag($content);

		if (!$requiresWrapper) {
			return $content;
		}

		// Determine the tag type based on the matched tag or default to 'div'
		$tag = ($hasMatches && in_array($tagMatch[2][0], $inlineElements)) ? 'span' : 'div';

		return '<' . $tag . '>' . $content . '</' . $tag . '>';
	}

	function insert($node, $action, $aspect, $newValue){

		// add tag if not already one, we always insert content within a HTML tag
		$newValue = $this->maybeWrapWithTag($newValue);

		switch($action) {
			case 'prepend':
				$node->prepend($this->createNodeFromInput($newValue, $node));
				break;
			case 'add':
			case 'append':
				$node->appendChild($this->createNodeFromInput(
					$newValue, null, false
				));
				break;
			case 'insertBefore':
				$node->before($this->createNodeFromInput($newValue, $node));
				break;
			case 'insertAfter':
				$node->after($this->createNodeFromInput($newValue, $node));
				break;
		}
	}

	function lazyLoad($node, $lazy_id, &$lazy){
		$replacedNode = $node->parentNode->replaceChild(
			$this->createNodeFromInput(
				$this->lazyPlaceholder($lazy_id, $node), $node, false
			),
			$node
		);
		$lazy['store'][$lazy_id][] = $this->doc->saveHTML($replacedNode);
	}

	function replace($node, $action, $aspect, $newValue, $isTextNode, $find){

		//$this->log('replace', [$action, $aspect, esc_html($newValue), $isTextNode, $find]);

		// text / inner HTML
		if ($isTextNode || $aspect === 'innerHTML'){
			$detached = $this->detachChildNodes($node, $action);

			if ($action === 'replaceSubstring'){
				$newValue = $this->substringReplace(
					$detached['innerHTML'], $find, $newValue
				);
			}

			$node->appendChild(
				$this->createNodeFromInput($newValue, $node, false)
			);
		}

		// outerHTML
		else {

			if ($action === 'replaceSubstring') {
				$newValue = $this->substringReplace(
					$node->ownerDocument->saveHTML($node), $find, $newValue
				);
			}

			$node->parentNode->replaceChild(
				$this->createNodeFromInput($newValue, $node),
				$node
			);
		}
	}

	function remove($node, $action, $aspect, $isTextNode){
		if ($isTextNode || $aspect === 'innerHTML'){
			$this->detachChildNodes($node, $action);
		} else {
			$node->parentNode->removeChild($node);
		}
	}
	
	function applyMod(&$nodes, $mod, $aspect, $newValueForNodes, &$lazy){

		if (!$nodes){
			return false;
		}

		$action = isset($mod['action']) ? $mod['action'] : null;
		$find = isset($mod['find']) ? $mod['find'] : null;
		$newValIsEmpty = $newValueForNodes === '';
		$tag = isset($mod['tag']) ? $mod['tag'] : ''; // div
		$lazy_id = isset($mod['lazy_id']) ? $mod['lazy_id'] : null;
		$removalDisabled = empty($mod['enable']) && ($action === 'remove' || $action === 'lazyLoad');

		// Bail if no action or replacement content is missing
		if (!$action
		    || ($action === 'replace' && $newValueForNodes === '')
		    || $removalDisabled
		    || ($action === 'replaceSubstring' && $find === '')){
			return false;
		}

		foreach ($nodes as $node){

			// reset default $newValue for each node to prevent accumulated changes within the loop
			// e.g. for string replace adjustments to $newValue
			$newValue = $newValueForNodes;

			// Aspect
			if (!empty($aspect)){

				$isTextNode = $aspect === 'text';

				// Handle an attribute
				if ($this->aspectIsAttribute($aspect)){

					$attName = $aspect;

					$split = false;
					$splitOn = null;
					$joinOn = null;

					// special case for attributesString
					if ($attName === 'attributesString'){
						switch ($action) {
							case 'add':
							case 'prepend':
							case 'append':
							case 'insertBefore':
							case 'insertAfter':
								$this->applyAttributesFromString($node, $newValue);
								break;
							case 'remove':
								$this->removeAllAttributes($node);
								break;
							case 'replace':
								$this->removeAllAttributes($node);
								$this->applyAttributesFromString($node, $newValue);
								break;
							case 'replaceSubstring':
								$originalAttributes = $this->removeAllAttributes($node, true);
								$newValue = $this->substringReplace(
									$originalAttributes, $find, $newValue
								);
								$this->applyAttributesFromString($node, $newValue);
								break;
						}

					}

					// regular attribute
					else {

						// normalise actions
						if ($attName === 'class' || $attName === 'style'){
							$split = true;
							$splitOn = "/\s+/";
							$joinOn = " ";
							if ($action === 'add'){
								$action = 'append';
							} if ($attName === 'style'){
								$splitOn = "/\s*;\s*/";
								$joinOn = "; ";
							}
						} else {
							if ($action === 'add'){
								$action = 'replace';
							}
						}

						switch($action){
							case 'prepend':
							case 'append':
							case 'insertBefore':
							case 'insertAfter':

								if ($newValIsEmpty){
									break;
								}

								$curVal = $node->getAttribute($attName);

								// if class or style, prevent dupe and ensure correct position
								if ($split){

									$curValArray = preg_split($splitOn, $curVal);
									$length = $curValArray ? count($curValArray) : 0;

									// prevent duplication
									if ($length){
										for ($i = $length - 1; $i >= 0; $i--) {
											if ($curValArray[$i] === $newValue){
												unset($curValArray[$i]);
											}
										}
									} else {
										$curValArray = array();
									}

									// add at start or end of array
									if ($action === 'append' || $action === 'insertAfter'){
										$curValArray[] = $newValue;
									} else {
										array_unshift($curValArray, $newValue);
									}

									$newValue = implode($joinOn, $curValArray);
								}

								// all other attributes - simply prepend/append string
								else {
									if ($action === 'append' || $action === 'insertAfter'){
										$newValue = $curVal . $newValue;
									} else {
										$newValue = $newValue . $curVal;
									}
								}
								break;
							// replace is just a simple use of the $newValue
							// replaceSubstring
							case 'replaceSubstring':
								$newValue = $this->substringReplace(
									$node->getAttribute($attName), $find, $newValue
								);
								break;
						}

						// remove attribute or set resolved attribute (for all other actions)
						if ($action === 'remove'){
							$node->removeAttribute($attName);
						} else {
							$node->setAttribute($attName, $newValue);
						}
					}
				}

				// All other aspects
				else {

					switch ($aspect){
						case 'html':
						case 'text':
						case 'innerHTML':
							switch($action) {
								case 'prepend':
								case 'add':
								case 'append':
								case 'insertBefore':
								case 'insertAfter':
									if (!$newValIsEmpty){
										$this->insert($node, $action, $aspect, $newValue);
									}
									break;
								case 'move':
									$this->move($node, $mod, $aspect, $lazy);
									break;
								case 'lazyLoad':
									$this->lazyLoad($node, $lazy_id, $lazy);
									break;
								case 'remove':
									$this->remove($node, $action, $aspect, $isTextNode);
									break;
								case 'replace':
								case 'replaceSubstring':
									$this->replace($node, $action, $aspect, $newValue, $isTextNode, $find);
							}
							break;
						case 'parentWrapper':
							switch($action){
								case 'add':
								case 'prepend':
								case 'append':

									if ($tag){

										// create element with any attributes
										$wrapperNode = $this->createNewNode($tag, $newValue);

										// replace node with new element
										$node->parentNode->replaceChild(
											$wrapperNode,
											$node
										);

										// now it is safe to append the original node to the new element
										$wrapperNode->appendChild($node);
									}
									break;
								case 'remove':
									if ($node->parentNode){
										$this->removeWrapperNodes($node->parentNode, $action);
									}
									break;
							}
							break;
						case 'childWrapper':
							switch($action) {
								case 'add':
								case 'prepend':
								case 'append':
									$detached = $this->detachChildNodes($node, $action);
									$wrapperNode = $this->createNewNode($tag, $newValue);
									$node->appendChild($wrapperNode);
									foreach ($detached['nodes'] as $childNode){
										$wrapperNode->appendChild($childNode);
									}
									break;
								case 'remove':
									if ($node->childNodes){
										foreach($node->childNodes as $childNode){
											if ($childNode->nodeType === XML_ELEMENT_NODE){
												$this->removeWrapperNodes($childNode, $action);
												break;
											}
										}
									}
									break;
							}
					}
				}
			}
		}
	}

	function move($node, $mod, $aspect, &$lazy){

		$destNode = $this->resolveDestNode($node, $mod);

		if ($destNode){

			// detach the node to move
			$detachedNode = $node->parentNode->removeChild($node);

			// format $destNode as array for applyMod format
			$destNodeArray = array($destNode);

			$move_action = isset($mod['move_action']) ? $mod['move_action'] : 'append';

			// Add the
			$this->applyMod($destNodeArray, array(
				'action' => $move_action
			), 'html', $detachedNode, $lazy);
		}
	}

	public function resolveDestNode($node, $mod) {

		$relativeDom = isset($mod['move_relative_dom'])
			? json_decode($mod['move_relative_dom'], true)
			: null;

		if (!$relativeDom) {
			return false;
		}

		$xpath = isset($relativeDom['xpath']) ? $relativeDom['xpath'] : '';

		// If no relative DOM is provided
		if (empty($relativeDom['iterate'])) {
			return $this->getFirstNode($this->xpath->query($xpath));
		}

		// If we have relative DOM instructions
		$nodes = array($node); // Start with an array containing the initial $node

		foreach ($relativeDom['iterate'] as $item) {
			$item_xpath = isset($item['xpath']) ? $item['xpath'] : $xpath;
			$matchedNodes = array();

			// Process each node in the array
			foreach ($nodes as $node) {
				switch ($item['directive']) {
					case 'parent':
						if ($parentNode = $node->parentNode) {
							$matchedNodes[] = $parentNode;
						}
						break;
					case 'parents':
						$matchedNodes = $this->getParents($node, $item_xpath);
						break;
					case 'closest':
						if ($closestNode = $this->getClosest($node, $item_xpath)) {
							$matchedNodes[] = $closestNode;
						}
						break;
					case 'children':
						$matchedNodes = $this->getChildren($node, $item_xpath);
						break;
					case 'find':
						$matchedNodes = $this->getDescendents($node, $item_xpath);
						break;
					case 'prev':
					case 'next':
						$type = $item['directive'] === 'prev' ? 'previousSibling' : 'nextSibling';
						if ($sibling = $this->getSibling($type, $node, $item_xpath)) {
							$matchedNodes[] = $sibling;
						}
						break;
					case 'siblings':
						$matchedNodes = $this->getSiblings($node, $item_xpath);
						break;
				}
			}

			$nodes = $matchedNodes;

			// If there are no more matched nodes, break early
			if (empty($nodes)) {
				return null;
			}
		}

		return $nodes[0]; // count($nodes) === 1 ? $nodes[0] : $nodes; // Return single node or array of nodes
	}

	private function getFirstNode($nodes) {
		return $nodes->length > 0 ? $nodes->item(0) : null;
	}

	// Helper function to check if an element matches a selector or XPath
	private function isElementMatching($node, $xpath) {

		if (!$xpath) {
			return true; // match any node if no $xpath is provided
		}

		$nodes = $this->xpath->query($xpath, $node->parentNode); // Scope the query to the parent
		foreach ($nodes as $n) {
			if ($n->isSameNode($node)) {
				return true;
			}
		}
		return false;
	}

	// Find parent nodes that match an XPath
	private function getParents($node, $xpath) {
		$matchedNodes = array();
		while ($node = $node->parentNode) {
			if ($this->isElementMatching($node, $xpath)) {
				$matchedNodes[] = $node;
			}
		}
		return $matchedNodes;
	}

	// Find the closest ancestor matching the XPath
	private function getClosest($node, $xpath) {
		do {
			if ($this->isElementMatching($node, $xpath)) {
				return $node;
			}
		} while ($node = $node->parentNode);

		return null;
	}

	// Get direct child nodes matching the XPath
	private function getChildren($node, $xpath) {
		$matchedNodes = array();
		foreach ($node->childNodes as $child) {
			if ($child->nodeType === XML_ELEMENT_NODE && $this->isElementMatching($child, $xpath)) {
				$matchedNodes[] = $child;
			}
		}
		return $matchedNodes;
	}

	// Get descendant nodes matching the XPath
	private function getDescendents($node, $xpath) {
		$descendents = array();
		$nodes = $this->xpath->query($xpath, $node);
		foreach ($nodes as $descendent) {
			$descendents[] = $descendent;
		}
		return $descendents;
	}

	private function getSibling($type, $node, $xpath) {
		while ($node = $node->$type) {
			if ($node->nodeType === XML_ELEMENT_NODE && $this->isElementMatching($node, $xpath)) {
				return $node;
			}
		}
		return null;
	}

	// Find sibling elements matching the XPath
	private function getSiblings($node, $xpath) {
		$matchedNodes = array();
		$parent = $node->parentNode;

		if (!$parent) {
			return $matchedNodes;
		}

		foreach ($parent->childNodes as $sibling) {
			if ($sibling !== $node && $sibling->nodeType === XML_ELEMENT_NODE) {
				if ($this->isElementMatching($sibling, $xpath)) {
					$matchedNodes[] = $sibling;
				}
			}
		}
		return $matchedNodes;
	}


	function lazyPlaceholder($lazy_id, $node){

		// Get the existing class attribute from the node, if any
		$existingClasses = $node->hasAttribute('class') ? $node->getAttribute('class') : '';

		// Combine the existing classes with 'lazy-placeholder' and the dynamic 'lazy-{lazy_id}' class
		$combinedClasses = trim($existingClasses . ' lazy-placeholder lazy-' . $lazy_id);

		// Return the new placeholder HTML with the combined classes and lazy-id data attribute
		return '<div class="' . $combinedClasses . '" data-lazy-id="' . $lazy_id . '"></div>';
	}

	// remove inner or outer wrapper nodes (todo at varying levels, for bloat removal)
	function removeWrapperNodes(&$wrapper, $action, $levels = 1){
		if (!$wrapper) return;

		$detached = $this->detachChildNodes($wrapper, $action);
		$prevChild = null;

		// If there are children, replace the wrapper with the first,
		// then insert the rest after, preserving DOM order.
		if (!empty($detached['nodes'])) {
			foreach ($detached['nodes'] as $i => $childNode) {
				if ($i < 1) {
					// Anchor the group exactly where the wrapper was
					$wrapper->replaceWith($childNode);
				} else {
					$prevChild->after($childNode);
				}
				$prevChild = $childNode;
			}
		}
		// If the wrapper still exists (e.g. it had no children), remove it now.
		if ($wrapper->parentNode) {
			$wrapper->parentNode->removeChild($wrapper);
		}
	}

	function createNewNode($tagName, $attributesString, $text = null){
		$wrapperNode = $this->doc->createElement($tagName);
		$this->applyAttributesFromString($wrapperNode, $attributesString);
		return $wrapperNode;
	}
	
	function detachChildNodes($node, $action){

		$detached = array(
			'nodes' => array(),
			'innerHTML' => ''
		);

		// mark nodes for removing, doesn't work removing them in the loop
		foreach ($node->childNodes as $childNode){
			$detached['nodes'][] = $childNode;
			if ($action === 'replaceSubstring'){
				$detached['innerHTML'].= $this->doc->saveHTML($childNode);
			}
		}

		foreach ($detached['nodes'] as $removeNode){
			$node->removeChild($removeNode);
		}

		return $detached;
	}

	public function removeAllAttributes(&$node, $getCurrent = false) {

		$current = '';

		if ($node instanceof \DOMElement) {

			if ($getCurrent) {
				foreach ($node->attributes as $attr) {
					$current .= $attr->name . '="' . $attr->value . '" ';
				}
				$current = trim($current);
			}

			while ($node->attributes->length > 0) {
				$node->removeAttribute($node->attributes->item(0)->name);
			}
		}

		return $current;
	}

	function applyAttributesFromString(&$node, $string){
		try {
			$attsArray = current((array) new \SimpleXMLElement("<element $string />"));
			foreach($attsArray as $attKey => $attValue) {
				if ($attKey){
					$node->setAttribute($attKey, $attValue);
				}
			}
		} catch (\Exception $e) {
			throw new \Exception("Attributes string error for: " . $string);
		}
	}

	function substringReplace($string, $find, $replacement){
		if (str_starts_with($find, '/')){
			return @preg_replace($find, $replacement, $string);
		}
		return str_replace($find, $replacement, $string);
	}

	// input could be text, html, or $node reference
	function createNodeFromInput($input, $node = null, $register = true, $isTextNode = false) {

		// If the input is a DOMNode, use it directly
		if ($input instanceof \DOMNode) {
			$importNode = $this->doc->importNode($input, true);
		}
		// If it's a string (either HTML or text), process it accordingly
		else {
			$tmpDoc = new \DOMDocument();

			if ($isTextNode) {
				// Treat the input as a text node
				$importNode = $this->doc->createTextNode($input);
			} else {
				// Wrap the input in a minimal HTML structure
				$htmlOrTextWrapped = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"></head><body>" . $input . "</body></html>";

				// Load the HTML with the temporary wrapper
				//libxml_use_internal_errors(true);
				$tmpDoc->loadHTML($htmlOrTextWrapped, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ); // | LIBXML_NOCDATA

				// Extract the inner content (all nodes inside <body>)
				$body = $tmpDoc->getElementsByTagName('body')->item(0);

				// Create a container for the imported nodes
				$fragment = $this->doc->createDocumentFragment();

				// Loop through the body’s child nodes and import them one by one
				foreach ($body->childNodes as $childNode) {
					$importedNode = $this->doc->importNode($childNode, true);
					$fragment->appendChild($importedNode);
				}

				$importNode = $fragment;
			}
		}

		if (!$register) {
			return $importNode;
		}

		return $this->registerNode($importNode, $node);
	}

	// for a fragment to be usable it must be added to a node using appendChild
	// this functions appends the fragment and then immediately removes it, so it can be placed where we want
	function registerNode($fragment, $node){

		if (!$node){
			$node = $this->doc->documentElement;
		}

		$tempNode = $node->appendChild($fragment);

		return $node->removeChild($tempNode);
	}

}