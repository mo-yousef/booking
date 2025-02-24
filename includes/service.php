<?php
namespace VandelBooking;

/**
 * Service class for handling service-related functionality
 */
class Service {
    /**
     * @var Database Database instance
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Database();
        
        add_action('add_meta_boxes', array($this, 'add_service_meta_boxes'));
        add_action('save_post_vb_service', array($this, 'save_service_meta'));
    }

    /**
     * Add service meta boxes
     */
    public function add_service_meta_boxes() {
        // Service Settings meta box
        add_meta_box(
            'vb_service_settings',
            __('Service Settings', 'vandel-booking'),
            array($this, 'render_service_meta_box'),
            'vb_service',
            'normal',
            'high'
        );

        // Parent Service meta box
        add_meta_box(
            'vb_service_parent',
            __('Parent Service', 'vandel-booking'),
            array($this, 'render_parent_service_metabox'),
            'vb_service',
            'side',
            'default'
        );
    }

    /**
     * Render parent service selection metabox
     */
    public function render_parent_service_metabox($post) {
        // Add nonce for security
        wp_nonce_field('vb_service_parent_nonce', 'vb_service_parent_nonce');

        // Get current parent service
        $parent_service_id = get_post_meta($post->ID, '_vb_parent_service', true);

        // Get all services except current one and its children
        $services = $this->get_available_parent_services($post->ID);

        ?>
        <p>
            <label for="vb_parent_service">
                <?php _e('Select Parent Service:', 'vandel-booking'); ?>
            </label>
            <select name="vb_parent_service" id="vb_parent_service" class="widefat">
                <option value=""><?php _e('None (Top Level Service)', 'vandel-booking'); ?></option>
                <?php foreach ($services as $service) : ?>
                    <option value="<?php echo esc_attr($service->ID); ?>" 
                            <?php selected($parent_service_id, $service->ID); ?>>
                        <?php echo esc_html($service->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            <?php _e('Making this a sub-service will make it available only when the parent service is selected.', 'vandel-booking'); ?>
        </p>
        <?php
    }

    /**
     * Get available parent services
     */
    private function get_available_parent_services($current_id) {
        // Get all child services of current service
        $child_services = $this->get_child_services($current_id);
        $excluded_ids = array_merge(array($current_id), $child_services);

        // Query services
        return get_posts(array(
            'post_type' => 'vb_service',
            'posts_per_page' => -1,
            'post__not_in' => $excluded_ids,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_vb_parent_service',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
    }

    /**
     * Get all child services recursively
     */
    private function get_child_services($service_id) {
        $children = array();
        
        $child_services = get_posts(array(
            'post_type' => 'vb_service',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_vb_parent_service',
                    'value' => $service_id
                )
            )
        ));

        foreach ($child_services as $child) {
            $children[] = $child->ID;
            $children = array_merge($children, $this->get_child_services($child->ID));
        }

        return $children;
    }

    /**
     * Render service meta box
     */
    public function render_service_meta_box($post) {
        wp_nonce_field('vb_service_meta_box', 'vb_service_meta_box_nonce');
        include VANDEL_BOOKING_PLUGIN_DIR . 'templates/admin/service-meta-box.php';
    }

    /**
     * Save service meta
     */
    public function save_service_meta($post_id) {
        // Check nonces
        if (!isset($_POST['vb_service_meta_box_nonce']) || 
            !wp_verify_nonce($_POST['vb_service_meta_box_nonce'], 'vb_service_meta_box')) {
            return;
        }

        // Check parent service nonce
        if (isset($_POST['vb_service_parent_nonce']) && 
            wp_verify_nonce($_POST['vb_service_parent_nonce'], 'vb_service_parent_nonce')) {
            // Save parent service
            $parent_service = isset($_POST['vb_parent_service']) ? 
                sanitize_text_field($_POST['vb_parent_service']) : '';

            if (empty($parent_service)) {
                delete_post_meta($post_id, '_vb_parent_service');
            } else {
                update_post_meta($post_id, '_vb_parent_service', $parent_service);
            }
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

        // Update service pricing table
        $this->update_service_pricing($post_id);
    }

    /**
     * Get sub-services for a given service
     */
    public function get_sub_services($service_id) {
        return get_posts(array(
            'post_type' => 'vb_service',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_vb_parent_service',
                    'value' => $service_id
                )
            )
        ));
    }

    /**
     * Check if a service has sub-services
     */
    public function has_sub_services($service_id) {
        $sub_services = $this->get_sub_services($service_id);
        return !empty($sub_services);
    }

    /**
     * Get the parent service ID
     */
    public function get_parent_service($service_id) {
        return get_post_meta($service_id, '_vb_parent_service', true);
    }

