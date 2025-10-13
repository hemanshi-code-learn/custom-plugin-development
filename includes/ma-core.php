<?php 

class MA_Contact_Form{
   private static $instance = null;
   private $db;
   private $plugin_url;

   private function __construct(){
    $this->db = Contact_Form_DB::get_instance();
    $this->plugin_url = plugin_dir_url(dirname(__FILE__)); // For assets


    // Frontend Hooks
    add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    add_action('init', [$this, 'register_shortcode']);


    // AJAX Hooks (for logged-in and logged-out users)
    add_action('wp_ajax_ma_submit_form', [$this, 'ajax_submit_form']);
    add_action('wp_ajax_nopriv_ma_submit_form', [$this, 'ajax_submit_form']);
   }

   public static function get_instance(){
    if(is_null(self::$instance)){
        self::$instance = new self();
    }
    return self::$instance;
   }

   public function enqueue_assets(){
    wp_enqueue_style('ma-cf-style', $this->plugin_url . 'assets/css/style.css', [],'1.0');
    wp_enqueue_script('ma-cf-ajax', $this->plugin_url . 'assets/js/ajax-script.js', ['jquery'], '1.0',true);

    // Pass essential data to JavaScript
    wp_localize_script('ma-cf-ajax', 'maContactFormAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ma_contact_form_nonce_action'),
    ]);

   }

   public function register_shortcode(){
    add_shortcode('ma_contact_form', [$this, 'render_contact_form']);
   }

   public function render_contact_form($atts){
    ob_start();
    ?>
    <div class="ma-contact-form-wrap">
            <form id="ma-contact-form" method="post">
                <?php wp_nonce_field('ma_contact_form_nonce_action', 'ma_contact_form_nonce_field'); ?>

                <input type="text" name="cf_firstname" placeholder="First Name *" required>
                <input type="text" name="cf_lastname" placeholder="Last Name (Optional)">
                <input type="email" name="cf_email" placeholder="Email Address *" required>
                <input type="text" name="cf_phone" placeholder="Phone (Optional)">
                <textarea name="cf_message" placeholder="Your Message *" rows="5" required></textarea>
                <button type="submit">Send Message</button>

                <div id="ma-form-response" aria-live="polite"></div>
            </form>
        </div>
    <?php 
    return ob_get_clean();
   }

   public function ajax_submit_form(){ 

    if(!check_ajax_referer('ma_contact_form_nonce_action', 'ma_contact_form_nonce_field', false)){
        wp_send_json_error(['message' => 'Security check failed.']);
        wp_die();
    }

    $validated_data = $this->validate_and_sanitize($_POST);
    if(is_wp_error($validated_data)){
        wp_send_json_error(['message' => $validated_data->get_error_message()]);
        wp_die();
    }

    $insert_success = $this->db->insert_data($validated_data);

    if($insert_success){
        $this->send_notification_email($validated_data);
    wp_send_json_success(['message' => 'Thank you! Your message has been sent successfully.']);
   } else {
    wp_send_json_error(['message' => 'A database error occurred. Please try again.']);
   }  
   wp_die();
   }

   private function validate_and_sanitize($data){
    // Sanitize all inputs
    $fields = [
        'cf_firstname' => sanitize_text_field( $data['cf_firstname'] ?? '' ),
        'cf_lastname'  => sanitize_text_field( $data['cf_lastname'] ?? '' ),
        'cf_email'     => sanitize_email( $data['cf_email'] ?? '' ),
        'cf_phone'     => sanitize_text_field( $data['cf_phone'] ?? '' ),
        'cf_message'   => sanitize_textarea_field( $data['cf_message'] ?? '' ),
    ];

    // Validation Checks
    if(empty($fields['cf_firstname']) || empty($fields['cf_email']) || empty($fields['cf_message'])){
        return new WP_Error('required_fields', 'Please fill in all required fields.');
    }
    if(!is_email($fields['cf_email'])){
        return new WP_Error('invalid_email', 'Please enter a valid email address.');
    }
    return $fields;
   }

   private function send_notification_email($data){
    // Get email from settings, fallback to site admin email
    $recipient = get_option('macf_notification_email', get_bloginfo('admin_email'));
    $subject = 'New Contact Form Submission from ' . get_bloginfo('name');
    
    $body = "New Submission Details:\n\n";
    $body .= "Name: {$data['cf_firstname']} {$data['cf_lastname']}\n";
    $body .= "Email: {$data['cf_email']}\n";
    $body .= "Phone: {$data['cf_phone']}\n\n";
    $body .= "Message:\n{$data['cf_message']}";

    wp_mail($recipient, $subject, $body);
}

}
?>