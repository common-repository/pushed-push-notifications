<?php

Class PushedConfig
{
	public $group = 'pushed';

	public $page = array(
		'name' => 'pushed',
		'title' => '<h1>Pushed Worpress Push Notifications</h1>',
		'intro_text' => '<div class="welcome-panel" style="margin-right:20px;padding:15px;"><div style="float:left;"><a href="https://pushed.co" target="_blank"><img src="https://s3-eu-west-1.amazonaws.com/pushed.co/assets/pushed/media/pushed_hello.png" height="100px;"></a></div><h3>Pushed Wordpress plugin allows you to send notifications to your subscribers every time you publish or update a Wordpress post.</h3> Integration between Wordpress and Pushed is <b>free</b> and <b>effortless</b>.<br/><a href="https://account.pushed.co/signup" target="_blank">Sign Up in Pushed</a> and request a <a href="https://account.pushed.co/" target="_blank">Developer Account</a>. It will only take 5 minutes!<div style="clear:both;"></div>Once you have a Pushed Developer account you have to create your app (here you have a <a href="https://pushed.co/get-started" target="_blank">complete guide to get started</a>) and then <a href="https://about.pushed.co/docs/integrations/wordpress" target="_blank">copy your app credentials</a> in this page.<br/>If you need further assistance, please do not hesitate <a href="https://about.pushed.support/">contacting us</a>, we\'ll be glad to help.</div>',
		'menu_title' => 'Pushed'
	);

	public $sections = array(
		'application_access' => array(
			'title' => 'Pushed Target Settings',
			'description' => 'Please configure the following settings below (you\'ll find this settings on your <a href="https://account.pushed.co/integrations/wordpress" target="_blank">Pushed Wordpress Plugin Credentials Generator</a>):',
			'fields' => array(
				'target_type' => array(
					'label' => 'Source',
					'description' => 'Select the type of your source (App, Channel or Multiple Sources).',
					'type' => 'select',
					"options" => array('app' => 'App', 'channel' => 'Channel', 'multiple' => 'Multiple Sources'),
					"default" => "",
					"class" => "disabled pushed_credentials_check",
				),
				'target_alias' => array(
					'label' => 'Pushed Source Alias',
					'description' => 'Your Pushed Source Alias (If you\'re sending to a Pushed Channel enter the Channel alias).',
					'type' => 'text',
					"default" => "",
					"class" => "hidden single pushed_credentials_check",
				),
				'app_key' => array(
					'label' => 'Pushed App Key',
					'description' => 'Your Pushed App Key.',
					'type' => 'text',
					"default" => "",
					"class" => "hidden single pushed_credentials_check",
				),
				'app_secret' => array(
					'label' => 'Pushed App Secret',
					'description' => 'Your Pushed App Secret.',
					'type' => 'text',
					"default" => "",
					"class" => "hidden single pushed_credentials_check",
				),
				'api_key' => array(
					'label' => 'Pushed Api Key',
					'description' => 'Your <a href="https://account.pushed.co/settings" target="_blank">Pushed Account Api Key</a>.',
					'type' => 'text',
					"default" => "",
					"class" => "hidden multiple pushed_credentials_check",
				),
				'sources' => array(
					'label' => 'Pushed Sources',
					'description' => 'Pushed Sources: Do not edit (will be autoloaded).',
					'type' => 'textarea',
					"default" => "",
					"class" => "hidden",
				),
			)
		)
	);
}

