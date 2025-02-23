<?php
namespace VandelBooking;

/**
 * Admin class for managing plugin settings and interface
 */
class Admin {
    /**
     * @var Database Database instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Database();
        
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_vb_admin_update_booking', array($this, 'update_booking_status'));
        add_action('wp_ajax_vb_admin_update_pricing', array($this, 'update_service_pricing'));
        add_action('wp_ajax_vb_admin_create_coupon', array($this, 'create_coupon'));

    add_action('add_meta_boxes', array($this, 'add_service_meta_boxes'));
    add_action('save_post_vb_service', array($this, 'save_service_meta'));

    }

    /**
     * Add admin menu pages
     */
    public function add_menu_pages() {
        add_menu_page(
            __('Vandel Booking', 'vandel-booking'),
            __('Vandel Booking', 'vandel-booking'),
            'manage_options',
            'vandel-booking',
            array($this, 'render_dashboard_page'),
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'vandel-booking',
            __('Dashboard', 'vandel-booking'),
            __('Dashboard', 'vandel-booking'),
            'manage_options',
            'vandel-booking',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'vandel-booking',
            __('Bookings', 'vandel-booking'),
            __('Bookings', 'vandel-booking'),
            'manage_options',
            'vandel-booking-bookings',
            array($this, 'render_bookings_page')
        );

        add_submenu_page(
            'vandel-booking',
            __('Services', 'vandel-booking'),
            __('Services', 'vandel-booking'),
            'manage_options',
            'edit.php?post_type=vb_service'
        );

        add_submenu_page(
            'vandel-booking',
            __('Coupons', 'vandel-booking'),
            __('Coupons', 'vandel-booking'),
            'manage_options',
            'vandel-booking-coupons',
            array($this, 'render_coupons_page')
        );

        add_submenu_page(
            'vandel-booking',
            __('Settings', 'vandel-booking'),
            __('Settings', 'vandel-booking'),
            'manage_options',
            'vandel-booking-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('vandel_booking_settings', 'vandel_booking_currency');
        register_setting('vandel_booking_settings', 'vandel_booking_stripe_test_mode');
        register_setting('vandel_booking_settings', 'vandel_booking_stripe_test_key');
        register_setting('vandel_booking_settings', 'vandel_booking_stripe_test_secret');
        register_setting('vandel_booking_settings', 'vandel_booking_stripe_live_key');
        register_setting('vandel_booking_settings', 'vandel_booking_stripe_live_secret');
        register_setting('vandel_booking_settings', 'vandel_booking_email_notifications');
        register_setting('vandel_booking_settings', 'vandel_booking_admin_email');
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        $admin_pages = array(
            'toplevel_page_vandel-booking',
            'vandel-booking_page_vandel-booking-bookings',
            'vandel-booking_page_vandel-booking-coupons',
            'vandel-booking_page_vandel-booking-settings'
        );

        if (!in_array($hook, $admin_pages) && get_post_type() !== 'vb_service') {
            return;
        }

        wp_enqueue_style(
            'vandel-booking-admin',
            VANDEL_BOOKING_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VANDEL_BOOKING_VERSION
        );

        wp_enqueue_script(
            'vandel-booking-admin',
            VANDEL_BOOKING_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VANDEL_BOOKING_VERSION,
            true
        );

        wp_localize_script('vandel-booking-admin', 'vbAdminData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vb_admin_nonce')
        ));
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Get statistics
        $stats = $this->get_booking_statistics();
        
        // Get recent bookings
        $recent_bookings = $this->get_recent_bookings();

        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render bookings page
     */
    public function render_bookings_page() {
        // Handle bulk actions
        $this->handle_bulk_actions();

        // Get bookings with filters
        $bookings = $this->get_filtered_bookings();

        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/bookings.php';
    }

