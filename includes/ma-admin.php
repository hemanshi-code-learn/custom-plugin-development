<?php 
if ( ! class_exists('Contact_Form_Admin') ) {

class Contact_Form_Admin{

    private static $instance = null;
    private function __construct(){
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public static function get_instance(){
        if(is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Adds the admin menu pages.
     */

    public function add_admin_menus(){
        add_menu_page(
            'Contact Form Submissions', //Page title
            'MA Contact Form', //Menu title
            'manage_options', //Capability
            'ma-cf-submissions',  //Menu slug
            [$this, 'submissions_page'], //Callback function
            'dashicons-email-alt',
            6
        );

        add_submenu_page(
            'ma-cf-submissions',
            'Form Settings',
            'Settings',
            'manage_options',
            'ma-cf-settings',
            [$this, 'settings_page']
        );
    }

    /**
     * Displays the submissions page content.
     */

    public function submissions_page(){
        ?>
       <div class="wrap">
           <h2>Contact Form submissions</h2>
        <?php 
        $db = Contact_Form_DB::get_instance();
        $submissions = $db->get_all_submissions();

        if($submissions){
            echo '<table class= "wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>Time</th><th>Name</th><th>Email</th><th>Phone</th><th>Message</th></tr></thead>';
            echo '<tbody>';

            foreach($submissions as $submission){
                echo '<tr>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html($submission['time']) . '</td>';
                echo '<td>' . esc_html($submission['firstname']) . ' ' . $submission['lastname'] . '</td>';
                echo '<td>' . esc_html($submission['email']) . '</td>';
                echo '<td>' . esc_html($submission['phone']) . '</td>';
                echo '<td>' . esc_html(wp_trim_words($submission['message'],15)) . '</td>';
                echo '</tr>';
            }  
            echo '</tbody>';
            echo '</table>'; 
        } else {
            echo '<p>No Submissions found yet.</p>';
        }
        ?>
       </div>
       <?php 

    }


     /**
     * Registers settings and fields for the settings page.
     */

     public function register_settings(){
        // Register the setting (notification email)
        register_setting('macf_settings_group', 'macf_notification_email', [
            'sanitize_callback' => 'sanitize_email',
            'default' => get_bloginfo('admin_email'),
        ]);
        // Register the setting (delete data option)
        register_setting('macf_settings_group', 'macf_delete_data_on_deactivation', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'no',
        ]);

        add_settings_section(
            'macf_general_section',
            'Form & Data Settings',
            null,
            'ma-cf-settings'
        );

        add_settings_field(
            'macf_email_field', // New ID for email field
            'Notification Email',
            [$this, 'render_email_field'],
            'ma-cf-settings',
            'macf_general_section'
        );

        add_settings_field(
            'macf_delete_data_field',
            'Delete Data on Deactivation',
            [$this, 'render_delete_data_field'],
            'ma-cf-settings',
            'macf_general_section'
        );
     }


     /**
     * Renders the Notification Email text input field.
     */

    public function render_email_field(){
        $email = get_option('macf_notification_email', get_bloginfo('admin_email'));
        echo '<input type="email" name="macf_notification_email" value="' . esc_attr($email) . '" class="regular-text">'; 
        echo '<p class="description">Email address to receive new submission alerts.</p>';
    }


     /**
     * Renders the Delete Data checkbox.
     */

     public function render_delete_data_field(){
        $opt = get_option('macf_delete_data_on_deactivation');
        $checked = ($opt === 'yes') ? 'checked="checked"' : ''; 
        echo '<label><input type="checkbox" name="macf_delete_data_on_deactivation" value="yes" ' . $checked . '/>Yes, delete all submission data when the plugin is deactivated.</label>';
     }


     /**
     * Displays the settings page wrapper.
     */

    public function settings_page(){
        ?>
        <div class="wrap">
            <h2>MA Contact Form Settings</h2>
            <form method="post" action="options.php">
                <?php 
                settings_fields('macf_settings_group');
                do_settings_sections('ma-cf-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php 
    }
}
}
?>