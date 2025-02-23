<?php
namespace VandelBooking;

/**
 * Database class for handling all database operations
 */
class Database {
    /**
     * @var wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Create plugin database tables
     */
    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $this->wpdb->get_charset_collate();

        // Bookings table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}vb_bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            booking_date datetime NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            deposit_amount decimal(10,2) DEFAULT NULL,
            tax_amount decimal(10,2) DEFAULT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            zip_code varchar(10) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY customer_id (customer_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql);

        // Service pricing by location table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}vb_service_pricing (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            service_id bigint(20) unsigned NOT NULL,
            zip_code varchar(10) NOT NULL,
            price decimal(10,2) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY service_zip (service_id, zip_code)
        ) $charset_collate;";
        dbDelta($sql);

        // Coupons table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}vb_coupons (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            discount_type enum('percentage', 'fixed') NOT NULL,
            discount_amount decimal(10,2) NOT NULL,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            usage_limit int unsigned DEFAULT NULL,
            usage_count int unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        dbDelta($sql);

        // Coupon-Service relationship table
        $sql = "CREATE TABLE IF NOT EXISTS {$this->wpdb->prefix}vb_coupon_services (
            coupon_id bigint(20) unsigned NOT NULL,
            service_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (coupon_id, service_id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    /**
     * Create a new booking
     */
    public function create_booking($data) {
        $defaults = array(
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );

        $data = wp_parse_args($data, $defaults);

        return $this->wpdb->insert(
            $this->wpdb->prefix . 'vb_bookings',
            $data,
            array(
                '%d', // service_id
                '%d', // customer_id
                '%s', // booking_date
                '%s', // status
                '%f', // total_amount
                '%f', // deposit_amount
                '%f', // tax_amount
                '%s', // currency
                '%s', // zip_code
                '%s', // created_at
                '%s', // updated_at
            )
        );
    }

    /**
     * Update a booking
     */
    public function update_booking($booking_id, $data) {
        $data['updated_at'] = current_time('mysql');

        return $this->wpdb->update(
            $this->wpdb->prefix . 'vb_bookings',
            $data,
            array('id' => $booking_id),
            array(
                '%d', // service_id
                '%d', // customer_id
                '%s', // booking_date
                '%s', // status
                '%f', // total_amount
                '%f', // deposit_amount
                '%f', // tax_amount
                '%s', // currency
                '%s', // zip_code
                '%s', // updated_at
            ),
            array('%d')
        );
    }

    /**
     * Get a booking by ID
     */
    public function get_booking($booking_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}vb_bookings WHERE id = %d",
                $booking_id
            )
        );
    }

    /**
     * Delete a booking
     */
    public function delete_booking($booking_id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'vb_bookings',
            array('id' => $booking_id),
            array('%d')
        );
    }

    /**
     * Get service price for a specific ZIP code
     */
    public function get_service_price($service_id, $zip_code) {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT price FROM {$this->wpdb->prefix}vb_service_pricing 
                WHERE service_id = %d AND zip_code = %s",
                $service_id,
                $zip_code
            )
        );
    }

    /**
     * Set service price for a specific ZIP code
     */
    public function set_service_price($service_id, $zip_code, $price) {
        return $this->wpdb->replace(
            $this->wpdb->prefix . 'vb_service_pricing',
            array(
                'service_id' => $service_id,
                'zip_code' => $zip_code,
                'price' => $price,
                'created_at' => current_time('mysql'),
            ),
            array(
                '%d',
                '%s',
                '%f',
                '%s',
            )
        );
    }
}