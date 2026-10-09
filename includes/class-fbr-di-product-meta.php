<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Product_Meta {

    public function __construct() {
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
    }

    public function add_product_fields() {
        global $post;

        echo '<div class="options_group fbr_product_options" style="border-top: 1px solid #eee; margin-top: 10px; padding-top: 10px;">';
        echo '<p style="margin: 0 0 10px 12px; font-weight: 700; font-size: 12px; color: #4338ca;">' . __('FBR Digital Invoicing Classification', 'fbr-digital-invoicing') . '</p>';

        woocommerce_wp_text_input(array(
            'id'          => '_fbr_hs_code',
            'label'       => __('FBR 8-Digit HS Code', 'fbr-digital-invoicing'),
            'placeholder' => get_option('fbr_di_default_hs_code', '8471.3010'),
            'desc_tip'    => true,
            'description' => __('8-digit Pakistan Customs HS Code under PRAL DI v1.12 specification.', 'fbr-digital-invoicing'),
        ));

        woocommerce_wp_select(array(
            'id'          => '_fbr_uom',
            'label'       => __('Unit of Measure (UOM)', 'fbr-digital-invoicing'),
            'options'     => array(
                'Numbers' => 'Numbers (PCS / Units)',
                'KG'      => 'Kilograms (KG)',
                'Meters'  => 'Meters (M)',
                'Liters'  => 'Liters (L)',
                'Packs'   => 'Packs / Cartons',
                'Dozen'   => 'Dozen',
                'Square Meters' => 'Square Meters',
            ),
            'default'     => 'Numbers',
        ));

        woocommerce_wp_text_input(array(
            'id'          => '_fbr_tax_rate_override',
            'label'       => __('Specific Tax Rate Override (%)', 'fbr-digital-invoicing'),
            'placeholder' => get_option('fbr_di_default_tax_rate', '18.00'),
            'type'        => 'number',
            'custom_attributes' => array('step' => '0.01', 'min' => '0', 'max' => '100'),
            'desc_tip'    => true,
            'description' => __('Leave blank to inherit global tax rate.', 'fbr-digital-invoicing'),
        ));

        echo '</div>';
    }

    public function save_product_fields($post_id) {
        if (isset($_POST['_fbr_hs_code'])) {
            update_post_meta($post_id, '_fbr_hs_code', sanitize_text_field($_POST['_fbr_hs_code']));
        }
        if (isset($_POST['_fbr_uom'])) {
            update_post_meta($post_id, '_fbr_uom', sanitize_text_field($_POST['_fbr_uom']));
        }
        if (isset($_POST['_fbr_tax_rate_override'])) {
            update_post_meta($post_id, '_fbr_tax_rate_override', sanitize_text_field($_POST['_fbr_tax_rate_override']));
        }
    }
}

new FBR_DI_Product_Meta();
