<?php 
/*
Plugin Name: MA Contact Form
Description: A contact form plugin.
Version: 1.0
Author: Mervan Agency
Author URI:
*/

if(!defined('ABSPATH')){
    exit;
}


define('CONTACT_FORM_PATH', plugin_dir_path(__FILE__));

// echo '<pre>';
// var_dump(CONTACT_FORM_PATH);
// echo '</pre>';

require_once CONTACT_FORM_PATH . 'includes/ma-admin.php';
require_once CONTACT_FORM_PATH . 'includes/ma-db.php';
require_once CONTACT_FORM_PATH . 'includes/ma-core.php';
// echo '<pre>';
// var_dump(CONTACT_FORM_PATH . 'includes/ma-admin.php');
// echo '</pre>';


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
register_deactivation_hook(__FILE__, 'macf_deactivate_plugin')



/**
 * Initialize the core plugin class instance.
 */
//MA_Contact_Form::get_instance();

?>