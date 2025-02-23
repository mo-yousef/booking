<?php
namespace Vandel_Booking\ServiceCPT;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Class Service_CPT
 *
 * Registers a "Service" Custom Post Type and adds meta boxes for:
 * - Regular Price
 * - Sale Price
 * - Duration
 * - Capacity
 * - Buffer Before/After
 * - Tax Rate
 * - Deposit Payment Options
 */
class Service_CPT {

    /**
     * Post type slug.
     *
     * @var string
     */
    private $post_type = 'vandel_service';

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress to register the CPT and meta boxes
        add_action( 'init', [ $this, 'register_service_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_service_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_service_meta' ] );
    }

    /**
     * Register the custom post type for Services.
     */

    /**
     * Registers the "vandel_service" custom post type
     * and nests it under "Vandel Booking" (slug: 'vandel_booking_main').
     */
    public function register_service_cpt() {
        $labels = [
            'name'               => __( 'Services', 'vandel-booking' ),
            'singular_name'      => __( 'Service', 'vandel-booking' ),
            'add_new'            => __( 'Add New Service', 'vandel-booking' ),
            'add_new_item'       => __( 'Add New Service', 'vandel-booking' ),
            'edit_item'          => __( 'Edit Service', 'vandel-booking' ),
            'new_item'           => __( 'New Service', 'vandel-booking' ),
            'all_items'          => __( 'All Services', 'vandel-booking' ),
            'view_item'          => __( 'View Service', 'vandel-booking' ),
            'search_items'       => __( 'Search Services', 'vandel-booking' ),
            'not_found'          => __( 'No services found.', 'vandel-booking' ),
            'not_found_in_trash' => __( 'No services found in Trash.', 'vandel-booking' ),
            'menu_name'          => __( 'Services', 'vandel-booking' ),
        ];

        $args = [
            'labels'            => $labels,
            'public'            => false,  // Not publicly queryable
            'show_ui'           => true,   // Show in the admin
            'show_in_menu'      => 'vandel_booking_main', // <--- This is the key
            'capability_type'   => 'post',
            'hierarchical'      => false,
            'menu_position'     => null,
            'supports'          => [ 'title', 'editor' ],
            'has_archive'       => false,
            'exclude_from_search' => true,
            'rewrite'           => false,
        ];

        register_post_type( 'vandel_service', $args );
    }







    /**
     * Add meta boxes for service fields.
     */
    public function add_service_meta_boxes() {
        add_meta_box(
            'vandel_service_meta_box',
            __( 'Service Details', 'vandel-booking' ),
            [ $this, 'render_service_meta_box' ],
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * Render the meta box HTML for service fields.
     *
     * @param \WP_Post $post
     */
    public function render_service_meta_box( $post ) {
        // We'll store meta in an array named `_vandel_service_meta`
        $stored_meta = get_post_meta( $post->ID, '_vandel_service_meta', true );
        if ( ! is_array( $stored_meta ) ) {
            $stored_meta = [];
        }

        // Nonce for security
        wp_nonce_field( 'vandel_service_save_meta', 'vandel_service_nonce' );

        // Helper function: safe getter
        $get_value = function( $key, $default = '' ) use ( $stored_meta ) {
            return isset( $stored_meta[ $key ] ) ? esc_attr( $stored_meta[ $key ] ) : $default;
        };

        // Fields
        $regular_price = $get_value( 'regular_price' );
        $sale_price    = $get_value( 'sale_price' );
        $duration      = $get_value( 'duration' );
        $capacity      = $get_value( 'capacity' );
        $buffer_before = $get_value( 'buffer_before' );
        $buffer_after  = $get_value( 'buffer_after' );
        $tax_rate      = $get_value( 'tax_rate' );
        $deposit_enabled = $get_value( 'deposit_enabled' );
        $deposit_type  = $get_value( 'deposit_type' );
        $deposit_amount= $get_value( 'deposit_amount' );

        ?>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <!-- Basic Information -->
            <div style="flex: 1 1 400px; background: #f9f9f9; padding: 15px;">
                <h3><?php esc_html_e( 'Basic Information', 'vandel-booking' ); ?></h3>

                <p>
                    <label for="regular_price"><strong><?php esc_html_e( 'Regular Price *', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="0.01" min="0" id="regular_price" name="vandel_service_meta[regular_price]" value="<?php echo $regular_price; ?>" required/>
                </p>

                <p>
                    <label for="sale_price"><strong><?php esc_html_e( 'Sale Price', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="0.01" min="0" id="sale_price" name="vandel_service_meta[sale_price]" value="<?php echo $sale_price; ?>" />
                </p>

                <p>
                    <label for="duration"><strong><?php esc_html_e( 'Duration (minutes)', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="1" min="0" id="duration" name="vandel_service_meta[duration]" value="<?php echo $duration; ?>" required/>
                </p>

                <p>
                    <label for="capacity"><strong><?php esc_html_e( 'Capacity (0 = unlimited)', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="1" min="0" id="capacity" name="vandel_service_meta[capacity]" value="<?php echo $capacity; ?>" />
                </p>
            </div>

            <!-- Advanced Settings -->
            <div style="flex: 1 1 400px; background: #f9f9f9; padding: 15px;">
                <h3><?php esc_html_e( 'Advanced Settings', 'vandel-booking' ); ?></h3>

                <p>
                    <label for="buffer_before"><strong><?php esc_html_e( 'Buffer Time Before (minutes)', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="1" min="0" id="buffer_before" name="vandel_service_meta[buffer_before]" value="<?php echo $buffer_before; ?>" />
                </p>

                <p>
                    <label for="buffer_after"><strong><?php esc_html_e( 'Buffer Time After (minutes)', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="1" min="0" id="buffer_after" name="vandel_service_meta[buffer_after]" value="<?php echo $buffer_after; ?>" />
                </p>

                <p>
                    <label for="tax_rate"><strong><?php esc_html_e( 'Tax Rate (%)', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="0.01" min="0" id="tax_rate" name="vandel_service_meta[tax_rate]" value="<?php echo $tax_rate; ?>" />
                </p>
            </div>

            <!-- Deposit Options -->
            <div style="flex: 1 1 400px; background: #f9f9f9; padding: 15px;">
                <h3><?php esc_html_e( 'Deposit Payment Options', 'vandel-booking' ); ?></h3>

                <p>
                    <input type="checkbox" id="deposit_enabled" name="vandel_service_meta[deposit_enabled]" value="1" <?php checked( $deposit_enabled, '1' ); ?> />
                    <label for="deposit_enabled"><?php esc_html_e( 'Enable Deposit Payment?', 'vandel-booking' ); ?></label>
                </p>

                <p>
                    <label for="deposit_type"><strong><?php esc_html_e( 'Deposit Type', 'vandel-booking' ); ?></strong></label><br/>
                    <select id="deposit_type" name="vandel_service_meta[deposit_type]">
                        <option value="percentage" <?php selected( $deposit_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'vandel-booking' ); ?></option>
                        <option value="fixed" <?php selected( $deposit_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'vandel-booking' ); ?></option>
                    </select>
                </p>

                <p>
                    <label for="deposit_amount"><strong><?php esc_html_e( 'Deposit Amount', 'vandel-booking' ); ?></strong></label><br/>
                    <input type="number" step="0.01" min="0" id="deposit_amount" name="vandel_service_meta[deposit_amount]" value="<?php echo $deposit_amount; ?>" />
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save the meta box data when the post is saved.
     *
     * @param int $post_id
     */
    public function save_service_meta( $post_id ) {
        // Check nonce
        if ( ! isset( $_POST['vandel_service_nonce'] ) ||
             ! wp_verify_nonce( $_POST['vandel_service_nonce'], 'vandel_service_save_meta' ) ) {
            return;
        }

        // Check auto-save
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check user permissions
        if ( isset( $_POST['post_type'] ) && 'vandel_service' === $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        // Save fields
        if ( isset( $_POST['vandel_service_meta'] ) && is_array( $_POST['vandel_service_meta'] ) ) {
            $meta = array_map( 'sanitize_text_field', $_POST['vandel_service_meta'] );

            // Ensure deposit_enabled is '1' if checked, or '0' if not
            $meta['deposit_enabled'] = isset( $meta['deposit_enabled'] ) && '1' === $meta['deposit_enabled']
                ? '1'
                : '0';

            update_post_meta( $post_id, '_vandel_service_meta', $meta );
        }
    }
}