Class PushedSectionHelper
{
	protected $_sections;

	public function __construct($sections)
	{
		$this->_sections = $sections;
	}

	public function section_legend($value)
	{
		echo sprintf("%s",$this->_sections[$value['id']]['description']);
	}

	public function input_textarea($value)
	{
		$options = get_option($value['name']);
		$default = !empty($options) && !empty($options['text_area']) ? $options['text_area'] : '';

		echo '<textarea class="' . $value['class'] . '" name="' . $value['name'] . '[text_area]"">' . $default . '</textarea>';
		echo '<br/><em>' . $value['description'] . '</em>';
	}

	public function input_text($value)
	{
		$options = get_option($value['name']);
		$default = (isset($value['default'])) ? $value['default'] : null;

		echo sprintf(
			'<input class="%s" id="%1$s" type="text" name="%2$s[text_string]" value="%3$s" size="40" /> %4$s%5$s',
			$value['class'],
			$value['name'],
			(!empty ($options['text_string'])) ? $options['text_string'] : $default,
			(!empty ($value['suffix'])) ? $value['suffix'] : null,
			(!empty ($value['description'])) ? sprintf("<br /><em>%s</em>", __($value['description'], 'pushed')) : null
		);
	}

	public function input_select($value)
	{
		$options = get_option($value['name']);
		$default = (isset($value['default'])) ? $value['default'] : null;
		$selected = !empty($options) && !empty($options['text_string']) ? $options['text_string'] : $default;

		echo sprintf('<select name="%s[text_string]">', $value['name']);
		echo '<option disabled="disabled" selected="selected">Select Source...</option>';
		foreach ($value['options'] as $optionValue => $optionText) {
			echo sprintf('<option value="%1$s"  %2$s> %3$s </option>',
				$optionValue,
				$selected == $optionValue ? 'selected' : '',
				$optionText);
		}
		echo '</select>';

		if (!empty ($value['description'])) {
			echo sprintf("<br /><em>%s</em>", __($value['description'], 'pushed'));
		}
	}

	public function input_verify_credentials($value)
	{
		echo sprintf('<input type="button" id="verify_pushed_credentials" value="%s" class="button button-alert"/> ', $value);
	}

	public function input_submit($value)
	{
		echo sprintf('<input type="submit" name="Submit" value="%s" class="button button-primary"/>', $value);
	}

	public function verify_credentials_info_section()
	{
		echo sprintf('<p>We strongly recomment to verify your Pushed credentials before saving to avoid issues.<br/>You can click on the <b>Verify Pushed Credentials</b> button below to check that your credentials are valid.<br/> Verification is <b>not mandatory</b>, it is just a way to make sure your credentials were introduced correctly.<br/><b>Remember that you must have Pushed subscribers in order to send push notifications.</b></p>');
	}

	public function verify_credentials_result_section()
	{
		echo sprintf('<div class="clearfix">&nbsp;</div><div id="results_section_body" class="pushed_msg"></div>');
	}

	public function form_start($action)
	{
		echo sprintf('<form method="POST" action="%s">', $action);
	}

	public function form_end()
	{
		echo '</form>';
	}
}

class PushedSettings
{
	protected $_config;

	public function __construct()
	{
		$this->_config = get_class_vars('PushedConfig');
		$this->_section = new PushedSectionHelper($this->_config['sections']);
		$this->initialize();
	}

	protected function initialize()
	{
		if (!function_exists('add_action')) {
			return;
		}

		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_menu', array($this, 'admin_add_page'));

		if (!function_exists('add_filter')) {
			return;
		}

		$filter = 'plugin_action_links_' . basename(__DIR__) . '/pushed.php';
		add_filter($filter, array($this, 'admin_add_links'), 10, 4);
	}

	public function admin_add_links($links, $file)
	{
		$settings_link = sprintf('<a href="options-general.php?page=%s">%s</a>',
			$this->_config['page']['name'],
			__('Settings')
		);
		array_unshift($links, $settings_link);
		return $links;
	}

