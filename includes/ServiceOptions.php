<?php
namespace VandelBooking;

/**
 * Service class for managing service options and pricing
 */
class ServiceOptions {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_service_options_metabox'));
        add_action('save_post', array($this, 'save_service_options'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vb_get_option_template', array($this, 'ajax_get_option_template'));
        add_action('wp_ajax_vb_get_choice_template', array($this, 'ajax_get_choice_template'));
        
        // Remove old settings metabox if it exists
        add_action('add_meta_boxes', array($this, 'remove_old_settings_metabox'), 20);
    }

    /**
     * Remove old settings metabox if it exists
     */
    public function remove_old_settings_metabox() {
        remove_meta_box('service_settings', 'vb_service', 'normal');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        global $post;

        // Only enqueue on service edit screen
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if (isset($post) && $post->post_type === 'vb_service') {
                wp_enqueue_script('jquery-ui-sortable');
                wp_enqueue_script('vb-service-options', VANDEL_BOOKING_PLUGIN_URL . 'assets/js/service-options.js', array('jquery', 'jquery-ui-sortable'), VANDEL_BOOKING_VERSION, true);
                wp_localize_script('vb-service-options', 'vbServiceOptions', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vb_service_options_nonce'),
                ));
                
                wp_enqueue_style('vb-service-options', VANDEL_BOOKING_PLUGIN_URL . 'assets/css/service-options.css', array(), VANDEL_BOOKING_VERSION);
                
                // Add small script to hide duplicate settings
                add_action('admin_footer', function() {
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function($) {
                            // Hide any duplicate settings metaboxes
                            $('#service_settings, .service-settings-metabox').hide();
                        });
                    </script>
                    <?php
                });
            }
        }
    }

    /**
     * Add metabox for service options
     */
    public function add_service_options_metabox() {
        add_meta_box(
            'vb_service_options',
            __('Service Options', 'vandel-booking'),
            array($this, 'render_service_options_metabox'),
            'vb_service',
            'normal',
            'high'
        );
    }

    /**
     * Render the service options metabox
     */
    public function render_service_options_metabox($post) {
        wp_nonce_field('vb_service_options_nonce', 'vb_service_options_nonce');
        
        // Get saved options
        $options = get_post_meta($post->ID, '_vb_service_options', true);
        if (!is_array($options)) {
            $options = array();
        }
        
        ?>
        <div class="vb-service-options-container">
            <p class="description"><?php _e('Add customizable options for this service. Each option can have its own price modifier.', 'vandel-booking'); ?></p>
            
            <div class="vb-service-options-header">
                <div class="vb-service-option-add">
                    <button type="button" class="button vb-add-option">
                        <?php _e('Add Option', 'vandel-booking'); ?>
                    </button>
                </div>
            </div>
            
            <div class="vb-service-options-list">
                <?php 
                if (!empty($options)) {
                    foreach ($options as $index => $option) {
                        $this->render_option_template($index, $option);
                    }
                }
                ?>
            </div>
            
            <div class="vb-service-options-footer">
                <p class="description"><?php _e('Drag and drop to reorder options. The order will affect how they appear on the booking form.', 'vandel-booking'); ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render a single option template
     */
    public function render_option_template($index, $option = array()) {
        $option = wp_parse_args($option, array(
            'title' => '',
            'description' => '',
            'type' => 'dropdown',
            'required' => false,
            'choices' => array(),
            'price_type' => 'fixed',
        ));
        
        $option_types = array(
            'number' => __('Number Input', 'vandel-booking'),
            'text' => __('Text Input', 'vandel-booking'),
            'textarea' => __('Textarea', 'vandel-booking'),
            'dropdown' => __('Dropdown Select', 'vandel-booking'),
            'checkbox' => __('Checkboxes', 'vandel-booking'),
            'radio' => __('Radio Buttons', 'vandel-booking'),
        );
        
        $price_types = array(
            'fixed' => __('Fixed Amount', 'vandel-booking'),
            'percentage' => __('Percentage of Base Price', 'vandel-booking'),
            'multiply' => __('Multiply by Value (for number inputs)', 'vandel-booking'),
        );
        
        ?>
        <div class="vb-service-option" data-index="<?php echo esc_attr($index); ?>">
            <div class="vb-option-header">
                <div class="vb-option-title">
                    <h4><?php echo !empty($option['title']) ? esc_html($option['title']) : __('New Option', 'vandel-booking'); ?></h4>
                </div>
                <div class="vb-option-actions">
                    <span class="vb-option-sort dashicons dashicons-move"></span>
                    <span class="vb-option-toggle dashicons dashicons-arrow-down-alt2"></span>
                    <span class="vb-option-remove dashicons dashicons-trash"></span>
                </div>
            </div>
            
            <div class="vb-option-content">
                <div class="vb-option-row">
                    <div class="vb-option-field">
                        <label for="vb_option_title_<?php echo esc_attr($index); ?>">
                            <?php _e('Option Title', 'vandel-booking'); ?>
                        </label>
                        <input type="text" 
                               name="vb_service_options[<?php echo esc_attr($index); ?>][title]" 
                               id="vb_option_title_<?php echo esc_attr($index); ?>" 
                               value="<?php echo esc_attr($option['title']); ?>" 
                               class="vb-option-title-input" 
                               placeholder="<?php esc_attr_e('e.g. Choose Color', 'vandel-booking'); ?>">
                    </div>
                    
                    <div class="vb-option-field">
                        <label for="vb_option_type_<?php echo esc_attr($index); ?>">
                            <?php _e('Input Type', 'vandel-booking'); ?>
                        </label>
                        <select name="vb_service_options[<?php echo esc_attr($index); ?>][type]" 
                                id="vb_option_type_<?php echo esc_attr($index); ?>" 
                                class="vb-option-type-select">
                            <?php foreach ($option_types as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($option['type'], $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="vb-option-row">
                    <div class="vb-option-field">
                        <label for="vb_option_desc_<?php echo esc_attr($index); ?>">
                            <?php _e('Description', 'vandel-booking'); ?>
                        </label>
                        <textarea name="vb_service_options[<?php echo esc_attr($index); ?>][description]" 
                                  id="vb_option_desc_<?php echo esc_attr($index); ?>" 
                                  placeholder="<?php esc_attr_e('Brief description of this option', 'vandel-booking'); ?>"
                                  rows="2"><?php echo esc_textarea($option['description']); ?></textarea>
                    </div>
                </div>
                
                <div class="vb-option-row">
                    <div class="vb-option-field vb-option-required">
                        <label>
                            <input type="checkbox" 
                                   name="vb_service_options[<?php echo esc_attr($index); ?>][required]" 
                                   value="1" 
                                   <?php checked(!empty($option['required']), true); ?>>
                            <?php _e('This option is required', 'vandel-booking'); ?>
                        </label>
                    </div>
                    
                    <div class="vb-option-field">
                        <label for="vb_option_price_type_<?php echo esc_attr($index); ?>">
                            <?php _e('Price Calculation', 'vandel-booking'); ?>
                        </label>
                        <select name="vb_service_options[<?php echo esc_attr($index); ?>][price_type]" 
                                id="vb_option_price_type_<?php echo esc_attr($index); ?>" 
                                class="vb-option-price-type-select">
                            <?php foreach ($price_types as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" 
                                       <?php selected($option['price_type'], $value); ?>
                                       <?php disabled($value === 'multiply' && $option['type'] !== 'number', true); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="vb-option-choices-container" data-type="<?php echo esc_attr($option['type']); ?>">
                    <h4><?php _e('Choices', 'vandel-booking'); ?></h4>
                    <p class="description">
                        <?php _e('Add choices for this option. Each choice can have its own price modifier.', 'vandel-booking'); ?>
                    </p>
                    
                    <div class="vb-option-choices-list">
                        <?php 
                        if (!empty($option['choices'])) {
                            foreach ($option['choices'] as $choice_index => $choice) {
                                $this->render_choice_template($index, $choice_index, $choice);
                            }
                        } else {
                            // Add at least one empty choice
                            $this->render_choice_template($index, 0);
                        }
                        ?>
                    </div>
                    
                    <button type="button" class="button vb-add-choice">
                        <?php _e('Add Choice', 'vandel-booking'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render a single choice template
     */
    public function render_choice_template($option_index, $choice_index, $choice = array()) {
        $choice = wp_parse_args($choice, array(
            'label' => '',
            'price' => '',
            'default' => false,
        ));
        
        ?>
        <div class="vb-option-choice" data-choice-index="<?php echo esc_attr($choice_index); ?>">
            <div class="vb-choice-row">
                <div class="vb-choice-field vb-choice-label">
                    <input type="text" 
                           name="vb_service_options[<?php echo esc_attr($option_index); ?>][choices][<?php echo esc_attr($choice_index); ?>][label]" 
                           value="<?php echo esc_attr($choice['label']); ?>" 
                           placeholder="<?php esc_attr_e('Choice label', 'vandel-booking'); ?>">
                </div>
                
                <div class="vb-choice-field vb-choice-price">
                    <input type="number" 
                           name="vb_service_options[<?php echo esc_attr($option_index); ?>][choices][<?php echo esc_attr($choice_index); ?>][price]" 
                           value="<?php echo esc_attr($choice['price']); ?>" 
                           step="0.01" 
                           placeholder="<?php esc_attr_e('Price', 'vandel-booking'); ?>">
                </div>
                
                <div class="vb-choice-field vb-choice-default">
                    <label>
                        <input type="checkbox" 
                               name="vb_service_options[<?php echo esc_attr($option_index); ?>][choices][<?php echo esc_attr($choice_index); ?>][default]" 
                               value="1" 
                               <?php checked(!empty($choice['default']), true); ?>>
                        <?php _e('Default', 'vandel-booking'); ?>
                    </label>
                </div>
                
                <div class="vb-choice-field vb-choice-actions">
                    <span class="vb-choice-remove dashicons dashicons-no-alt"></span>
                    <span class="vb-choice-sort dashicons dashicons-move"></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler to get option template
     */
    public function ajax_get_option_template() {
        check_ajax_referer('vb_service_options_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'vandel-booking')));
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        
        ob_start();
        $this->render_option_template($index);
        $template = ob_get_clean();
        
        wp_send_json_success(array('template' => $template));
    }
    
    /**
     * AJAX handler to get choice template
     */
    public function ajax_get_choice_template() {
        check_ajax_referer('vb_service_options_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'vandel-booking')));
        }
        
        $option_index = isset($_POST['option_index']) ? intval($_POST['option_index']) : 0;
        $choice_index = isset($_POST['choice_index']) ? intval($_POST['choice_index']) : 0;
        
        ob_start();
        $this->render_choice_template($option_index, $choice_index);
        $template = ob_get_clean();
        
        wp_send_json_success(array('template' => $template));
    }
    
    /**
     * Save service options
     */
    public function save_service_options($post_id) {
        // Check if our nonce is set
        if (!isset($_POST['vb_service_options_nonce'])) {
            return;
        }
        
        // Verify the nonce
        if (!wp_verify_nonce($_POST['vb_service_options_nonce'], 'vb_service_options_nonce')) {
            return;
        }
        
        // Check if user has permissions to save data
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if not an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check if not a revision
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // Check post type
        if ('vb_service' != get_post_type($post_id)) {
            return;
        }
        
        // Save options
        if (isset($_POST['vb_service_options'])) {
            $options = $this->sanitize_service_options($_POST['vb_service_options']);
            update_post_meta($post_id, '_vb_service_options', $options);
        } else {
            delete_post_meta($post_id, '_vb_service_options');
        }
    }
    
    /**
     * Sanitize service options
     */
    private function sanitize_service_options($options) {
        $sanitized = array();
        
        if (is_array($options)) {
            foreach ($options as $index => $option) {
                $sanitized_option = array(
                    'title' => sanitize_text_field($option['title']),
                    'description' => wp_kses_post($option['description']),
                    'type' => sanitize_key($option['type']),
                    'required' => isset($option['required']) ? true : false,
                    'price_type' => sanitize_key($option['price_type']),
                    'choices' => array()
                );
                
                if (isset($option['choices']) && is_array($option['choices'])) {
                    foreach ($option['choices'] as $choice_index => $choice) {
                        $sanitized_option['choices'][$choice_index] = array(
                            'label' => sanitize_text_field($choice['label']),
                            'price' => floatval($choice['price']),
                            'default' => isset($choice['default']) ? true : false
                        );
                    }
                }
                
                $sanitized[] = $sanitized_option;
            }
        }
        
        return $sanitized;
    }
    
    /**Service Settings
     * Get service options for a specific service
     */
    public function get_service_options($service_id) {
        $options = get_post_meta($service_id, '_vb_service_options', true);
        if (!is_array($options)) {
            $options = array();
        }
        return $options;
    }
}