<?php 
if ( ! class_exists('MA_Contact_Form') ) {
class MA_Contact_Form{
   private static $instance = null;
   private $db;
   private $plugin_url;

   private function __construct(){
    $this->db = Contact_Form_DB::get_instance();
    $this->plugin_url = plugin_dir_url(__FILE__) . '../'; // For assets

    
    // Frontend Hooks
    // add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
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

//    public function enqueue_assets(){
//     wp_enqueue_style('ma-cf-style', CONTACT_FORM_URL . 'assets/css/style.css', [],'1.0');
//     wp_enqueue_script('ma-cf-ajax', CONTACT_FORM_URL . 'assets/js/ajax-script.js', ['jquery'], '1.0',true);
    
//     // Pass essential data to JavaScript
//     wp_localize_script('ma-cf-ajax', 'maContactFormAjax', [
//         'ajaxurl' => admin_url('admin-ajax.php'),
//         'nonce' => wp_create_nonce('ma_contact_form_nonce_action'),
//     ]);

    

//    }

   public function register_shortcode(){
    add_shortcode('ma_contact_form', [$this, 'render_contact_form']);
   }

   public function render_contact_form(){
    ob_start();
            ?>
            <div class="ma-contact-form-wrap">
                <form id="ma-contact-form" method="post" novalidate>
                    <?php wp_nonce_field('my_new_form_action', 'ma_contact_form_nonce_field'); ?>
                    <div class="macf-field-wrap">
                        <input type="text" name="cf_firstname" id="cf_firstname" placeholder="First Name *" required>
                        <span class="macf-error" id="error-cf_firstname"></span>
                    </div>
                    <div class="macf-field-wrap">
                        <input type="text" name="cf_lastname" id="cf_lastname" placeholder="Last Name (Optional)">
                        <span class="macf-error" id="error-cf_lastname"></span>
                    </div>
                    <div class="macf-field-wrap">
                        <input type="email" name="cf_email" id="cf_email" placeholder="Email Address *" required>
                        <span class="macf-error" id="error-cf_email"></span>
                    </div>
                    <div class="macf-field-wrap">
                        <input type="text" name="cf_phone" id="cf_phone" placeholder="Phone (Optional)">
                        <span class="macf-error" id="error-cf_phone"></span>
                    </div>
                    <div class="macf-field-wrap text">
                        <textarea name="cf_message" id="cf_message" placeholder="Your Message *" rows="5" required></textarea>
                        <span class="macf-error" id="error-cf_message"></span>
                    </div>
                    <button type="submit">Send Message</button>
                    <div id="ma-form-response" aria-live="polite"></div>
                </form>
            </div>
            <?php
            return ob_get_clean();
   }

   public function ajax_submit_form(){ 

//    Check nonce
   if ( ! isset($_POST['ma_contact_form_nonce_field']) ||
   ! wp_verify_nonce($_POST['ma_contact_form_nonce_field'], 'my_new_form_action') ) {
  wp_send_json_error(['message' => 'Security check failed.'], 400);
}

    $validated_data = $this->validate_and_sanitize($_POST);

    
    // Check if validation failed (is a WP_Error object)
    if(is_wp_error($validated_data)){
       
        $field_errors = [];
        $codes = $validated_data->get_error_codes();
        foreach ($codes as $code) {
             $field_errors[$code] = $validated_data->get_error_message($code);
        }

        // echo "<pre>";
        // var_dump($field_errors);
        // echo "</pre>";
       
        wp_send_json_error([
            'type' => 'validation_errors',
            'errors' => $field_errors,
        ], 200);
    }

    $insert_success = $this->db->insert_data($validated_data);

    if ( $insert_success === false ) {
        wp_send_json_error(['message' => 'Database insert failed.'], 500);
    }

    // Send notification email
    $this->send_notification_email($validated_data);

    wp_send_json_success(['message' => 'Thank you! Your message has been sent successfully.'], 200);
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
    
    $max_message_length = 500;
    
    $errors = new WP_Error();

    $db = Contact_Form_DB::get_instance(); 

    // --- Sanitize all inputs ---
    $fields = [
        'cf_firstname' => sanitize_text_field( $data['cf_firstname'] ?? '' ),
        'cf_lastname'  => sanitize_text_field( $data['cf_lastname'] ?? '' ),
        'cf_email'     => sanitize_email( $data['cf_email'] ?? '' ),
        'cf_phone'     => sanitize_text_field( $data['cf_phone'] ?? '' ),
        'cf_message'   => sanitize_textarea_field( $data['cf_message'] ?? '' ),
    ];

    // --- Validation Checks ---

    if(empty($fields['cf_firstname'])){
        $errors->add('cf_firstname', 'First Name is required.');
    }
    if(empty($fields['cf_email'])){
        $errors->add('cf_email', 'Email Address is required.');
    }
    if(empty($fields['cf_message'])){
        $errors->add('cf_message', 'Message is required.');
    }
    
    
    if(!empty($fields['cf_email'])) {
        if(!is_email($fields['cf_email'])){
            $errors->add('cf_email', 'Please enter a valid email address (e.g., user@domain.com).');
        } 
        // Run duplicate check only if email format is initially valid
        else if ($db->is_email_duplicate($fields['cf_email'])) {
            $errors->add('cf_email', 'This email address has already been used for a submission.');
        }
    }


    if (!empty($fields['cf_phone'])) {
       
        $clean_phone = preg_replace('/[^\d]/', '', $fields['cf_phone']);

        
        if (strlen($clean_phone) !== 10) {
            $errors->add('cf_phone', 'The phone number must contain exactly 10 digits.');
        } 
        
        else if ($db->is_phone_duplicate($clean_phone)) {
            $errors->add('cf_phone', 'This phone number has already been used for a submission.');
        }
        
        
        if (!$errors->get_error_codes('cf_phone')) {
             $fields['cf_phone'] = $clean_phone; 
        }
    }

    $message_length = function_exists('mb_strlen') ? mb_strlen($fields['cf_message']) : strlen($fields['cf_message']);

    if($message_length > $max_message_length){
        $errors->add('cf_message', "Your message is too long. Maximum allowed is $max_message_length characters.");
    }

   
    if ($errors->get_error_codes()) {
        return $errors;
    }
    
    return $fields;
}

private function send_notification_email($data){
   
    $recipient = get_option('macf_notification_email', get_bloginfo('admin_email'));
    $subject = 'New Contact Form Submission from ' . get_bloginfo('name');
    
    $body = "New Submission Details:\n\n";
    $body .= "Name: {$data['cf_firstname']} {$data['cf_lastname']}\n";
    $body .= "Email: {$data['cf_email']}\n";
    $body .= "Phone: {$data['cf_phone']}\n\n";
    $body .= "Message:\n{$data['cf_message']}";


    $from_email = get_option('macf_notification_email', get_bloginfo('admin_email')); 
    $from_name = 'MA Contact Form Notifier'; 

    $headers = [
        "From: {$from_name} <{$from_email}>",
        "Reply-To: {$data['cf_firstname']} <{$data['cf_email']}>", 
        "Content-Type: text/plain; charset=UTF-8"
    ];

    wp_mail($recipient, $subject, $body, $headers);

}

}
}
?>