    /**
     * Render coupons page
     */
    public function render_coupons_page() {
        global $wpdb;
        
        // Get all coupons
        $coupons = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}vb_coupons ORDER BY created_at DESC"
        );

        // Get all services for coupon assignment
        $services = get_posts(array(
            'post_type' => 'vb_service',
            'posts_per_page' => -1
        ));

        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/coupons.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * AJAX handler for updating booking status
     */
    public function update_booking_status() {
        check_ajax_referer('vb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'vandel-booking')));
        }

        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$booking_id || !$status) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'vandel-booking')));
        }

        $result = $this->database->update_booking($booking_id, array('status' => $status));

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to update booking status.', 'vandel-booking')));
        }

        // Send notification email if needed
        if (get_option('vandel_booking_email_notifications', true)) {
            $this->send_status_update_email($booking_id, $status);
        }

        wp_send_json_success(array(
            'message' => __('Booking status updated successfully.', 'vandel-booking')
        ));
    }

    /**
     * AJAX handler for updating service pricing
     */
    public function update_service_pricing() {
        check_ajax_referer('vb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'vandel-booking')));
        }

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;

        if (!$service_id || !$zip_code || !$price) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'vandel-booking')));
        }

        $result = $this->database->set_service_price($service_id, $zip_code, $price);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to update service pricing.', 'vandel-booking')));
        }

        wp_send_json_success(array(
            'message' => __('Service pricing updated successfully.', 'vandel-booking')
        ));
    }

    /**
     * AJAX handler for creating coupons
     */
    public function create_coupon() {
        check_ajax_referer('vb_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'vandel-booking')));
        }

        // Validate and sanitize input
        $code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : '';
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : '';
        $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
        $usage_limit = isset($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        $services = isset($_POST['services']) ? array_map('intval', $_POST['services']) : array();

        if (!$code || !$discount_type || !$discount_amount || empty($services)) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'vandel-booking')));
        }

        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Insert coupon
            $wpdb->insert(
                $wpdb->prefix . 'vb_coupons',
                array(
                    'code' => $code,
                    'discount_type' => $discount_type,
                    'discount_amount' => $discount_amount,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'usage_limit' => $usage_limit,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%f', '%s', '%s', '%d', '%s')
            );

            $coupon_id = $wpdb->insert_id;

            // Insert service relationships
            foreach ($services as $service_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'vb_coupon_services',
                    array(
                        'coupon_id' => $coupon_id,
                        'service_id' => $service_id
                    ),
                    array('%d', '%d')
                );
            }

            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message' => __('Coupon created successfully.', 'vandel-booking'),
                'coupon_id' => $coupon_id
            ));

        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => __('Failed to create coupon.', 'vandel-booking')));
        }
    }

    /**
     * Get booking statistics
     */
    private function get_booking_statistics() {
        global $wpdb;

        $stats = array(
            'total_bookings' => 0,
            'pending_bookings' => 0,
            'completed_bookings' => 0,
            'total_revenue' => 0
        );

        // Get counts
        $results = $wpdb->get_results(
            "SELECT status, COUNT(*) as count, SUM(total_amount) as revenue
            FROM {$wpdb->prefix}vb_bookings
            GROUP BY status"
        );

        foreach ($results as $result) {
            $stats['total_bookings'] += $result->count;
            if ($result->status === 'pending') {
                $stats['pending_bookings'] = $result->count;
            } elseif ($result->status === 'completed') {
                $stats['completed_bookings'] = $result->count;
                $stats['total_revenue'] = $result->revenue;
            }
        }

        return $stats;
    }

    /**
     * Get recent bookings
     */
    private function get_recent_bookings($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.post_title as service_name, u.display_name as customer_name
            FROM {$wpdb->prefix}vb_bookings b
            LEFT JOIN {$wpdb->posts} p ON b.service_id = p.ID
            LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
            ORDER BY b.created_at DESC
            LIMIT %d",
            $limit
        ));
    }

    /**
     * Get filtered bookings
     */
    private function get_filtered_bookings() {
        global $wpdb;

        $where = array('1=1');
        $values = array();

        // Apply filters
        if (isset($_GET['status']) && $_GET['status']) {
            $where[] = 'b.status = %s';
            $values[] = sanitize_text_field($_GET['status']);
        }

        if (isset($_GET['service']) && $_GET['service']) {
            $where[] = 'b.service_id = %d';
            $values[] = intval($_GET['service']);
        }

        if (isset($_GET['date_from']) && $_GET['date_from']) {
            $where[] = 'b.booking_date >= %s';
            $values[] = sanitize_text_field($_GET['date_from']);
        }

        if (isset($_GET['date_to']) && $_GET['date_to']) {
            $where[] = 'b.booking_date <= %s';
            $values[] = sanitize_text_field($_GET['date_to']);
        }

        // Build query
        $query = "SELECT b.*, p.post_title as service_name, u.display_name as customer_name
                 FROM {$wpdb->prefix}vb_bookings b
                 LEFT JOIN {$wpdb->posts} p ON b.service_id = p.ID
                 LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY b.booking_date DESC";

        // Add pagination
        $items_per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $items_per_page;

        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $items_per_page, $offset);

        // Get total items for pagination
        $total_items = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vb_bookings b WHERE " . implode(' AND ', $where)
        );

        return array(
            'items' => $wpdb->get_results($wpdb->prepare($query, $values)),
            'total_items' => $total_items,
            'items_per_page' => $items_per_page,
            'current_page' => $current_page
        );
    }

    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['action']) || !isset($_POST['booking'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-bookings')) {
            wp_die(__('Security check failed.', 'vandel-booking'));
        }

        $action = sanitize_text_field($_POST['action']);
        $bookings = array_map('intval', $_POST['booking']);

        switch ($action) {
            case 'delete':
                $this->bulk_delete_bookings($bookings);
                break;
            case 'complete':
                $this->bulk_update_booking_status($bookings, 'completed');
                break;
            case 'cancel':
                $this->bulk_update_booking_status($bookings, 'cancelled');
                break;
        }

        wp_redirect(add_query_arg('bulk_action_completed', '1'));
        exit;
    }

    /**
     * Bulk delete bookings
     */
    private function bulk_delete_bookings($booking_ids) {
        if (!current_user_can('manage_options')) {
            return;
        }

        foreach ($booking_ids as $booking_id) {
            $this->database->delete_booking($booking_id);
        }
    }

    /**
     * Bulk update booking status
     */
    private function bulk_update_booking_status($booking_ids, $status) {
        if (!current_user_can('manage_options')) {
            return;
        }

        foreach ($booking_ids as $booking_id) {
            $this->database->update_booking($booking_id, array('status' => $status));
            
            // Send notification email if enabled
            if (get_option('vandel_booking_email_notifications', true)) {
                $this->send_status_update_email($booking_id, $status);
            }
        }
    }

    /**
     * Send status update email
     */
    private function send_status_update_email($booking_id, $status) {
        $booking = $this->database->get_booking($booking_id);
        if (!$booking) {
            return;
        }

        $customer = get_user_by('ID', $booking->customer_id);
        if (!$customer) {
            return;
        }

        $service = get_post($booking->service_id);
        if (!$service) {
            return;
        }

        $admin_email = get_option('vandel_booking_admin_email', get_option('admin_email'));
        
        // Customer notification
        $subject = sprintf(
            __('Booking #%d Status Update - %s', 'vandel-booking'),
            $booking_id,
            $service->post_title
        );

        $message = $this->get_status_email_template($booking, $service, $status);
        
        wp_mail($customer->user_email, $subject, $message);

        // Admin notification
        $admin_subject = sprintf(
            __('Booking #%d Updated to %s', 'vandel-booking'),
            $booking_id,
            strtoupper($status)
        );

        $admin_message = $this->get_admin_status_email_template($booking, $service, $status, $customer);
        
        wp_mail($admin_email, $admin_subject, $admin_message);
    }

    /**
     * Get status update email template
     */
    private function get_status_email_template($booking, $service, $status) {
        ob_start();
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/emails/status-update.php';
        return ob_get_clean();
    }

    /**
     * Get admin status update email template
     */
    private function get_admin_status_email_template($booking, $service, $status, $customer) {
        ob_start();
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/emails/admin-status-update.php';
        return ob_get_clean();
    }

    /**
     * Export bookings to CSV
     */
    public function export_bookings_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied.', 'vandel-booking'));
        }

        $bookings = $this->get_filtered_bookings()['items'];
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings-export-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Add headers
        fputcsv($output, array(
            __('Booking ID', 'vandel-booking'),
            __('Service', 'vandel-booking'),
            __('Customer', 'vandel-booking'),
            __('Date', 'vandel-booking'),
            __('Status', 'vandel-booking'),
            __('Total Amount', 'vandel-booking'),
            __('ZIP Code', 'vandel-booking'),
        ));

        // Add data
        foreach ($bookings as $booking) {
            fputcsv($output, array(
                $booking->id,
                $booking->service_name,
                $booking->customer_name,
                $booking->booking_date,
                $booking->status,
                $booking->total_amount,
                $booking->zip_code,
            ));
        }

        fclose($output);
        exit;
    }



