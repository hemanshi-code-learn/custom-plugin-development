<?php 
if ( ! class_exists('Contact_Form_Admin') ) {

class Contact_Form_Admin{

    private static $instance = null;
    private $db;
    private $plugin_url;

    private function __construct(){

        $this->db = Contact_Form_DB::get_instance();
        $this->plugin_url = plugin_dir_url( __FILE__ ) . '../';

        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'register_settings']);

        // AJAX Hook for pagination
        add_action('wp_ajax_ma_get_submissions', [$this, 'ajax_get_submissions']);
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
            'Form Preview',   // Page title
            'Form',            // Submenu title
            'manage_options',  // Capability
            'ma-cf-form',      // Submenu slug
            [ $this, 'form_preview_page' ]
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
           <div id="macf-submissions-container">
                <?php $this->render_submissions_table(); // Initial load ?>
            </div>
            <div id="macf-loading-indicator" style="display:none; text-align: center; margin-top: 20px;">
                <p>Loading submissions...</p>
            </div>
       </div>
       <?php 

    }

    /**
     * Renders the submissions table and pagination.
     */
    private function render_submissions_table($paged = 1) {
        
        $current_page = max(1, absint($paged));
        $db = Contact_Form_DB::get_instance();
        $data = $db->get_submission($current_page, 4); // Using 10 per page
        
        $submissions = $data['results'];
        $total_items = $data['total_items'];
        $total_pages = $data['total_pages'];

        if ($submissions) {
            echo '<table class="macf-submissions">';
            echo '<thead>
                    <tr>    
                        <th>ID</th>
                        <th>Time</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Message</th>
                    </tr>
                </thead>';
            echo '<tbody>';

            foreach ($submissions as $submission) {
                echo '<tr>';
                echo '<td>' . esc_html($submission['id']) . '</td>';
                echo '<td>' . esc_html($submission['time']) . '</td>';
                echo '<td>' . esc_html($submission['firstname']) . ' ' . esc_html($submission['lastname']) . '</td>';
                echo '<td>' . esc_html($submission['email']) . '</td>';
                echo '<td>' . esc_html($submission['phone']) . '</td>';
                echo '<td>' . esc_html(wp_trim_words($submission['message'], 15)) . '</td>';
                echo '</tr>';
            }   
            echo '</tbody>';
            echo '</table>';

            // Pagination Links
            $big = 999999; // need an unlikely integer
            $paginate_args = [
                'base' => add_query_arg( 'paged', '%#%' ), 
                'format' => '', 
                'total' => $total_pages,
                'current' => $current_page,
                'show_all' => false,
                'end_size' => 1,
                'mid_size' => 2,
                'prev_next' => true,
                'prev_text' => __('&laquo; Previous'),
                'next_text' => __('Next &raquo;'),
                'type' => 'list',
            ];

            $pagination_links = paginate_links($paginate_args);

            if ($pagination_links) {
                $pagination_links = preg_replace_callback(
                    '/<a[^>]+href=["\']?([^"\'>]+)["\']?[^>]*>(.*?)<\/a>/i',
                    function ($matches) {
                        $url = esc_url_raw($matches[1]);
                        parse_str(parse_url($url, PHP_URL_QUERY), $params);
                        $paged = isset($params['paged']) ? (int)$params['paged'] : 1;
                        return str_replace(
                            '<a',
                            '<a data-page="' . esc_attr($paged) . '"',
                            $matches[0]
                        );
                    },
                    $pagination_links
                );

                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo $pagination_links;
                echo '</div></div>';
            }


        } else {
            echo '<p>No Submissions found yet.</p>';
        }
    }

    /**
     * AJAX handler to fetch submissions for a specific page.
     */
    public function ajax_get_submissions() {
        // Security check
        if ( ! check_ajax_referer( 'macf_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( ['message' => 'Security check failed.'], 403 );
        }
        
        // Get the requested page number
        $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;

        // Output the new table content
        ob_start();
        $this->render_submissions_table($paged);
        $html = ob_get_clean();

        wp_send_json_success( ['html' => $html] );
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
            'macf_email_field', 
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

    public function form_preview_page(){
        wp_enqueue_style(
            'ma-cf-style', 
            CONTACT_FORM_URL . 'assets/css/style.css', 
            [],
            '1.0'
        );
        ?>
        <div class="wrap">
            <h1>Form Preview & Usage</h1>
            <p>Use this shortcode to display the form anywhere in your posts/pages:</p>
            <input type="text" readonly value="[ma_contact_form]" id="macf-shortcode-input" />
            <button class="button" id="macf-copy-button">Copy Shortcode</button>
    
            <h2>Live Preview</h2>
            <!-- <div style="margin-top:20px; padding:20px; border:1px solid #ddd; background:#fff;"> -->
                
                <?php
                // Render the form using your core shortcode function
                echo do_shortcode('[ma_contact_form]');
                ?>
            
        </div>
    
        <script type="text/javascript">
            (function(){
                const input = document.getElementById('macf-shortcode-input');
                const btn = document.getElementById('macf-copy-button');
                btn.addEventListener('click', function(){
                    input.select();
                    document.execCommand('copy');
                    btn.innerText = 'Copied!';
                    setTimeout(function(){
                        btn.innerText = 'Copy Shortcode';
                    }, 2000);
                });

                const submitBtn = document.querySelector('#ma-contact-form button[type="submit"]');
                if (submitBtn) {
                    submitBtn.setAttribute('type', 'button'); 
                    submitBtn.innerText = 'Send Message (DISABLED IN PREVIEW)';
                    submitBtn.classList.add('macf-preview-disabled');
                }
            })();
        </script>
        <?php
    }






}
}
?>