	public function admin_init()
	{
		wp_register_script('pushed_js', plugins_url('/js/pushed.js', __FILE__), array(), '1.0');
		wp_register_style('pushed_css', plugins_url('/css/pushed.css', __FILE__), array(), '1.0');

		foreach ($this->_config['sections'] as $key => $section) {
			add_settings_section(
				$key,
				__($section['title'], 'pushed'),
				array($this->_section, 'section_legend'),
				$this->_config['page']['name'],
				$section
			);

			foreach ($section['fields'] as $field_key => $field_value) {
				$function = array($this->_section, 'input_' . $field_value['type']);

				/** Validate input settings */
				$callback = 'pushed_input_settings_validation';

				add_settings_field(
					$this->_config['group'] . '_' . $field_key,
					__($field_value['label'], 'pushed'),
					$function,
					$this->_config['page']['name'],
					$key,
					array_merge(
						$field_value,
						array('name' => $this->_config['group'] . '_' . $field_key)
					)
				);

				register_setting(
					$this->_config['group'],
					$this->_config['group'] . '_' . $field_key,
					$callback
				);
			}
		}
	}

	public function admin_add_page()
	{
		$args = array(
			__($this->_config['page']['title'], 'pushed'),
			__($this->_config['page']['menu_title'], 'pushed'),
			'manage_options',
			$this->_config['page']['name'],
			array($this, 'options_page')
		);
		call_user_func_array('add_options_page', $args);
	}

	public function options_page()
	{
		echo sprintf('<h2>%s</h2><p>%s</p>',
			__($this->_config['page']['title'], 'pushed'),
			__($this->_config['page']['intro_text'], 'pushed')
		);
		$this->_section->form_start('options.php');

		settings_fields($this->_config['group']);
		do_settings_sections($this->_config['page']['name']);

		$this->_section->verify_credentials_info_section();
		$this->_section->input_verify_credentials(__('Verify Pushed Credentials', 'pushed'));
		$this->_section->input_submit(__('Save changes', 'pushed'));
		$this->_section->verify_credentials_result_section();
		$this->_section->form_end();
	}
}

function pushed_input_settings_validation($input = null)
{
	if(is_null($input)) {
		return false;
	}

	/** Create our array for storing the validated options */
	$output = array();

	/** Loop through each of the incoming options */
	foreach( $input as $key => $value ) {
		/** Check to see if the current option has a value. If so, process it. */
		if( isset( $input[$key] ) ) {
			/** Strip all HTML and PHP tags and properly handle quoted strings */
			$output[$key] = strip_tags( stripslashes( $input[ $key ] ) );
		}
	}

	/** Return the array processing any additional functions filtered by this action */
	return apply_filters( 'sandbox_theme_validate_input_examples', $output, $input );
}

function test_ajax_load_scripts() {
	// load our jquery file that sends the $.post request
	//wp_enqueue_script( "ajax-test", plugin_dir_url( __FILE__ ) . '/ajax-test.js', array( 'jquery' ) );
 
	// make the ajaxurl var available to the above script
	wp_localize_script( 'ajax-test', 'the_ajax_script', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );	
}
add_action('wp_print_scripts', 'test_ajax_load_scripts');

function text_ajax_validate_pushed_credentials()
{
	if (!function_exists('curl_version')) {
		echo '{"error":{"type":"curl_not_available","message":"Curl function not available. Please install Curl for PHP."}}';
		die();
	}

	$params = array_merge($_POST, array('version' => get_bloginfo('version')));

	curl_setopt_array(
		$ch = curl_init(), 
		array( 
			CURLOPT_URL 			=> "https://api.pushed.co/1/developer/credentials", 
			CURLOPT_POSTFIELDS 		=> $params, 
			CURLOPT_SAFE_UPLOAD 	=> false,
			CURLOPT_SSL_VERIFYHOST	=> false,
			CURLOPT_SSL_VERIFYPEER	=> false,
		)
	); 
	$result = curl_exec($ch); 
	curl_close($ch);
	
	if($result === false) {
		echo '{"error":{"type":"curl_error","message":"Curl (' . json_encode(curl_version()) . ') error: ' . curl_error($ch) . '"}}';
	}

	die();
}
add_action('wp_ajax_validate_pushed_credentials', 'text_ajax_validate_pushed_credentials');

new PushedSettings();