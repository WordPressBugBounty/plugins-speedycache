<?php
/*
* SpeedyCache
* https://speedycache.com
* (c) Softaculous Team
*/

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('Hacking Attempt!');
}

/**
 * SpeedyCache Abilities settings page.
 *
 * Renders the AI Abilities setup wizard (MCP Adapter install, Application
 * Password generation, connection test) and the AI client configuration
 * snippet. The ability catalogue merges Free and Pro abilities so the UI
 * always reflects what is actually registered.
 *
 * The UI follows the classic SpeedyCache admin look: a white tab card with
 * the classic h2 header, the custom checkbox slider for the enable toggle
 * and the SpeedyCache sidebar style.
 *
 * Note: the actual wp_register_ability() calls live in:
 *  - SpeedyCache\AbilitiesRegister  (Free, always registered)
 *  - SpeedyCache\AbilitiesPro       (Pro, only when SpeedyCache Pro is active)
 */

class Abilities{

	// Marker used for the WordPress Application Password created by SpeedyCache.
	static $APP_PASSWORD_NAME = 'SpeedyCache AI Agents';
	static $APP_PASSWORD_APP_ID = 'speedycache-mcp';

	// GitHub release endpoint for the official WordPress MCP Adapter plugin.
	static $MCP_ADAPTER_RELEASE_URL = 'https://api.github.com/repos/WordPress/mcp-adapter/releases/latest';
	static $MCP_ADAPTER_RELEASE_CACHE = 'speedycache_mcp_adapter_release';

	// REST endpoint (provided by the adapter / WP 6.9+) used to test the connection.
	static $ABILITIES_ENDPOINT = '/wp-json/wp-abilities/v1/abilities';

	// MCP HTTP transport endpoint exposed by the adapter.
	static $MCP_SERVER_ENDPOINT = '/wp-json/mcp/mcp-adapter-default-server';

	// User meta key used to persist the last "Test connection" result so the
	// pill stays in the correct state after a page reload.
	static $TEST_STATUS_META = 'speedycache_mcp_test_status';

	/**
	 * Render the AI Abilities settings page.
	 *
	 * @return void
	 */
	static function ui_abilities(){
		global $speedycache;

		// Capability gate - mirrored on every admin page.
		if(!current_user_can('manage_options')){
			return;
		}

		// Enqueue dedicated assets for this page.
		wp_enqueue_style('speedycache-admin');
		wp_enqueue_style('speedycache-abilities', SPEEDYCACHE_URL . '/assets/css/abilities.css', ['speedycache-admin'], SPEEDYCACHE_VERSION);
		wp_enqueue_script('speedycache-abilities', SPEEDYCACHE_URL . '/assets/js/abilities.js', ['jquery'], SPEEDYCACHE_VERSION, true);
		wp_localize_script('speedycache-abilities', 'speedycache_abilities', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('speedycache_ajax_nonce'),
			'endpoint_url' => trailingslashit(home_url()) . ltrim(self::$ABILITIES_ENDPOINT, '/'),
			'i18n' => [
				'installing' => esc_html__('Installing...', 'speedycache'),
				'activating' => esc_html__('Activating...', 'speedycache'),
				'updating' => esc_html__('Updating...', 'speedycache'),
				'generating' => esc_html__('Generating...', 'speedycache'),
				'testing' => esc_html__('Testing...', 'speedycache'),
				'genErrTitle' => esc_html__('Could not generate the password.', 'speedycache'),
				'testOkPrefix' => esc_html__('Success:', 'speedycache'),
				'testFailPrefix' => esc_html__('Failed:', 'speedycache'),
			],
		]);

		// Status probes (kept cheap; safe to run on each render).
		$abilities_api_available = function_exists('wp_register_ability');
		$adapter_active = is_plugin_active('mcp-adapter/mcp-adapter.php');
		$adapter_installed = '' !== self::get_installed_mcp_adapter_file();
		$has_app_password = self::current_user_has_mcp_app_password();
		$test_status = self::get_test_connection_status();
		$site_url = trailingslashit(rtrim(home_url(), '/'));
		$endpoint_url = $site_url . ltrim(self::$ABILITIES_ENDPOINT, '/');
		$mcp_server_url = $site_url . ltrim(self::$MCP_SERVER_ENDPOINT, '/');
		$username = function_exists('wp_get_current_user') ? wp_get_current_user()->user_login : '';
		$app_pass_placeholder = esc_html__('your-application-password', 'speedycache');
		$user_placeholder = $username ?: esc_html__('<your-username>', 'speedycache');
		$adapter_installed_version = $adapter_installed ? self::get_installed_mcp_adapter_version() : '';
		$adapter_latest_version = '';
		$adapter_update_available = false;

