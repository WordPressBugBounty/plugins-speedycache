<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT!');
}

use \SpeedyCache\Util;

class Ajax{
	
	static function hooks(){
		add_action('wp_ajax_speedycache_delete_page_cache', '\SpeedyCache\Ajax::delete_page_cache');
		add_action('wp_ajax_speedycache_save_cache_settings', '\SpeedyCache\Ajax::save_cache_settings');
		add_action('wp_ajax_speedycache_save_file_settings', '\SpeedyCache\Ajax::save_file_settings');
		add_action('wp_ajax_speedycache_save_preload_settings', '\SpeedyCache\Ajax::save_preload_settings');
		add_action('wp_ajax_speedycache_save_media_settings', '\SpeedyCache\Ajax::save_media_settings');
		add_action('wp_ajax_speedycache_save_cdn_settings', '\SpeedyCache\Ajax::save_cdn_settings');
		add_action('wp_ajax_speedycache_test_pagespeed', '\SpeedyCache\Ajax::test_pagespeed');
		add_action('wp_ajax_speedycache_save_excludes', '\SpeedyCache\Ajax::save_excludes');
		add_action('wp_ajax_speedycache_delete_exclude_rule', '\SpeedyCache\Ajax::delete_exclude_rule');
		add_action('wp_ajax_speedycache_save_deletion_role_settings', '\SpeedyCache\Ajax::save_deletion_roles');
		add_action('wp_ajax_speedycache_import_settings', '\SpeedyCache\Ajax::import_settings');
		add_action('wp_ajax_speedycache_export_settings', '\SpeedyCache\Ajax::export_settings');
		add_action('wp_ajax_speedycache_close_update_notice', '\SpeedyCache\Ajax::close_update_notice');
		add_action('wp_ajax_speedycache_reset_settings', '\SpeedyCache\Ajax::reset_settings');
		add_action('wp_ajax_speedycache_save_ai_abilities', '\SpeedyCache\Ajax::save_ai_abilities');
		add_action('wp_ajax_speedycache_install_mcp_adapter', '\SpeedyCache\Ajax::install_mcp_adapter');
		add_action('wp_ajax_speedycache_generate_app_password', '\SpeedyCache\Ajax::generate_app_password');
		add_action('wp_ajax_speedycache_test_mcp_connection', '\SpeedyCache\Ajax::test_mcp_connection');
		add_action('wp_ajax_speedycache_save_test_status', '\SpeedyCache\Ajax::save_test_status');

		// This is just to make sure, close of update notice works.
		if(isset($_GET['action']) && 'speedycache_close_update_notice' === sanitize_text_field(wp_unslash($_GET['action']))){
			add_filter('softaculous_plugin_update_notice', '\SpeedyCache\Admin::update_notice_filter');
		}

		if(defined('SPEEDYCACHE_PRO')){
			add_action('wp_ajax_speedycache_optm_db', '\SpeedyCache\Ajax::optm_db');
			add_action('wp_ajax_speedycache_flush_objects', '\SpeedyCache\Ajax::flush_objs');
			add_action('wp_ajax_speedycache_save_object_settings', '\SpeedyCache\Ajax::save_object_settings');
			add_action('wp_ajax_speedycache_save_bloat_settings', '\SpeedyCache\Ajax::save_bloat_settings');
			add_action('wp_ajax_speedycache_preloading_add_settings', '\SpeedyCache\Ajax::add_preload_settings');
			add_action('wp_ajax_speedycache_preloading_delete_resource', '\SpeedyCache\Ajax::delete_preload_resource');

			// Critical CSS
			add_action('wp_ajax_speedycache_critical_css', '\SpeedyCache\Ajax::generate_critical_css');
		}
	}

	static function delete_page_cache(){
		check_ajax_referer('speedycache_ajax_nonce');

		$page_id = Util::sanitize_get('page_id');
		
		if(empty($page_id)){
			wp_send_json_error(__('Can not delete cache of this page as the page ID is empty', 'speedycache'));
		}

	}

	static function save_cache_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;

		$options = get_option('speedycache_options');

		$options['status'] = isset($_REQUEST['status']);
		$options['preload'] = isset($_REQUEST['preload']);
		$options['preload_interval'] = (int) Util::sanitize_request('preload_interval', 0);
		$options['logged_in_user'] = isset($_REQUEST['logged_in_user']);
		$options['mobile'] = isset($_REQUEST['mobile']);
		$options['mobile_theme'] = isset($_REQUEST['mobile_theme']);
		$options['lbc'] = isset($_REQUEST['lbc']);
		$options['gzip'] = isset($_REQUEST['gzip']);
		$options['purge_varnish'] = isset($_REQUEST['purge_varnish']);
		$options['varniship'] = !empty($_REQUEST['varniship']) ? Util::sanitize_request('varniship') : '';
		$options['purge_interval'] = (int) Util::sanitize_request('purge_interval', 0);
		$options['purge_interval_unit'] = Util::sanitize_request('purge_interval_unit', 'days');
		$options['purge_enable_exact_time'] = isset($_REQUEST['purge_enable_exact_time']);
		$options['purge_exact_time'] = Util::sanitize_request('purge_exact_time', 0);
		$options['auto_purge_fonts'] = isset($_REQUEST['auto_purge_fonts']);
		$options['auto_purge_gravatar'] = isset($_REQUEST['auto_purge_gravatar']);
		$options['disable_webp'] = isset($_REQUEST['disable_webp']);

