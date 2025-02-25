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
        add_action('wp_ajax_vb_apply_coupon', array($this, 'apply_coupon'));
        add_action('wp_ajax_nopriv_vb_apply_coupon', array($this, 'apply_coupon'));
        add_action('wp_ajax_vb_select_service', array($this, 'ajax_select_service'));
        add_action('wp_ajax_nopriv_vb_select_service', array($this, 'ajax_select_service'));
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

        // Create the service instance if not already created
        if (!$this->service) {
            $this->service = new Service();
        }

        // If no service ID provided, show service selection form
        if (empty($atts['service_id'])) {
            // Get all services for selection
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
            
            if (empty($top_level_services)) {
                return '<p>' . __('No services available for booking.', 'vandel-booking') . '</p>';
            }
            
            // Prepare form data for JavaScript
            wp_localize_script('vandel-booking-public', 'vbBookingData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vb_booking_form'),
                'currency' => get_option('vandel_booking_currency', 'USD'),
            ));
            
            // Set service to the first available service for template display
            // (this is just a placeholder, actual service will be selected by user)
            $service = $top_level_services[0];
            $service_data = array();
            
            ob_start();
            include VANDEL_BOOKING_PLUGIN_DIR . 'templates/booking-form.php';
            return ob_get_clean();
        }

        $service = get_post($atts['service_id']);
        if (!$service || $service->post_type !== 'vb_service') {
            return '<p>' . __('Invalid service ID.', 'vandel-booking') . '</p>';
        }

        // Get service details
        $service_data = array(
            'id' => $service->ID,
            'title' => $service->post_title,
            'duration' => get_post_meta($service->ID, 'vb_duration', true),
            'regular_price' => get_post_meta($service->ID, 'vb_regular_price', true),
            'sale_price' => get_post_meta($service->ID, 'vb_sale_price', true),
            'enable_deposit' => get_post_meta($service->ID, 'vb_enable_deposit', true),
            'deposit_type' => get_post_meta($service->ID, 'vb_deposit_type', true),
            'deposit_amount' => get_post_meta($service->ID, 'vb_deposit_amount', true),
            'tax_rate' => get_post_meta($service->ID, 'vb_tax_rate', true),
        );

        // Prepare form data for JavaScript
        wp_localize_script('vandel-booking-public', 'vbBookingData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vb_booking_form'),

            'service' => $service_data,
            'currency' => get_option('vandel_booking_currency', 'USD'),
            'stripeKey' => get_option('vandel_booking_stripe_test_mode') ? 
                get_option('vandel_booking_stripe_test_key') : 
                get_option('vandel_booking_stripe_live_key'),
        ));
        
        // Get top-level services (no parent) for the service selection step if needed
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

        ob_start();
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/booking-form.php';
        return ob_get_clean();
    }

    /**
     * AJAX handler for checking service availability
     */
public function check_availability() {
    // Verify nonce - using the correct action name for checking
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vb_booking_form')) {
        wp_send_json_error(array(
            'message' => __('Security check failed. Please refresh the page and try again.', 'vandel-booking')
        ));
        exit;
    }

    // Log and validate inputs
    error_log('check_availability called with: ' . print_r($_POST, true));

    // Validate and sanitize input
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';

    // Validate required parameters
    if (!$service_id) {
        wp_send_json_error(array(
            'message' => __('Service ID is required.', 'vandel-booking')
        ));
        exit;
    }
    
    if (!$date) {
        wp_send_json_error(array(
            'message' => __('Date is required.', 'vandel-booking')
        ));
        exit;
    }
    
    if (!$zip_code) {
        wp_send_json_error(array(
            'message' => __('ZIP code is required.', 'vandel-booking')
        ));
        exit;
    }

    // Validate date format
    $date_obj = date_create($date);
    if (!$date_obj) {
        wp_send_json_error(array(
            'message' => __('Invalid date format. Please use YYYY-MM-DD.', 'vandel-booking')
        ));
        exit;
    }

    // Ensure the service class is available
    if (!$this->service) {
        $this->service = new Service();
    }

    try {
        // Get available slots
        $slots = $this->service->get_available_slots($service_id, $date, $zip_code);
        
        // Log for debugging
        error_log('Available slots: ' . print_r($slots, true));

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
    } catch (Exception $e) {
        error_log('Error in check_availability: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => __('An error occurred while checking availability: ', 'vandel-booking') . $e->getMessage()
        ));
    }
}

    /**
     * AJAX handler for creating a booking
     */
    public function create_booking() {
        // Log server information
        error_log('CREATE BOOKING ENDPOINT CALLED');
        error_log('SERVER REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD']);
        error_log('SERVER HTTP REFERER: ' . ($_SERVER['HTTP_REFERER'] ?? 'Not Set'));
        error_log('REMOTE ADDRESS: ' . $_SERVER['REMOTE_ADDR']);

        // Detailed POST data logging (be careful with sensitive info)
        $sanitized_post = $_POST;
        unset($sanitized_post['customer_email'], $sanitized_post['customer_phone']);
        error_log('RECEIVED POST DATA: ' . print_r($sanitized_post, true));

        // Enhanced nonce verification
        if (!isset($_POST['nonce'])) {
            error_log('NO NONCE RECEIVED');
            wp_send_json_error(array(
                'message' => 'No nonce provided',
                'debug' => 'Nonce is missing from the request'
            ));
            exit;
        }

        // Verify nonce with more context
        if (!wp_verify_nonce($_POST['nonce'], 'vb_booking_form')) {
            error_log('NONCE VERIFICATION FAILED');
            error_log('Received nonce: ' . $_POST['nonce']);
            wp_send_json_error(array(
                'message' => 'Security check failed',
                'debug' => 'Nonce verification failed'
            ));
            exit;
        }

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

        error_log('create_booking called with: ' . print_r($_POST, true));
    }

