<?php

namespace SpeedyCache;

if(!defined('ABSPATH')){
	die('HACKING ATTEMPT');
}

use \SpeedyCache\Util;

class Delete{
	
	static $cache_lifespan = 0;

	static function run($actions){
		global $speedycache;
		
		// Even if the actions are empty, the cache will be deleted.
		self::all_cache();
		self::purge_varnish();
		\SpeedyCache\CDN::purge();
		delete_option('speedycache_html_size');
		delete_option('speedycache_assets_size');

		if(empty($actions)){
			return;
		}
		
		if(!empty($actions['minified'])){
			self::minified();
		}
		
		if(!empty($actions['font'])){
			self::local_fonts();
		}
		
		if(!empty($actions['gravatars'])){
			self::gravatar();
		}
		
		if(!empty($actions['domain'])){
			self::all_for_domain();
		}
		
		if(!empty($actions['preload'])){
			if(!empty($speedycache->options['preload'])){
				\SpeedyCache\Preload::build_preload_list();
			}
		}
	}
	
	/**
	 * Deletes cache of a single page
	 * @param int $post_id
	 */
	static function cache($post_id = false){
		global $speedycache;

		if(!isset($post_id) || $post_id === FALSE || !is_numeric($post_id)){
			return;
		}

		$link = get_permalink($post_id);

		// If its 0 then it's a homepage
		if($post_id == 0){
			$link = home_url();
		}

		if(empty($link)){
			return;
		}

		self::url($link);

		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
		
		if(!empty($speedycache->options['preload'])){
			\SpeedyCache\Preload::url($link);
		}

		delete_option('speedycache_html_size');
		delete_option('speedycache_assets_size');
	}

	/**
	* Deletes cache of a URL
	* Parses and converts a URL to cache path and purges it
	* @param array|string $urls
	*/
	static function url($urls){
		global $speedycache;

		if(!is_array($urls)){
			$urls = [$urls];
		}
		
		foreach($urls as $url){
			$parsed_url = wp_parse_url($url);
			$path = $parsed_url['path'];
			
			$file = '';
			if(empty($path) || $path == '/'){
				$file = 'index.html';
			} else {
				$file = trim($path, '/') . '/index.html';
			}

			$cache_path = [];
			$cache_path[] = Util::cache_path('all') . $file;
			if(!empty($speedycache->options['mobile_theme'])){
				$cache_path[] = Util::cache_path('mobile-cache') . $file;
			}

			foreach($cache_path as $c_path){
				if(!file_exists($c_path)){
					continue;
				}

				if(is_dir($c_path)){
					self::rmdir($c_path);
					continue;
				}

				unlink($c_path);
			}
		}
	}

	// Delete cache of whole site
	static function all_cache(){

		// Our cache is saved in 2 file, /all and /mobile-cache
		// We also need to delete Critical CSS too as it gets injected in the HTML
		$deletable_dirs = ['all', 'mobile-cache', 'critical-css'];
		
		foreach($deletable_dirs as $dir){
			$path = Util::cache_path($dir);
			self::rmdir($path);
		}

		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}

	// Delete minified and Critical css content.
	static function minified(){
		$assets_cache_path = Util::cache_path('assets');

		if(!file_exists($assets_cache_path)){
			return;
		}

		self::rmdir($assets_cache_path);

		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}

