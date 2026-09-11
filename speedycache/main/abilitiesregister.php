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
 * Registers SpeedyCache FREE abilities with the WordPress 6.9+ Abilities API.
 *
 * These abilities are always available (Free version) and provide read-only
 * access to the cache configuration, file optimization settings, CDN and
 * excludes plus the ability to manage cache (delete / preload) and run a
 * PageSpeed test.
 *
 * Pro-only abilities are registered by SpeedyCache\AbilitiesPro when the
 * SpeedyCache Pro plugin is installed and active.
 */
class AbilitiesRegister{

	/**
	 * Register the SpeedyCache ability categories (Free subset).
	 *
	 * @return void
	 */
	static function register_categories(){
		$categories = [
			'speedycache-settings' => __('SpeedyCache — Settings', 'speedycache'),
			'speedycache-cache' => __('SpeedyCache — Cache', 'speedycache'),
			'speedycache-file' => __('SpeedyCache — File Optimization', 'speedycache'),
			'speedycache-cdn' => __('SpeedyCache — CDN', 'speedycache'),
			'speedycache-excludes' => __('SpeedyCache — Excludes', 'speedycache'),
			'speedycache-preload' => __('SpeedyCache — Preload', 'speedycache'),
			'speedycache-import' => __('SpeedyCache — Import / Export', 'speedycache'),
		];

		foreach($categories as $slug => $label){
			wp_register_ability_category($slug, [
				'label' => $label,
				'description' => __('Cache and performance optimization abilities provided by SpeedyCache.', 'speedycache'),
			]);
		}
	}

	/**
	 * Register all Free SpeedyCache abilities.
	 *
	 * @return void
	 */
	static function register_abilities(){
		self::register_settings_abilities();
		self::register_cache_abilities();
		self::register_file_abilities();
		self::register_cdn_abilities();
		self::register_excludes_abilities();
		self::register_preload_abilities();
		self::register_import_abilities();
	}

	// =========================================================================
	// Shared helpers
	// =========================================================================

	/**
	 * Shared meta block for read-only abilities.
	 *
	 * @return array
	 */
	protected static function readonly_meta(){
		return [
			'annotations' => ['readonly' => true],
			'show_in_rest' => true,
			'mcp' => ['public' => true],
		];
	}

	/**
	 * Input schema for abilities that take no input.
	 *
	 * @return array
	 */
	protected static function no_input_schema(){
		return [
			'type' => 'object',
			'additionalProperties' => false,
			'default' => [],
		];
	}

	// =========================================================================
	// Permission callbacks
	// =========================================================================

	/**
	 * @return bool
	 */
	public static function can_manage_options(){
		return current_user_can('manage_options');
	}

	// =========================================================================
	// Settings abilities (read-only)
	// =========================================================================