		if($adapter_installed && $adapter_installed_version){
			$release = self::get_mcp_adapter_release();
			if(!empty($release['version']) && version_compare($release['version'], $adapter_installed_version, '>')){
				$adapter_latest_version = $release['version'];
				$adapter_update_available = true;
			}
		}

		// Setup progress
		$done_count = 0;
		$next_step = '';
		if(!$abilities_api_available){
			$next_step = esc_html__('Abilities registration', 'speedycache');
		}elseif(!$adapter_active){
			$done_count++;
			$next_step = esc_html__('MCP Adapter installation', 'speedycache');
		}elseif(!$has_app_password){
			$done_count += 2;
			$next_step = esc_html__('Application Password', 'speedycache');
		}elseif(empty($test_status['ok'])){
			$done_count += 3;
			$next_step = esc_html__('Connection test', 'speedycache');
		}else{
			$done_count += 4;
		}

		$test_ok = !empty($test_status['ok']);
		$test_message = !empty($test_status['message']) ? $test_status['message'] : '';

		$speedycache_options = get_option('speedycache_options', []);
		$is_enabled = !empty($speedycache_options['ai_abilities']['enabled']) ? 1 : 0;

		?>
		<div id="speedycache-admin">
			<div id="speedycache-abilities-page">
				<div class="speedycache-abilities-content">

					<!-- Main tab card -->
					<div class="speedycache-tab speedycache-abilities-tab">
						<h2>
							<img src="<?php echo esc_url(SPEEDYCACHE_URL); ?>/assets/images/icons/dashboard.svg" height="28" width="28" alt="" />
							<?php esc_html_e('AI Abilities', 'speedycache'); ?>
						</h2>

						<p class="speedycache-abilities-intro">
							<?php esc_html_e('Connect any MCP-compatible AI client to your site. The setup walks you through installing the MCP Adapter, generating a WordPress Application Password and verifying the connection.', 'speedycache'); ?>
						</p>

						<!-- Setup progress -->
						<div class="speedycache-abilities-progress">
							<div class="speedycache-abilities-progress-bar">
								<span class="speedycache-abilities-progress-fill" style="width:<?php echo esc_attr(($done_count / 4) * 100); ?>%"></span>
							</div>
							<div class="speedycache-abilities-progress-meta">
								<?php
									if($next_step){
										echo '<span class="speedycache-abilities-progress-next">' . sprintf(esc_html__('Next up: %s', 'speedycache'), '<strong>' . esc_html($next_step) . '</strong>') . '</span>';
									}else{
										echo '<span class="speedycache-abilities-progress-done">' . esc_html__('All steps complete. Your AI client can now connect.', 'speedycache') . '</span>';
									}
								?>
								<span class="speedycache-abilities-progress-count"><?php echo esc_html(sprintf(__('%1$d of %2$d steps complete', 'speedycache'), $done_count, 4)); ?></span>
							</div>
						</div>

						<!-- Step list -->
						<div class="speedycache-abilities-steps">