/**
 * Get available slots for a service on a specific date
 */
public function get_available_slots($service_id, $date, $zip_code) {
    // Validate service exists
    $service = get_post($service_id);
    if (!$service || $service->post_type !== 'vb_service') {
        return array();
    }

    // Get service time slots
    $time_slots = get_post_meta($service_id, 'vb_time_slots', true);
    
    // Log for debugging
    error_log('Raw time slots: ' . print_r($time_slots, true));
    
    if (empty($time_slots) || !is_array($time_slots)) {
        // Create a default time slot if none exist
        $time_slots = array(
            array(
                'start' => '09:00',
                'end' => '17:00',
                'capacity' => 1
            )
        );
    }
    
    // Get service duration
    $duration = intval(get_post_meta($service_id, 'vb_duration', true));
    if (empty($duration) || $duration < 15) {
        $duration = 60; // Default to 1 hour
    }
    
    // Calculate available slots
    $available_slots = array();
    
    // Parse date to ensure correct format
    $date_obj = date_create($date);
    if (!$date_obj) {
        // Invalid date provided
        error_log('Invalid date format: ' . $date);
        return array();
    }
    
    $formatted_date = $date_obj->format('Y-m-d');
    $day_of_week = strtolower($date_obj->format('l')); // e.g., "monday"
    
    // Check if service is available on this day
    $available_days = get_post_meta($service_id, 'vb_available_days', true);
    
    // If no days are specified, assume all days are available
    if (empty($available_days) || !is_array($available_days)) {
        $available_days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday');
    }
    
    if (!in_array($day_of_week, $available_days)) {
        error_log('Service not available on ' . $day_of_week);
        return array();
    }
    
    // Get existing bookings for this date
    global $wpdb;
    $table_name = $wpdb->prefix . 'vb_bookings';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        error_log('Bookings table does not exist. Using sample data.');
        $existing_bookings = array(); // Table doesn't exist yet
    } else {
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_date FROM $table_name 
            WHERE service_id = %d 
            AND DATE(booking_date) = %s
            AND status NOT IN ('cancelled', 'rejected')",
            $service_id,
            $formatted_date
        ));
    }
    
    // Build array of booked times
    $booked_times = array();
    foreach ($existing_bookings as $booking) {
        $booking_time = strtotime($booking->booking_date);
        $booked_times[] = date('H:i', $booking_time);
    }
    
    // Current server time
    $current_time = current_time('timestamp');
    
    // Build available slots
    foreach ($time_slots as $slot) {
        if (empty($slot['start']) || empty($slot['end'])) {
            continue; // Skip invalid slots
        }
        
        $start_time = strtotime($formatted_date . ' ' . $slot['start']);
        $end_time = strtotime($formatted_date . ' ' . $slot['end']);
        
        // Skip if entire slot is in the past
        if ($end_time < $current_time) {
            continue;
        }
        
        // Calculate slots within this time range
        $interval_minutes = 30; // Default 30 minute intervals
        $slot_time = $start_time;
        
        while ($slot_time + ($duration * 60) <= $end_time) {
            // Skip slots that have already passed
            if ($slot_time < $current_time) {
                $slot_time += $interval_minutes * 60;
                continue;
            }
            
            $slot_start = date('H:i', $slot_time);
            
            // Check if slot is available (not booked or under capacity)
            $booked_count = 0;
            if (in_array($slot_start, $booked_times)) {
                $booked_count = array_count_values($booked_times)[$slot_start];
            }
            
            $capacity = isset($slot['capacity']) ? intval($slot['capacity']) : 1;
            $available = $booked_count < $capacity;
            
            $available_slots[] = array(
                'start' => $formatted_date . ' ' . $slot_start,
                'end' => date('Y-m-d H:i', $slot_time + ($duration * 60)),
                'available' => $available
            );
            
            // Move to next slot
            $slot_time += $interval_minutes * 60;
        }
    }
    
    // Log the final available slots
    error_log('Generated available slots: ' . count($available_slots));
    
    return $available_slots;
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
 * AJAX handler for selecting a service
 */
