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

//    private function validate_and_sanitize($data){
//     // Sanitize all inputs
//     $fields = [
//         'cf_firstname' => sanitize_text_field( $data['cf_firstname'] ?? '' ),
//         'cf_lastname'  => sanitize_text_field( $data['cf_lastname'] ?? '' ),
//         'cf_email'     => sanitize_email( $data['cf_email'] ?? '' ),
//         'cf_phone'     => sanitize_text_field( $data['cf_phone'] ?? '' ),
//         'cf_message'   => sanitize_textarea_field( $data['cf_message'] ?? '' ),
//     ];

//     // Validation Checks
//     if(empty($fields['cf_firstname']) || empty($fields['cf_email']) || empty($fields['cf_message'])){
//         return new WP_Error('required_fields', 'Please fill in all required fields.');
//     }
//     if(!is_email($fields['cf_email'])){
//         return new WP_Error('invalid_email', 'Please enter a valid email address.');
//     }
//     return $fields;
//    }

private function validate_and_sanitize($data){
    // --- Configuration ---
    $max_message_length = 500;
    $strict_phone_regex = '/^\d{10}$/'; 

    // Initialize the WP_Error object
    $errors = new WP_Error();

    // Get the database instance for duplicate checks
    // NOTE: Replace 'Contact_Form_DB' with the actual class name if different
    $db = Contact_Form_DB::get_instance(); 

    // --- 1. Sanitize all inputs ---
    $fields = [
        'cf_firstname' => sanitize_text_field( $data['cf_firstname'] ?? '' ),
        'cf_lastname'  => sanitize_text_field( $data['cf_lastname'] ?? '' ),
        'cf_email'     => sanitize_email( $data['cf_email'] ?? '' ),
        'cf_phone'     => sanitize_text_field( $data['cf_phone'] ?? '' ),
        'cf_message'   => sanitize_textarea_field( $data['cf_message'] ?? '' ),
    ];

    // --- 2. Validation Checks ---

    // A. Check for REQUIRED Fields
    if(empty($fields['cf_firstname'])){
        $errors->add('firstname_required', 'First Name is a required field.');
    }
    if(empty($fields['cf_email'])){
        $errors->add('email_required', 'Email is a required field.');
    }
    if(empty($fields['cf_message'])){
        $errors->add('message_required', 'Message is a required field.');
    }

    // Return required errors immediately before proceeding to data-specific checks
    if (!empty($errors->get_error_codes())) {
        return $errors;
    }

    // B. Email Format and Duplication Validation (ALWAYS REQUIRED)
    if(!is_email($fields['cf_email'])){
        $errors->add('invalid_email', 'Please enter a valid email address, e.g., name@example.com.');
    } 
    // Run duplicate check only if email format is initially valid
    else if ($db->is_email_duplicate($fields['cf_email'])) {
        $errors->add('duplicate_email', 'This email address has already submitted a form.');
    }


    // C. Phone Number Validation (OPTIONAL but strict if entered)
    if (!empty($fields['cf_phone'])) {
        // 1. Clean the input to remove symbols
        $clean_phone = preg_replace('/[^\d]/', '', $fields['cf_phone']);

        // 2. Check the cleaned string against the 10-digit requirement
        if (!preg_match($strict_phone_regex, $clean_phone)) {
            $errors->add('invalid_phone', 'The phone number must contain exactly 10 digits.');
        } 
        // 3. Check for Duplication (using the standardized 10-digit format)
        else if ($db->is_phone_duplicate($clean_phone)) {
            $errors->add('duplicate_phone', 'This phone number has already submitted a form.');
        }
        
        // 4. Update the field to the cleaned, standardized number for saving
        if (!$errors->get_error_codes('invalid_phone') && !$errors->get_error_codes('duplicate_phone')) {
             $fields['cf_phone'] = $clean_phone; 
        }
    }

    // D. Message Character Limit
    $message_length = function_exists('mb_strlen') ? mb_strlen($fields['cf_message']) : strlen($fields['cf_message']);

    if($message_length > $max_message_length){
        $error_message = sprintf(
            'Your message is too long. Maximum allowed is %d characters, but you used %d.',
            $max_message_length,
            $message_length
        );
        $errors->add('message_too_long', $error_message);
    }

    // --- 3. Final Return ---
    if ($errors->get_error_codes()) {
        // Return WP_Error object if any errors occurred
        return $errors;
    }

    // Return the sanitized and validated fields array
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