<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Order_Handler {

    public function __construct() {
        $trigger_status = get_option('fbr_di_trigger_status', 'completed');

        if ($trigger_status !== 'manual') {
            add_action('woocommerce_order_status_' . $trigger_status, array($this, 'auto_issue_invoice'), 10, 1);
        }

        // Admin Metabox for Orders
        add_action('add_meta_boxes', array($this, 'add_order_metabox'));
        add_action('wp_ajax_fbr_di_manual_submit_order', array($this, 'ajax_manual_submit'));
    }

    public function auto_issue_invoice($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Avoid duplicate submissions
        if ($order->get_meta('_fbr_status') === 'CERTIFIED') {
            return;
        }

        $this->process_fbr_submission($order);
    }

    public function process_fbr_submission($order) {
        $api_client = new FBR_DI_API_Client();
        if (!$api_client->is_configured()) {
            $order->add_order_note(__('FBR Digital Invoicing skipped: Gateway API Key not configured.', 'fbr-digital-invoicing'));
            return array('success' => false, 'message' => 'API Key Missing');
        }

        $payload = $this->build_pral_payload($order);
        $response = $api_client->submit_invoice($payload);

        if ($response['success']) {
            $data = $response['data'];
            $fbr_invoice_number = $data['fbr_invoice_number'] ?? ($data['invoice_number'] ?? ($data['invoiceNumber'] ?? ''));
            $qr_svg = $data['qr_svg'] ?? ($data['data']['qr_svg'] ?? '');
            $qr_url = $data['qr_url'] ?? ($data['data']['qr_url'] ?? '');

            $order->update_meta_data('_fbr_status', 'CERTIFIED');
            $order->update_meta_data('_fbr_invoice_number', $fbr_invoice_number);
            $order->update_meta_data('_fbr_qr_svg', $qr_svg);
            $order->update_meta_data('_fbr_qr_url', $qr_url);
            $order->update_meta_data('_fbr_issued_at', current_time('mysql'));
            $order->save();

            $order->add_order_note(sprintf(
                __('✓ FBR Digital Invoice Certified: <strong>%s</strong> (Mode: %s)', 'fbr-digital-invoicing'),
                esc_html($fbr_invoice_number),
                $api_client->is_sandbox() ? 'Sandbox' : 'Production'
            ));

            return array('success' => true, 'invoice_number' => $fbr_invoice_number);
        } else {
            $error_msg = $response['message'] ?? 'Unknown Error';
            if (!empty($response['errors'])) {
                $error_msg .= ' | Details: ' . json_encode($response['errors']);
            }

            $order->update_meta_data('_fbr_status', 'FAILED');
            $order->update_meta_data('_fbr_error_log', $error_msg);
            $order->save();

            $order->add_order_note(__('✗ FBR Invoice Submission Failed: ', 'fbr-digital-invoicing') . $error_msg);
            return array('success' => false, 'message' => $error_msg);
        }
    }

    public function build_pral_payload($order) {
        $buyer_ntn = $order->get_meta('_billing_fbr_ntn_cnic');
        $buyer_title = $order->get_meta('_billing_fbr_business_name');
        
        if (empty($buyer_title)) {
            $buyer_title = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if (empty($buyer_title)) $buyer_title = 'Consumer';
        }

        $province = $this->map_pakistan_province($order->get_billing_state());
        $address = trim($order->get_billing_address_1() . ', ' . $order->get_billing_city());
        if (empty($address)) $address = 'Pakistan';

        $is_registered = !empty($buyer_ntn) && strlen($buyer_ntn) <= 8;

        $items = array();
        $default_hs = get_option('fbr_di_default_hs_code', '8471.3010');
        $default_tax_rate = (float) get_option('fbr_di_default_tax_rate', 18.00);

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $hs_code = $product ? $product->get_meta('_fbr_hs_code') : '';
            if (empty($hs_code)) $hs_code = $default_hs;

            $uom = $product ? $product->get_meta('_fbr_uom') : 'Numbers';
            if (empty($uom)) $uom = 'Numbers';

            $tax_override = $product ? $product->get_meta('_fbr_tax_rate_override') : '';
            $rate = ($tax_override !== '') ? (float) $tax_override : $default_tax_rate;

            $qty = (float) $item->get_quantity();
            $line_subtotal = (float) $item->get_subtotal();
            $line_tax = (float) $item->get_total_tax();

            if ($line_tax <= 0 && $rate > 0) {
                $line_tax = round(($line_subtotal * $rate) / 100, 2);
            }

            $items[] = array(
                'hsCode'                => (string) $hs_code,
                'productDescription'    => (string) $item->get_name(),
                'rate'                  => (string) (rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%'),
                'uoM'                   => (string) $uom,
                'quantity'              => $qty,
                'totalValues'           => round($line_subtotal + $line_tax, 2),
                'valueSalesExcludingST' => round($line_subtotal, 2),
                'fixedNotifiedValueOrRetailPrice' => 0.0,
                'salesTaxApplicable'    => round($line_tax, 2),
                'salesTaxWithheldAtSource' => 0.0,
                'extraTax'              => 0.0,
                'furtherTax'            => 0.0,
                'fedPayable'            => 0.0,
                'discount'              => 0.0,
                'saleType'              => 'Goods',
            );
        }

        // Shipping line if exists
        if ((float) $order->get_shipping_total() > 0) {
            $ship_total = (float) $order->get_shipping_total();
            $ship_tax = (float) $order->get_shipping_tax();
            if ($ship_tax <= 0 && $default_tax_rate > 0) {
                $ship_tax = round(($ship_total * $default_tax_rate) / 100, 2);
            }

            $items[] = array(
                'hsCode'                => '9801.0000',
                'productDescription'    => 'Courier & Shipping Service',
                'rate'                  => (string) (rtrim(rtrim(number_format($default_tax_rate, 2, '.', ''), '0'), '.') . '%'),
                'uoM'                   => 'Numbers',
                'quantity'              => 1.0,
                'totalValues'           => round($ship_total + $ship_tax, 2),
                'valueSalesExcludingST' => round($ship_total, 2),
                'fixedNotifiedValueOrRetailPrice' => 0.0,
                'salesTaxApplicable'    => round($ship_tax, 2),
                'salesTaxWithheldAtSource' => 0.0,
                'extraTax'              => 0.0,
                'furtherTax'            => 0.0,
                'fedPayable'            => 0.0,
                'discount'              => 0.0,
                'saleType'              => 'Services',
            );
        }

        $scenario = $is_registered ? 'SN001' : 'SN008';

        return array(
            'invoiceType'           => 'Sale Invoice',
            'invoiceDate'           => date('Y-m-d', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time()),
            'invoiceRefNo'          => 'WC-' . $order->get_order_number(),
            'scenarioId'            => $scenario,
            'sellerNTNCNIC'         => (string) get_option('fbr_di_seller_ntn', ''),
            'sellerBusinessName'    => (string) get_option('fbr_di_seller_name', ''),
            'sellerProvince'        => (string) get_option('fbr_di_seller_province', 'Punjab'),
            'sellerAddress'         => (string) get_option('fbr_di_seller_address', ''),
            'buyerNTNCNIC'          => (string) $buyer_ntn,
            'buyerBusinessName'     => (string) $buyer_title,
            'buyerProvince'         => (string) $province,
            'buyerAddress'          => (string) $address,
            'buyerRegistrationType' => $is_registered ? 'Registered' : 'Unregistered',
            'items'                 => $items,
        );
    }

    private function map_pakistan_province($state) {
        $state_upper = strtoupper(trim($state));
        $map = array(
            'PB' => 'Punjab',
            'SD' => 'Sindh',
            'KP' => 'Khyber Pakhtunkhwa',
            'NWFP' => 'Khyber Pakhtunkhwa',
            'BA' => 'Balochistan',
            'IS' => 'Islamabad Capital Territory',
            'ICT' => 'Islamabad Capital Territory',
            'AJK' => 'Azad Jammu & Kashmir',
            'GB' => 'Gilgit-Baltistan',
        );

        return $map[$state_upper] ?? get_option('fbr_di_seller_province', 'Punjab');
    }

    public function add_order_metabox() {
        $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'fbr_di_order_metabox',
            __('FBR Digital Invoicing Status (PRAL DI v1.12)', 'fbr-digital-invoicing'),
            array($this, 'render_order_metabox'),
            $screen,
            'side',
            'high'
        );
    }

    public function render_order_metabox($post_or_order) {
        $order = ($post_or_order instanceof \WC_Order) ? $post_or_order : wc_get_order($post_or_order->ID);
        if (!$order) return;

        include FBR_DI_PLUGIN_DIR . 'templates/admin-order-metabox.php';
    }

    public function ajax_manual_submit() {
        check_ajax_referer('fbr_di_order_nonce', 'nonce');
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }

        $result = $this->process_fbr_submission($order);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

new FBR_DI_Order_Handler();
