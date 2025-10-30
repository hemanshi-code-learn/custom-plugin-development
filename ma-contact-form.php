<?php 
/*
Plugin Name: MA Contact Form
Description: A contact form plugin. Short Code: [ma_contact_form]
Version: 1.0
Author: Mervan Agency
Author URI:
*/

if(!defined('ABSPATH')){
    exit;
}


define('CONTACT_FORM_PATH', plugin_dir_path(__FILE__));
define('CONTACT_FORM_URL', plugin_dir_url(__FILE__));


// echo '<pre>';
// var_dump(CONTACT_FORM_PATH);
// echo '</pre>';

require_once CONTACT_FORM_PATH . 'includes/ma-admin.php';
require_once CONTACT_FORM_PATH . 'includes/ma-db.php';
require_once CONTACT_FORM_PATH . 'includes/ma-core.php';

function enqueue_assets(){
    wp_enqueue_style('ma-cf-style', CONTACT_FORM_URL . 'assets/css/style.css', [],'1.0');
    wp_enqueue_script('ma-cf-ajax', CONTACT_FORM_URL . 'assets/js/ajax-script.js', ['jquery'], '1.0',true);
    
    // Pass essential data to JavaScript
    wp_localize_script('ma-cf-ajax', 'maContactFormAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('my_new_form_action'),
        'action' => 'ma_submit_form',
    ]);  

   }
   add_action('wp_enqueue_scripts', 'enqueue_assets');


   function enqueue_admin_assets($hook){
    
    $admin_page_slug = 'toplevel_page_ma-cf-submissions';
    if ($hook != $admin_page_slug) {
        return;
    }

    wp_enqueue_style(
        'ma-cf-admin-style', 
        CONTACT_FORM_URL . 'assets/css/admin-style.css', 
        [], 
        '1.0'
    );

    // Enqueue new admin AJAX script for pagination
    wp_enqueue_script(
        'ma-cf-admin-ajax',
        CONTACT_FORM_URL . 'assets/js/admin-ajax-script.js',
        ['jquery'],
        '1.0',
        true
    );

    // Pass essential data to admin JavaScript
    wp_localize_script('ma-cf-admin-ajax', 'macfAdminAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('macf_admin_nonce'),
        'action' => 'ma_get_submissions', 
        ]);
    
}

add_action('admin_enqueue_scripts', 'enqueue_admin_assets');

function ma_get_fresh_nonce() {
    // Generate a fresh nonce for the specific action 'my_new_form_action'
    $nonce = wp_create_nonce('my_new_form_action');
    wp_send_json_success(['nonce' => $nonce]);
    wp_die();
}
add_action('wp_ajax_ma_get_fresh_nonce', 'ma_get_fresh_nonce');
add_action('wp_ajax_nopriv_ma_get_fresh_nonce', 'ma_get_fresh_nonce');

/**
 * Runs on plugin activation(Creates the database table)
 */

function macf_activate_plugin(){
    Contact_Form_DB::get_instance()->create_table();
}
register_activation_hook(__FILE__, 'macf_activate_plugin');

function macf_deactivate_plugin(){
    $delete_data = get_option('macf_delete_data_on_deactivation', 'no');
    if($delete_data === 'yes'){
        Contact_Form_DB::get_instance()->delete_table();
    }
}
register_deactivation_hook(__FILE__, 'macf_deactivate_plugin');



/**
 * Initialize the core plugin class instance.
 */
//MA_Contact_Form::get_instance();
add_action('plugins_loaded', ['MA_Contact_Form', 'get_instance']);
add_action('plugins_loaded', ['Contact_Form_Admin', 'get_instance']);
?>