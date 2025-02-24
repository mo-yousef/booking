<?php
namespace VandelBooking;

/**
 * Plugin Name: Vandel Booking
 * Plugin URI: https://example.com/vandel-booking
 * Description: A comprehensive booking plugin supporting location-based bookings and variable pricing.
 * Version: 1.0.1
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: vandel-booking
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VANDEL_BOOKING_VERSION', '1.0.1');
define('VANDEL_BOOKING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VANDEL_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'VandelBooking\\';
    $base_dir = VANDEL_BOOKING_PLUGIN_DIR . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
class VandelBooking {
    /**
     * @var VandelBooking Single instance of the class
     */
    private static $instance = null;

    /**
     * @var Database Database instance
     */
    public $database;

    /**
     * @var Form Form instance
     */
    public $form;

    /**
     * @var Admin Admin instance
     */
    public $admin;

    /**
     * Returns single instance of the class
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'public_enqueue_scripts'));
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        $this->database = new Database();
        $this->form = new Form();
        
        if (is_admin()) {
            $this->admin = new Admin();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'vandel-booking',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts() {
        $screen = get_current_screen();
        if (strpos($screen->id, 'vb_service') !== false) {
            wp_enqueue_style('vandel-booking-admin', VANDEL_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), VANDEL_BOOKING_VERSION);
            wp_enqueue_script('vandel-booking-admin', VANDEL_BOOKING_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), VANDEL_BOOKING_VERSION, true);
            
            wp_localize_script('vandel-booking-admin', 'vbAdminData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_booking_admin'),
            ));
        }
    }

    /**
     * Enqueue public scripts and styles
     */
    public function public_enqueue_scripts() {
        wp_enqueue_style('vandel-booking-public', VANDEL_BOOKING_PLUGIN_URL . 'assets/css/public.css', array(), VANDEL_BOOKING_VERSION);
        wp_enqueue_script('vandel-booking-public', VANDEL_BOOKING_PLUGIN_URL . 'assets/js/public.js', array('jquery'), VANDEL_BOOKING_VERSION, true);
        
        wp_localize_script('vandel-booking-public', 'vbBookingData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_booking_public'),
            'currency' => get_option('vandel_booking_currency', 'USD'),
        ));
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'currency' => 'USD',
            'tax_rate' => 0,
            'stripe_test_mode' => true,
            'email_notifications' => true,
            'admin_email' => get_option('admin_email'),
        );

        foreach ($defaults as $key => $value) {
            if (get_option('vandel_booking_' . $key) === false) {
                update_option('vandel_booking_' . $key, $value);
            }
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Register post types
        $this->register_post_types();
        
        // Register taxonomies
        $this->register_taxonomies();
    }

    /**
     * Register custom post types
     */
    private function register_post_types() {
        register_post_type('vb_service', array(
            'labels' => array(
                'name' => __('Services', 'vandel-booking'),
                'singular_name' => __('Service', 'vandel-booking'),
                'add_new' => __('Add New', 'vandel-booking'),
                'add_new_item' => __('Add New Service', 'vandel-booking'),
                'edit_item' => __('Edit Service', 'vandel-booking'),
                'new_item' => __('New Service', 'vandel-booking'),
                'view_item' => __('View Service', 'vandel-booking'),
                'search_items' => __('Search Services', 'vandel-booking'),
                'not_found' => __('No services found', 'vandel-booking'),
                'not_found_in_trash' => __('No services found in trash', 'vandel-booking'),
            ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'supports' => array('title', 'editor', 'thumbnail'),
            'has_archive' => true,
            'rewrite' => array('slug' => 'services'),
            'menu_icon' => 'dashicons-calendar',
            'menu_position' => 30,
        ));
    }

    /**
     * Register taxonomies
     */
    private function register_taxonomies() {
        register_taxonomy('service_category', 'vb_service', array(
            'labels' => array(
                'name' => __('Service Categories', 'vandel-booking'),
                'singular_name' => __('Service Category', 'vandel-booking'),
                'search_items' => __('Search Categories', 'vandel-booking'),
                'all_items' => __('All Categories', 'vandel-booking'),
                'parent_item' => __('Parent Category', 'vandel-booking'),
                'parent_item_colon' => __('Parent Category:', 'vandel-booking'),
                'edit_item' => __('Edit Category', 'vandel-booking'),
                'update_item' => __('Update Category', 'vandel-booking'),
                'add_new_item' => __('Add New Category', 'vandel-booking'),
                'new_item_name' => __('New Category Name', 'vandel-booking'),
                'menu_name' => __('Categories', 'vandel-booking'),
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'service-category'),
        ));
    }
}

// Initialize the plugin
function vandel_booking() {
    return VandelBooking::instance();
}

// Start the plugin
vandel_booking();

