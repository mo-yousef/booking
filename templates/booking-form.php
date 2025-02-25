<?php
// Get service options if ServiceOptions class exists
$service_options = array();
if (isset($service) && is_object($service) && class_exists('VandelBooking\\ServiceOptions')) {
    if (!isset($options_manager)) {
        $options_manager = new \VandelBooking\ServiceOptions();
    }
    $service_options = $options_manager->get_service_options($service->ID);
}

// Determine if we have options to display
$has_options = !empty($service_options);
?>

<div class="vb-booking-form" data-service-id="<?php echo isset($service) && is_object($service) ? esc_attr($service->ID) : '0'; ?>">
    <!-- Progress indicator -->
    <div class="vb-form-progress">
        <ul>
            <li data-step="1" class="active"><?php _e('Service', 'vandel-booking'); ?></li>
            <?php if ($has_options) : ?>
                <li data-step="2"><?php _e('Options', 'vandel-booking'); ?></li>
                <li data-step="3"><?php _e('Date & Time', 'vandel-booking'); ?></li>
                <li data-step="4"><?php _e('Your Info', 'vandel-booking'); ?></li>
                <li data-step="5"><?php _e('Payment', 'vandel-booking'); ?></li>
                <li data-step="6"><?php _e('Confirmation', 'vandel-booking'); ?></li>
            <?php else : ?>
                <li data-step="2"><?php _e('Date & Time', 'vandel-booking'); ?></li>
                <li data-step="3"><?php _e('Your Info', 'vandel-booking'); ?></li>
                <li data-step="4"><?php _e('Payment', 'vandel-booking'); ?></li>
                <li data-step="5"><?php _e('Confirmation', 'vandel-booking'); ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Step 1: Service Selection -->
    <div class="vb-step" data-step="1">
        <h3><?php _e('Step 1: Select Service', 'vandel-booking'); ?></h3>
        
        <?php if (!empty($top_level_services) && is_array($top_level_services)) : ?>
            <div class="vb-services-list">
                <?php foreach ($top_level_services as $service_item) : ?>
                    <?php if ($service_item && is_object($service_item)) : 
                        // Get sub-services specifically for this service 
                        $item_sub_services = array();
                        if (isset($this->service) && method_exists($this->service, 'get_sub_services')) {
                            $item_sub_services = $this->service->get_sub_services($service_item->ID);
                        }
                    ?>
                        <div class="vb-service-item" data-service-id="<?php echo esc_attr($service_item->ID); ?>">
                            <h4><?php echo esc_html($service_item->post_title); ?></h4>
                            
                            <?php if (!empty($item_sub_services)) : ?>
                                <div class="vb-sub-services" style="display: none;">
                                    <label><?php _e('Choose a sub-service:', 'vandel-booking'); ?></label>
                                    <select class="vb-sub-service-select" name="sub_service_<?php echo esc_attr($service_item->ID); ?>">
                                        <option value=""><?php _e('Select sub-service', 'vandel-booking'); ?></option>
                                        <?php foreach ($item_sub_services as $sub) : ?>
                                            <option value="<?php echo esc_attr($sub->ID); ?>">
                                                <?php 
                                                echo esc_html($sub->post_title); 
                                                $price = get_post_meta($sub->ID, 'vb_regular_price', true);
                                                if ($price) {
                                                    echo ' - ' . esc_html(get_option('vandel_booking_currency', 'USD')) . ' ' . esc_html(number_format($price, 2));
                                                }
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>

                            <div class="vb-service-price">
                                <?php
                                $price = get_post_meta($service_item->ID, 'vb_regular_price', true);
                                if ($price) :
                                    echo esc_html(sprintf(__('Starting from %s %s', 'vandel-booking'), 
                                        get_option('vandel_booking_currency', 'USD'),
                                        number_format($price, 2)));
                                endif;
                                ?>
                            </div>

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

    <?php if ($has_options) : ?>
    <!-- Step 2: Service Options (with options) -->
    <div class="vb-step" data-step="2" style="display: none;">
        <h3><?php _e('Step 2: Service Options', 'vandel-booking'); ?></h3>
        
        <div class="vb-service-summary">
            <h4><?php _e('Selected Service', 'vandel-booking'); ?></h4>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Service', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-step2-service">
                    <?php if (isset($service) && is_object($service)) echo esc_html($service->post_title); ?>
                </span>
            </div>
        </div>
        
        <div class="vb-options-container">
            <?php foreach ($service_options as $index => $option) : ?>
                <div class="vb-option-group" data-option-index="<?php echo esc_attr($index); ?>" 
                     data-option-type="<?php echo esc_attr($option['type']); ?>" 
                     data-price-type="<?php echo esc_attr($option['price_type']); ?>">
                    <h4><?php echo esc_html($option['title']); ?></h4>
                    
                    <?php if (!empty($option['description'])) : ?>
                        <div class="vb-option-description"><?php echo wp_kses_post($option['description']); ?></div>
                    <?php endif; ?>
                    
                    <div class="vb-option-field">
                        <?php 
                        // Render option field based on type
                        switch ($option['type']) {
                            case 'text':
                                $default_value = '';
                                $price = 0;
                                if (!empty($option['choices'][0])) {
                                    $default_value = $option['choices'][0]['label'];
                                    $price = $option['choices'][0]['price'];
                                }
                                ?>
                                <input type="text" 
                                       class="vb-option-input vb-option-text" 
                                       name="vb_option[<?php echo esc_attr($index); ?>]" 
                                       value="<?php echo esc_attr($default_value); ?>"
                                       <?php echo (!empty($option['required'])) ? 'required' : ''; ?>
                                       data-price="<?php echo esc_attr($price); ?>">
                                <?php
                                break;
                                
                            case 'textarea':
                                $default_value = '';
                                $price = 0;
                                if (!empty($option['choices'][0])) {
                                    $default_value = $option['choices'][0]['label'];
                                    $price = $option['choices'][0]['price'];
                                }
                                ?>
                                <textarea class="vb-option-input vb-option-textarea" 
                                          name="vb_option[<?php echo esc_attr($index); ?>]"
                                          <?php echo (!empty($option['required'])) ? 'required' : ''; ?>
                                          data-price="<?php echo esc_attr($price); ?>"
                                          rows="3"><?php echo esc_textarea($default_value); ?></textarea>
                                <?php
                                break;
                                
                            case 'number':
                                $default_value = '';
                                $price = 0;
                                if (!empty($option['choices'][0])) {
                                    $default_value = $option['choices'][0]['label'];
                                    $price = $option['choices'][0]['price'];
                                }
                                ?>
                                <input type="number" 
                                       class="vb-option-input vb-option-number" 
                                       name="vb_option[<?php echo esc_attr($index); ?>]" 
                                       value="<?php echo esc_attr($default_value); ?>"
                                       <?php echo (!empty($option['required'])) ? 'required' : ''; ?>
                                       min="0" 
                                       step="1"
                                       data-price="<?php echo esc_attr($price); ?>">
                                <?php
                                break;
                                
                            case 'dropdown':
                                ?>
                                <select class="vb-option-input vb-option-select" 
                                        name="vb_option[<?php echo esc_attr($index); ?>]"
                                        <?php echo (!empty($option['required'])) ? 'required' : ''; ?>>
                                    <option value=""><?php _e('Choose an option...', 'vandel-booking'); ?></option>
                                    <?php foreach ($option['choices'] as $choice_index => $choice) : ?>
                                        <option value="<?php echo esc_attr($choice_index); ?>" 
                                                data-price="<?php echo esc_attr($choice['price']); ?>"
                                                <?php selected(!empty($choice['default']), true); ?>>
                                            <?php echo esc_html($choice['label']); ?>
                                            <?php if ($choice['price'] > 0) : ?>
                                                (<?php echo ($option['price_type'] === 'percentage') ? '+' . $choice['price'] . '%' : '+' . number_format($choice['price'], 2); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php
                                break;
                                
                            case 'radio':
                                ?>
                                <div class="vb-radio-options">
                                    <?php foreach ($option['choices'] as $choice_index => $choice) : ?>
                                        <div class="vb-radio-option">
                                            <label>
                                                <input type="radio" 
                                                       class="vb-option-input vb-option-radio" 
                                                       name="vb_option[<?php echo esc_attr($index); ?>]" 
                                                       value="<?php echo esc_attr($choice_index); ?>"
                                                       data-price="<?php echo esc_attr($choice['price']); ?>"
                                                       <?php checked(!empty($choice['default']), true); ?>
                                                       <?php echo (!empty($option['required']) && $choice_index === 0) ? 'required' : ''; ?>>
                                                <?php echo esc_html($choice['label']); ?>
                                                <?php if ($choice['price'] > 0) : ?>
                                                    <span class="vb-option-price">
                                                        <?php echo ($option['price_type'] === 'percentage') ? '+' . $choice['price'] . '%' : '+' . number_format($choice['price'], 2); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                                break;
                                
                            case 'checkbox':
                                ?>
                                <div class="vb-checkbox-options">
                                    <?php foreach ($option['choices'] as $choice_index => $choice) : ?>
                                        <div class="vb-checkbox-option">
                                            <label>
                                                <input type="checkbox" 
                                                       class="vb-option-input vb-option-checkbox" 
                                                       name="vb_option[<?php echo esc_attr($index); ?>][]" 
                                                       value="<?php echo esc_attr($choice_index); ?>"
                                                       data-price="<?php echo esc_attr($choice['price']); ?>"
                                                       <?php checked(!empty($choice['default']), true); ?>>
                                                <?php echo esc_html($choice['label']); ?>
                                                <?php if ($choice['price'] > 0) : ?>
                                                    <span class="vb-option-price">
                                                        <?php echo ($option['price_type'] === 'percentage') ? '+' . $choice['price'] . '%' : '+' . number_format($choice['price'], 2); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                                break;
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="vb-running-total">
            <span class="vb-total-label"><?php _e('Total:', 'vandel-booking'); ?></span>
            <span class="vb-total-amount"></span>
        </div>
        
        <button type="button" class="vb-prev-step" data-prev="1"><?php _e('Previous', 'vandel-booking'); ?></button>
        <button type="button" class="vb-next-step" data-next="3"><?php _e('Next: Select Date & Time', 'vandel-booking'); ?></button>
    </div>
    
    <!-- Step 3: Date & Time (with options) -->
    <div class="vb-step" data-step="3" style="display: none;">
    <?php else : ?>
    <!-- Step 2: Date & Time (without options) -->
    <div class="vb-step" data-step="2" style="display: none;">
    <?php endif; ?>
        <h3><?php _e('Select Date & Time', 'vandel-booking'); ?></h3>
        
        <div class="vb-service-summary">
            <h4><?php _e('Selected Service', 'vandel-booking'); ?></h4>
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Service', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-step2-service">
                    <?php if (isset($service) && is_object($service)) echo esc_html($service->post_title); ?>
                </span>
            </div>
        </div>
        
        <div class="vb-form-row">
            <label for="vb-zip-code"><?php _e('ZIP Code', 'vandel-booking'); ?></label>
            <input type="text" 
                   id="vb-zip-code" 
                   name="zip_code" 
                   pattern="\d{5}" 
                   maxlength="5" 
                   placeholder="<?php _e('Enter 5-digit ZIP code', 'vandel-booking'); ?>" 
                   required>
            <span class="vb-help-text"><?php _e('Enter your ZIP code to check availability and pricing', 'vandel-booking'); ?></span>
        </div>
        
        <div class="vb-form-row">
            <label for="vb-booking-date"><?php _e('Date', 'vandel-booking'); ?></label>
            <input type="date" 
                   id="vb-booking-date" 
                   name="booking_date" 
                   min="<?php echo esc_attr(date('Y-m-d')); ?>" 
                   required>
        </div>
        
        <div class="vb-form-row">
            <label><?php _e('Available Time Slots', 'vandel-booking'); ?></label>
            <div id="vb-time-slots" class="vb-time-slots">
                <!-- Time slots will be loaded dynamically -->
                <div class="vb-time-slots-message"><?php _e('Please select a date and enter your ZIP code to see available time slots.', 'vandel-booking'); ?></div>
            </div>
        </div>
        
        <div class="vb-running-total">
            <span class="vb-total-label"><?php _e('Total:', 'vandel-booking'); ?></span>
            <span class="vb-total-amount"></span>
        </div>
        
        <?php if ($has_options) : ?>
            <button type="button" class="vb-prev-step" data-prev="2"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="button" class="vb-next-step" data-next="4"><?php _e('Next: Your Information', 'vandel-booking'); ?></button>
        <?php else : ?>
            <button type="button" class="vb-prev-step" data-prev="1"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="button" class="vb-next-step" data-next="3"><?php _e('Next: Your Information', 'vandel-booking'); ?></button>
        <?php endif; ?>
    </div>
    
    <?php if ($has_options) : ?>
    <!-- Step 4: Customer Information (with options) -->
    <div class="vb-step" data-step="4" style="display: none;">
    <?php else : ?>
    <!-- Step 3: Customer Information (without options) -->
    <div class="vb-step" data-step="3" style="display: none;">
    <?php endif; ?>
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
        
        <?php if ($has_options) : ?>
            <button type="button" class="vb-prev-step" data-prev="3"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="button" class="vb-next-step" data-next="5"><?php _e('Next: Review & Payment', 'vandel-booking'); ?></button>
        <?php else : ?>
            <button type="button" class="vb-prev-step" data-prev="2"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="button" class="vb-next-step" data-next="4"><?php _e('Next: Review & Payment', 'vandel-booking'); ?></button>
        <?php endif; ?>
    </div>
    
    <?php if ($has_options) : ?>
    <!-- Step 5: Review & Payment (with options) -->
    <div class="vb-step" data-step="5" style="display: none;">
    <?php else : ?>
    <!-- Step 4: Review & Payment (without options) -->
    <div class="vb-step" data-step="4" style="display: none;">
    <?php endif; ?>
        <h3><?php _e('Review & Payment', 'vandel-booking'); ?></h3>
        
        <div class="vb-booking-summary">
            <h4><?php _e('Booking Summary', 'vandel-booking'); ?></h4>
            
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Service', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-service">
                    <?php if (isset($service) && is_object($service)) echo esc_html($service->post_title); ?>
                </span>
            </div>
            
            <?php if ($has_options) : ?>
                <h4><?php _e('Selected Options', 'vandel-booking'); ?></h4>
                <div class="vb-selected-options-summary">
                    <!-- Selected options will be populated dynamically -->
                </div>
            <?php endif; ?>
            
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Date & Time', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-datetime"></span>
            </div>
            
            <div class="vb-summary-row">
                <span class="vb-summary-label"><?php _e('Location', 'vandel-booking'); ?></span>
                <span class="vb-summary-value" id="vb-summary-location"></span>
            </div>
            
            <div class="vb-pricing-breakdown">
                <h4><?php _e('Price Breakdown', 'vandel-booking'); ?></h4>
                
                <div class="vb-summary-row">
                    <span class="vb-summary-label"><?php _e('Base Price', 'vandel-booking'); ?></span>
                    <span class="vb-summary-value" id="vb-summary-base-price"></span>
                </div>
                
                <?php if ($has_options) : ?>
                    <div class="vb-options-price-summary">
                        <!-- Options price breakdown will be populated dynamically -->
                    </div>
                <?php endif; ?>
                
                <?php if (isset($service_data) && !empty($service_data['tax_rate'])) : ?>
                    <div class="vb-summary-row tax-row">
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
            
            <?php if (isset($service_data) && !empty($service_data['enable_deposit'])) : ?>
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
        
        <?php if ($has_options) : ?>
            <button type="button" class="vb-prev-step" data-prev="4"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="submit" class="vb-submit-booking"><?php _e('Complete Booking', 'vandel-booking'); ?></button>
        <?php else : ?>
            <button type="button" class="vb-prev-step" data-prev="3"><?php _e('Previous', 'vandel-booking'); ?></button>
            <button type="submit" class="vb-submit-booking"><?php _e('Complete Booking', 'vandel-booking'); ?></button>
        <?php endif; ?>
    </div>
    
    <?php if ($has_options) : ?>
    <!-- Step 6: Confirmation (with options) -->
    <div class="vb-step" data-step="6" style="display: none;">
    <?php else : ?>
    <!-- Step 5: Confirmation (without options) -->
    <div class="vb-step" data-step="5" style="display: none;">
    <?php endif; ?>
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
    
    <!-- Loading Overlay -->
    <div class="vb-loading-overlay">
        <div class="vb-loading-spinner"></div>
    </div>
</div>

<?php
// Debug output if WP_DEBUG is enabled
if (defined('WP_DEBUG') && WP_DEBUG) {
    echo '<div style="background: #f5f5f5; padding: 10px; margin: 10px 0;">';
    echo '<h4>Debug Information:</h4>';
    
    echo '<div>Service object exists: ' . (isset($service) && is_object($service) ? 'Yes' : 'No') . '</div>';
    echo '<div>Service ID: ' . (isset($service) && is_object($service) ? $service->ID : 'N/A') . '</div>';
    echo '<div>Service class exists: ' . (isset($this->service) ? 'Yes' : 'No') . '</div>';
    
    if (isset($this->service)) {
        echo '<div>get_sub_services method exists: ' . (method_exists($this->service, 'get_sub_services') ? 'Yes' : 'No') . '</div>';
    }
    
    echo '<div>Service options found: ' . count($service_options) . '</div>';
    
    if (class_exists('VandelBooking\\ServiceOptions')) {
        echo '<div>ServiceOptions class exists: Yes</div>';
    } else {
        echo '<div>ServiceOptions class exists: No</div>';
    }
    
    echo '</div>';
}
?>