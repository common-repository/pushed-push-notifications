<?php

	/**
	 * @package Pushed
	 * @version 2.2
	 */

	/**
	* Plugin Name: Pushed
	* Plugin URI: https://wordpress.org/plugins/pushed-push-notifications/
	* Description: Push notifications plugin for wordpress by Pushed
	* Author: Get Pushed Ltd
	* Author URI: https://pushed.co/
	* Version: 2.2
	*
	* Copyright 2014-2017 Get Pushed Ltd (email: hello@pushed.co)
	* This program is free software; you can redistribute it and/or modify
	* it under the terms of the GNU General Public License as published by
	* the Free Software Foundation; either version 2 of the License, or
	* (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful,
	* but WITHOUT ANY WARRANTY; without even the implied warranty of
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	* GNU General Public License for more details.
	*
	* You should have received a copy of the GNU General Public License
	* along with this program; if not, write to the Free Software
	* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
	*
	*/

	include_once('settings.php');
	require_once('lib/pushed.php');

	// Add Meta Box in Post
	function pushed_add_meta_box()
	{
		wp_enqueue_style('pushed_css');
		wp_enqueue_script('jquery');
		wp_enqueue_script('pushed_js');
		add_meta_box(
			'pushed_section_id',
			__('<img src="'.plugins_url('assets/pushed_favicon_wordpress_edit_page.png', __FILE__).'" alt="Pushed" title="Pushed" height="18px;" style="vertical-align:sub;"> Pushed Notification', 'pushed'),
			'pushed_message_box',
			'post',
			'side',
			'high'
		);

		// add Pushed meta box for all custom post types
		$args = array(
			'public'   => true,
			'_builtin' => false
		);
		$output = 'names'; // names or objects, note names is the default
		$operator = 'and'; // 'and' or 'or'
		$post_types = get_post_types( $args, $output, $operator );
		foreach ( $post_types  as $post_type ) {
			add_meta_box(
				'pushed_section_id',
				__('Pushed Notification', 'pushed'),
				'pushed_message_box',
				$post_type,
				'side',
				'high'
			);
			/** Actions to listen */
			add_action('draft_to_publish', 'pushed_publish_post');
			add_action('pending_to_publish', 'pushed_publish_post');
			add_action('auto-draft_to_publish', 'pushed_publish_post');
			add_action('publish_to_publish', 'pushed_publish_post');
			add_action('future_to_publish', 'pushed_scheduled_post');

			add_action('draft_'. $post_type, 'pushed_save_post');
			add_action('pending_'. $post_type, 'pushed_save_post');
		}
	}

	// Add plugin notices
	add_action('admin_notices', 'pushed_admin_notices');

	// Add plugin metabox
	add_action('admin_init', 'pushed_add_meta_box');

	// do not use http://codex.wordpress.org/Plugin_API/Action_Reference/save_post
	// it can produce twice push send (if another plugins installed)
	add_action('new_to_publish', 'pushed_publish_post');
	add_action('draft_to_publish', 'pushed_publish_post');
	add_action('pending_to_publish', 'pushed_publish_post');
	add_action('auto-draft_to_publish', 'pushed_publish_post');
	add_action('publish_to_publish', 'pushed_publish_post');
	add_action('future_to_publish', 'pushed_scheduled_post');
	add_action('draft_post', 'pushed_save_post');
	add_action('pending_post', 'pushed_save_post');

	function pushed_message_box($post)
	{
		// Make sure that settings has been added
		$pushed_target_credentials = array(
			'api_key'		=> get_option('pushed_api_key'),
			'app_key' 		=> get_option('pushed_app_key'),
			'app_secret' 	=> get_option('pushed_app_secret'),
			'target_type' 	=> get_option('pushed_target_type'),
			'target_alias' 	=> get_option('pushed_target_alias'),
			'sources' 		=> get_option('pushed_sources'),
		);

		$action = null;
		if (!empty($_GET['action'])) {
			$action = htmlentities($_GET['action']);
		}

		wp_nonce_field(plugin_basename( __FILE__ ), 'pushed_post_nonce');
		$post_type = $post->post_type;
		$checkbox_label = sprintf('Send Pushed push notification %s', htmlentities($post_type));
		$textarea_placeholder = 'Enter the Push Notification content here, otherwise, the post title will be used. 140 characters allowed.';
		$checkbox_checked = 'checked="checked"';
		$message_content = '';

		if ($action == 'edit') {
			$checkbox_checked = '';
			$checkbox_label = sprintf('Send a push notification when the %s is updated.', htmlentities($post_type));
			$message_content = get_post_meta($post->ID, 'pushed_message_content', true);
		}

		$source_selector = false;
		$sources = get_pushed_sources($pushed_target_credentials);
		if ($sources) {
			$selector = 'Select Source';
			$source_selector = '<br/><select id="pushed_select_source" name="pushed_send_source">';
	
			$source_selector .= '<option selected disabled>Select Source...</option>';
			foreach($sources as $source) {
				$source_selector .= '<option value="' . $source['target_alias'] . '" 
											 data-target_type="' . $source['target_type'] . '" 
											 data-app_key="' . $source['app_key'] . '" 
											 data-app_secret="' . $source['app_key'] . '">' . $source['name'] . '</option>';
			}
			$source_selector .= '</select>';
		}

		$plugin_content = file_get_contents(plugin_dir_path(__FILE__) . '/html/pushed.html');
		echo sprintf($plugin_content,
			__($textarea_placeholder, 'pushed'),
			$message_content,
			$source_selector,
			$checkbox_checked,
			__($checkbox_label, 'pushed')
		);
	}

	function get_pushed_sources($pushed_target_credentials)
	{
		if (empty($pushed_target_credentials['api_key']['text_string'])) {
			return false;
		}

		if (empty($pushed_target_credentials['sources']['text_area'])) {
			return false;
		}

		$sources = json_decode($pushed_target_credentials['sources']['text_area'], true);

		if (!is_array($sources)) {
			return false;
		}

		if (!count($sources)) {
			return false;
		}

		return $sources;
	}

	function get_pushed_source_by_alias($pushed_target_credentials, $target_alias)
	{
		$sources = get_pushed_sources($pushed_target_credentials);
		foreach($sources as $source) {
			if($source['target_alias'] == $target_alias) {
				return $source;
			}
		}

		return false;
	}

	function pushed_send_push_by_post($post_id, $post_title, $post_url)
	{
		// Pushed settings come from single source configuration
		$pushed_target_credentials = array(
			'app_key' 		=> get_option('pushed_app_key'),
			'app_secret' 	=> get_option('pushed_app_secret'),
			'target_type' 	=> get_option('pushed_target_type'),
			'target_alias' 	=> get_option('pushed_target_alias'),
			'sources' 		=> get_option('pushed_sources'),
			'api_key'		=> get_option('pushed_api_key'),
		);
		
		if (isset($_POST['pushed_send_source'])) {
			// Pushed settings come from multiple source configuration
			$credentials = get_pushed_source_by_alias($pushed_target_credentials, $_POST['pushed_send_source']);
			if(!$credentials) {
				add_filter('redirect_post_location', 'add_error_invalid_source', 99 );
				return false;
			}
			$pushed_target_credentials = array(
				'app_key' 		=> $credentials['app_key'],
				'app_secret' 	=> $credentials['app_secret'],
				'target_type' 	=> $credentials['target_type'],
				'target_alias' 	=> $credentials['target_alias'],
			);
		} else {
			// Pushed settings come from single source configuration
			if ($pushed_target_credentials['target_type']['text_string'] == 'multiple') {
				add_filter('redirect_post_location', 'add_error_no_source', 99 );
				return false;
			}
			$credentials = array();
			$check_credentials = array('app_key', 'app_secret', 'target_type', 'target_alias');
			foreach ($check_credentials as $key) {
				if (!$pushed_target_credentials[$key]) {
					add_filter('redirect_post_location', 'add_error_credentials_notice', 99 );
					return false;
				} else {
					$credentials[$key] = $pushed_target_credentials[$key]['text_string'];
				}
			}
			$pushed_target_credentials = $credentials;
		}

		$pushed = new Pushed($credentials);

		try {
			$response = $pushed->push($post_title, $post_url);
		} catch (Exception $e) {
			$response = 'Failed: ' . $e->getMessage();
		}

		update_post_meta($post_id, 'pushed_api_request', $response['response']['message']);

		return $response;
	}

	function pushed_save_post($ID)
	{
		// If update many posts, don't send push
		if (array_key_exists('post_status', $_GET) && $_GET['post_status']=='all') {
			return;
		}

		if (!empty($_POST)) {
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (!isset($_POST['pushed_post_nonce'])) {
				return;
			}
			if (!wp_verify_nonce($_POST['pushed_post_nonce'], plugin_basename( __FILE__ ))) {
				return;
			}
			if (array_key_exists('pushed_message_content', $_POST)) {
				$message_content = $_POST['pushed_message_content'];
			}

			update_post_meta($ID, 'pushed_message_content', $message_content);
		}
	}

	function pushed_publish_post($post)
	{
		// If update many posts, don't send push
		if (array_key_exists('post_status', $_GET) && $_GET['post_status'] == 'all') {
			return;
		}

		$safe_message_content = null;

		// Check this is a post request.
		if (empty($_POST)) {
			return;
		}

		// Check this is not an auto-save wordpress request.
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		// Check $_POST['pushed_post_nonce']
		if (!isset($_POST['pushed_post_nonce'])) {
			return;
		}
		
		if (!wp_verify_nonce($_POST['pushed_post_nonce'], plugin_basename( __FILE__ ))) {
			return;
		}

		// Check $_POST['pushed_send_push']
		if (!array_key_exists('pushed_send_push', $_POST)) {
			return;
		}

		$safe_send_push = sanitize_text_field($_POST['pushed_send_push']);

		if ($safe_send_push != 1){
			return;
		}

		if ($post->post_status != 'publish') {
			return;
		}

		// Check $_POST['pushed_message_content']
		$safe_message_content = sanitize_text_field($_POST['pushed_message_content']);
		// If there is no special message content set, grab the post title by default.
		if (array_key_exists('pushed_message_content', $_POST)) {
			$safe_message_content = empty($_POST['pushed_message_content']) ? $post->post_title : sanitize_text_field($_POST['pushed_message_content']);
		} else {
			$safe_message_content = $post->post_title;
		}		

		if (!$safe_message_content) {
			return;
		}

		if ($safe_message_content == '') {
			return;
		}

		// Un-quotes a quoted string
		$safe_message_content = stripslashes($safe_message_content);		

		// Limit to 140 characters, othwerwise it won't be accepted by Pushed API.
		if (strlen($safe_message_content) > 140) {
			$safe_message_content = substr($safe_message_content, 0, 140);
		}

		// Save safe_message_content as a post meta.
		update_post_meta($post->ID, 'pushed_message_content', $safe_message_content);

		$pushed_send_push_by_post = pushed_send_push_by_post($post->ID, $safe_message_content, get_permalink($post->ID));

		if (isset($pushed_send_push_by_post['response'])) {
			add_filter('redirect_post_location', 'add_success_notice', 99 );
		} else {
			if ($pushed_send_push_by_post['error']['type'] == 'target_has_no_subscribers') {
				add_filter('redirect_post_location', 'add_no_subscribers_notice', 99 );
			} else {
				add_filter('redirect_post_location', 'add_error_notice', 99 ); // There was an error sending the notification.
			}
		}
	}

	function pushed_scheduled_post($post)
	{
		$safe_message_content = get_post_meta($post->ID, 'pushed_message_content', true);

		if($safe_message_content == '') {
			$safe_message_content = $post->post_title;
		}

		if (strlen($safe_message_content) > 140) {
			$safe_message_content = mb_substr($safe_message_content, 0, 140);
		}

		$pushed_send_push_by_post = pushed_send_push_by_post($post->ID, $safe_message_content, get_permalink($post->ID));	
	}

	function add_error_notice($location)
	{
		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(array('pushed_error' => 'true'), $location );
	}

	function add_no_subscribers_notice($location)
	{
		remove_filter('redirect_post_location', 'add_no_subscribers_notice', 99 );
		return add_query_arg(array('pushed_no_subscribers' => 'true'), $location );
	}

	function add_error_credentials_notice($location)
	{
		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(array('pushed_credentials_error' => 'true'), $location );
	}

	function add_success_notice($location)
	{
		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(array('pushed_success' => 'true'), $location );
	}

	function add_error_no_source($location)
	{
		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(array('pushed_no_source_selected' => 'true'), $location );
	}

	function add_error_invalid_source($location)
	{
		remove_filter('redirect_post_location', 'add_notice_query_var', 99 );
		return add_query_arg(array('pushed_error_invalid_source' => 'true'), $location );
	}

	function pushed_admin_notices()
	{
		if (isset($_GET['pushed_credentials_error'])) {
			$class = 'error';
			$message = 'Pushed credentials are not set. Please got to <a href="' . admin_url('options-general.php?page=pushed') . '">Pushed Settings</a> and enter your Pushed credentials.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_no_subscribers'])) {
			$class = 'notice notice-warning';
			$message = 'The notification has not been sent because there are no subscribers in your Pushed app.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_error'])) {
			$class = 'error';
			$message = 'Pushed message could not be sent. Please verify that your credentials were introduced successfully. <a href="https://about.pushed.co/support">Contact Pushed</a>.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_success'])) {
			$class = 'updated';
			$message = 'Pushed message successfully processed.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_no_source_selected'])) {
			$class = 'error';
			$message = 'You did not specify the Pushed source to send the push notification. No push message will be sent to Pushed.';
			pushed_admin_notices_format($message, $class);
		}

		if (isset($_GET['pushed_error_invalid_source'])) {
			$class = 'error';
			$message = 'A valid source was not specified send the push notification. No push message will be sent to Pushed.';
			pushed_admin_notices_format($message, $class);
		}

		remove_action('admin_notices', 'pushed_admin_notices');
	}

	function pushed_admin_notices_format($message, $class)
	{
		$favicon = '<img src="'.plugins_url('assets/pushed_favicon_wordpress_edit_page.png', __FILE__).'" alt="Pushed" title="Pushed" height="18px;" style="vertical-align:sub;">';
		$content = $favicon . '&nbsp;'. $message;
		echo '<div class="' . $class . '"> <p>' . $content . '</p></div>'; 
	}
