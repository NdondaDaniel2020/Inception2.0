<?php

namespace Microthemer\Content;

class PluginUpdater {

	protected $updateChecker;
	protected string $pluginSlug = 'amender';
	protected string $pluginFile;
	protected string $pluginFolder;
	protected string $updateApiUrl = 'https://themeover.com/wp-content/tvr-auto-update/amender-meta-info.json';
	protected bool $updatesAllowed = true;

	public function __construct(string $pluginFile) {
		$this->pluginFile = $pluginFile;
		$this->pluginFolder = plugin_basename($pluginFile);

		$this->init_update_checker();

		// Hook into WP admin UI
		add_action('in_plugin_update_message-' . $this->pluginFolder, [$this, 'plugin_update_message'], 10, 2);
		add_filter('plugins_api_result', [$this, 'plugins_api_result'], 99, 3);
		add_filter('site_transient_update_plugins', [$this, 'maybe_block_update']);
	}

	protected function init_update_checker(): void {

		$checkerFile = __DIR__ . '/plugin-update-checker-master/plugin-update-checker.php';

		if (!class_exists('\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory')) {
			if (!file_exists($checkerFile)) {
				error_log('[Amender] Plugin Update Checker not found at: ' . $checkerFile);
				return;
			}
			require_once $checkerFile;
		}

		$this->updateChecker = \YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
			$this->updateApiUrl,
			$this->pluginFile,
			$this->pluginSlug
		);
	}

	public function disable_updates(): void {
		$this->updatesAllowed = false;
	}

	public function maybe_block_update($transient) {
		if (!$this->updatesAllowed && isset($transient->response[$this->pluginFolder])) {
			$transient->response[$this->pluginFolder]->package = false;
			$transient->response[$this->pluginFolder]->upgrade_notice = 'Updates are disabled.';
		}
		return $transient;
	}

	public function plugin_update_message($plugin_data, $response): void {
		if (empty($response->package)) {
			echo '<div class="update-message notice inline notice-error"><p>';
			echo 'Updates are currently disabled.';
			echo '</p></div>';
		}
	}

	public function plugins_api_result($res, $action, $args) {
		if (
			isset($args->slug) &&
			$args->slug === $this->pluginSlug &&
			!$this->updatesAllowed
		) {
			$res->download_link = false;
		}
		return $res;
	}
}