		wp_clear_scheduled_hook('speedycache_purge_cache');
		wp_clear_scheduled_hook('speedycache_preload');

		$speedycache->options = $options;
		update_option('speedycache_options', $options);

		\SpeedyCache\Htaccess::init();
		\SpeedyCache\Install::set_advanced_cache();
		Util::set_config_file(); // Updates the config file

		wp_send_json_success();
	}
	
	static function save_file_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		$options = get_option('speedycache_options', []);
		
		// CSS options
		$options['minify_html'] = isset($_REQUEST['minify_html']);
		$options['minify_css'] = isset($_REQUEST['minify_css']);
		$options['combine_css'] = isset($_REQUEST['combine_css']);

		if(defined('SPEEDYCACHE_PRO')){
			$options['unused_css'] = isset($_REQUEST['unused_css']);
			$options['critical_css'] = isset($_REQUEST['critical_css']);
			$options['unusedcss_load'] = Util::sanitize_request('unusedcss_load');
			$options['unused_css_exclude_stylesheets'] = !empty($_REQUEST['unused_css_exclude_stylesheets']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['unused_css_exclude_stylesheets']))) : [];
			$options['unusedcss_include_selector'] = !empty($_REQUEST['unusedcss_include_selector']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['unusedcss_include_selector']))) : [];
		}

		// JS options
		$options['minify_js'] = isset($_REQUEST['minify_js']);
		$options['combine_js'] = isset($_REQUEST['combine_js']);
		$options['delay_js'] = isset($_REQUEST['delay_js']);
		$options['delay_js_mode'] = isset($_REQUEST['delay_js_mode']) ? Util::sanitize_request('delay_js_mode') : '';
		$options['delay_js_excludes'] = !empty($_REQUEST['delay_js_excludes']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['delay_js_excludes']))) : [];
		$options['delay_js_scripts'] = !empty($_REQUEST['delay_js_scripts']) ? array_unique(array_map('trim', explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['delay_js_scripts']))))) : [];
		$options['render_blocking'] = isset($_REQUEST['render_blocking']);
		$options['render_blocking_excludes'] = isset($_REQUEST['render_blocking_excludes']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['render_blocking_excludes']))) : [];
		$options['disable_emojis'] = isset($_REQUEST['disable_emojis']);
		$options['lazy_load_html'] = isset($_REQUEST['lazy_load_html']);

		if(isset($_REQUEST['lazy_load_html_elements'])){
			$options['lazy_load_html_elements'] = !empty($_REQUEST['lazy_load_html_elements']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['lazy_load_html_elements']))) : [];
		}

		$speedycache->options = $options;
		update_option('speedycache_options', $options);
		
		wp_send_json_success();
	}
	
	static function save_preload_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		$options = get_option('speedycache_options');
		
		$options['critical_images'] = isset($_REQUEST['critical_images']);
		$options['critical_image_count'] = isset($_REQUEST['critical_images']) ? Util::sanitize_request('critical_image_count') : '';
		$options['instant_page'] = isset($_REQUEST['instant_page']);
		$options['speculation_loading'] = isset($_REQUEST['speculation_loading']);
		$options['speculation_mode'] = Util::sanitize_request('speculation_mode', 0);
		$options['speculation_eagerness'] = Util::sanitize_request('speculation_eagerness', 0);
		$options['dns_prefetch'] = isset($_REQUEST['dns_prefetch']);
		if(!empty($_REQUEST['dns_urls'])){
			$options['dns_urls'] = explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['dns_urls'])));
		}

		$options['preload_resources'] = isset($_REQUEST['preload_resources']);
		$options['pre_connect'] = isset($_REQUEST['pre_connect']);
		
		// TODO: here more options will be added after all modals have been added.
		$speedycache->options = $options;
		update_option('speedycache_options', $options);
		
		wp_send_json_success();
	}
	
	static function save_media_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		$options = get_option('speedycache_options');
		$options['gravatar_cache'] = isset($_REQUEST['gravatar_cache']);
		$options['lazy_load'] = isset($_REQUEST['lazy_load']);
		$options['lazy_load_placeholder'] = Util::sanitize_request('lazy_load_placeholder');
		
		if(isset($_REQUEST['lazy_load_placeholder_custom_url'])){
			$options['lazy_load_placeholder_custom_url'] = !empty($_REQUEST['lazy_load_placeholder_custom_url']) ? sanitize_url(wp_unslash($_REQUEST['lazy_load_placeholder_custom_url'])) : '';
		}
		
		if(isset($_REQUEST['exclude_above_fold'])){
			$options['exclude_above_fold'] = !empty($_REQUEST['exclude_above_fold']) ? Util::sanitize_request('exclude_above_fold') : '';
		}
		
		if(isset($_REQUEST['lazy_load_keywords'])){
			$options['lazy_load_keywords'] = !empty($_REQUEST['lazy_load_keywords']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['lazy_load_keywords']))) : [];
		}
		
		$options['image_dimensions'] = isset($_REQUEST['image_dimensions']);
		$options['local_gfonts'] = isset($_REQUEST['local_gfonts']);
		$options['google_fonts'] = isset($_REQUEST['google_fonts']);
		$options['font_rendering'] = isset($_REQUEST['font_rendering']);

		// TODO: here more options will be added after all modals have been added.
		$speedycache->options = $options;
		update_option('speedycache_options', $options);
		
		wp_send_json_success();
		
	}
	
	static function save_object_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		if(!class_exists('Redis')){
			wp_send_json_error(__('phpRedis Library not found', 'speedycache'));
			return;
		}

		$options = get_option('speedycache_object_cache', []);
		$options['enable'] = isset($_REQUEST['enable_object']);
		$options['host'] = Util::sanitize_request('host');
		$options['port'] = Util::sanitize_request('port');
		$options['username'] = Util::sanitize_request('username');
		$options['password'] = Util::sanitize_request('password');
		$options['hashed_prefix'] = substr(md5(site_url()), 0, 12);
		$options['ttl'] = Util::sanitize_request('ttl', 0);
		$options['db-id'] = Util::sanitize_request('db-id', 0);
		$options['persistent'] = isset($_REQUEST['persistent']);
		$options['admin'] = isset($_REQUEST['admin']);
		$options['async_flush'] = isset($_REQUEST['async_flush']);
		$options['serialization'] = Util::sanitize_request('serialization');
		$options['compress'] = Util::sanitize_request('compress');
		$options['non_cache_group'] = !empty($_REQUEST['non_cache_group']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['non_cache_group']))) : [];
	
		// Sanitize and store non-cache group as an array.
		if(!empty($_REQUEST['non_cache_group'])){
			$raw_input = sanitize_textarea_field( wp_unslash( $_REQUEST['non_cache_group'] ) );
			$groups_array = preg_split( '/\r\n|\r|\n/', $raw_input );
			$options['non_cache_group'] = array_values( array_filter( array_map( 'trim', $groups_array ) ) );
		}else {
   			$options['non_cache_group'] = [];
		}

		$speedycache->object = $options;
		
		if(!empty($speedycache->object['enable'])){
			\SpeedyCache\ObjectCache::update_file();
		} else {
			if(file_exists(WP_CONTENT_DIR . '/object-cache.php')){
				unlink(WP_CONTENT_DIR . '/object-cache.php');
			}
		}
		
		// If we are disabling object cache then it should be saved early as there could be issue connecting the redis server.
		if(empty($options['enable'])){
			update_option('speedycache_object_cache', $options);
		}
		
		try{
			\SpeedyCache\ObjectCache::boot($speedycache->object);
		} catch(\Exception $e) {
			$speedycache->object['enable'] = false;
			file_put_contents(\SpeedyCache\ObjectCache::$conf_file, '<?php exit(); '."\n".json_encode($speedycache->object));
			wp_send_json_error($e->getMessage());
			return;
		}
		
		// Updating the 
		if(!file_put_contents(\SpeedyCache\ObjectCache::$conf_file, '<?php exit(); '."\n".json_encode($speedycache->object))){
			wp_send_json_error(__('Unable to modify Object Cache Conf file, the issue might be related to permission on your server.', 'speedycache'));
			return;
		}

		\SpeedyCache\ObjectCache::flush_db();
		\SpeedyCache\ObjectCache::$instance = null;
		
		update_option('speedycache_object_cache', $options); // We need to update at last only after object cache can be enabled.

		wp_send_json_success();
	}
	
	static function save_cdn_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		$options = get_option('speedycache_cdn', []);
		if(!is_array($options)){
			$options = [];
		}

		$options['enabled'] = isset($_REQUEST['enable_cdn']);
		$options['cdn_type'] = Util::sanitize_request('cdn_type');
		$options['cdn_key'] = sanitize_text_field(wp_unslash($_REQUEST['cdn_key']));
		$options['enabled_cloudflare'] = isset($_REQUEST['enabled_cloudflare']);
		$options['cdn_url'] = sanitize_url(wp_unslash($_REQUEST['cdn_url']));
		$options['excludekeywords'] = !empty($_REQUEST['excludekeywords']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['excludekeywords']))) : [];
		$options['file_types'] = !empty($_REQUEST['file_types']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['file_types']))) : [];
		$options['keywords'] = !empty($_REQUEST['keywords']) ? explode("\n", sanitize_textarea_field(wp_unslash($_REQUEST['keywords']))) : [];
		
		if(!empty($options['file_types'])){
			$options['file_types'] = map_deep($options['file_types'], 'trim');
		}
		
		if(!empty($options['keywords'])){
			$options['keywords'] = map_deep($options['keywords'], 'trim');
		}
		
		if(!empty($options['excludekeywords'])){
			$options['excludekeywords'] = map_deep($options['excludekeywords'], 'trim');
		}

		// Fetching the Zone/Pull ID's
		if($options['cdn_type'] === 'bunny' && !empty($options['cdn_key'])){
			$pull_id = \SpeedyCache\CDN::bunny_get_pull_id($options);
			
			if(!empty($pull_id) && !is_array($pull_id)){
				$options['bunny_pull_id'] = $pull_id;
			}
		}else if($options['cdn_type'] === 'cloudflare' && !empty($options['cdn_key'])){
			$zone_id = \SpeedyCache\CDN::cloudflare_zone_id($options);
			
			if(!empty($zone_id)){
				$options['cloudflare_zone_id'] = $zone_id;
			}
		}

		update_option('speedycache_cdn', $options);
		
		
		$speedycache->cdn = $options;
		
		do_action('speedycache_after_cdn_save');
		
		if(!empty($speedycache->cdn['error'])){
			wp_send_json_error(esc_html($speedycache->cdn['error']));
		}

		wp_send_json_success();
	}
	
	static function save_bloat_settings(){
		check_ajax_referer('speedycache_ajax_nonce');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;
		
		$options = get_option('speedycache_bloat', []);
		$options['disable_xmlrpc'] = isset($_REQUEST['disable_xmlrpc']);
		$options['remove_gfonts'] = isset($_REQUEST['remove_gfonts']);
		$options['disable_jmigrate'] = isset($_REQUEST['disable_jmigrate']);
		$options['disable_dashicons'] = isset($_REQUEST['disable_dashicons']);
		$options['disable_gutenberg'] = isset($_REQUEST['disable_gutenberg']);
		$options['disable_block_css'] = isset($_REQUEST['disable_block_css']);
		$options['disable_oembeds'] = isset($_REQUEST['disable_oembeds']);
		$options['disable_cart_fragment'] = isset($_REQUEST['disable_cart_fragment']);
		$options['disable_woo_assets'] = isset($_REQUEST['disable_woo_assets']);
		$options['disable_rss'] = isset($_REQUEST['disable_rss']);
		$options['update_heartbeat'] = isset($_REQUEST['update_heartbeat']);
		$options['heartbeat_frequency'] = Util::sanitize_request('heartbeat_frequency');
		$options['disable_heartbeat'] = Util::sanitize_request('disable_heartbeat');
		$options['limit_post_revision'] = isset($_REQUEST['limit_post_revision']);
		$options['post_revision_count'] = Util::sanitize_request('post_revision_count');

		$speedycache->bloat = $options;
		update_option('speedycache_bloat', $options);

		wp_send_json_success();
		
	}
	
	// Adds settings of Preload and preconnect options.
	static function add_preload_settings(){
		check_ajax_referer('speedycache_ajax_nonce', 'security');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error('Must be admin');
		}
		
		global $speedycache;
		
		if(empty($_REQUEST['type'])){
			wp_send_json_error('Unable to find the settings type');
		}
		
		$type = sanitize_text_field(wp_unslash($_REQUEST['type']));

		if(!in_array($type, ['pre_connect_list', 'preload_resource_list'])){
			wp_send_json_error(__('Could not figure out type of the setting being saved!', 'speedycache'));
		}

		if(empty($_REQUEST['settings'])){
			wp_send_json_error(__('No settings provided to save', 'speedycache'));
		}
		
		if(empty($speedycache->options[$type])){
			$speedycache->options[$type] = [];
		}
		
		$settings = [];
		if(empty($_REQUEST['settings']['resource'])){
			wp_send_json_error(__('No resource provided!', 'speedycache'));
		}

		$settings['resource'] = sanitize_url(wp_unslash($_REQUEST['settings']['resource']));
		$settings['crossorigin'] = isset($_REQUEST['settings']['crossorigin']);
		$settings['type'] = sanitize_text_field(wp_unslash($_REQUEST['settings']['type']));
		if(!empty($_REQUEST['settings']['fetch_priority'])){
			$settings['fetch_priority'] = sanitize_text_field(wp_unslash($_REQUEST['settings']['fetch_priority']));
		}
		
		if(!empty($_REQUEST['settings']['device'])){
			$settings['device'] = sanitize_text_field(wp_unslash($_REQUEST['settings']['device']));
		}
		
		$pages_string = $_REQUEST['settings']['preload_resource_pages'];
		$pages = [];
		if(!empty($pages_string)){
			$pages = map_deep(explode("\n", $pages_string), 'trim');
			if(!empty($pages) && is_array($pages)){
				$settings['pages'] = map_deep(wp_unslash($pages), 'sanitize_url');
			}
		}

		if(empty($speedycache->options[$type])){
			$speedycache->options[$type] = [];
			$speedycache->options[$type][] = $settings;
			update_option('speedycache_options', $speedycache->options);
			$index = key(array_slice($speedycache->options[$type], -1, 1, true)); // Getting the index we just added
			wp_send_json_success($index);
		}
		
		foreach($speedycache->options[$type] as $pre_connect){
			if($pre_connect['resource'] == $settings['resource']){
				wp_send_json_error(__('This resource has already been added before', 'speedycache'));
			}
		}

		$speedycache->options[$type][] = $settings;
		update_option('speedycache_options', $speedycache->options);
		$index = key(array_slice($speedycache->options[$type], -1, 1, true)); // Getting the index we just added

		wp_send_json_success($index);

	}

	static function delete_preload_resource(){
		check_ajax_referer('speedycache_ajax_nonce', 'security');

		if(!current_user_can('manage_options')){
			wp_send_json_error('Must be admin');
		}
		
		global $speedycache;

		if(!isset($_REQUEST['type']) || !isset($_REQUEST['key']) || $_REQUEST['key'] == NULL){
			wp_send_json_error('Key or Type is empty so can not delete this resource');
		}

		$type = isset($_REQUEST['type']) ? sanitize_text_field(wp_unslash($_REQUEST['type'])) : '';
		$key = isset($_REQUEST['key']) ? sanitize_text_field(wp_unslash($_REQUEST['key'])) : '';

		if(!in_array($type, ['pre_connect_list', 'preload_resource_list'])){
			wp_send_json_error('Could not figure out type of the resource being deleted!');
		}

		if(empty($speedycache->options[$type])){
			wp_send_json_error('Nothing there to delete');
		}
		
		if(array_key_exists($key, $speedycache->options[$type])){
			unset($speedycache->options[$type][$key]);
			update_option('speedycache_options', $speedycache->options);
		}

		wp_send_json_success();
	}
	
	static function test_pagespeed(){

		check_ajax_referer('speedycache_ajax_nonce', 'security');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		$url = home_url();
		
		if(empty($url)){
			wp_send_json_error('Your site does not have a home URL');
		}

		$api_url = SPEEDYCACHE_API . 'pagespeed.php?url='. $url; 

		$res = wp_remote_post($api_url, array(
			'sslverify' => false,
			'timeout' => 60
		));
		
		if(empty($res) || is_wp_error($res)){
			if($res->get_error_message()){
				wp_send_json_error($res->get_error_message());
			}

			wp_send_json_error('The response turned out to be empty');
		}

		if(empty($res['body'])){
			wp_send_json_error('The response body is empty');
		}
		
		$body = json_decode($res['body'], 1);
		
		if(empty($body['success'])){
			wp_send_json_error($res['body']);
		}
		
		if(empty($body['results'])){
			wp_send_json_error('Result is empty');
		}

		// Normalize the score to an integer
		if(!empty($body['results']['score'])){
			$body['results']['score'] = (int) round((float) $body['results']['score']);
		}
		//Saving the pagespeed test
		update_option('speedycache_pagespeed_test', $body['results'], false);

		$body['results']['color'] = \SpeedyCache\Util::pagespeed_color($body['results']['score']);

		// We will now need to filter the output.
		wp_send_json_success($body['results']);
	}
	
	static function save_excludes(){

		check_ajax_referer('speedycache_ajax_nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		if(empty($_REQUEST['type'])){
			wp_send_json_error(__('You need to select a Exclude type', 'speedycache'));
		}
		
		if(empty($_REQUEST['prefix'])){
			wp_send_json_error(__('You have not selected, the exclude option', 'speedycache'));
		}
		
		$type = Util::sanitize_request('type');
		$prefix = Util::sanitize_request('prefix');
		
		$single_prefixes = ['homepage', 'category', 'tag', 'post', 'page', 'archive', 'attachment', 'woocommerce_items_in_cart', 'post_id'];
		
		if(empty($_REQUEST['content']) && !in_array($prefix, $single_prefixes)){
			wp_send_json_error(__('You need to fill the content field', 'speedycache'));
		}
		
		$excludes = get_option('speedycache_exclude', []);
		
		$rule['type'] = $type;
		$rule['prefix'] = $prefix;
		$rule['content'] = !empty($_REQUEST['content']) ? Util::sanitize_request('content') : '';
		if($type == 'post_id' && !empty($rule['content'])){
			$rule['content'] = explode(',', $rule['content']);
		}

		array_push($excludes, $rule);
		
		update_option('speedycache_exclude', $excludes);
		Util::set_config_file(); // Updates the config file

		wp_send_json_success();
	}

	static function validate_and_sanitize_import_data($data) {
		if(is_array($data)){
			$sanitized = [];
			foreach($data as $key => $value){
				$sanitized_key = is_string($key) ? sanitize_key($key) : $key;
				$sanitized[$sanitized_key] = self::validate_and_sanitize_import_data($value);
			}
			return $sanitized;
		} elseif (is_string($data)){
			if(filter_var($data, FILTER_VALIDATE_URL)){
				return sanitize_url(wp_unslash($data));
			}

			return sanitize_text_field(wp_unslash($data));
		} elseif(is_bool($data) || is_null($data) || is_int($data) || is_float($data)){
			return $data;
		}

		return '';
	}

	static function import_settings(){

		check_ajax_referer('speedycache_ajax_nonce', 'security');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permissions.', 'speedycache'));
		}

		if(!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK){
			wp_send_json_error(__('Failed to receive uploaded file.', 'speedycache'));
		}
		
		$filename = sanitize_file_name($_FILES['file']['name']);
		
		if(!preg_match('/\.json$/', $filename)){
			wp_send_json_error(__('The file you uploaded is not a JSON file.', 'speedycache'));
		}

		if(!preg_match('/speedycache-settings-\d{4}-\d{2}-\d{2}.*\.json/', $filename)){
			wp_send_json_error(__('File name is not of expected format.', 'speedycache'));
		}
		
		$imported_file = $_FILES['file']['tmp_name'];
		
		if(!file_exists($imported_file) || !is_readable($imported_file) || !is_uploaded_file($imported_file)){
			wp_send_json_error(__('Uploaded file is not readable.', 'speedycache'));
		}

		$file_contents = file_get_contents($imported_file);
		$decoded_data = json_decode($file_contents, true);

		if(empty($decoded_data) || !is_array($decoded_data)){
			wp_send_json_error(__('Invalid JSON file.', 'speedycache'));
		}

		$current_oc = get_option('speedycache_object_cache');
		$imported_oc = $decoded_data['speedycache_object_cache'] ? $decoded_data['speedycache_object_cache'] : false;

		$current_enabled = (is_array($current_oc) && !empty($current_oc['enable']));
		$import_enabled  = (is_array($imported_oc) && !empty($imported_oc['enable']));

		if($current_enabled && $import_enabled){
			$imported_oc['hashed_prefix'] = $current_oc['hashed_prefix'];
		} else {
			$imported_oc['hashed_prefix'] = null;
		}

		$decoded_data['speedycache_object_cache'] = $imported_oc;

		$valid_keys = array(
			'speedycache_options',
			'speedycache_cdn',
			'speedycache_img',
			'speedycache_object_cache',
			'speedycache_exclude',
			'speedycache_bloat',
		);

		foreach($valid_keys as $key){
			if (isset($decoded_data[$key]) && $decoded_data[$key] !== false) {
				update_option($key, self::validate_and_sanitize_import_data($decoded_data[$key]));
			}
			else {
				delete_option($key);
			}
		}

		wp_send_json_success();
	}

	static function export_settings(){

		check_ajax_referer('speedycache_ajax_nonce', 'security');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permissions.', 'speedycache'));
		}

		$object_cache = get_option('speedycache_object_cache');
		if(is_array($object_cache)){
			$object_cache['hashed_prefix'] = null;
		}

		$export_data = array(
			'speedycache_options' => get_option('speedycache_options'),
			'speedycache_cdn' => get_option('speedycache_cdn'),
			'speedycache_img' => get_option('speedycache_img'),
			'speedycache_object_cache' => $object_cache,
			'speedycache_exclude' => get_option('speedycache_exclude'),
			'speedycache_bloat' => get_option('speedycache_bloat'),
		);

		wp_send_json_success($export_data);
	}
	
	static function save_deletion_roles(){

		check_ajax_referer('speedycache_ajax_nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		$roles = [];
		if(!empty($_POST['cache_deletion_roles'])){
			$roles = Util::sanitize_post('cache_deletion_roles');
		}

		update_option('speedycache_deletion_roles', $roles);
		wp_send_json_success();
	}

	static function reset_settings(){

		check_ajax_referer('speedycache_ajax_nonce', 'nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}

		global $speedycache;

		// Restore the factory defaults set during activation (see \SpeedyCache\Install::activate).
		$default_options = [
			'lbc' => true,
			'gzip' => true,
			'minify_css' => true,
			'minify_html' => true,
			'minify_js' => true
		];

		// Remove all custom options so the rest fall back to their unchecked/default state.
		delete_option('speedycache_options');
		update_option('speedycache_options', $default_options);

		// Reset the auxiliary settings groups to a clean state.
		delete_option('speedycache_cdn');
		delete_option('speedycache_exclude');
		delete_option('speedycache_bloat');
		delete_option('speedycache_deletion_roles');
		delete_option('speedycache_pagespeed_test');

		if(defined('SPEEDYCACHE_PRO')){
			delete_option('speedycache_img');
		}

		// Clear any scheduled cron events so they do not run with stale configuration.
		wp_clear_scheduled_hook('speedycache_purge_cache');
		wp_clear_scheduled_hook('speedycache_preload');
		wp_clear_scheduled_hook('speedycache_preload_split');
		wp_clear_scheduled_hook('speedycache_optimize_db');

		// Refresh the in-memory global so the rest of this request sees the defaults.
		if(empty($speedycache)){
			$speedycache = new \SpeedyCache();
		}

		$speedycache->options = $default_options;
		$speedycache->cdn = [];
		$speedycache->bloat = [];

		// Rebuild the htaccess / config files to match the defaults.
		if(class_exists('\SpeedyCache\Htaccess')){
			\SpeedyCache\Htaccess::init();
		}

		if(class_exists('\SpeedyCache\Util')){
			Util::set_config_file();
		}

		wp_send_json_success(__('Settings have been reset to default.', 'speedycache'));
	}
	
	static function delete_exclude_rule(){

		check_ajax_referer('speedycache_ajax_nonce');

		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		if(!isset($_REQUEST['rule_id'])){
			wp_send_json_error(__('No rule ID provided to delete', 'speedycache'));
		}
		
		$excludes = get_option('speedycache_exclude', []);
		
		if(empty($excludes)){
			wp_send_json_error(__('Exclude rule list is already empty', 'speedycache'));
		}
		
		$rule_id = sanitize_text_field(wp_unslash($_REQUEST['rule_id']));
		
		if(!isset($excludes[$rule_id])){
			wp_send_json_error(__('There is not rule with the given rule id', 'speedycache'));
		}

		unset($excludes[$rule_id]);

		// TODO: updating the htaccess to include the excludes.
		update_option('speedycache_exclude', $excludes);
		
		wp_send_json_success();
	}
	
	static function optm_db(){
		check_ajax_referer('speedycache_ajax_nonce', 'security');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		if(!isset($_REQUEST['db_action'])){
			wp_send_json_error(__('No Database optimization action present', 'speedycache'));
		}
		
		$db_action = \SpeedyCache\Util::sanitize_request('db_action');
		
		if(!defined('SPEEDYCACHE_PRO')){
			wp_send_json_error(__('This is a Pro feature you can not use this with a Free version', 'speedycache'));
		}

		\SpeedyCache\DB::clean($db_action);
	}
	
	static function flush_objs(){
		check_ajax_referer('speedycache_ajax_nonce', 'security');
		
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}
		
		global $speedycache;

		if(empty($speedycache->object['enable'])){
			wp_send_json_error(__('Object Cache need to be enabled for it to be flushed', 'speedycache'));
		}

		try{
			\SpeedyCache\ObjectCache::boot();
		} catch(\Exception $e){
			wp_send_json_error($e->getMessage());
		}
		
		$res = \SpeedyCache\ObjectCache::flush_db();

		if(!empty($res)){
			wp_send_json_success();
		}
	}

	static function generate_critical_css(){
		check_ajax_referer('speedycache_ajax_nonce', 'security');
	
		if(!current_user_can('manage_options')){
			wp_die('Must be admin');
		}

		global $speedycache;
		
		if(!class_exists('\SpeedyCache\CriticalCss')){
			wp_send_json_error(array('message' => 'Your SpeedyCache Pro does not have the required file to run Critical CSS'));
		}
		
		if(empty($speedycache->license['license'])){
			wp_send_json_error(array('message' => 'You have not linked your License, please do it before creating Critical CSS'));
		}

		$urls = \SpeedyCache\CriticalCss::get_url_list();

		if(empty($urls)){
			wp_send_json_error(array('message' => 'No URL found to create critical CSS'));
		}
		
		\SpeedyCache\CriticalCss::schedule('speedycache_generate_ccss', $urls);
		
		wp_send_json_success(array('message' => 'The URLs have been queued to generate Critical CSS'));
	}
	
	static function close_update_notice(){

		if(!wp_verify_nonce($_GET['security'], 'speedycache_promo_nonce')){
			wp_send_json_error('Security Check failed!');
		}

		if(!current_user_can('manage_options')){
			wp_send_json_error('You don\'t have privilege to close this notice!');
		}

		$plugin_update_notice = get_option('softaculous_plugin_update_notice', []);
		$available_update_list = get_site_transient('update_plugins');
		$to_update_plugins = apply_filters('softaculous_plugin_update_notice', []);

		if(empty($available_update_list) || empty($available_update_list->response)){
			return;
		}

		foreach($to_update_plugins as $plugin_path => $plugin_name){
			if(isset($available_update_list->response[$plugin_path])){
				$plugin_update_notice[$plugin_path] = $available_update_list->response[$plugin_path]->new_version;
			}
		}

		update_option('softaculous_plugin_update_notice', $plugin_update_notice);
	}

	// =========================================================================
	// AI Abilities / MCP Adapter handlers
	// =========================================================================

	/**
	 * Save the "Enable AI Abilities" toggle.
	 *
	 * @return void
	 */
	static function save_ai_abilities(){
		
		check_ajax_referer('speedycache_ajax_nonce', 'nonce');
	
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have required permission.', 'speedycache'));
		}

		$options = get_option('speedycache_options', []);
		$options['ai_abilities']['enabled'] = !empty($_POST['enabled']) ? 1 : 0;

		update_option('speedycache_options', $options);

		wp_send_json_success([
			'message' => __('Settings Updated Successfully', 'speedycache'),
		]);
	}

	/**
	 * Install (or activate) the official WordPress MCP Adapter plugin with one click.
	 *
	 * @return void
	 */
	static function install_mcp_adapter(){
		
		check_ajax_referer('speedycache_ajax_nonce', 'nonce');
	
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to do that.', 'speedycache'));
		}

		$options = get_option('speedycache_options', []);

		if(empty($options['ai_abilities']['enabled'])){
			wp_send_json_error(__('The MCP Adapter cannot be installed because the Enable AI Abilities Toggle in the right sidebar is turned off. Please check it and try again.', 'speedycache'));
		}

		$requested_action = !empty($_POST['adapter_action']) ? sanitize_key(wp_unslash($_POST['adapter_action'])) : 'install';

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$installed_file = \SpeedyCache\Abilities::get_installed_mcp_adapter_file();

		// Update path -> fetch the latest release and re-install over the existing plugin.
		if($requested_action === 'update'){

			if(empty($installed_file)){
				wp_send_json_error(__('The MCP Adapter is not installed yet, so there is nothing to update.', 'speedycache'));
			}

			$release = \SpeedyCache\Abilities::get_mcp_adapter_release();
			
			if(empty($release['download_url'])){
				wp_send_json_error(__('Could not resolve the MCP Adapter download URL. Please try again in a moment.', 'speedycache'));
			}

			// Include WordPress core File and Upgrader dependencies.
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$skin = new \WP_Ajax_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader($skin);
			$result   = $upgrader->install($release['download_url'], ['overwrite_package' => true]);

			if(is_wp_error($result)){
				wp_send_json_error($result->get_error_message());
			}

			if(false === $result || !$upgrader->plugin_info()){
				$errors = method_exists($skin, 'get_errors') ? $skin->get_errors() : new \WP_Error();
				$message = is_wp_error($errors) && $errors->get_error_message() ? $errors->get_error_message() : __('The MCP Adapter could not be updated.', 'speedycache');
				wp_send_json_error($message);
			}

			// Activate the updated plugin file.
			$plugin_file = $upgrader->plugin_info() ?: $installed_file;
			$activated   = activate_plugin($plugin_file);

			if(is_wp_error($activated)){
				wp_send_json_error($activated->get_error_message());
			}

			wp_send_json_success([
				'message' => sprintf(__('MCP Adapter updated to version %s and activated.', 'speedycache'), $release['version']),
				'state'   => 'active',
				'version' => $release['version'],
			]);
		}

		// Installed but inactive -> just activate.
		if($installed_file){
			$activated = activate_plugin($installed_file);
			if(is_wp_error($activated)){
				wp_send_json_error($activated->get_error_message());
			}
			wp_send_json_success([
				'message' => __('MCP Adapter activated.', 'speedycache'),
				'state'   => 'active',
			]);
		}

		// Nothing installed yet -> fetch the release and run the upgrader.
		if($requested_action !== 'install'){
			wp_send_json_error(__('Invalid adapter action.', 'speedycache'));
		}

		$release = \SpeedyCache\Abilities::get_mcp_adapter_release();
		if(empty($release['download_url'])){
			wp_send_json_error(__('Could not resolve the MCP Adapter download URL. Please try again in a moment.', 'speedycache'));
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader($skin);
		$result   = $upgrader->install($release['download_url']);

		if(is_wp_error($result)){
			wp_send_json_error($result->get_error_message());
		}

		if(false === $result || !$upgrader->plugin_info()){
			$errors = method_exists($skin, 'get_errors') ? $skin->get_errors() : new \WP_Error();
			$message = is_wp_error($errors) && $errors->get_error_message() ? $errors->get_error_message() : __('The MCP Adapter could not be installed.', 'speedycache');
			wp_send_json_error($message);
		}

		// Activate the newly installed plugin package.
		$plugin_file = $upgrader->plugin_info();
		$activated    = activate_plugin($plugin_file);

		if(is_wp_error($activated)){
			wp_send_json_error($activated->get_error_message());
		}

		wp_send_json_success([
			'message' => sprintf(__('MCP Adapter %s installed and activated.', 'speedycache'), $release['version']),
			'state'   => 'active',
			'version' => $release['version'],
		]);
	}

	/**
	 * Generate a WordPress Application Password for the current user.
	 *
	 * @return void
	 */
	static function generate_app_password(){
		
		check_ajax_referer('speedycache_ajax_nonce', 'nonce');
	
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to do that.', 'speedycache'));
		}

		$options = get_option('speedycache_options', []);

		if(empty($options['ai_abilities']['enabled'])){
			wp_send_json_error(__('Cannot generate AI Application Password because the Abilities feature is disabled. Please enable it first.', 'speedycache'));
		}

		if(!class_exists('\WP_Application_Passwords')){
			wp_send_json_error(__('Application Passwords are not available on this site.', 'speedycache'));
		}

		$user_id = get_current_user_id();
		if(!$user_id){
			wp_send_json_error(__('You must be logged in to generate an Application Password.', 'speedycache'));
		}

		if(!wp_is_application_passwords_available_for_user($user_id)){
			wp_send_json_error(__('Application Passwords are not available for your account. Please contact a site administrator.', 'speedycache'));
		}

		// Create a new Application Password using WordPress core API.
		$created = \WP_Application_Passwords::create_new_application_password($user_id, [
			'name'   => \SpeedyCache\Abilities::$APP_PASSWORD_NAME,
			'app_id' => \SpeedyCache\Abilities::$APP_PASSWORD_APP_ID,
		]);

		// Handle WP_Error if password generation fails.
		if(is_wp_error($created)){
			wp_send_json_error($created->get_error_message());
		}

		$user = wp_get_current_user();

		wp_send_json_success([
			'username' => $user ? $user->user_login : '',
			'password' => isset($created[0]) ? (string)$created[0] : '',
			'message'  => __('Application Password generated. Copy it now — it will not be shown again.', 'speedycache'),
		]);
	}

	/**
	 * Server-side test of the MCP endpoint (fallback when client-side fetch
	 * is blocked by CORS or unavailable).
	 *
	 * @return void
	 */
	static function test_mcp_connection(){
		
		check_ajax_referer('speedycache_ajax_nonce', 'nonce');
	
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to test the connection.', 'speedycache'));
		}

		$start  = microtime(true);
		$url    = trailingslashit(home_url()) . ltrim(\SpeedyCache\Abilities::$ABILITIES_ENDPOINT, '/');

		$username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
		$password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';

		$response = wp_remote_get($url, [
			'headers' => [
				'Accept' => 'application/json',
				'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
			],
		]);

		$elapsed = round((microtime(true) - $start) * 1000);

		if(is_wp_error($response)){
			$message = sprintf(__('Could not reach the abilities endpoint (%s).', 'speedycache'), $response->get_error_message());
			\SpeedyCache\Abilities::save_test_connection_status(false, $message);
			wp_send_json_error(['message' => $message]);
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if($code >= 200 && $code < 300 && is_array($body)){
			$speedycache_abilities = 0;
			foreach($body as $ability){
				$name = isset($ability['name']) ? $ability['name'] : (isset($ability['id']) ? $ability['id'] : '');
				if(is_string($name) && strpos($name, 'speedycache-') === 0){
					$speedycache_abilities++;
				}
			}

			$message = sprintf(__('Authenticated with your Application Password and discovered %1$d SpeedyCache abilities in %2$dms. Your site is ready to connect an AI client below.', 'speedycache'), $speedycache_abilities, $elapsed);

			\SpeedyCache\Abilities::save_test_connection_status(true, $message);

			wp_send_json_success([
				'message'    => $message,
				'abilities'  => $speedycache_abilities,
				'elapsed_ms' => $elapsed,
			]);
		}

		if($code === 401 || $code === 403){
			$message = __('Your Application Password was rejected — it may have been revoked. Generate a new one and test again.', 'speedycache');
			\SpeedyCache\Abilities::save_test_connection_status(false, $message);
			wp_send_json_error(['message' => $message]);
		}

		$message = sprintf(__('The abilities endpoint responded with status %1$d. Check the MCP Adapter is active and try again.', 'speedycache'), $code);
		\SpeedyCache\Abilities::save_test_connection_status(false, $message);
		wp_send_json_error(['message' => $message]);
	}

	/**
	 * Persist the "Test connection" result produced by the client-side fetch
	 * (which owns the plaintext Application Password) so the pill keeps its
	 * state across page reloads.
	 *
	 * @return void
	 */
	static function save_test_status(){
		
		check_ajax_referer('speedycache_ajax_nonce', 'nonce');
	
		if(!current_user_can('manage_options')){
			wp_send_json_error(__('You do not have permission to do that.', 'speedycache'));
		}

		$ok = !empty($_POST['ok']);
		$message = !empty($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';

		\SpeedyCache\Abilities::save_test_connection_status($ok, $message);

		wp_send_json_success();
	}
}
