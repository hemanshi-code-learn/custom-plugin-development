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
        'nonce' => wp_create_nonce('ma_contact_form_nonce_action'),
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
    
}

add_action('admin_enqueue_scripts', 'enqueue_admin_assets');

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