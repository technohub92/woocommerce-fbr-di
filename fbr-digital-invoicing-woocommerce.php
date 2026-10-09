<?php
/**
 * Plugin Name: FBR Digital Invoicing for WooCommerce
 * Plugin URI: https://invoicehub.pk/docs/api
 * Description: Official Pakistani FBR PRAL Digital Invoicing (SRO 350/2024 & SRO 1832/2024) integration for WooCommerce. Automates digital fiscal invoices, verification QR codes, and buyer NTN/CNIC compliance.
 * Version: 1.0.0
 * Author: InvoiceHub / TechnoHub
 * Author URI: https://invoicehub.pk
 * Text Domain: fbr-digital-invoicing
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('FBR_DI_VERSION', '1.0.0');
define('FBR_DI_PLUGIN_FILE', __FILE__);
define('FBR_DI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FBR_DI_PLUGIN_URL', plugin_dir_url(__FILE__));

class FBR_Digital_Invoicing_Plugin {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Verify WooCommerce is active
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public function init_plugin() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-api-client.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-settings.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-checkout-fields.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-product-meta.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-order-handler.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-receipt-renderer.php';
        require_once FBR_DI_PLUGIN_DIR . 'includes/class-fbr-di-updater.php';
    }

    private function init_hooks() {
        // Declare HPOS (High-Performance Order Storage) compatibility
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });

        // Add plugin action links (Settings)
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links'));
    }

    public function action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=fbr_digital_invoicing') . '">' . __('Settings', 'fbr-digital-invoicing') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p><strong>' . __('FBR Digital Invoicing requires WooCommerce to be installed and active.', 'fbr-digital-invoicing') . '</strong></p></div>';
    }
}

// Instantiate plugin
FBR_Digital_Invoicing_Plugin::instance();