	// Delete local fonts
	static function local_fonts(){
		$fonts_path = Util::cache_path('fonts');

		if(!file_exists($fonts_path)){
			return;
		}

		self::rmdir($fonts_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}

	static function gravatar(){
		$gravatar_path = Util::cache_path('gravatars');
		
		if(!file_exists($gravatar_path)){
			return;
		}

		self::rmdir($gravatar_path);
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	// Delete everything of the current domain, like minfied, cache, gravatar and fonts.
	static function all_for_domain(){
		
	}

	static function rmdir($dir){

		if(!file_exists($dir)){
			return;
		}

		$files = array_diff(scandir($dir), ['..', '.']);

		foreach($files as $file){			
			if(is_dir($dir.'/'.$file)){
				self::rmdir($dir.'/'.$file);
				continue;
			}

			unlink($dir.'/'.$file);
		}

		rmdir($dir);
	}
	
	static function purge_varnish(){
		global $speedycache;

		if(empty($speedycache->options['purge_varnish'])){
			return;
		}

		$server = !empty($speedycache->options['varniship']) ? $speedycache->options['varniship'] : '127.0.0.1';
		
		
		$url = home_url();
		$url = parse_url($url);

		if($url == FALSE){
			return;
		}
		
		$sslverify = ($url['scheme'] === 'https') ? true : false;
		$request_url = $url['scheme'] .'://'. $server . '/.*';

		$request_args = array(
			'method'    => 'PURGE',
			'headers'   => array(
				'Host'       => $url['host'],
			),
			'sslverify' => $sslverify,
		);

		$res = wp_remote_request($request_url, $request_args);

		if(is_wp_error($res)){
			$msg = $res->get_error_message();
			return array($msg, 'error');
		}

		if(is_array($res) && !empty($res['response']['code']) && '200' != $res['response']['code']){
			$msg = 'Something Went Wrong Unable to Purge Varnish';
			
			if(empty($res['response']['code']) && '501' == $res['response']['code']){
				$msg = 'Your server dosen\'t allows PURGE request';

				if(!empty($res['headers']['allow'])){
					$msg .= 'The accepted HTTP methods are' . $res['headers']['allow'];
				}
				
				$msg = __('Please contact your hosting provider if, Varnish is enabled and still getting this error', 'speedycache');
			}
			
			return array($msg, 'error');
		}
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
		
		return array(__('Purged Varnish Cache Succesfully', 'speedycache'), 'success');
	}

	static function expired_cache(){
		global $speedycache;

		self::$cache_lifespan = Util::cache_lifespan();
		
		// We don't want to clean cache if cache is disabled
		if(empty($speedycache->options['status']) || empty(self::$cache_lifespan)){
			wp_clear_scheduled_hook('speedycache_purge_cache');
			return;
		}

		$cache_path = [];
		$cache_path[] = Util::cache_path('all');
		$cache_path[] = Util::cache_path('mobile-cache');
		
		foreach($cache_path as $path){		
			if(!file_exists($path)){
				return;
			}

			self::rec_clean_expired($path);
		}
		
		if(class_exists('\SpeedyCache\Logs')){
			\SpeedyCache\Logs::log('delete');
			\SpeedyCache\Logs::action();
		}
	}
	
	// Recursively deletes expired cache
	static function rec_clean_expired($path){
		$files = array_diff(scandir($path), array('..', '.'));

		if(empty($files)){
			return;
		}

		foreach($files as $file){
			$file_path = $path . '/'. $file;

			if(is_dir($file_path)){
				self::rec_clean_expired($file_path);
				return;
			}

			if((filemtime($file_path) + self::$cache_lifespan) < time()){
				unlink($file_path);
			}
		}
	}

	// Deletes the cache of the post whose status got changed,
	// only deletes when the post transitions in our out of published mode
	static function on_status_change($new_status, $old_status, $post){
		global $speedycache;

		if($old_status == $new_status && $old_status !== 'publish') return;

		if($old_status !== 'publish' && $new_status !== 'publish'){
			return;
		}

		if(empty($speedycache->options['status'])){
			return;
		}

		if(!empty(wp_is_post_revision($post->ID))){
			return;
		}

		// Current post should not be deleted when its anything other than publish,
		// As in some states its URL changes to ?page_id=
		if($new_status == 'publish'){
			self::cache($post->ID);
		}
		
		// Deleting the cache of home page and blog page
		$home_page_id = get_option('page_on_front');
		self::cache($home_page_id);
		
		// For some sites home page and blog page could be same
		$blog_page_id = get_option('page_for_posts');
		if($home_page_id !== $blog_page_id){
			self::cache($blog_page_id);
		}

		// Deleting the author page cache
		$author_page_url = get_author_posts_url($post->post_author);
		self::url($author_page_url);
		
		// Deleting cache of related terms
		self::terms($post->ID);
		
	}

	// Deletes cache of the page where a comments status got change.
	static function on_comment_status($new_status, $old_status, $comment){
		global $speedycache;

		if($old_status == $new_status && $old_status !== 'approved') return;

		if($old_status !== 'approved' && $new_status !== 'approved'){
			return;
		}

		if(empty($speedycache->options['status'])){
			return;
		}

		self::cache($comment->comment_parent);
		
	}

	static function terms($post_id){
		global $speedycache;
		
		if(empty($post_id) || !is_numeric($post_id)){
			return;
		}

		$terms = wp_get_post_terms($post_id);

		$deletable_links = [];
		foreach($terms as $term){
			$link = get_term_link($term->term_id);
			
			$deletable_links[] = $link;
		}

		if(empty($deletable_links)){
			return;
		}
		
		self::url($deletable_links);
		
		if(!empty($speedycache->options['preload'])){
			\SpeedyCache\Preload::url($deletable_links);
		}
	}

	// Deletes cache of product page and its related pages when a order is made
	static function order($order_id){
		global $speedycache;

		if(empty($speedycache->options['status'])){
			return;
		}

		if(!function_exists('wc_get_order')){
			return;
		}

		$order = wc_get_order($order_id);
		$items = $order->get_items();

		foreach($items as $item){
			$product_id = $item->get_product_id();
			
			if(empty($product_id)){
				continue;
			}

			self::cache($product_id);
			
			$categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));

			foreach($categories as $category){
				self::cache($category);
			}
		}

		$shop_page_id = wc_get_page_id('shop');
		self::cache($shop_page_id);
	}
}