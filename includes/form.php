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
        wp_localize_script('vandel-booking-public', 'vbBookingData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vb_booking_form'),
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
        check_ajax_referer('vb_check_availability', 'nonce');

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

        if (!$service_id || !$date || !$zip_code) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'vandel-booking')
            ));
        }

        // Get available slots
        $slots = $this->service->get_available_slots($service_id, $date, $zip_code);

        // Get pricing for the location
        $price = $this->database->get_service_price($service_id, $zip_code);
        if (!$price) {
            $price = get_post_meta($service_id, 'vb_regular_price', true);
        }

        wp_send_json_success(array(
            'slots' => $slots,
            'price' => $price
        ));
    }

    /**
     * AJAX handler for creating a booking
     */
    public function create_booking() {
        check_ajax_referer('vb_create_booking', 'nonce');

        // Validate required fields
        $required_fields = array(
            'service_id' => 'intval',
            'booking_date' => 'sanitize_text_field',
            'zip_code' => 'sanitize_text_field',
            'customer_name' => 'sanitize_text_field',
            'customer_email' => 'sanitize_email',
            'customer_phone' => 'sanitize_text_field'
        );

        $data = array();
        foreach ($required_fields as $field => $sanitize_function) {
            if (!isset($_POST[$field])) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Missing required field: %s', 'vandel-booking'), $field)
                ));
            }
            $data[$field] = $sanitize_function($_POST[$field]);
        }

        // Validate booking date format
        $booking_timestamp = strtotime($data['booking_date']);
        if (!$booking_timestamp) {
            wp_send_json_error(array(
                'message' => __('Invalid booking date format.', 'vandel-booking')
            ));
        }

        // Check if slot is still available
        $slots = $this->service->get_available_slots(
            $data['service_id'],
            date('Y-m-d', $booking_timestamp),
            $data['zip_code']
        );

        $slot_available = false;
        foreach ($slots as $slot) {
            if ($slot['start'] === $data['booking_date'] && $slot['available']) {
                $slot_available = true;
                break;
            }
        }

        if (!$slot_available) {
            wp_send_json_error(array(
                'message' => __('Selected time slot is no longer available.', 'vandel-booking')
            ));
        }

        // Calculate pricing
        $price = $this->database->get_service_price($data['service_id'], $data['zip_code']);
        if (!$price) {
            $price = get_post_meta($data['service_id'], 'vb_regular_price', true);
        }

        // Apply tax
        $tax_rate = get_post_meta($data['service_id'], 'vb_tax_rate', true);
        $tax_amount = $price * ($tax_rate / 100);

        // Check for deposit payment
        $enable_deposit = get_post_meta($data['service_id'], 'vb_enable_deposit', true);
        $deposit_amount = null;
        if ($enable_deposit) {
            $deposit_type = get_post_meta($data['service_id'], 'vb_deposit_type', true);
            $deposit_value = get_post_meta($data['service_id'], 'vb_deposit_amount', true);
            
            if ($deposit_type === 'percentage') {
                $deposit_amount = $price * ($deposit_value / 100);
            } else {
                $deposit_amount = $deposit_value;
            }
        }

        // Create customer if not exists
        $customer_id = $this->create_or_get_customer($data);

        // Prepare booking data
        $booking_data = array(
            'service_id' => $data['service_id'],
            'customer_id' => $customer_id,
            'booking_date' => $data['booking_date'],
            'status' => 'pending',
            'total_amount' => $price + $tax_amount,
            'deposit_amount' => $deposit_amount,
            'tax_amount' => $tax_amount,
            'currency' => get_option('vandel_booking_currency', 'USD'),
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
     * Create or get customer
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