    /**
     * Update service pricing in the database table
     */
    private function update_service_pricing($service_id) {
        if (!isset($_POST['vb_location_pricing']) || !is_array($_POST['vb_location_pricing'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vb_service_pricing';

        // Delete existing prices for this service
        $wpdb->delete(
            $table_name,
            array('service_id' => $service_id),
            array('%d')
        );

        // Insert new prices
        foreach ($_POST['vb_location_pricing'] as $pricing) {
            if (!empty($pricing['zip']) && isset($pricing['price'])) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'service_id' => $service_id,
                        'zip_code' => sanitize_text_field($pricing['zip']),
                        'price' => floatval($pricing['price']),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%s')
                );
            }
        }
    }

    /**
     * Get available time slots for a service
     */
    public function get_available_slots($service_id, $date, $zip_code) {
        // Validate inputs
        if (!$service_id || !$date || !$zip_code) {
            return array();
        }

        // Get service-specific settings
        $duration = get_post_meta($service_id, 'vb_duration', true) ?: 60; // Default 60 minutes
        $buffer_time = get_post_meta($service_id, 'vb_buffer_time', true) ?: 15; // Default 15 minutes buffer
        $max_bookings_per_slot = get_post_meta($service_id, 'vb_max_bookings_per_day', true) ?: 1; // Default 1 booking per slot

        // Get day of week
        $day = strtolower(date('l', strtotime($date)));
        $schedule = get_post_meta($service_id, 'vb_schedule_' . $day, true);

        // If no schedule is set for this day, return empty
        if (!$schedule || !$schedule['enabled']) {
            return array();
        }

        // Convert schedule times to timestamps
        $start_time = strtotime($date . ' ' . $schedule['start']);
        $end_time = strtotime($date . ' ' . $schedule['end']);
        $slot_interval = ($duration + $buffer_time) * 60; // Convert to seconds

        $slots = array();
        $current_time = $start_time;

        while ($current_time + ($duration * 60) <= $end_time) {
            $slot_start = date('Y-m-d H:i:s', $current_time);
            $slot_end = date('Y-m-d H:i:s', $current_time + ($duration * 60));

            // Check slot availability
            $slot_availability = $this->check_slot_availability(
                $service_id, 
                $slot_start, 
                $slot_end, 
                $zip_code, 
                $max_bookings_per_slot
            );

            $slots[] = array(
                'start' => $slot_start,
                'end' => $slot_end,
                'available' => $slot_availability
            );

            // Move to next slot
            $current_time += $slot_interval;
        }

        return $slots;
    }

    /**
     * Check availability for a specific time slot
     */
    private function check_slot_availability($service_id, $slot_start, $slot_end, $zip_code, $max_bookings) {
        global $wpdb;

        // Check existing bookings for this time slot
        $existing_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}vb_bookings 
            WHERE service_id = %d 
            AND booking_date BETWEEN %s AND %s
            AND zip_code = %s
            AND status NOT IN ('cancelled', 'rejected')",
            $service_id,
            $slot_start,
            $slot_end,
            $zip_code
        ));

        // Return true if bookings are less than max allowed
        return $existing_bookings < $max_bookings;
    }

    /**
     * Get service price for a specific ZIP code
     */
    public function get_price($service_id, $zip_code) {
        // First, try location-specific pricing
        $price = $this->database->get_service_price($service_id, $zip_code);
        
        // If no location-specific price, use service's regular price
        if (!$price) {
            $price = get_post_meta($service_id, 'vb_regular_price', true);
            
            // Check for sale price
            $sale_price = get_post_meta($service_id, 'vb_sale_price', true);
            if ($sale_price && $sale_price < $price) {
                $price = $sale_price;
            }
        }

        return floatval($price ?: 0);
    }

/**
     * Calculate total price including tax
     */
    public function calculate_total($service_id, $price) {
        $tax_rate = get_post_meta($service_id, 'vb_tax_rate', true) ?: 0;
        $tax_amount = $price * ($tax_rate / 100);
        
        return array(
            'price' => $price,
            'tax_rate' => $tax_rate,
            'tax_amount' => $tax_amount,
            'total' => $price + $tax_amount
        );
    }

    /**
     * Calculate deposit amount
     */
    public function calculate_deposit($service_id, $total_amount) {
        $enable_deposit = get_post_meta($service_id, 'vb_enable_deposit', true);
        
        if (!$enable_deposit) {
            return 0;
        }

        $deposit_type = get_post_meta($service_id, 'vb_deposit_type', true);
        $deposit_amount = get_post_meta($service_id, 'vb_deposit_amount', true);

        if ($deposit_type === 'percentage') {
            return $total_amount * ($deposit_amount / 100);
        }

        return min($deposit_amount, $total_amount);
    }
}