							<!-- Step 1 — Abilities registered -->
							<div class="speedycache-abilities-step speedycache-abilities-step-<?php echo ($abilities_api_available ? 'done' : 'todo'); ?>">
								<button type="button" class="speedycache-abilities-step-toggle" aria-expanded="true">
									<span class="speedycache-abilities-step-indicator"><?php echo ($abilities_api_available ? '&#10003;' : '1'); ?></span>
									<span class="speedycache-abilities-step-text">
										<span class="speedycache-abilities-step-title"><?php esc_html_e('SpeedyCache abilities registered', 'speedycache'); ?></span>
										<span class="speedycache-abilities-step-sub"><?php esc_html_e('Exposed via the WordPress 6.9+ Abilities API', 'speedycache'); ?></span>
									</span>
									<span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'warning'); ?>"><?php echo ($abilities_api_available ? esc_html__('Ready', 'speedycache') : esc_html__('Requires WP 6.9+', 'speedycache')); ?></span>
								</button>
								<div class="speedycache-abilities-step-body">
									<?php
										if($abilities_api_available){
											echo '<p>' . esc_html__('SpeedyCache exposes the abilities listed below. They are picked up automatically by any installed MCP adapter and surfaced to AI clients as tools.', 'speedycache') . '</p>';
											echo wp_kses_post(self::render_abilities_list());
										}else{
											echo '<p>' . esc_html__('The WordPress Abilities API ships in WordPress 6.9+. SpeedyCache can still work today — installing the MCP Adapter backfills the API so your AI client can connect immediately. Consider updating WordPress for native abilities support.', 'speedycache') . '</p>';
										}
									?>
								</div>
							</div>

							<!-- Step 2 — MCP Adapter -->
							<?php
								$adapter_state = $adapter_active ? 'done' : ($adapter_installed ? 'progress' : 'todo');
								$adapter_label = $adapter_active ? esc_html__('Active', 'speedycache') : ($adapter_installed ? esc_html__('Inactive', 'speedycache') : esc_html__('Not installed', 'speedycache'));
							?>
							<div class="speedycache-abilities-step speedycache-abilities-step-<?php echo esc_attr($adapter_state); ?>">
								<button type="button" class="speedycache-abilities-step-toggle" aria-expanded="<?php echo ($adapter_active ? 'false' : 'true'); ?>">
									<span class="speedycache-abilities-step-indicator"><?php echo ($adapter_state === 'done' ? '&#10003;' : '2'); ?></span>
									<span class="speedycache-abilities-step-text">
										<span class="speedycache-abilities-step-title"><?php esc_html_e('MCP Adapter installed', 'speedycache'); ?></span>
										<span class="speedycache-abilities-step-sub"><?php esc_html_e('Bridges your site to MCP-compatible AI clients', 'speedycache'); ?></span>
									</span>
									<span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($adapter_active ? 'success' : 'warning'); ?>"><?php echo esc_html($adapter_label); ?></span>
								</button>
								<div class="speedycache-abilities-step-body">
									<?php
										if($adapter_active){
											echo '<p>' . esc_html__('The MCP Adapter plugin is installed and active. Your site now exposes an MCP server that AI clients can connect to.', 'speedycache') . '</p>';
											if($adapter_update_available){
												echo '<p class="speedycache-abilities-adapter-update-notice">' . sprintf(esc_html__('A new version of the MCP Adapter is available (installed: %1$s, latest: %2$s).', 'speedycache'), '<strong>' . esc_html($adapter_installed_version) . '</strong>', '<strong>' . esc_html($adapter_latest_version) . '</strong>') . '</p>';
												echo '<button type="button" class="speedycache-button speedycache-btn-black speedycache-abilities-adapter-btn" data-action="update">' . esc_html__('Update MCP Adapter', 'speedycache') . '</button>';
											}
										}elseif($adapter_installed){
											echo '<p>' . esc_html__('The MCP Adapter is installed but not active. Activate it to enable the MCP server.', 'speedycache') . '</p>';
											echo '<button type="button" class="speedycache-button speedycache-btn-black speedycache-abilities-adapter-btn" data-action="activate">' . esc_html__('Activate MCP Adapter', 'speedycache') . '</button>';
										}else{
											echo '<p>' . esc_html__('Install the official WordPress MCP Adapter plugin with a single click. It bridges your site to any MCP-compatible AI client. SpeedyCache requires the MCP Adapter to expose its abilities to AI clients.', 'speedycache') . '</p>';
											echo '<button type="button" class="speedycache-button speedycache-btn-black speedycache-abilities-adapter-btn" data-action="install">' . esc_html__('Install MCP Adapter', 'speedycache') . '</button>';
										}
									?>
									<div class="speedycache-abilities-feedback" data-area="adapter" hidden></div>
								</div>
							</div>

							<!-- Step 3 — Application Password -->
							<div class="speedycache-abilities-step speedycache-abilities-step-<?php echo ($has_app_password ? 'done' : 'todo'); ?>">
								<button type="button" class="speedycache-abilities-step-toggle" aria-expanded="<?php echo ($has_app_password ? 'false' : 'true'); ?>">
									<span class="speedycache-abilities-step-indicator"><?php echo ($has_app_password ? '&#10003;' : '3'); ?></span>
									<span class="speedycache-abilities-step-text">
										<span class="speedycache-abilities-step-title"><?php esc_html_e('Application Password generated', 'speedycache'); ?></span>
										<span class="speedycache-abilities-step-sub"><?php esc_html_e('Authenticates your AI client against your site', 'speedycache'); ?></span>
									</span>
									<span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($has_app_password ? 'success' : 'warning'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'speedycache') : esc_html__('Action needed', 'speedycache')); ?></span>
								</button>
								<div class="speedycache-abilities-step-body">
									<p><?php esc_html_e('Generate a WordPress Application Password for your AI client. The password is shown only once — copy it somewhere safe.', 'speedycache'); ?></p>
									<div class="speedycache-abilities-app-pass-fields" data-hidden="true">
										<label class="speedycache-abilities-field"><span><?php esc_html_e('Username', 'speedycache'); ?></span><input type="text" readonly class="speedycache-abilities-username" value="<?php echo esc_attr($username); ?>" /></label>
										<label class="speedycache-abilities-field"><span><?php esc_html_e('Application Password', 'speedycache'); ?></span><input type="text" readonly class="speedycache-abilities-password" value="" /></label>
										<a class="speedycache-abilities-profile-link" href="<?php echo esc_url(admin_url('profile.php#application-passwords-section')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Manage in profile', 'speedycache'); ?> &nbsp;&rarr;</a>
									</div>
									<button type="button" class="speedycache-button speedycache-btn-black speedycache-abilities-gen-pass-btn"><?php echo ($has_app_password ? esc_html__('Generate a new Application Password', 'speedycache') : esc_html__('Generate Application Password', 'speedycache')); ?></button>
									<div class="speedycache-abilities-feedback" data-area="app-pass" hidden></div>
								</div>
							</div>

							<!-- Step 4 — Test connection -->
							<?php $test_class = $test_ok ? 'done' : ($test_message ? 'progress' : 'todo'); ?>
							<div class="speedycache-abilities-step speedycache-abilities-step-<?php echo esc_attr($test_class); ?>">
								<button type="button" class="speedycache-abilities-step-toggle" aria-expanded="<?php echo ($test_ok ? 'false' : 'true'); ?>">
									<span class="speedycache-abilities-step-indicator"><?php echo ($test_ok ? '&#10003;' : '4'); ?></span>
									<span class="speedycache-abilities-step-text">
										<span class="speedycache-abilities-step-title"><?php esc_html_e('Test the connection', 'speedycache'); ?></span>
										<span class="speedycache-abilities-step-sub"><?php esc_html_e('Verify authentication and ability discovery', 'speedycache'); ?></span>
									</span>
									<span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($test_ok ? 'success' : 'neutral'); ?> speedycache-abilities-test-pill"><?php echo ($test_ok ? esc_html__('Verified', 'speedycache') : esc_html__('Pending', 'speedycache')); ?></span>
								</button>
								<div class="speedycache-abilities-step-body">
									<p><?php esc_html_e('Verify that your AI client can authenticate against your site and discover SpeedyCache abilities.', 'speedycache'); ?></p>
									<button type="button" class="speedycache-button speedycache-btn-black speedycache-abilities-test-btn"><?php esc_html_e('Test connection', 'speedycache'); ?></button>
									<?php
										if($test_message){
											echo '<div class="speedycache-abilities-test-result speedycache-abilities-test-result-' . ($test_ok ? 'ok' : 'error') . '">' . esc_html(($test_ok ? __('Success: ', 'speedycache') : __('Failed: ', 'speedycache')) . $test_message) . '</div>';
										}else{
											echo '<div class="speedycache-abilities-test-result" data-hidden="true"></div>';
										}
									?>
								</div>
							</div>
						</div>

						<!-- AI Client Configuration -->
						<div class="speedycache-abilities-clients">
							<h3><?php esc_html_e('Connect to AI Client', 'speedycache'); ?></h3>
							<p class="speedycache-abilities-clients-desc"><?php esc_html_e('Pick a client, copy the snippet, paste it in the right place.', 'speedycache'); ?></p>

							<?php
								echo '<script type="application/json" id="speedycache-abilities-data">' . wp_json_encode([
									'siteUrl' => $site_url,
									'mcpServerUrl' => $mcp_server_url,
									'endpointUrl' => $endpoint_url,
									'username' => $username,
									'passwordHolder' => $app_pass_placeholder,
									'userPlaceholder' => $user_placeholder,
									'serverName' => 'speedycache-site',
									'adapterActive' => $adapter_active,
									'hasAppPassword' => $has_app_password,
									'i18n' => [
										'copy' => esc_html__('Copy snippet', 'speedycache'),
										'copied' => esc_html__('Copied!', 'speedycache'),
										'installing' => esc_html__('Installing...', 'speedycache'),
										'activating' => esc_html__('Activating...', 'speedycache'),
										'updating' => esc_html__('Updating...', 'speedycache'),
										'generating' => esc_html__('Generating...', 'speedycache'),
										'testing' => esc_html__('Testing...', 'speedycache'),
										'genErrTitle' => esc_html__('Could not generate the password.', 'speedycache'),
										'testOkPrefix' => esc_html__('Success:', 'speedycache'),
										'testFailPrefix' => esc_html__('Failed:', 'speedycache'),
									],
								]) . '</script>';

								$clients = [
									'claude-desktop' => ['label' => esc_html__('Claude Desktop', 'speedycache')],
									'claude-code' => ['label' => esc_html__('Claude Code CLI', 'speedycache')],
									'cursor' => ['label' => esc_html__('Cursor', 'speedycache')],
									'vscode' => ['label' => esc_html__('VS Code', 'speedycache')],
									'antigravity' => ['label' => esc_html__('Antigravity', 'speedycache')],
									'opencode' => ['label' => esc_html__('OpenCode', 'speedycache'), 'icon' => 'OC'],
									'gemini-cli' => ['label' => esc_html__('Gemini CLI', 'speedycache'), 'deprecated' => true],
								];

								echo '<div class="speedycache-abilities-segmented" role="tablist" aria-label="' . esc_attr__('AI clients', 'speedycache') . '">';
								$first = true;
								foreach($clients as $id => $data){
									$badge = '';
									if(!empty($data['deprecated'])){
										$badge = ' <span class="speedycache-deprecated-badge">' . esc_html__('Deprecated', 'speedycache') . '</span>';
									}
									echo '<button type="button" class="speedycache-abilities-segmented-btn' . ($first ? ' active' : '') . '" data-client="' . esc_attr($id) . '" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '">' . esc_html($data['label']) . wp_kses_post($badge) . '</button>';
									$first = false;
								}
							?>
							</div>

							<div class="speedycache-abilities-client-panel">
								<div class="speedycache-abilities-client-meta">
									<span class="speedycache-abilities-client-path-label"></span>
									<code class="speedycache-abilities-client-path-value"></code>
								</div>

								<div class="speedycache-abilities-snippet-wrap">
									<pre class="speedycache-abilities-snippet" tabindex="0"></pre>
									<button type="button" class="speedycache-abilities-copy-btn">
										<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
										<span class="speedycache-abilities-copy-btn-label"><?php esc_html_e('Copy', 'speedycache'); ?></span>
									</button>
								</div>

								<ol class="speedycache-abilities-instructions"></ol>
							</div>
						</div>
					</div>
				</div> <!-- .speedycache-abilities-content -->

				<!-- Sidebar (matches the classic SpeedyCache sidebar) -->
				<div class="speedycache-sidebar">
					<div class="speedycache-need-help">
						<p><?php esc_html_e('Enable Abilities', 'speedycache'); ?></p>
						<div class="speedycache-abilities-enable-block">
							<div class="speedycache-option-wrap">
								<label class="speedycache-custom-checkbox">
									<input class="speedycache_ai_abilities" id="speedycache_ai_abilities" name="ai_abilities" type="checkbox" value="1" data-nonce="<?php echo esc_attr(wp_create_nonce('speedycache_ajax_nonce')); ?>" <?php checked(1, $is_enabled); ?>>
									<span class="speedycache-input-slider"></span>
								</label>
								<div class="speedycache-option-info">
									<span class="speedycache-option-name"><?php esc_html_e('Enable AI Abilities', 'speedycache'); ?></span>
									<span class="speedycache-option-desc"><?php esc_html_e('Allow AI clients to securely access SpeedyCache features through the WordPress Abilities API.', 'speedycache'); ?></span>
								</div>
							</div>
						</div>
					</div>

					<div class="speedycache-need-help">
						<p><?php esc_html_e('Site status', 'speedycache'); ?></p>
						<div class="speedycache-quick-links">
							<dl class="speedycache-abilities-status-list">
								<div><dt><?php esc_html_e('Abilities API', 'speedycache'); ?></dt><dd><span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($abilities_api_available ? 'success' : 'neutral'); ?>"><?php echo ($abilities_api_available ? esc_html__('Available', 'speedycache') : esc_html__('Unavailable', 'speedycache')); ?></span></dd></div>
								<div><dt><?php esc_html_e('MCP Adapter', 'speedycache'); ?></dt><dd><span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($adapter_active ? 'success' : ($adapter_installed ? 'warning' : 'neutral')); ?>"><?php echo esc_html($adapter_label); ?></span></dd></div>
								<div><dt><?php esc_html_e('App Password', 'speedycache'); ?></dt><dd><span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($has_app_password ? 'success' : 'neutral'); ?>"><?php echo ($has_app_password ? esc_html__('Generated', 'speedycache') : esc_html__('Not generated', 'speedycache')); ?></span></dd></div>
								<div><dt><?php esc_html_e('Last test', 'speedycache'); ?></dt><dd><span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo ($test_ok ? 'success' : ($test_message ? 'error' : 'neutral')); ?>"><?php echo ($test_ok ? esc_html__('Tested', 'speedycache') : ($test_message ? esc_html__('Failed', 'speedycache') : esc_html__('Never', 'speedycache'))); ?></span></dd></div>
								<div><dt><?php esc_html_e('SpeedyCache Pro', 'speedycache'); ?></dt><dd><span class="speedycache-abilities-pill speedycache-abilities-pill-<?php echo defined('SPEEDYCACHE_PRO') ? 'success' : 'neutral'; ?>"><?php echo defined('SPEEDYCACHE_PRO') ? esc_html__('Active', 'speedycache') : esc_html__('Inactive', 'speedycache'); ?></span></dd></div>
							</dl>
						</div>
					</div>

					<div class="speedycache-need-help">
						<p><?php esc_html_e('Quick tips', 'speedycache'); ?></p>
						<div class="speedycache-quick-links">
							<ul class="speedycache-abilities-tip-list">
								<li><?php esc_html_e('Generate one Application Password per AI client so you can revoke them individually.', 'speedycache'); ?></li>
								<li><?php esc_html_e('The password is shown only once. Copy it before navigating away.', 'speedycache'); ?></li>
								<li><?php esc_html_e('Pro abilities (Database, Logs, Image Optimization, Object Cache) are only available when SpeedyCache Pro is active.', 'speedycache'); ?></li>
							</ul>
						</div>
					</div>

					<div class="speedycache-need-help">
						<p><?php esc_html_e('Resources', 'speedycache'); ?></p>
						<div class="speedycache-quick-links">
							<ul class="speedycache-abilities-resource-list">
								<li><a href="https://speedycache.com/docs/site-optimization/how-to-setup-mcp-adapter-and-abilities" target="_blank" rel="noopener noreferrer"><?php esc_html_e('SpeedyCache documentation', 'speedycache'); ?></a></li>
								<li><a href="https://modelcontextprotocol.io/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Model Context Protocol', 'speedycache'); ?></a></li>
								<li><a href="https://github.com/WordPress/mcp-adapter" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress MCP Adapter', 'speedycache'); ?></a></li>
							</ul>
						</div>
					</div>
				</div> <!-- .speedycache-sidebar -->
			</div>
		</div>

		<div class="speedycache-toast" style="display:none;"></div>
		<?php
	}

	/**
	 * Render the list of abilities SpeedyCache exposes through the MCP adapter.
	 * Merges Free and (when active) Pro ability catalogues.
	 *
	 * @return string
	 */
	static function render_abilities_list(){
		$groups = self::get_registered_abilities();

		if(empty($groups)){
			return '<p class="speedycache-abilities-empty">' . esc_html__('No SpeedyCache abilities are currently exposed. Activate the MCP Adapter to surface them.', 'speedycache') . '</p>';
		}

		$html = '<div class="speedycache-abilities-list">';
		foreach($groups as $group => $items){
			$html .= '<div class="speedycache-abilities-group">';
			$html .= '<h4>' . esc_html($group) . '</h4>';
			$html .= '<ul>';
			foreach($items as $ability){
				$html .= '<li>';
				$html .= '<span class="speedycache-abilities-ability-name">' . esc_html($ability['label']) . '</span>';
				if(!empty($ability['description'])){
					$html .= '<span class="speedycache-abilities-ability-desc">' . esc_html($ability['description']) . '</span>';
				}
				if(!empty($ability['pro'])){
					$html .= '<span class="speedycache-abilities-ability-pro">' . esc_html__('Pro', 'speedycache') . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			$html .= '</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/**
	 * The catalogue of SpeedyCache abilities exposed to MCP clients. Mirrors
	 * the abilities registered in main/abilitiesregister.php (Free) and
	 * main/abilitiespro.php in Pro. The Pro section is only merged when
	 * the SpeedyCache Pro plugin is installed and active.
	 *
	 * @return array
	 */
	static function get_registered_abilities(){
		$free = [
			esc_html__('Settings', 'speedycache') => [
				[
					'label' => esc_html__('Get SpeedyCache Settings', 'speedycache'),
					'description' => esc_html__('Returns global cache settings: status, gzip, mobile, preload, lifespan, Varnish and more.', 'speedycache'),
				],
			],
			esc_html__('Cache', 'speedycache') => [
				[
					'label' => esc_html__('Delete All Cache', 'speedycache'),
					'description' => esc_html__('Purges the entire page cache and optionally the minified assets, fonts and gravatars.', 'speedycache'),
				],
				[
					'label' => esc_html__('Delete Cache for a URL', 'speedycache'),
					'description' => esc_html__('Purges the cached page(s) for the given URL(s).', 'speedycache'),
				],
				[
					'label' => esc_html__('Delete Cache for a Post', 'speedycache'),
					'description' => esc_html__('Purges the cached page for a given post ID (0 = homepage).', 'speedycache'),
				],
				[
					'label' => esc_html__('Get Cache Statistics', 'speedycache'),
					'description' => esc_html__('Returns basic cache stats: HTML file count and total size.', 'speedycache'),
				],
			],
			esc_html__('File Optimization', 'speedycache') => [
				[
					'label' => esc_html__('Get File Optimization Settings', 'speedycache'),
					'description' => esc_html__('Returns minify HTML/CSS/JS, combine, delay JS, render-blocking, lazy load and speculation toggles.', 'speedycache'),
				],
			],
			esc_html__('CDN', 'speedycache') => [
				[
					'label' => esc_html__('Get CDN Settings', 'speedycache'),
					'description' => esc_html__('Returns the CDN configuration: enabled, URL, file types and include/exclude keywords.', 'speedycache'),
				],
			],
			esc_html__('Excludes', 'speedycache') => [
				[
					'label' => esc_html__('List Cache Excludes', 'speedycache'),
					'description' => esc_html__('Returns the list of cache exclusion rules (type, prefix, content).', 'speedycache'),
				],
				[
					'label' => esc_html__('Delete a Cache Exclude Rule', 'speedycache'),
					'description' => esc_html__('Deletes a single cache exclusion rule by its index.', 'speedycache'),
				],
			],
			esc_html__('Preload', 'speedycache') => [
				[
					'label' => esc_html__('Get Preload Status', 'speedycache'),
					'description' => esc_html__('Returns whether preloading is enabled and the queued URL list.', 'speedycache'),
				],
				[
					'label' => esc_html__('Build the Preload List', 'speedycache'),
					'description' => esc_html__('Re-builds the preload list and kicks off the preload cron.', 'speedycache'),
				],
			],
			esc_html__('Import / Export', 'speedycache') => [
				[
					'label' => esc_html__('Export SpeedyCache Settings', 'speedycache'),
					'description' => esc_html__('Exports the full settings tree as JSON. Object-cache credentials are scrubbed.', 'speedycache'),
				],
			],
		];

		// Allow SpeedyCache Pro to register additional abilities.
		return apply_filters('speedycache_registered_abilities', $free);
	}

	/**
	 * Scan installed plugins for the MCP Adapter (folder prefix: mcp-adapter/).
	 *
	 * @return string
	 */
	static function get_installed_mcp_adapter_file(){
		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		
		$all_plugins = get_plugins();
		$target_file = 'mcp-adapter/mcp-adapter.php';
        
		if(isset($all_plugins[ $target_file ])){
			return $target_file;
		}
		return '';
	}

	/**
	 * Read the installed MCP Adapter version from the plugin headers.
	 *
	 * @return string Empty string when the adapter is not installed.
	 */
	static function get_installed_mcp_adapter_version(){

		$file = self::get_installed_mcp_adapter_file();

		if('' === $file){
			return '';
		}

		if(!function_exists('get_plugins')){
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		if(!isset($all_plugins[ $file ]['Version'])){
			return '';
		}
		return (string)$all_plugins[ $file ]['Version'];
	}

	/**
	 * Does the current user already have an MCP app password created by SpeedyCache?
	 *
	 * @return bool
	 */
	static function current_user_has_mcp_app_password(){
		$user_id = get_current_user_id();
		if(!$user_id || !class_exists('WP_Application_Passwords')){
			return false;
		}
		foreach(\WP_Application_Passwords::get_user_application_passwords($user_id) as $app){
			if(!empty($app['app_id']) && $app['app_id'] === self::$APP_PASSWORD_APP_ID){
				return true;
			}
		}
		return false;
	}

	/**
	 * Read the saved test-connection status for the current user.
	 *
	 * @return array
	 */
	static function get_test_connection_status(){
		$user_id = get_current_user_id();
		if(!$user_id){
			return ['ok' => false, 'message' => ''];
		}
		$stored = get_user_meta($user_id, self::$TEST_STATUS_META, true);
		if(!is_array($stored)){
			return ['ok' => false, 'message' => ''];
		}
		return [
			'ok' => !empty($stored['ok']),
			'message' => !empty($stored['message']) ? $stored['message'] : '',
		];
	}

	/**
	 * Persist the test-connection status for the current user.
	 *
	 * @param bool   $ok
	 * @param string $message
	 * @return void
	 */
	static function save_test_connection_status($ok, $message){
		$user_id = get_current_user_id();
		if(!$user_id){
			return;
		}
		update_user_meta($user_id, self::$TEST_STATUS_META, [
			'ok' => !empty($ok),
			'message' => (string)$message,
		]);
	}

	// Fetch the latest MCP Adapter release info from GitHub (cached 1 hour).
	static function get_mcp_adapter_release(){

		$cached = get_transient(\SpeedyCache\Abilities::$MCP_ADAPTER_RELEASE_CACHE);
		if(false !== $cached && is_array($cached)){
			return $cached;
		}

		$response = wp_remote_get(\SpeedyCache\Abilities::$MCP_ADAPTER_RELEASE_URL, [
			'timeout' => 10,
			'headers' => ['Accept' => 'application/vnd.github+json'],
		]);

		if(is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)){
			return [];
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		if(!is_array($body) || empty($body['tag_name']) || empty($body['assets'][0]['browser_download_url'])){
			return [];
		}

		$payload = [
			'version' => ltrim((string)$body['tag_name'], 'v'),
			'download_url' => esc_url_raw($body['assets'][0]['browser_download_url']),
		];

		set_transient(\SpeedyCache\Abilities::$MCP_ADAPTER_RELEASE_CACHE, $payload, DAY_IN_SECONDS);
		return $payload;
	}
}