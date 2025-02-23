<!-- templates/booking-form.php -->
<div class="vb-booking-form">
    <div class="vb-step" data-step="1">
        <h3><?php _e('Step 1: Select Service', 'vandel-booking'); ?></h3>
        
        <?php if (!empty($top_level_services) && is_array($top_level_services)) : ?>
            <div class="vb-services-list">
                <?php foreach ($top_level_services as $service_item) : ?>
                    <?php if ($service_item && is_object($service_item)) : ?>
                        <div class="vb-service-item" data-service-id="<?php echo esc_attr($service_item->ID); ?>">
                            <h4><?php echo esc_html($service_item->post_title); ?></h4>
                            <?php if ($sub_services = $this->service->get_sub_services($service_item->ID)) : ?>
                                <div class="vb-sub-services">
                                    <select class="vb-sub-service-select">
                                        <option value=""><?php _e('Choose a sub-service...', 'vandel-booking'); ?></option>
                                        <?php foreach ($sub_services as $sub) : ?>
                                            <option value="<?php echo esc_attr($sub->ID); ?>">
                                                <?php echo esc_html($sub->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="vb-select-service">
                                <?php _e('Select', 'vandel-booking'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php _e('No services available.', 'vandel-booking'); ?></p>
        <?php endif; ?>
    </div>

    <div class="vb-step" data-step="2" style="display: none;">
        <h3><?php _e('Step 2: Date & Time', 'vandel-booking'); ?></h3>
        <div class="vb-form-row">
            <label for="vb-booking-date"><?php _e('Select Date', 'vandel-booking'); ?></label>
            <input type="date" id="vb-booking-date" name="booking_date" required min="<?php echo esc_attr(date('Y-m-d')); ?>">
        </div>
        <div class="vb-form-row">
            <label><?php _e('Available Time Slots', 'vandel-booking'); ?></label>
            <div id="vb-time-slots" class="vb-time-slots">
                <!-- Time slots will be populated via AJAX -->
            </div>
        </div>
        <button type="button" class="vb-prev-step" data-prev="1"><?php _e('Previous', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="3"><?php _e('Next', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="3" style="display: none;">
        <h3><?php _e('Step 3: Your Information', 'vandel-booking'); ?></h3>
        <div class="vb-form-row">
            <label for="vb-customer-name"><?php _e('Full Name', 'vandel-booking'); ?></label>
            <input type="text" id="vb-customer-name" name="customer_name" required>
        </div>
        <div class="vb-form-row">
            <label for="vb-customer-email"><?php _e('Email', 'vandel-booking'); ?></label>
            <input type="email" id="vb-customer-email" name="customer_email" required>
        </div>
        <div class="vb-form-row">
            <label for="vb-customer-phone"><?php _e('Phone', 'vandel-booking'); ?></label>
            <input type="tel" id="vb-customer-phone" name="customer_phone" required>
        </div>
        <div class="vb-form-row">
            <label for="vb-customer-notes"><?php _e('Special Instructions', 'vandel-booking'); ?></label>
            <textarea id="vb-customer-notes" name="customer_notes" rows="3"></textarea>
        </div>
        <button type="button" class="vb-prev-step" data-prev="2"><?php _e('Previous', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="4"><?php _e('Next', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="4" style="display: none;">
        <h3><?php _e('Step 4: Review & Payment', 'vandel-booking'); ?></h3>
        <div class="vb-booking-summary">
            <h4><?php _e('Booking Summary', 'vandel-booking'); ?></h4>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Service', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-service"></span>
            </div>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Date & Time', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-datetime"></span>
            </div>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Location', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-location"></span>
            </div>
            <div class="vb-pricing-breakdown">
                <div class="vb-summary-row">
                    <span class="vb-summary-label"><?php _e('Service Price', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-price"></span>
                </div>
                <div class="vb-summary-row tax-row" style="display: none;">
                    <span class="vb-summary-label"><?php _e('Tax', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-tax"></span>
                </div>
                <div class="vb-summary-row vb-coupon-row" style="display: none;">
                    <span class="vb-summary-label"><?php _e('Discount', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-discount"></span>
                </div>
                <div class="vb-summary-row vb-total-row">
                    <span class="vb-summary-label"><?php _e('Total', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-total"></span>
                </div>
            </div>

            <div class="vb-coupon-form">
                <div class="vb-form-row">
                    <label for="vb-coupon-code"><?php _e('Have a coupon?', 'vandel-booking'); ?></label>
                    <div class="vb-coupon-input">
                        <input type="text" id="vb-coupon-code" name="coupon_code" placeholder="<?php esc_attr_e('Enter coupon code', 'vandel-booking'); ?>">
                        <button type="button" class="vb-apply-coupon"><?php _e('Apply', 'vandel-booking'); ?></button>
                    </div>
                </div>
            </div>

            <div class="vb-deposit-option" style="display: none;">
                <label>
                    <input type="checkbox" name="pay_deposit" value="1">
                    <?php _e('Pay deposit only', 'vandel-booking'); ?>
                    <span class="vb-deposit-amount"></span>
                </label>
            </div>

            <div class="vb-payment-section">
                <h4><?php _e('Payment Details', 'vandel-booking'); ?></h4>
                <div id="vb-stripe-card-element"></div>
                <div id="vb-card-errors" role="alert"></div>
            </div>
        </div>

        <button type="button" class="vb-prev-step" data-prev="3"><?php _e('Previous', 'vandel-booking'); ?></button>
        <button type="submit" class="vb-submit-booking"><?php _e('Complete Booking', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="5" style="display: none;">
        <h3><?php _e('Booking Confirmed', 'vandel-booking'); ?></h3>
        <div class="vb-confirmation-message">
            <div class="vb-success-icon">✓</div>
            <h4><?php _e('Thank you for your booking!', 'vandel-booking'); ?></h4>
            <p><?php _e('Your booking has been confirmed. A confirmation email has been sent to your email address.', 'vandel-booking'); ?></p>
            <div class="vb-booking-details">
                <strong><?php _e('Booking Reference:', 'vandel-booking'); ?></strong>
                <span id="vb-booking-reference"></span>
            </div>
        </div>
    </div>
</div>

<?php
// Debug output
if (defined('WP_DEBUG') && WP_DEBUG) {
    $debug_services = get_posts(array(
        'post_type' => 'vb_service',
        'posts_per_page' => -1,
    ));

    echo '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0;">';
    echo '<h4>Debug Information:</h4>';

    foreach ($debug_services as $service) {
        $parent_id = get_post_meta($service->ID, '_vb_parent_service', true);
        echo sprintf(
            'Service: %s (ID: %d) - Parent ID: %s<br>',
            esc_html($service->post_title),
            $service->ID,
            $parent_id ? $parent_id : 'None'
        );
    }

    if (!isset($this->service)) {
        echo '<div style="color: red;">Service object not available!</div>';
    }

    echo '</div>';
}
?>