public function add_service_meta_boxes() {
    add_meta_box(
        'vb_service_settings',
        __('Service Settings', 'vandel-booking'),
        array($this, 'render_service_meta_box'),
        'vb_service',
        'normal',
        'high'
    );
}

public function render_service_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('vb_service_meta_box', 'vb_service_meta_box_nonce');
    
    // Include the meta box template
    include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/service-meta-box.php';
}

public function save_service_meta($post_id) {
    // Check if nonce is set
    if (!isset($_POST['vb_service_meta_box_nonce'])) {
        return;
    }

    // Verify nonce
    if (!wp_verify_nonce($_POST['vb_service_meta_box_nonce'], 'vb_service_meta_box')) {
        return;
    }

    // If this is an autosave, don't do anything
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save general settings
    $fields = array(
        'vb_duration' => 'intval',
        'vb_buffer_time' => 'intval',
        'vb_max_bookings_per_day' => 'intval',
        'vb_regular_price' => 'floatval',
        'vb_sale_price' => 'floatval',
        'vb_tax_rate' => 'floatval',
        'vb_enable_scheduling' => 'intval',
        'vb_enable_deposit' => 'intval',
        'vb_deposit_type' => 'sanitize_text_field',
        'vb_deposit_amount' => 'floatval'
    );

    foreach ($fields as $field => $sanitize_callback) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, $sanitize_callback($_POST[$field]));
        } else {
            delete_post_meta($post_id, $field);
        }
    }

    // Save location pricing
    if (isset($_POST['vb_location_pricing']) && is_array($_POST['vb_location_pricing'])) {
        $location_pricing = array();
        foreach ($_POST['vb_location_pricing'] as $pricing) {
            if (!empty($pricing['zip']) && isset($pricing['price'])) {
                $location_pricing[] = array(
                    'zip' => sanitize_text_field($pricing['zip']),
                    'price' => floatval($pricing['price'])
                );
            }
        }
        update_post_meta($post_id, 'vb_location_pricing', $location_pricing);
    } else {
        delete_post_meta($post_id, 'vb_location_pricing');
    }

    // Save schedule settings
    if (isset($_POST['vb_schedule']) && is_array($_POST['vb_schedule'])) {
        $enabled_days = isset($_POST['vb_enabled_days']) ? $_POST['vb_enabled_days'] : array();
        
        foreach ($_POST['vb_schedule'] as $day => $times) {
            $schedule = array(
                'enabled' => in_array($day, $enabled_days),
                'start' => sanitize_text_field($times['start']),
                'end' => sanitize_text_field($times['end'])
            );
            update_post_meta($post_id, 'vb_schedule_' . $day, $schedule);
        }
    }

    // Save to pricing table for quick lookups
    if (isset($_POST['vb_location_pricing']) && is_array($_POST['vb_location_pricing'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vb_service_pricing';

        // Delete existing prices for this service
        $wpdb->delete(
            $table_name,
            array('service_id' => $post_id),
            array('%d')
        );

        // Insert new prices
        foreach ($_POST['vb_location_pricing'] as $pricing) {
            if (!empty($pricing['zip']) && isset($pricing['price'])) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'service_id' => $post_id,
                        'zip_code' => sanitize_text_field($pricing['zip']),
                        'price' => floatval($pricing['price']),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%s')
                );
            }
        }
    }
}
}
