<?php 

class Contact_Form_Admin{

    private static $instance = null;
    private function __construct(){
        add_action('admin_menu', [$this, '']);
        add_action('admin_init', [$this, '']);
    }

    public static function get_instance(){
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }
}
?>