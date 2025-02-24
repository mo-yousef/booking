<?php
namespace VandelBooking;

/**
 * Form class for handling booking forms
 */
class Form {
    /**
     * @var Service Service instance
     */
    private $service;

    /**
     * @var Database Database instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->service = new Service();
        $this->database = new Database();
        
        add_shortcode('vandel_booking_form', array($this, 'render_booking_form'));
        add_action('wp_ajax_vb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_vb_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_vb_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_nopriv_vb_create_booking', array($this, 'create_booking'));


        add_action('wp_ajax_vb_select_service', array($this, 'ajax_select_service'));
        add_action('wp_ajax_nopriv_vb_select_service', array($this, 'ajax_select_service'));

    }

public function ajax_select_service() {
    // Verify nonce
    check_ajax_referer('vb_booking_form', 'nonce');

    // Sanitize input
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $sub_service_id = isset($_POST['sub_service_id']) ? intval($_POST['sub_service_id']) : null;

    // Validate service
    $service = get_post($service_id);
    if (!$service || $service->post_type !== 'vb_service') {
        wp_send_json_error(array(
            'message' => __('Invalid service selected.', 'vandel-booking')
        ));
    }

    // If sub-service is provided, validate it
    if ($sub_service_id) {
        $sub_service = get_post($sub_service_id);
        if (!$sub_service || $sub_service->post_type !== 'vb_service') {
            wp_send_json_error(array(
                'message' => __('Invalid sub-service selected.', 'vandel-booking')
            ));
        }

        // Optional: Check if sub-service is actually a child of the main service
        $parent_id = get_post_meta($sub_service_id, '_vb_parent_service', true);
        if ($parent_id != $service_id) {
            wp_send_json_error(array(
                'message' => __('Selected sub-service does not belong to this service.', 'vandel-booking')
            ));
        }
    }

    // Prepare service data to return
    $service_data = array(
        'id' => $service_id,
        'title' => $service->post_title,
        'sub_service_id' => $sub_service_id,
        'price' => get_post_meta($service_id, 'vb_regular_price', true),
        'tax_rate' => get_post_meta($service_id, 'vb_tax_rate', true),
        'enable_deposit' => get_post_meta($service_id, 'vb_enable_deposit', true),
        'deposit_type' => get_post_meta($service_id, 'vb_deposit_type', true),
        'deposit_amount' => get_post_meta($service_id, 'vb_deposit_amount', true),
    );

    // If a sub-service is selected, override some details
    if ($sub_service_id) {
        $service_data['price'] = get_post_meta($sub_service_id, 'vb_regular_price', true);
        $service_data['title'] .= ' - ' . get_post($sub_service_id)->post_title;
    }

    wp_send_json_success($service_data);
}

   /**
     * Render the booking form
     */
    public function render_booking_form($atts) {
        wp_enqueue_style('vandel-booking-public');
        wp_enqueue_script('vandel-booking-public');

        $atts = shortcode_atts(array(
            'service_id' => 0,
        ), $atts);

        // Get top-level services
        $top_level_services = get_posts(array(
            'post_type' => 'vb_service',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_vb_parent_service',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        // Prepare form data for JavaScript
        // wp_localize_script('vandel-booking-public', 'vbBookingData', array(
        //     'ajaxurl' => admin_url('admin-ajax.php'),
        //     'nonce' => wp_create_nonce('vb_booking_form'),
        // ));

wp_enqueue_script('vandel-booking-public', plugin_dir_url(__FILE__) . 'assets/js/public.js', array('jquery'), '1.0.0', true);
wp_localize_script('vandel-booking-public', 'vbBookingData', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('vb_booking_form'),
    // Other necessary data
));

        // Include the template
        ob_start();
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }

/**
 * AJAX handler for checking service availability
 */
public function check_availability() {
    // Verify nonce
    check_ajax_referer('vb_booking_form', 'nonce');

    // Validate and sanitize input
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

    // Validate required parameters
    if (!$service_id || !$date || !$zip_code) {
        wp_send_json_error(array(
            'message' => __('Missing required parameters.', 'vandel-booking')
        ));
    }

    // Validate date format
    if (!strtotime($date)) {
        wp_send_json_error(array(
            'message' => __('Invalid date format.', 'vandel-booking')
        ));
    }

    // Get available slots
    $slots = $this->service->get_available_slots($service_id, $date, $zip_code);

    // Get pricing for the location
    $price = $this->database->get_service_price($service_id, $zip_code);
    if (!$price) {
        $price = get_post_meta($service_id, 'vb_regular_price', true);
    }

    // Prepare and return response
    wp_send_json_success(array(
        'slots' => $slots,
        'price' => $price
    ));
}

/**
 * AJAX handler for creating a booking
 */
public function create_booking() {
    // Verify nonce
    check_ajax_referer('vb_booking_form', 'nonce');

    // Sanitize and validate input
    $data = array(
        'service_id' => isset($_POST['service_id']) ? intval($_POST['service_id']) : 0,
        'sub_service_id' => isset($_POST['sub_service_id']) ? intval($_POST['sub_service_id']) : null,
        'booking_date' => isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '',
        'zip_code' => isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '',
        'customer_name' => isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '',
        'customer_email' => isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '',
        'customer_phone' => isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '',
        'customer_notes' => isset($_POST['customer_notes']) ? sanitize_textarea_field($_POST['customer_notes']) : '',
        'total_amount' => isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0,
        'tax_amount' => isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0,
        'discount' => isset($_POST['discount']) ? floatval($_POST['discount']) : 0,
        'pay_deposit' => isset($_POST['pay_deposit']) ? true : false
    );

    // Validate required fields
    $required_fields = array('service_id', 'booking_date', 'zip_code', 'customer_name', 'customer_email', 'customer_phone');
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            wp_send_json_error(array(
                'message' => sprintf(__('Missing required field: %s', 'vandel-booking'), $field)
            ));
        }
    }

