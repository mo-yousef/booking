<!-- templates/booking-form.php -->
<div class="vb-booking-form" data-service-id="<?php echo esc_attr($service->ID); ?>">
    <div class="vb-form-progress">
        <ul>
            <li class="active" data-step="1"><?php _e('Service', 'vandel-booking'); ?></li>
            <li data-step="2"><?php _e('Options', 'vandel-booking'); ?></li>
            <li data-step="3"><?php _e('Date & Time', 'vandel-booking'); ?></li>
            <li data-step="4"><?php _e('Your Info', 'vandel-booking'); ?></li>
            <li data-step="5"><?php _e('Payment', 'vandel-booking'); ?></li>
        </ul>
    </div>

    <div class="vb-step" data-step="1">
        <h3><?php _e('Service Details', 'vandel-booking'); ?></h3>
        <div class="vb-service-info">
            <h4><?php echo esc_html($service->post_title); ?></h4>
            <div class="vb-service-description">
                <?php echo wp_kses_post(get_post_meta($service->ID, 'vb_short_description', true)); ?>
            </div>
            <div class="vb-service-price">
                <?php
                $price = get_post_meta($service->ID, 'vb_regular_price', true);
                $sale_price = get_post_meta($service->ID, 'vb_sale_price', true);
                
                if ($sale_price && $sale_price < $price) {
                    echo '<span class="vb-regular-price">' . esc_html(number_format($price, 2)) . '</span>';
                    echo '<span class="vb-sale-price">' . esc_html(number_format($sale_price, 2)) . '</span>';
                } else {
                    echo '<span class="vb-price">' . esc_html(number_format($price, 2)) . '</span>';
                }
                ?>
            </div>
        </div>
        <button type="button" class="vb-next-step" data-next="2"><?php _e('Continue', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="2" style="display: none;">
        <h3><?php _e('Customize Your Service', 'vandel-booking'); ?></h3>
        
        <?php
        // Get service options
        $service_options = new ServiceOptions();
        $options = $service_options->get_service_options($service->ID);
        ?>
        
        <?php if (!empty($options)) : ?>
            <div class="vb-options-container">
                <?php foreach ($options as $option_index => $option) : ?>
                    <div class="vb-option-group" data-option-index="<?php echo esc_attr($option_index); ?>" data-option-type="<?php echo esc_attr($option['type']); ?>" data-price-type="<?php echo esc_attr($option['price_type']); ?>">
                        <h4><?php echo esc_html($option['title']); ?></h4>
                        
                        <?php if (!empty($option['description'])) : ?>
                            <p class="vb-option-description"><?php echo wp_kses_post($option['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="vb-option-field">
                            <?php 
                            $field_name = 'vb_option_' . $option_index;
                            $field_id = 'vb_option_' . $option_index;
                            $required = !empty($option['required']) ? 'required' : '';
                            
                            switch ($option['type']) {
                                case 'text':
                                    $default = !empty($option['choices'][0]['label']) ? $option['choices'][0]['label'] : '';
                                    ?>
                                    <input type="text" 
                                           id="<?php echo esc_attr($field_id); ?>" 
                                           name="<?php echo esc_attr($field_name); ?>" 
                                           value="<?php echo esc_attr($default); ?>"
                                           <?php echo $required; ?>
                                           class="vb-option-input"
                                           data-price="<?php echo esc_attr(!empty($option['choices'][0]['price']) ? $option['choices'][0]['price'] : 0); ?>"
                                           >
                                    <?php
                                    break;
                                    
                                case 'textarea':
                                    $default = !empty($option['choices'][0]['label']) ? $option['choices'][0]['label'] : '';
                                    ?>
                                    <textarea id="<?php echo esc_attr($field_id); ?>" 
                                              name="<?php echo esc_attr($field_name); ?>" 
                                              <?php echo $required; ?>
                                              class="vb-option-input"
                                              data-price="<?php echo esc_attr(!empty($option['choices'][0]['price']) ? $option['choices'][0]['price'] : 0); ?>"
                                              rows="4"><?php echo esc_textarea($default); ?></textarea>
                                    <?php
                                    break;
                                    
                                case 'number':
                                    $default = !empty($option['choices'][0]['label']) ? $option['choices'][0]['label'] : '';
                                    ?>
                                    <input type="number" 
                                           id="<?php echo esc_attr($field_id); ?>" 
                                           name="<?php echo esc_attr($field_name); ?>" 
                                           value="<?php echo esc_attr($default); ?>"
                                           min="0"
                                           step="1"
                                           <?php echo $required; ?>
                                           class="vb-option-input vb-option-number"
                                           data-price="<?php echo esc_attr(!empty($option['choices'][0]['price']) ? $option['choices'][0]['price'] : 0); ?>"
                                           >
                                    <?php
                                    break;
                                    
                                case 'dropdown':
                                    ?>
                                    <select id="<?php echo esc_attr($field_id); ?>" 
                                            name="<?php echo esc_attr($field_name); ?>" 
                                            <?php echo $required; ?>
                                            class="vb-option-input vb-option-select">
                                        <?php foreach ($option['choices'] as $choice_index => $choice) : ?>
                                            <option value="<?php echo esc_attr($choice_index); ?>" 
                                                    data-price="<?php echo esc_attr(!empty($choice['price']) ? $choice['price'] : 0); ?>"
                                                    <?php selected(!empty($choice['default']), true); ?>>
                                                <?php echo esc_html($choice['label']); ?>
                                                <?php if (!empty($choice['price']) && $choice['price'] > 0) : ?>
                                                    (+ <?php echo esc_html(number_format($choice['price'], 2)); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    break;
                                    
                                case 'radio':
                                    foreach ($option['choices'] as $choice_index => $choice) :
                                        $choice_id = $field_id . '_' . $choice_index;
                                        ?>
                                        <div class="vb-radio-option">
                                            <label>
                                                <input type="radio" 
                                                       id="<?php echo esc_attr($choice_id); ?>" 
                                                       name="<?php echo esc_attr($field_name); ?>" 
                                                       value="<?php echo esc_attr($choice_index); ?>"
                                                       data-price="<?php echo esc_attr(!empty($choice['price']) ? $choice['price'] : 0); ?>"
                                                       class="vb-option-input vb-option-radio"
                                                       <?php checked(!empty($choice['default']), true); ?>
                                                       <?php echo $required; ?>>
                                                <?php echo esc_html($choice['label']); ?>
                                                <?php if (!empty($choice['price']) && $choice['price'] > 0) : ?>
                                                    <span class="vb-option-price">(+ <?php echo esc_html(number_format($choice['price'], 2)); ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                    break;
                                    
                                case 'checkbox':
                                    foreach ($option['choices'] as $choice_index => $choice) :
                                        $choice_id = $field_id . '_' . $choice_index;
                                        ?>
                                        <div class="vb-checkbox-option">
                                            <label>
                                                <input type="checkbox" 
                                                       id="<?php echo esc_attr($choice_id); ?>" 
                                                       name="<?php echo esc_attr($field_name); ?>[]" 
                                                       value="<?php echo esc_attr($choice_index); ?>"
                                                       data-price="<?php echo esc_attr(!empty($choice['price']) ? $choice['price'] : 0); ?>"
                                                       class="vb-option-input vb-option-checkbox"
                                                       <?php checked(!empty($choice['default']), true); ?>>
                                                <?php echo esc_html($choice['label']); ?>
                                                <?php if (!empty($choice['price']) && $choice['price'] > 0) : ?>
                                                    <span class="vb-option-price">(+ <?php echo esc_html(number_format($choice['price'], 2)); ?>)</span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach;
                                    break;
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php _e('No customization options available for this service.', 'vandel-booking'); ?></p>
        <?php endif; ?>
        
        <div class="vb-running-total">
            <div class="vb-total-label"><?php _e('Current Total:', 'vandel-booking'); ?></div>
            <div class="vb-total-amount"></div>
        </div>
        
        <button type="button" class="vb-prev-step" data-prev="1"><?php _e('Back', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="3"><?php _e('Continue', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="3" style="display: none;">
        <h3><?php _e('Choose Date & Time', 'vandel-booking'); ?></h3>
        <div class="vb-form-row">
            <label for="vb-zip-code"><?php _e('Your ZIP Code', 'vandel-booking'); ?></label>
            <input type="text" 
                   id="vb-zip-code" 
                   name="zip_code" 
                   pattern="\d{5}" 
                   maxlength="5" 
                   placeholder="<?php _e('Enter 5-digit ZIP code', 'vandel-booking'); ?>" 
                   required>
            <span class="vb-help-text"><?php _e('We need your ZIP code to check service availability in your area.', 'vandel-booking'); ?></span>
        </div>

        <div class="vb-form-row">
            <label for="vb-booking-date"><?php _e('Preferred Date', 'vandel-booking'); ?></label>
            <input type="date" 
                   id="vb-booking-date" 
                   name="booking_date" 
                   min="<?php echo esc_attr(date('Y-m-d')); ?>" 
                   required>
        </div>

        <div class="vb-form-row">
            <label><?php _e('Available Time Slots', 'vandel-booking'); ?></label>
            <div id="vb-time-slots" class="vb-time-slots">
                <!-- Time slots will be populated via AJAX -->
                <div class="vb-time-slots-message">
                    <?php _e('Please enter your ZIP code and select a date to view available time slots.', 'vandel-booking'); ?>
                </div>
            </div>
        </div>

        <button type="button" class="vb-prev-step" data-prev="2"><?php _e('Back', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="4"><?php _e('Continue', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="4" style="display: none;">
        <h3><?php _e('Your Information', 'vandel-booking'); ?></h3>
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
        <button type="button" class="vb-prev-step" data-prev="3"><?php _e('Back', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="5"><?php _e('Continue', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="5" style="display: none;">
        <h3><?php _e('Review & Payment', 'vandel-booking'); ?></h3>
        <div class="vb-booking-summary">
            <h4><?php _e('Booking Summary', 'vandel-booking'); ?></h4>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Service', 'vandel-booking'); ?></span>
                <span class="vb-summary-value"><?php echo esc_html($service->post_title); ?></span>
            </div>
            
            <div class="vb-selected-options-summary">
                <!-- Selected options will be displayed here -->
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
                    <span class="vb-summary-label"><?php _e('Base Price', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-base-price"></span>
                </div>
                
                <div class="vb-options-price-summary">
                    <!-- Option prices will be listed here -->
                </div>
                
                <?php if (get_post_meta($service->ID, 'vb_tax_rate', true)): ?>
                    <div class="vb-summary-row">
                        <span class="vb-summary-label"><?php _e('Tax', 'vandel-booking'); ?></span>
                        <span class="vb-summary-value" id="vb-summary-tax"></span>
                    </div>
                <?php endif; ?>
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

            <?php if (get_post_meta($service->ID, 'vb_enable_deposit', true)): ?>
                <div class="vb-deposit-option">
                    <label>
                        <input type="checkbox" name="pay_deposit" value="1">
                        <?php _e('Pay deposit only', 'vandel-booking'); ?>
                        <span class="vb-deposit-amount"></span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="vb-payment-section">
                <h4><?php _e('Payment Details', 'vandel-booking'); ?></h4>
                <div id="vb-stripe-card-element"></div>
                <div id="vb-card-errors" role="alert"></div>
            </div>
        </div>

        <button type="button" class="vb-prev-step" data-prev="4"><?php _e('Back', 'vandel-booking'); ?></button>
        <button type="submit" class="vb-submit-booking"><?php _e('Complete Booking', 'vandel-booking'); ?></button>
    </div>

    <div class="vb-step" data-step="6" style="display: none;">
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