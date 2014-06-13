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

function rp_gravity_private_key_callback() {
  $private_key = get_option('rp_gravity_private_key');
  $val = '';
  if (isset($private_key)) {
    $val = 'value="' . $private_key . '"';
  }
  echo '<input name="rp_gravity_private_key" id="rp_gravity_private_key" type="text" ' . $val . ' />';
}

function rp_gravity_public_key_callback() {
  $public_key = get_option('rp_gravity_public_key');
  $val = '';
  if (isset($public_key)) {
    $val = 'value="' . $public_key . '"';
  }
  echo '<input name="rp_gravity_public_key" id="rp_gravity_public_key" type="text" ' . $val . ' />';
}


function rp_menu() {
  $page_hook = add_management_page( 'ITP Review', 'ITP Review', 'manage_options', 'rp-review', 'rp_page');
  add_action('admin_print_scripts-' . $page_hook, 'rp_script_load');
}

function rp_page() {
  include (plugin_dir_path(__FILE__) . '/view/rp-page.php');
}

function rp_input_setting_callback($arg) {
  $option_name = $arg[0];
  $option_data = get_option($option_name);
  $val = '';
  if (isset($option_data)) {
    $val = 'value="' . $option_data . '"';
  }
  echo "<input name=\"$option_name\" id=\"$option_name\" type=\"text\" $val />";
}

function rp_textarea_setting_callback($arg) {
  $option_name = $arg[0];
  $option_data = get_option($option_name);
  echo "<textarea name=\"$option_name\" id=\"$option_name\" rows=\"20\" cols=\"60\" />$option_data</textarea>";
}

function rp_message_section() {
}

function rp_gravity_section() {
}

function rp_review_api_section() {
}

function rp_paytrack_api_section() {
}

function rp_settings() {
  add_settings_section('rp_message_section',
    'ITP Review: Message Settings',
    'rp_message_section',
    'general'
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

  register_setting('general', 'rp_message_accept');
  register_setting('general', 'rp_message_comp');
  register_setting('general', 'rp_message_reject');

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
    'Gravity Form to Review (form number)',
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

  register_setting ('general', 'rp_paytrack_api_url');
  register_setting ('general', 'rp_paytrack_api_key');
  register_setting ('general', 'rp_review_api_url');
  register_setting ('general', 'rp_review_api_key');
}

function rp_setup() {
  add_option('rp_message_accept');
  add_option('rp_message_comp');
  add_option('rp_message_reject');
  add_option('rp_gravity_public_key');
  add_option('rp_gravity_private_key');
  add_option('rp_paytrack_api_url');
  add_option('rp_paytrack_api_key');
  add_option('rp_review_api_url');
  add_option('rp_review_api_key');
} 

?>
