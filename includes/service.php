<?php
namespace VandelBooking;

/**
 * Service class for managing service data and availability
 */
class Service {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }

    /**
     * Register meta boxes
     */
    public function register_meta_boxes() {
        add_meta_box(
            'vb_service_details',
            __('Service Details', 'vandel-booking'),
            array($this, 'render_service_details_meta_box'),
            'vb_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vb_service_pricing',
            __('Service Pricing', 'vandel-booking'),
            array($this, 'render_service_pricing_meta_box'),
            'vb_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vb_service_availability',
            __('Service Availability', 'vandel-booking'),
            array($this, 'render_service_availability_meta_box'),
            'vb_service',
            'normal',
            'high'
        );
    }

    /**
     * Render service details meta box
     */
    public function render_service_details_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('vb_service_details_nonce', 'vb_service_details_nonce');
        
        // Get the saved values
        $duration = get_post_meta($post->ID, 'vb_duration', true);
        $short_description = get_post_meta($post->ID, 'vb_short_description', true);
        $parent_service = get_post_meta($post->ID, '_vb_parent_service', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="vb_duration"><?php _e('Duration (minutes)', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <input type="number" id="vb_duration" name="vb_duration" value="<?php echo esc_attr($duration); ?>" min="15" step="15">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="vb_short_description"><?php _e('Short Description', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <textarea id="vb_short_description" name="vb_short_description" rows="3" class="large-text"><?php echo esc_textarea($short_description); ?></textarea>
                    <p class="description"><?php _e('A brief description of the service (displayed in the booking form).', 'vandel-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="vb_parent_service"><?php _e('Parent Service', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <?php
                    $services = get_posts(array(
                        'post_type' => 'vb_service',
                        'posts_per_page' => -1,
                        'post__not_in' => array($post->ID),
                        'meta_query' => array(
                            array(
                                'key' => '_vb_parent_service',
                                'compare' => 'NOT EXISTS'
                            )
                        )
                    ));
                    
                    if (!empty($services)) {
                        echo '<select id="vb_parent_service" name="vb_parent_service">';
                        echo '<option value="">' . __('None (Top Level Service)', 'vandel-booking') . '</option>';
                        
                        foreach ($services as $service) {
                            echo '<option value="' . esc_attr($service->ID) . '" ' . selected($parent_service, $service->ID, false) . '>' . esc_html($service->post_title) . '</option>';
                        }
                        
                        echo '</select>';
                        echo '<p class="description">' . __('Select a parent service if this is a sub-service or variant.', 'vandel-booking') . '</p>';
                    } else {
                        echo '<p>' . __('No parent services available.', 'vandel-booking') . '</p>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render service pricing meta box
     */
    public function render_service_pricing_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('vb_service_pricing_nonce', 'vb_service_pricing_nonce');
        
        // Get the saved values
        $regular_price = get_post_meta($post->ID, 'vb_regular_price', true);
        $sale_price = get_post_meta($post->ID, 'vb_sale_price', true);
        $tax_rate = get_post_meta($post->ID, 'vb_tax_rate', true);
        $enable_deposit = get_post_meta($post->ID, 'vb_enable_deposit', true);
        $deposit_type = get_post_meta($post->ID, 'vb_deposit_type', true) ?: 'percentage';
        $deposit_amount = get_post_meta($post->ID, 'vb_deposit_amount', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="vb_regular_price"><?php _e('Regular Price', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <input type="number" id="vb_regular_price" name="vb_regular_price" value="<?php echo esc_attr($regular_price); ?>" min="0" step="0.01">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="vb_sale_price"><?php _e('Sale Price (optional)', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <input type="number" id="vb_sale_price" name="vb_sale_price" value="<?php echo esc_attr($sale_price); ?>" min="0" step="0.01">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="vb_tax_rate"><?php _e('Tax Rate (%)', 'vandel-booking'); ?></label>
                </th>
                <td>
                    <input type="number" id="vb_tax_rate" name="vb_tax_rate" value="<?php echo esc_attr($tax_rate); ?>" min="0" max="100" step="0.01">
                    <p class="description"><?php _e('Leave empty to use the default tax rate.', 'vandel-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php _e('Deposit Options', 'vandel-booking'); ?>
                </th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" name="vb_enable_deposit" value="1" <?php checked($enable_deposit, '1'); ?>>
                            <?php _e('Enable deposit payment', 'vandel-booking'); ?>
                        </label>
                        
                        <div class="vb-deposit-options" style="margin-top: 10px; <?php echo $enable_deposit ? '' : 'display: none;'; ?>">
                            <label>
                                <input type="radio" name="vb_deposit_type" value="percentage" <?php checked($deposit_type, 'percentage'); ?>>
                                <?php _e('Percentage of total', 'vandel-booking'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="vb_deposit_type" value="fixed" <?php checked($deposit_type, 'fixed'); ?>>
                                <?php _e('Fixed amount', 'vandel-booking'); ?>
                            </label>
                            <br>
                            <label for="vb_deposit_amount" style="display: block; margin-top: 10px;">
                                <?php _e('Deposit Amount', 'vandel-booking'); ?>
                            </label>
                            <input type="number" id="vb_deposit_amount" name="vb_deposit_amount" value="<?php echo esc_attr($deposit_amount); ?>" min="0" step="0.01">
                        </div>
                    </fieldset>
                </td>
            </tr>
        </table>
        <script>
            jQuery(document).ready(function($) {
                $('input[name="vb_enable_deposit"]').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('.vb-deposit-options').show();
                    } else {
                        $('.vb-deposit-options').hide();
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * Render service availability meta box
     */
    public function render_service_availability_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('vb_service_availability_nonce', 'vb_service_availability_nonce');
        
        // Get the saved values
        $available_days = get_post_meta($post->ID, 'vb_available_days', true) ?: array();
        $time_slots = get_post_meta($post->ID, 'vb_time_slots', true) ?: array();
        
        // Days of the week
        $days = array(
            'monday' => __('Monday', 'vandel-booking'),
            'tuesday' => __('Tuesday', 'vandel-booking'),
            'wednesday' => __('Wednesday', 'vandel-booking'),
            'thursday' => __('Thursday', 'vandel-booking'),
            'friday' => __('Friday', 'vandel-booking'),
            'saturday' => __('Saturday', 'vandel-booking'),
            'sunday' => __('Sunday', 'vandel-booking')
        );
        
        ?>
        <div class="vb-availability-container">
            <h3><?php _e('Available Days', 'vandel-booking'); ?></h3>
            <div class="vb-available-days">
                <?php foreach ($days as $day_key => $day_label) : ?>
                    <label>
                        <input type="checkbox" name="vb_available_days[]" value="<?php echo esc_attr($day_key); ?>" <?php checked(in_array($day_key, (array)$available_days), true); ?>>
                        <?php echo esc_html($day_label); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            
            <h3><?php _e('Time Slots', 'vandel-booking'); ?></h3>
            <div class="vb-time-slots">
                <table class="widefat" id="vb-time-slots-table">
                    <thead>
                        <tr>
                            <th><?php _e('Start Time', 'vandel-booking'); ?></th>
                            <th><?php _e('End Time', 'vandel-booking'); ?></th>
                            <th><?php _e('Capacity', 'vandel-booking'); ?></th>
                            <th><?php _e('Action', 'vandel-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($time_slots)) : ?>
                            <?php foreach ($time_slots as $index => $slot) : ?>
                                <tr class="vb-time-slot-row">
                                    <td>
                                        <input type="time" name="vb_time_slots[<?php echo $index; ?>][start]" value="<?php echo esc_attr($slot['start']); ?>">
                                    </td>
                                    <td>
                                        <input type="time" name="vb_time_slots[<?php echo $index; ?>][end]" value="<?php echo esc_attr($slot['end']); ?>">
                                    </td>
                                    <td>
                                        <input type="number" name="vb_time_slots[<?php echo $index; ?>][capacity]" value="<?php echo esc_attr($slot['capacity']); ?>" min="1">
                                    </td>
                                    <td>
                                        <button type="button" class="button vb-remove-slot"><?php _e('Remove', 'vandel-booking'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr class="vb-time-slot-row">
                                <td>
                                    <input type="time" name="vb_time_slots[0][start]" value="09:00">
                                </td>
                                <td>
                                    <input type="time" name="vb_time_slots[0][end]" value="10:00">
                                </td>
                                <td>
                                    <input type="number" name="vb_time_slots[0][capacity]" value="1" min="1">
                                </td>
                                <td>
                                    <button type="button" class="button vb-remove-slot"><?php _e('Remove', 'vandel-booking'); ?></button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <button type="button" class="button" id="vb-add-slot"><?php _e('Add Time Slot', 'vandel-booking'); ?></button>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Add time slot
                $('#vb-add-slot').on('click', function() {
                    var rowCount = $('.vb-time-slot-row').length;
                    var newRow = `
                        <tr class="vb-time-slot-row">
                            <td>
                                <input type="time" name="vb_time_slots[${rowCount}][start]" value="09:00">
                            </td>
                            <td>
                                <input type="time" name="vb_time_slots[${rowCount}][end]" value="10:00">
                            </td>
                            <td>
                                <input type="number" name="vb_time_slots[${rowCount}][capacity]" value="1" min="1">
                            </td>
                            <td>
                                <button type="button" class="button vb-remove-slot"><?php _e('Remove', 'vandel-booking'); ?></button>
                            </td>
                        </tr>
                    `;
                    $('#vb-time-slots-table tbody').append(newRow);
                });
                
                // Remove time slot
                $(document).on('click', '.vb-remove-slot', function() {
                    $(this).closest('tr').remove();
                    // Reindex the rows
                    $('.vb-time-slot-row').each(function(index) {
                        $(this).find('input').each(function() {
                            var name = $(this).attr('name');
                            name = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', name);
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_box_data($post_id) {
        // Check if we're saving a service
        if ('vb_service' !== get_post_type($post_id)) {
            return;
        }
        
        // Service Details
        if (isset($_POST['vb_service_details_nonce']) && wp_verify_nonce($_POST['vb_service_details_nonce'], 'vb_service_details_nonce')) {
            // Save duration
            if (isset($_POST['vb_duration'])) {
                update_post_meta($post_id, 'vb_duration', intval($_POST['vb_duration']));
            }
            
            // Save short description
            if (isset($_POST['vb_short_description'])) {
                update_post_meta($post_id, 'vb_short_description', sanitize_textarea_field($_POST['vb_short_description']));
            }
            
            // Save parent service
            if (isset($_POST['vb_parent_service'])) {
                $parent_id = absint($_POST['vb_parent_service']);
                if ($parent_id > 0 && $parent_id !== $post_id) {
                    update_post_meta($post_id, '_vb_parent_service', $parent_id);
                } else {
                    delete_post_meta($post_id, '_vb_parent_service');
                }
            } else {
                delete_post_meta($post_id, '_vb_parent_service');
            }
        }
        
        // Service Pricing
        if (isset($_POST['vb_service_pricing_nonce']) && wp_verify_nonce($_POST['vb_service_pricing_nonce'], 'vb_service_pricing_nonce')) {
            // Save regular price
            if (isset($_POST['vb_regular_price'])) {
                update_post_meta($post_id, 'vb_regular_price', floatval($_POST['vb_regular_price']));
            }
            
            // Save sale price
            if (isset($_POST['vb_sale_price'])) {
                update_post_meta($post_id, 'vb_sale_price', floatval($_POST['vb_sale_price']));
            }
            
            // Save tax rate
            if (isset($_POST['vb_tax_rate'])) {
                update_post_meta($post_id, 'vb_tax_rate', floatval($_POST['vb_tax_rate']));
            }
            
            // Save deposit settings
            update_post_meta($post_id, 'vb_enable_deposit', isset($_POST['vb_enable_deposit']) ? '1' : '');
            
            if (isset($_POST['vb_deposit_type'])) {
                update_post_meta($post_id, 'vb_deposit_type', sanitize_text_field($_POST['vb_deposit_type']));
            }
            
            if (isset($_POST['vb_deposit_amount'])) {
                update_post_meta($post_id, 'vb_deposit_amount', floatval($_POST['vb_deposit_amount']));
            }
        }
        
        // Service Availability
        if (isset($_POST['vb_service_availability_nonce']) && wp_verify_nonce($_POST['vb_service_availability_nonce'], 'vb_service_availability_nonce')) {
            // Save available days
            $available_days = isset($_POST['vb_available_days']) ? array_map('sanitize_text_field', $_POST['vb_available_days']) : array();
            update_post_meta($post_id, 'vb_available_days', $available_days);
            
            // Save time slots
            $time_slots = array();
            if (isset($_POST['vb_time_slots']) && is_array($_POST['vb_time_slots'])) {
                foreach ($_POST['vb_time_slots'] as $slot) {
                    if (!empty($slot['start']) && !empty($slot['end'])) {
                        $time_slots[] = array(
                            'start' => sanitize_text_field($slot['start']),
                            'end' => sanitize_text_field($slot['end']),
                            'capacity' => absint($slot['capacity'])
                        );
                    }
                }
            }
            update_post_meta($post_id, 'vb_time_slots', $time_slots);
        }
    }

/**
 * Get sub-services for a specific service
 */
public function get_sub_services($service_id) {
    if (!$service_id) {
        return array();
    }
    
    // For debugging
    error_log("Getting sub-services for service ID: $service_id");
    
    $sub_services = get_posts(array(
        'post_type' => 'vb_service',
        'posts_per_page' => -1,
        'meta_key' => '_vb_parent_service',
        'meta_value' => $service_id,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ));
    
    error_log("Found " . count($sub_services) . " sub-services for service ID: $service_id");
    
    // For debugging, log each sub-service
    if (count($sub_services) > 0) {
        foreach ($sub_services as $sub) {
            error_log("Sub-service: ID=" . $sub->ID . ", Title=" . $sub->post_title . ", Parent=" . get_post_meta($sub->ID, '_vb_parent_service', true));
        }
    }
    
    return $sub_services;
}

    /**
     * Get available slots for a service on a specific date
     */
    public function get_available_slots($service_id, $date, $zip_code) {
        // Get service time slots
        $time_slots = get_post_meta($service_id, 'vb_time_slots', true);
        if (empty($time_slots)) {
            return array();
        }
        
        // Get service duration
        $duration = get_post_meta($service_id, 'vb_duration', true);
        if (empty($duration)) {
            $duration = 60; // Default to 1 hour
        }
        
        // Calculate available slots
        $available_slots = array();
        $date_obj = new \DateTime($date);
        $day_of_week = strtolower($date_obj->format('l'));
        
        // Check if service is available on this day
        $available_days = get_post_meta($service_id, 'vb_available_days', true);
        if (!in_array($day_of_week, (array)$available_days)) {
            return $available_slots;
        }
        
        // Get existing bookings for this date
        global $wpdb;
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT booking_date FROM {$wpdb->prefix}vb_bookings 
            WHERE service_id = %d 
            AND DATE(booking_date) = %s
            AND status NOT IN ('cancelled', 'rejected')",
            $service_id,
            $date
        ));
        
        // Build array of booked times
        $booked_times = array();
        foreach ($existing_bookings as $booking) {
            $booking_time = strtotime($booking->booking_date);
            $booked_times[] = date('H:i', $booking_time);
        }
        
        // Build available slots
        foreach ($time_slots as $slot) {
            $start_time = strtotime($date . ' ' . $slot['start']);
            $end_time = strtotime($date . ' ' . $slot['end']);
            
            // Skip if the time slot is in the past
            if ($start_time < current_time('timestamp')) {
                continue;
            }
            
            // Calculate slots within this time range
            $current_time = $start_time;
            while ($current_time + ($duration * 60) <= $end_time) {
                $slot_start = date('H:i', $current_time);
                
                // Check if slot is available (not booked)
                $available = true;
                if (in_array($slot_start, $booked_times)) {
                    $booked_count = array_count_values($booked_times)[$slot_start];
                    if ($booked_count >= $slot['capacity']) {
                        $available = false;
                    }
                }
                
                $available_slots[] = array(
                    'start' => $date . ' ' . $slot_start,
                    'end' => date('Y-m-d H:i', $current_time + ($duration * 60)),
                    'available' => $available
                );
                
                // Move to next slot
                $current_time += 30 * 60; // 30-minute intervals
            }
        }
        
        return $available_slots;
    }
}