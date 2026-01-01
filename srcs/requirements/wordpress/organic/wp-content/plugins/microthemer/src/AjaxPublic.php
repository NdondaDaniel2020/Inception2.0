<?php

// This is mainly to allow lazy-loading content, but may have other uses if we need a non-auth ajax request
namespace Microthemer;

class AjaxPublic {

	public function __construct() {
		add_action('admin_init', array($this, 'hookPublicAjax'));
	}

	// Register AJAX actions for public and logged-in users
	public function hookPublicAjax() {
		add_action('wp_ajax_nopriv_tvra_request', array($this, 'processPublicAjax'));
		add_action('wp_ajax_tvra_request', array($this, 'processPublicAjax'));
	}

	function processPublicAjax(){
		$request = json_decode(file_get_contents('php://input'), true);
		if (isset($request['lazy_ids'])){
			$this->getLazyContent($request);
		}
	}

	function getLazyContent($request){

		// Set the response content type to JSON
		header('Content-Type: application/json');

		if (!is_array($request['lazy_ids'])) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid request']);
			exit;
		}

		global $wpdb;

		$lazy_ids = array_slice(array_map('sanitize_text_field', $request['lazy_ids']), 0, 50);

		// Prepare the query to fetch the lazy content based on the provided IDs
		$placeholders = implode(', ', array_fill(0, count($lazy_ids), '%s'));
		$sql = $wpdb->prepare(
			"SELECT slug, content FROM {$wpdb->prefix}micro_content 
                     WHERE type = 'lazy' AND slug IN ($placeholders)",
			$lazy_ids
		);

		// Fetch the lazy content from the database
		$results = $wpdb->get_results($sql);

		// Prepare the data for the response
		$response = array('store' => []);
		foreach ($results as $row) {
			$response['store'][$row->slug][] = json_decode($row->content);
		}

		// Send the JSON response
		echo json_encode($response);
		exit;

	}
}
