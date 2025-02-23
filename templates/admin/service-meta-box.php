<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="vb-service-meta-box">
    <div class="vb-tabs">
        <button type="button" class="vb-tab-link active" data-tab="general">
            <?php _e('General', 'vandel-booking'); ?>
        </button>
        <button type="button" class="vb-tab-link" data-tab="pricing">
            <?php _e('Pricing', 'vandel-booking'); ?>
        </button>
        <button type="button" class="vb-tab-link" data-tab="availability">
            <?php _e('Availability', 'vandel-booking'); ?>
        </button>
        <button type="button" class="vb-tab-link" data-tab="deposit">
            <?php _e('Deposit', 'vandel-booking'); ?>
        </button>
    </div>

    <div class="vb-tab-content active" data-tab="general">
        <div class="vb-field-row">
            <label for="vb_duration">
                <?php _e('Duration (minutes)', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_duration" 
                   name="vb_duration" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_duration', true)); ?>" 
                   min="15" 
                   step="15">
        </div>

        <div class="vb-field-row">
            <label for="vb_buffer_time">
                <?php _e('Buffer Time (minutes)', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_buffer_time" 
                   name="vb_buffer_time" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_buffer_time', true)); ?>" 
                   min="0" 
                   step="5">
        </div>

        <div class="vb-field-row">
            <label for="vb_max_bookings_per_day">
                <?php _e('Max Bookings Per Day', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_max_bookings_per_day" 
                   name="vb_max_bookings_per_day" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_max_bookings_per_day', true)); ?>" 
                   min="1">
        </div>
    </div>

    <div class="vb-tab-content" data-tab="pricing">
        <div class="vb-field-row">
            <label for="vb_regular_price">
                <?php _e('Regular Price', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_regular_price" 
                   name="vb_regular_price" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_regular_price', true)); ?>" 
                   min="0" 
                   step="0.01">
        </div>

        <div class="vb-field-row">
            <label for="vb_sale_price">
                <?php _e('Sale Price', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_sale_price" 
                   name="vb_sale_price" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_sale_price', true)); ?>" 
                   min="0" 
                   step="0.01">
        </div>

        <div class="vb-field-row">
            <label for="vb_tax_rate">
                <?php _e('Tax Rate (%)', 'vandel-booking'); ?>
            </label>
            <input type="number" 
                   id="vb_tax_rate" 
                   name="vb_tax_rate" 
                   value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_tax_rate', true)); ?>" 
                   min="0" 
                   max="100" 
                   step="0.01">
        </div>

        <div class="vb-field-row">
            <h4><?php _e('Location-based Pricing', 'vandel-booking'); ?></h4>
            <div id="vb-location-pricing">
                <?php
                $location_pricing = get_post_meta($post->ID, 'vb_location_pricing', true);
                if (is_array($location_pricing)) {
                    foreach ($location_pricing as $index => $pricing) {
                        ?>
                        <div class="vb-location-price-row">
                            <input type="text" 
                                   name="vb_location_pricing[<?php echo $index; ?>][zip]" 
                                   value="<?php echo esc_attr($pricing['zip']); ?>" 
                                   placeholder="<?php esc_attr_e('ZIP Code', 'vandel-booking'); ?>">
                            <input type="number" 
                                   name="vb_location_pricing[<?php echo $index; ?>][price]" 
                                   value="<?php echo esc_attr($pricing['price']); ?>" 
                                   min="0" 
                                   step="0.01" 
                                   placeholder="<?php esc_attr_e('Price', 'vandel-booking'); ?>">
                            <button type="button" class="button vb-remove-location">
                                <?php _e('Remove', 'vandel-booking'); ?>
                            </button>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button type="button" class="button vb-add-location">
                <?php _e('Add Location Price', 'vandel-booking'); ?>
            </button>
        </div>
    </div>

    <div class="vb-tab-content" data-tab="availability">
        <div class="vb-field-row">
            <label>
                <input type="checkbox" 
                       name="vb_enable_scheduling" 
                       value="1" 
                       <?php checked(get_post_meta($post->ID, 'vb_enable_scheduling', true), '1'); ?>>
                <?php _e('Enable Custom Schedule', 'vandel-booking'); ?>
            </label>
        </div>

        <div id="vb-schedule-settings" style="display: none;">
            <?php
            $days = array(
                'monday' => __('Monday', 'vandel-booking'),
                'tuesday' => __('Tuesday', 'vandel-booking'),
                'wednesday' => __('Wednesday', 'vandel-booking'),
                'thursday' => __('Thursday', 'vandel-booking'),
                'friday' => __('Friday', 'vandel-booking'),
                'saturday' => __('Saturday', 'vandel-booking'),
                'sunday' => __('Sunday', 'vandel-booking')
            );

            foreach ($days as $day_key => $day_label) {
                $schedule = get_post_meta($post->ID, 'vb_schedule_' . $day_key, true);
                ?>
                <div class="vb-schedule-day">
                    <label>
                        <input type="checkbox" 
                               name="vb_enabled_days[]" 
                               value="<?php echo $day_key; ?>" 
                               <?php checked(isset($schedule['enabled']) && $schedule['enabled'], true); ?>>
                        <?php echo $day_label; ?>
                    </label>
                    <div class="vb-time-slots">
                        <input type="time" 
                               name="vb_schedule[<?php echo $day_key; ?>][start]" 
                               value="<?php echo isset($schedule['start']) ? esc_attr($schedule['start']) : '09:00'; ?>">
                        <span><?php _e('to', 'vandel-booking'); ?></span>
                        <input type="time" 
                               name="vb_schedule[<?php echo $day_key; ?>][end]" 
                               value="<?php echo isset($schedule['end']) ? esc_attr($schedule['end']) : '17:00'; ?>">
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="vb-tab-content" data-tab="deposit">
        <div class="vb-field-row">
            <label>
                <input type="checkbox" 
                       name="vb_enable_deposit" 
                       value="1" 
                       <?php checked(get_post_meta($post->ID, 'vb_enable_deposit', true), '1'); ?>>
                <?php _e('Enable Deposit', 'vandel-booking'); ?>
            </label>
        </div>

        <div id="vb-deposit-settings" style="display: none;">
            <div class="vb-field-row">
                <label><?php _e('Deposit Type', 'vandel-booking'); ?></label>
                <select name="vb_deposit_type">
                    <option value="percentage" <?php selected(get_post_meta($post->ID, 'vb_deposit_type', true), 'percentage'); ?>>
                        <?php _e('Percentage', 'vandel-booking'); ?>
                    </option>
                    <option value="fixed" <?php selected(get_post_meta($post->ID, 'vb_deposit_type', true), 'fixed'); ?>>
                        <?php _e('Fixed Amount', 'vandel-booking'); ?>
                    </option>
                </select>
            </div>

            <div class="vb-field-row">
                <label for="vb_deposit_amount">
                    <?php _e('Deposit Amount', 'vandel-booking'); ?>
                </label>
                <input type="number" 
                       id="vb_deposit_amount" 
                       name="vb_deposit_amount" 
                       value="<?php echo esc_attr(get_post_meta($post->ID, 'vb_deposit_amount', true)); ?>" 
                       min="0" 
                       step="0.01">
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.vb-tab-link').on('click', function() {
        const tabId = $(this).data('tab');
        $('.vb-tab-link').removeClass('active');
        $('.vb-tab-content').removeClass('active');
        $(this).addClass('active');
        $('.vb-tab-content[data-tab="' + tabId + '"]').addClass('active');
    });

    // Location pricing
    $('.vb-add-location').on('click', function() {
        const index = $('#vb-location-pricing .vb-location-price-row').length;
        const template = `
            <div class="vb-location-price-row">
                <input type="text" 
                       name="vb_location_pricing[${index}][zip]" 
                       placeholder="<?php esc_attr_e('ZIP Code', 'vandel-booking'); ?>">
                <input type="number" 
                       name="vb_location_pricing[${index}][price]" 
                       min="0" 
                       step="0.01" 
                       placeholder="<?php esc_attr_e('Price', 'vandel-booking'); ?>">
                <button type="button" class="button vb-remove-location">
                    <?php _e('Remove', 'vandel-booking'); ?>
                </button>
            </div>
        `;
        $('#vb-location-pricing').append(template);
    });

    $(document).on('click', '.vb-remove-location', function() {
        $(this).closest('.vb-location-price-row').remove();
    });

    // Toggle scheduling settings
    $('input[name="vb_enable_scheduling"]').on('change', function() {
        $('#vb-schedule-settings').toggle(this.checked);
    }).trigger('change');

    // Toggle deposit settings
    $('input[name="vb_enable_deposit"]').on('change', function() {
        $('#vb-deposit-settings').toggle(this.checked);
    }).trigger('change');
});
</script>

<style>
.vb-service-meta-box {
    margin: 15px 0;
}

.vb-tabs {
    margin-bottom: 15px;
    border-bottom: 1px solid #ccd0d4;
}

.vb-tab-link {
    padding: 8px 12px;
    margin: 0 4px -1px 0;
    border: 1px solid transparent;
    border-radius: 4px 4px 0 0;
    background: none;
    cursor: pointer;
}

.vb-tab-link.active {
    border-color: #ccd0d4;
    border-bottom-color: #fff;
    background: #fff;
}

.vb-tab-content {
    display: none;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.vb-tab-content.active {
    display: block;
}

.vb-field-row {
    margin-bottom: 15px;
}

.vb-field-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.vb-location-price-row {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.vb-schedule-day {
    margin-bottom: 15px;
    padding: 10px;
    background: #f9f9f9;
    border: 1px solid #e5e5e5;
}

.vb-time-slots {
    margin-top: 10px;
    margin-left: 25px;
}

.vb-time-slots span {
    margin: 0 10px;
}
</style>