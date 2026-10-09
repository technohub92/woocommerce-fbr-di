<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Checkout_Fields {

    public function __construct() {
        if (get_option('fbr_di_enable_checkout_ntn', 'yes') === 'yes') {
            add_filter('woocommerce_billing_fields', array($this, 'add_billing_fields'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_fields'));
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'));
        }
    }

    public function add_billing_fields($fields) {
        $fields['billing_fbr_ntn_cnic'] = array(
            'label'       => __('Buyer NTN / CNIC (Optional for B2B/FBR)', 'fbr-digital-invoicing'),
            'placeholder' => __('e.g. 4210112345671 or 3520248', 'fbr-digital-invoicing'),
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 25,
        );

        $fields['billing_fbr_business_name'] = array(
            'label'       => __('Buyer Registered Business Title', 'fbr-digital-invoicing'),
            'placeholder' => __('Optional legal company name for tax invoice', 'fbr-digital-invoicing'),
            'required'    => false,
            'class'       => array('form-row-wide'),
            'clear'       => true,
            'priority'    => 26,
        );

        return $fields;
    }

    public function save_checkout_fields($order_id) {
        if (!empty($_POST['billing_fbr_ntn_cnic'])) {
            $cleaned = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['billing_fbr_ntn_cnic']));
            update_post_meta($order_id, '_billing_fbr_ntn_cnic', $cleaned);
        }

        if (!empty($_POST['billing_fbr_business_name'])) {
            update_post_meta($order_id, '_billing_fbr_business_name', sanitize_text_field($_POST['billing_fbr_business_name']));
        }
    }

    public function display_admin_order_meta($order) {
        $ntn_cnic = $order->get_meta('_billing_fbr_ntn_cnic');
        $business_name = $order->get_meta('_billing_fbr_business_name');

        if (!empty($ntn_cnic) || !empty($business_name)) {
            echo '<div class="fbr-buyer-info" style="margin-top: 10px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">';
            echo '<h4 style="margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; color: #475569;">' . __('FBR Tax Information', 'fbr-digital-invoicing') . '</h4>';
            if (!empty($ntn_cnic)) {
                echo '<p style="margin: 2px 0; font-size: 12px;"><strong>' . __('NTN/CNIC:', 'fbr-digital-invoicing') . '</strong> ' . esc_html($ntn_cnic) . '</p>';
            }
            if (!empty($business_name)) {
                echo '<p style="margin: 2px 0; font-size: 12px;"><strong>' . __('Legal Title:', 'fbr-digital-invoicing') . '</strong> ' . esc_html($business_name) . '</p>';
            }
            echo '</div>';
        }
    }
}

new FBR_DI_Checkout_Fields();