    // Create customer if not exists
    $customer_id = $this->create_or_get_customer($data);

    // Prepare booking data for database
    $booking_data = array(
        'service_id' => $data['service_id'],
        'customer_id' => $customer_id,
        'booking_date' => $data['booking_date'],
        'status' => 'pending',
        'total_amount' => $data['total_amount'],
        'tax_amount' => $data['tax_amount'],
        'zip_code' => $data['zip_code']
    );

    // Create booking
    $booking_id = $this->database->create_booking($booking_data);

    if (!$booking_id) {
        wp_send_json_error(array(
            'message' => __('Failed to create booking.', 'vandel-booking')
        ));
    }

    // Send confirmation emails
    $this->send_booking_emails($booking_id);

    wp_send_json_success(array(
        'booking_id' => $booking_id,
        'message' => __('Booking created successfully.', 'vandel-booking')
    ));
}

    /**
     * AJAX handler for applying a coupon
     */
    public function apply_coupon() {
        check_ajax_referer('vb_apply_coupon', 'nonce');

        $coupon_code = isset($_POST['coupon']) ? sanitize_text_field($_POST['coupon']) : '';
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $total_amount = isset($_POST['total']) ? floatval($_POST['total']) : 0;

        if (!$coupon_code || !$service_id || !$total_amount) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'vandel-booking')
            ));
        }

        global $wpdb;
        
        // Get coupon details
        $coupon = $wpdb->get_row($wpdb->prepare(
            "SELECT c.* FROM {$wpdb->prefix}vb_coupons c
            JOIN {$wpdb->prefix}vb_coupon_services cs ON c.id = cs.coupon_id
            WHERE c.code = %s AND cs.service_id = %d
            AND c.status = 'active'
            AND (c.start_date IS NULL OR c.start_date <= NOW())
            AND (c.end_date IS NULL OR c.end_date >= NOW())
            AND (c.usage_limit IS NULL OR c.usage_count < c.usage_limit)",
            $coupon_code,
            $service_id
        ));

        if (!$coupon) {
            wp_send_json_error(array(
                'message' => __('Invalid or expired coupon code.', 'vandel-booking')
            ));
        }

        // Calculate discount
        $discount = 0;
        if ($coupon->discount_type === 'percentage') {
            $discount = $total_amount * ($coupon->discount_amount / 100);
        } else {
            $discount = $coupon->discount_amount;
        }

        wp_send_json_success(array(
            'discount' => $discount,
            'message' => __('Coupon applied successfully.', 'vandel-booking')
        ));
    }

/**
 * Create or retrieve customer
 */
private function create_or_get_customer($data) {
    // Check if customer exists
    $user = get_user_by('email', $data['customer_email']);
    
    if ($user) {
        return $user->ID;
    }

    // Create new customer
    $user_data = array(
        'user_login' => $data['customer_email'],
        'user_email' => $data['customer_email'],
        'user_pass' => wp_generate_password(),
        'first_name' => $data['customer_name'],
        'role' => 'customer'
    );

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        return 0;
    }

    // Add phone number as user meta
    update_user_meta($user_id, 'billing_phone', $data['customer_phone']);

    return $user_id;
}

    /**
     * Send booking confirmation emails
     */
    private function send_booking_emails($booking_id) {
        // Implementation for sending emails
        // You'll need to create email templates and use wp_mail()
    }
}