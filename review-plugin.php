<?php
/**
 * Plugin Name: ITP Review
 * Plugin URI: http://github.com/ITPNYU/review-plugin
 * Description: Wordpress plugin for reviewing form submissions
 * Version: 1.0
 * Author: NYU ITP
 * Author URI: http://itp.nyu.edu
 * License: GPLv3
 */

require 'rp-gravity.php';

register_activation_hook( __FILE__, 'rp_setup');

add_action('admin_init', 'rp_settings');
add_action('admin_menu', 'rp_menu');

function rp_menu() {
  $page_hook = add_management_page( 'ITP Review', 'ITP Review', 'manage_options', 'rp-review', 'rp_page');
  add_action('admin_print_scripts-' . $page_hook, 'rp_script_load');
}

// load CSS and JavaScript for dashboard page
function rp_script_load() {
  wp_enqueue_style('bootstrap', '//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css');
  wp_enqueue_style('bootstrap-theme', '//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap-theme.min.css');
  wp_register_script('bootstrap', '//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js');
  wp_enqueue_script('jquery');
  wp_enqueue_script('bootstrap');
}

// display the dashboard page
function rp_page() {
  include (plugin_dir_path(__FILE__) . '/view/rp-page.php');
}

// set and display setting with input text
function rp_input_setting_callback($arg) {
  $option_name = $arg[0];
  $option_data = get_option($option_name);
  $val = '';
  if (isset($option_data)) {
    $val = 'value="' . $option_data . '"';
  }
  echo "<input name=\"$option_name\" id=\"$option_name\" type=\"text\" $val />";
}

// set and display setting with input password
function rp_password_setting_callback($arg) {
  $option_name = $arg[0];
  $option_data = get_option($option_name);
  $val = '';
  if (isset($option_data)) {
    $val = 'value="' . $option_data . '"';
  }
  echo "<input name=\"$option_name\" id=\"$option_name\" type=\"password\" $val />";
}

// set and display setting with textarea
function rp_textarea_setting_callback($arg) {
  $option_name = $arg[0];
  $option_data = get_option($option_name);
  echo "<textarea name=\"$option_name\" id=\"$option_name\" rows=\"20\" cols=\"60\" />$option_data</textarea>";
}

// section callback (empty)
function rp_message_section() {
}

// section callback (empty)
function rp_gravity_section() {
}

// section callback (empty)
function rp_review_api_section() {
}

// section callback (empty)
function rp_paytrack_api_section() {
}

// load all the settings into dashboard settings
function rp_settings() {
  add_settings_section('rp_message_section',
    'ITP Review: Message Settings',
    'rp_message_section',
    'general'
  );

  add_settings_field('rp_register_url',
    'Register URL',
    'rp_input_setting_callback',
    'general',
    'rp_message_section',
    array('rp_register_url')
  );

  add_settings_field('rp_message_accept',
    'Acceptance Message',
    'rp_textarea_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_accept')
  );

  add_settings_field('rp_message_comp',
    'Comp Message',
    'rp_textarea_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_comp')
  );

  add_settings_field('rp_message_reject',
    'Reject Message',
    'rp_textarea_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_reject')
  );

  add_settings_field('rp_message_server',
    'Message email server name',
    'rp_input_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_server')
  );

  add_settings_field('rp_message_port',
    'Message email server port',
    'rp_input_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_port')
  );

  add_settings_field('rp_message_transport',
    'Message email server transport (must be ssl or tls)',
    'rp_input_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_transport')
  );

  add_settings_field('rp_message_username',
    'Message email user name',
    'rp_input_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_username')
  );

  add_settings_field('rp_message_password',
    'Message email password',
    'rp_password_setting_callback',
    'general',
    'rp_message_section',
    array('rp_message_password')
  );

  register_setting('general', 'rp_register_url');
  register_setting('general', 'rp_message_accept');
  register_setting('general', 'rp_message_comp');
  register_setting('general', 'rp_message_reject');
  register_setting('general', 'rp_message_server');
  register_setting('general', 'rp_message_port');
  register_setting('general', 'rp_message_transport');
  register_setting('general', 'rp_message_username');
  register_setting('general', 'rp_message_password');

  // Gravity Forms API settings
  add_settings_section('rp_gravity_section',
    'ITP Review: Gravity Settings',
    'rp_gravity_section',
    'general'
  );

  add_settings_field('rp_gravity_public_key',
    'Gravity Forms API Public Key',
    'rp_input_setting_callback',
    'general',
    'rp_gravity_section',
    array('rp_gravity_public_key')
  );
  
  add_settings_field('rp_gravity_private_key',
    'Gravity Forms API Private Key',
    'rp_input_setting_callback',
    'general',
    'rp_gravity_section',
    array('rp_gravity_private_key')
  );

  add_settings_field('rp_gravity_form',
    'Gravity Form to Review (number)',
    'rp_input_setting_callback',
    'general',
    'rp_gravity_section',
    array('rp_gravity_form')
  );

  register_setting('general', 'rp_gravity_public_key');
  register_setting('general', 'rp_gravity_private_key');
  register_setting('general', 'rp_gravity_form');

  // ITP Paytrack and Review API settings
  add_settings_section('rp_paytrack_api_section',
    'ITP Review: Paytrack API Settings',
    'rp_paytrack_api_section',
    'general'
  );
  add_settings_section('rp_review_api_section',
    'ITP Review: Review API Settings',
    'rp_review_api_section',
    'general'
  );

  add_settings_field('rp_paytrack_api_url',
    'ITP Review: Paytrack API URL',
    'rp_input_setting_callback',
    'general',
    'rp_paytrack_api_section',
    array('rp_paytrack_api_url')
  );

  add_settings_field('rp_paytrack_api_key',
    'ITP Review: Paytrack API Key',
    'rp_input_setting_callback',
    'general',
    'rp_paytrack_api_section',
    array('rp_paytrack_api_key')
  );

  add_settings_field('rp_review_api_url',
    'ITP Review: Review API URL',
    'rp_input_setting_callback',
    'general',
    'rp_review_api_section',
    array('rp_review_api_url')
  );

  add_settings_field('rp_review_api_key',
    'ITP Review: Review API Key',
    'rp_input_setting_callback',
    'general',
    'rp_review_api_section',
    array('rp_review_api_key')
  );

  add_settings_field('rp_review_api_collection',
    'ITP Review: Review API Collection ID (number)',
    'rp_input_setting_callback',
    'general',
    'rp_review_api_section',
    array('rp_review_api_collection')
  );

  register_setting ('general', 'rp_paytrack_api_url');
  register_setting ('general', 'rp_paytrack_api_key');
  register_setting ('general', 'rp_review_api_url');
  register_setting ('general', 'rp_review_api_key');
  register_setting ('general', 'rp_review_api_collection');
}

// make sure each setting is stored in options
function rp_setup() {
  add_option('rp_register_url');
  add_option('rp_message_accept');
  add_option('rp_message_comp');
  add_option('rp_message_reject');
  add_option('rp_message_server');
  add_option('rp_message_port');
  add_option('rp_message_transport');
  add_option('rp_message_username');
  add_option('rp_message_password');
  add_option('rp_gravity_public_key');
  add_option('rp_gravity_private_key');
  add_option('rp_gravity_form');
  add_option('rp_paytrack_api_url');
  add_option('rp_paytrack_api_key');
  add_option('rp_review_api_url');
  add_option('rp_review_api_key');
  add_option('rp_review_api_collection');
} 

?>