	protected static function register_settings_abilities(){
		// speedycache-settings/get
		wp_register_ability('speedycache-settings/get', [
			'label' => __('Get SpeedyCache Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache global settings tree: cache status, gzip, mobile cache, logged-in user caching, preload, cache lifespan, Varnish purge and more. Read-only.', 'speedycache'),
			'category' => 'speedycache-settings',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'cache_enabled' => ['type' => 'boolean'],
					'gzip' => ['type' => 'boolean'],
					'logged_in_user' => ['type' => 'boolean'],
					'mobile' => ['type' => 'boolean'],
					'mobile_theme' => ['type' => 'boolean'],
					'lbc' => ['type' => 'boolean'],
					'purge_varnish' => ['type' => 'boolean'],
					'varniship' => ['type' => ['string', 'null']],
					'preload' => ['type' => 'boolean'],
					'purge_interval' => ['type' => 'integer'],
					'purge_interval_unit'=> ['type' => 'string'],
					'auto_purge_fonts' => ['type' => 'boolean'],
					'auto_purge_gravatar' => ['type' => 'boolean'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::get_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Cache management abilities
	// =========================================================================

	protected static function register_cache_abilities(){
		// speedycache-cache/delete-all
		wp_register_ability('speedycache-cache/delete-all', [
			'label' => __('Delete All Cache', 'speedycache'),
			'description' => __('Purges the entire SpeedyCache page cache (desktop + mobile + critical CSS) and optionally the minified assets, local fonts and gravatar cache. Useful for "clear my cache" prompts.', 'speedycache'),
			'category' => 'speedycache-cache',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'minified' => [
						'type' => 'boolean',
						'description' => __('Also delete the minified / combined CSS & JS assets cache.', 'speedycache'),
						'default' => false,
					],
					'fonts' => [
						'type' => 'boolean',
						'description' => __('Also delete the locally cached fonts.', 'speedycache'),
						'default' => false,
					],
					'gravatars' => [
						'type' => 'boolean',
						'description' => __('Also delete the cached gravatars.', 'speedycache'),
						'default' => false,
					],
					'preload' => [
						'type' => 'boolean',
						'description' => __('Re-build the preload list after purging the cache.', 'speedycache'),
						'default' => false,
					],
				],
				'additionalProperties' => false,
				'default' => [],
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'deleted' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::delete_all_cache',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);

		// speedycache-cache/delete-url
		wp_register_ability('speedycache-cache/delete-url', [
			'label' => __('Delete Cache for a URL', 'speedycache'),
			'description' => __('Purges the SpeedyCache cached page(s) for the given URL(s). Accepts a single URL or a list of URLs. Useful for "clear the cache for this page" prompts.', 'speedycache'),
			'category' => 'speedycache-cache',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'urls' => [
						'type' => 'array',
						'items' => ['type' => 'string', 'format' => 'uri'],
						'description' => __('One or more URLs whose cache should be purged.', 'speedycache'),
					],
				],
				'required' => ['urls'],
				'additionalProperties' => false,
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'deleted' => ['type' => 'boolean'],
					'count' => ['type' => 'integer'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::delete_url_cache',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);

		// speedycache-cache/delete-post
		wp_register_ability('speedycache-cache/delete-post', [
			'label' => __('Delete Cache for a Post', 'speedycache'),
			'description' => __('Purges the SpeedyCache cached page for a given post ID (0 = homepage). Also re-preloads the URL if preloading is enabled.', 'speedycache'),
			'category' => 'speedycache-cache',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'post_id' => [
						'type' => 'integer',
						'description' => __('The post ID whose cache should be purged. Use 0 for the homepage.', 'speedycache'),
					],
				],
				'required' => ['post_id'],
				'additionalProperties' => false,
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'deleted' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::delete_post_cache',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);

		// speedycache-cache/get-stats (free: desktop + mobile + html size)
		wp_register_ability('speedycache-cache/get-stats', [
			'label' => __('Get Cache Statistics', 'speedycache'),
			'description' => __('Returns basic cache statistics: the number of cached HTML pages and the total HTML cache size. Useful for "how big is my cache?" prompts.', 'speedycache'),
			'category' => 'speedycache-cache',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'html_files' => ['type' => 'integer'],
					'html_size_kb' => ['type' => 'number'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::get_cache_stats',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// File optimization abilities (read-only)
	// =========================================================================

	protected static function register_file_abilities(){
		// speedycache-file/get
		wp_register_ability('speedycache-file/get', [
			'label' => __('Get File Optimization Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache file optimization settings: HTML / CSS / JS minification, render-blocking handling, delay JS, combine CSS/JS and related toggles. Read-only.', 'speedycache'),
			'category' => 'speedycache-file',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'minify_html' => ['type' => 'boolean'],
					'minify_css' => ['type' => 'boolean'],
					'combine_css' => ['type' => 'boolean'],
					'critical_css' => ['type' => 'boolean'],
					'unused_css' => ['type' => 'boolean'],
					'minify_js' => ['type' => 'boolean'],
					'combine_js' => ['type' => 'boolean'],
					'delay_js' => ['type' => 'boolean'],
					'delay_js_mode' => ['type' => ['string', 'null']],
					'render_blocking' => ['type' => 'boolean'],
					'critical_images' => ['type' => 'boolean'],
					'disable_emojis' => ['type' => 'boolean'],
					'lazy_load' => ['type' => 'boolean'],
					'speculation_loading' => ['type' => 'boolean'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::get_file_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// CDN abilities (read-only)
	// =========================================================================

	protected static function register_cdn_abilities(){
		// speedycache-cdn/get
		wp_register_ability('speedycache-cdn/get', [
			'label' => __('Get CDN Settings', 'speedycache'),
			'description' => __('Returns the SpeedyCache CDN configuration: whether CDN is enabled, the CDN URL, file types served by the CDN, include/exclude keywords and the zone ID. Credentials are never exposed. Read-only.', 'speedycache'),
			'category' => 'speedycache-cdn',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'enabled' => ['type' => 'boolean'],
					'cdn_type' => ['type' => ['string', 'null']],
					'cdn_key' => ['type' => ['string', 'null']],
					'enabled_cloudflare'=> ['type' => 'boolean'],
					'cdn_url' => ['type' => ['string', 'null']],
					'file_types' => ['type' => ['string', 'array', 'null']],
					'keywords' => ['type' => ['string', 'array', 'null']],
					'excludekeywords' => ['type' => ['string', 'array', 'null']],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::get_cdn_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Excludes abilities
	// =========================================================================

	protected static function register_excludes_abilities(){
		// speedycache-excludes/list
		wp_register_ability('speedycache-excludes/list', [
			'label' => __('List Cache Excludes', 'speedycache'),
			'description' => __('Returns the list of SpeedyCache cache exclusion rules: type, prefix and content for each rule. Useful for "which pages are not cached?" prompts. Read-only.', 'speedycache'),
			'category' => 'speedycache-excludes',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'excludes' => [
						'type' => 'array',
						'items' => [
							'type' => 'object',
							'properties' => [
								'type' => ['type' => 'string'],
								'prefix' => ['type' => 'string'],
								'content' => ['type' => ['string', 'array', 'null']],
							],
						],
					],
					'total' => ['type' => 'integer'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::list_excludes',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);

		// speedycache-excludes/delete
		wp_register_ability('speedycache-excludes/delete', [
			'label' => __('Delete a Cache Exclude Rule', 'speedycache'),
			'description' => __('Deletes a single SpeedyCache cache exclusion rule by its index in the excludes list. Useful for "remove the exclude rule for my blog page" prompts.', 'speedycache'),
			'category' => 'speedycache-excludes',
			'input_schema' => [
				'type' => 'object',
				'properties' => [
					'rule_id' => [
						'type' => 'integer',
						'description' => __('The zero-based index of the exclude rule to delete.', 'speedycache'),
					],
				],
				'required' => ['rule_id'],
				'additionalProperties' => false,
			],
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'deleted' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::delete_exclude_rule',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Preload abilities
	// =========================================================================

	protected static function register_preload_abilities(){
		// speedycache-preload/status
		wp_register_ability('speedycache-preload/status', [
			'label' => __('Get Preload Status', 'speedycache'),
			'description' => __('Returns whether preloading is enabled and the current list of URLs queued for preloading. Read-only.', 'speedycache'),
			'category' => 'speedycache-preload',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'enabled' => ['type' => 'boolean'],
					'queued' => ['type' => 'integer'],
					'urls' => [
						'type' => 'array',
						'items' => ['type' => 'string'],
					],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::preload_status',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);

		// speedycache-preload/build
		wp_register_ability('speedycache-preload/build', [
			'label' => __('Build the Preload List', 'speedycache'),
			'description' => __('Re-builds the SpeedyCache preload list (home page + latest posts + latest pages) and kicks off the preload cron. Useful for "warm up my cache" prompts.', 'speedycache'),
			'category' => 'speedycache-preload',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'started' => ['type' => 'boolean'],
					'message' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::build_preload',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => [
				'show_in_rest' => true,
				'mcp' => ['public' => true],
			],
		]);
	}

	// =========================================================================
	// Import / Export abilities
	// =========================================================================

	protected static function register_import_abilities(){
		// speedycache-import/export
		wp_register_ability('speedycache-import/export', [
			'label' => __('Export SpeedyCache Settings', 'speedycache'),
			'description'=> __('Exports the full SpeedyCache settings tree (cache options, CDN, image, object cache, excludes, bloat) as a JSON string so an AI client can save or display it. Object-cache credentials are scrubbed. Read-only.', 'speedycache'),
			'category' => 'speedycache-import',
			'input_schema' => self::no_input_schema(),
			'output_schema' => [
				'type' => 'object',
				'properties' => [
					'settings' => ['type' => 'object'],
					'json' => ['type' => 'string'],
				],
			],
			'execute_callback' => '\SpeedyCache\AbilitiesRegister::export_settings',
			'permission_callback' => '\SpeedyCache\AbilitiesRegister::can_manage_options',
			'meta' => self::readonly_meta(),
		]);
	}

	// =========================================================================
	// Execute callbacks — Settings
	// =========================================================================

	/**
	 * Execute callback for speedycache-settings/get.
	 *
	 * @return array
	 */
	public static function get_settings(){
		$options = get_option('speedycache_options', []);

		return [
			'cache_enabled' => !empty($options['status']),
			'gzip' => !empty($options['gzip']),
			'logged_in_user' => !empty($options['logged_in_user']),
			'mobile' => !empty($options['mobile']),
			'mobile_theme' => !empty($options['mobile_theme']),
			'lbc' => !empty($options['lbc']),
			'purge_varnish' => !empty($options['purge_varnish']),
			'varniship' => isset($options['varniship']) ? $options['varniship'] : null,
			'preload' => !empty($options['preload']),
			'purge_interval' => isset($options['purge_interval']) ? (int)$options['purge_interval'] : 0,
			'purge_interval_unit' => isset($options['purge_interval_unit']) ? $options['purge_interval_unit'] : 'days',
			'auto_purge_fonts' => !empty($options['auto_purge_fonts']),
			'auto_purge_gravatar' => !empty($options['auto_purge_gravatar']),
		];
	}

	// =========================================================================
	// Execute callbacks — Cache
	// =========================================================================

	/**
	 * Execute callback for speedycache-cache/delete-all.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function delete_all_cache($input){
		$input = is_array($input) ? $input : [];
		$actions = [];

		if(!empty($input['minified'])){
			$actions['minified'] = true;
		}
		if(!empty($input['fonts'])){
			$actions['font'] = true;
		}
		if(!empty($input['gravatars'])){
			$actions['gravatars'] = true;
		}
		if(!empty($input['preload'])){
			$actions['preload'] = true;
		}

		Delete::run($actions);

		return [
			'deleted' => true,
			'message' => __('The SpeedyCache cache has been purged.', 'speedycache'),
		];
	}

	/**
	 * Execute callback for speedycache-cache/delete-url.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function delete_url_cache($input){
		$input = is_array($input) ? $input : [];
		$urls = isset($input['urls']) ? $input['urls'] : [];

		if(empty($urls)){
			return new \WP_Error('invalid_urls', __('At least one URL is required.', 'speedycache'));
		}

		$urls = is_array($urls) ? $urls : [$urls];
		$clean = [];
		foreach($urls as $url){
			$url = esc_url_raw($url);
			if(!empty($url)){
				$clean[] = $url;
			}
		}

		if(empty($clean)){
			return new \WP_Error('invalid_urls', __('No valid URLs were provided.', 'speedycache'));
		}

		Delete::url($clean);

		global $speedycache;
		if(!empty($speedycache->options['preload'])){
			Preload::url($clean);
		}

		return [
			'deleted' => true,
			'count' => count($clean),
			'message' => sprintf(_n('Cache purged for %d URL.', 'Cache purged for %d URLs.', count($clean), 'speedycache'), count($clean)),
		];
	}

	/**
	 * Execute callback for speedycache-cache/delete-post.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function delete_post_cache($input){
		$input = is_array($input) ? $input : [];
		$post_id = isset($input['post_id']) ? (int)$input['post_id'] : 0;

		if($post_id < 0){
			return new \WP_Error('invalid_post_id', __('A valid post ID is required (0 for the homepage).', 'speedycache'));
		}

		Delete::cache($post_id);

		return [
			'deleted' => true,
			'message' => sprintf(__('Cache purged for post ID %d.', 'speedycache'), $post_id),
		];
	}

	/**
	 * Execute callback for speedycache-cache/get-stats.
	 *
	 * @return array
	 */
	public static function get_cache_stats(){
		$html_files = (int) get_option('speedycache_html', 0);
		$html_size = (int) get_option('speedycache_html_size', 0);

		return [
			'html_files' => $html_files,
			'html_size_kb' => round($html_size / 1000, 2),
		];
	}

	// =========================================================================
	// Execute callbacks — File optimization
	// =========================================================================

	/**
	 * Execute callback for speedycache-file/get.
	 *
	 * @return array
	 */
	public static function get_file_settings(){
		$options = get_option('speedycache_options', []);

		return [
			'minify_html' => !empty($options['minify_html']),
			'minify_css' => !empty($options['minify_css']),
			'combine_css' => !empty($options['combine_css']),
			'critical_css' => !empty($options['critical_css']),
			'unused_css' => !empty($options['unused_css']),
			'minify_js' => !empty($options['minify_js']),
			'combine_js' => !empty($options['combine_js']),
			'delay_js' => !empty($options['delay_js']),
			'delay_js_mode' => isset($options['delay_js_mode']) ? $options['delay_js_mode'] : null,
			'render_blocking' => !empty($options['render_blocking']),
			'critical_images' => !empty($options['critical_images']),
			'disable_emojis' => !empty($options['disable_emojis']),
			'lazy_load' => !empty($options['lazy_load']),
			'speculation_loading' => !empty($options['speculation_loading']),
		];
	}

	// =========================================================================
	// Execute callbacks — CDN
	// =========================================================================

	/**
	 * Execute callback for speedycache-cdn/get.
	 *
	 * @return array
	 */
	public static function get_cdn_settings(){
		$cdn = get_option('speedycache_cdn', []);

		return [
			'enabled' => !empty($cdn['enabled']),
			'cdn_type' => isset($cdn['cdn_type']) ? $cdn['cdn_type'] : null,
			'cdn_key' => isset($cdn['cdn_key']) ? $cdn['cdn_key'] : null,
			'enabled_cloudflare'=> !empty($cdn['enabled_cloudflare']),
			'cdn_url' => isset($cdn['cdn_url']) ? $cdn['cdn_url'] : null,
			'file_types' => isset($cdn['file_types']) ? $cdn['file_types'] : null,
			'keywords' => isset($cdn['keywords']) ? $cdn['keywords'] : null,
			'excludekeywords' => isset($cdn['excludekeywords']) ? $cdn['excludekeywords'] : null,
		];
	}

	// =========================================================================
	// Execute callbacks — Excludes
	// =========================================================================

	/**
	 * Execute callback for speedycache-excludes/list.
	 *
	 * @return array
	 */
	public static function list_excludes(){
		$excludes = get_option('speedycache_exclude', []);

		$list = [];
		if(!empty($excludes) && is_array($excludes)){
			foreach($excludes as $rule){
				$list[] = [
					'type' => isset($rule['type']) ? $rule['type'] : '',
					'prefix' => isset($rule['prefix']) ? $rule['prefix'] : '',
					'content' => isset($rule['content']) ? $rule['content'] : null,
				];
			}
		}

		return [
			'excludes' => $list,
			'total' => count($list),
		];
	}

	/**
	 * Execute callback for speedycache-excludes/delete.
	 *
	 * @param array $input
	 * @return array|\WP_Error
	 */
	public static function delete_exclude_rule($input){
		$input = is_array($input) ? $input : [];
		$rule_id = isset($input['rule_id']) ? (int)$input['rule_id'] : -1;

		if($rule_id < 0){
			return new \WP_Error('invalid_rule_id', __('A valid rule ID is required.', 'speedycache'));
		}

		$excludes = get_option('speedycache_exclude', []);

		if(empty($excludes) || !is_array($excludes)){
			return new \WP_Error('no_excludes', __('The exclude rule list is empty.', 'speedycache'));
		}

		if(!isset($excludes[$rule_id])){
			return new \WP_Error('not_found', __('There is no rule with the given rule id.', 'speedycache'));
		}

		unset($excludes[$rule_id]);

		update_option('speedycache_exclude', $excludes);

		if(class_exists('\SpeedyCache\Util')){
			Util::set_config_file();
		}

		return [
			'deleted' => true,
			'message' => __('The exclude rule has been deleted.', 'speedycache'),
		];
	}

	// =========================================================================
	// Execute callbacks — Preload
	// =========================================================================

	/**
	 * Execute callback for speedycache-preload/status.
	 *
	 * @return array
	 */
	public static function preload_status(){
		global $speedycache;

		$enabled = !empty($speedycache->options['preload']);
		$urls = get_transient('speedycache_preload_transient');
		$urls = is_array($urls) ? $urls : [];

		return [
			'enabled' => $enabled,
			'queued' => count($urls),
			'urls' => array_values($urls),
		];
	}

	/**
	 * Execute callback for speedycache-preload/build.
	 *
	 * @return array
	 */
	public static function build_preload(){
		global $speedycache;

		if(empty($speedycache->options['status'])){
			return [
				'started' => false,
				'message' => __('Caching is disabled, enable it before building the preload list.', 'speedycache'),
			];
		}

		Preload::build_preload_list();

		return [
			'started' => true,
			'message' => __('The preload list has been built and the preload cron has been scheduled.', 'speedycache'),
		];
	}

	// =========================================================================
	// Execute callbacks — Import / Export
	// =========================================================================

	/**
	 * Execute callback for speedycache-import/export.
	 *
	 * @return array
	 */
	public static function export_settings(){
		$object_cache = get_option('speedycache_object_cache');
		if(is_array($object_cache)){
			$object_cache['hashed_prefix'] = null;
		}

		$settings = [
			'speedycache_options' => get_option('speedycache_options'),
			'speedycache_cdn' => get_option('speedycache_cdn'),
			'speedycache_img' => get_option('speedycache_img'),
			'speedycache_object_cache' => $object_cache,
			'speedycache_exclude' => get_option('speedycache_exclude'),
			'speedycache_bloat' => get_option('speedycache_bloat'),
		];

		return [
			'settings' => $settings,
			'json' => wp_json_encode($settings),
		];
	}
}