public function ajax_select_service() {
    // Verify nonce
    check_ajax_referer('vb_booking_form', 'nonce');

    // Sanitize input
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    $sub_service_id = isset($_POST['sub_service_id']) ? intval($_POST['sub_service_id']) : null;

    // For debugging
    error_log("ajax_select_service called with service_id: $service_id, sub_service_id: " . ($sub_service_id ? $sub_service_id : "null"));

    // Validate service
    $service = get_post($service_id);
    if (!$service || $service->post_type !== 'vb_service') {
        wp_send_json_error(array(
            'message' => __('Invalid service selected.', 'vandel-booking')
        ));
    }

    // If sub-service is provided, validate it
    $sub_service = null;
    if ($sub_service_id) {
        $sub_service = get_post($sub_service_id);
        if (!$sub_service || $sub_service->post_type !== 'vb_service') {
            wp_send_json_error(array(
                'message' => __('Invalid sub-service selected.', 'vandel-booking')
            ));
        }

        // Verify this is actually a sub-service of the selected service
        $parent_id = get_post_meta($sub_service_id, '_vb_parent_service', true);
        if ((int)$parent_id !== (int)$service_id) {
            error_log("Parent mismatch: sub-service parent_id: $parent_id, service_id: $service_id");
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
        'regular_price' => get_post_meta($service_id, 'vb_regular_price', true),
        'sale_price' => get_post_meta($service_id, 'vb_sale_price', true),
        'tax_rate' => get_post_meta($service_id, 'vb_tax_rate', true),
        'enable_deposit' => get_post_meta($service_id, 'vb_enable_deposit', true),
        'deposit_type' => get_post_meta($service_id, 'vb_deposit_type', true),
        'deposit_amount' => get_post_meta($service_id, 'vb_deposit_amount', true),
    );

    // If a sub-service is selected, override relevant details
    if ($sub_service) {
        $service_data['price'] = get_post_meta($sub_service_id, 'vb_regular_price', true);
        $service_data['title'] = $service->post_title . ' - ' . $sub_service->post_title;
        
        // Also get these values from sub-service if they exist
        $sub_tax_rate = get_post_meta($sub_service_id, 'vb_tax_rate', true);
        if ($sub_tax_rate) {
            $service_data['tax_rate'] = $sub_tax_rate;
        }
        
        $sub_deposit_enabled = get_post_meta($sub_service_id, 'vb_enable_deposit', true);
        if ($sub_deposit_enabled) {
            $service_data['enable_deposit'] = $sub_deposit_enabled;
            $service_data['deposit_type'] = get_post_meta($sub_service_id, 'vb_deposit_type', true);
            $service_data['deposit_amount'] = get_post_meta($sub_service_id, 'vb_deposit_amount', true);
        }
    } else {
        // If no sub-service, set price directly from service
        $service_data['price'] = get_post_meta($service_id, 'vb_regular_price', true);
        $sale_price = get_post_meta($service_id, 'vb_sale_price', true);
        if ($sale_price && $sale_price < $service_data['price']) {
            $service_data['price'] = $sale_price;
        }
    }

    error_log("Sending service data: " . print_r($service_data, true));
    wp_send_json_success($service_data);
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



public function security_test_endpoint() {
    error_log('SECURITY TEST ENDPOINT CALLED');
    
    // Log detailed request information
    error_log('SERVER REQUEST METHOD: ' . $_SERVER['REQUEST_METHOD']);
    error_log('SERVER HTTP REFERER: ' . ($_SERVER['HTTP_REFERER'] ?? 'Not Set'));
    error_log('REMOTE ADDRESS: ' . $_SERVER['REMOTE_ADDR']);

    wp_send_json_success('Security test passed');
}
}