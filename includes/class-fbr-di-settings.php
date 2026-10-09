<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Settings {

    public function __construct() {
        add_filter('woocommerce_settings_tabs_array', array($this, 'add_settings_tab'), 50);
        add_action('woocommerce_settings_tabs_fbr_digital_invoicing', array($this, 'settings_tab_content'));
        add_action('woocommerce_update_options_fbr_digital_invoicing', array($this, 'update_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_fbr_di_test_connection', array($this, 'ajax_test_connection'));
    }

    public function add_settings_tab($tabs) {
        $tabs['fbr_digital_invoicing'] = __('FBR Digital Invoicing', 'fbr-digital-invoicing');
        return $tabs;
    }

    public function enqueue_admin_scripts($hook) {
        if (isset($_GET['tab']) && $_GET['tab'] === 'fbr_digital_invoicing') {
            wp_enqueue_script('jquery');
        }
    }

    public function settings_tab_content() {
        $api_client = new FBR_DI_API_Client();
        $is_configured = $api_client->is_configured();
        $portal_url = rtrim(str_replace('/api', '', get_option('fbr_di_base_url', 'https://invoicehub.pk')), '/');

        ?>
        <div class="fbr-di-settings-wrap" style="max-width: 900px; margin-top: 15px;">
            
            <!-- Onboarding Banner for New Users -->
            <div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 20px 24px; border-radius: 12px; margin-bottom: 25px; box-shadow: 0 4px 14px rgba(0,0,0,0.15);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <span style="background: rgba(255,255,255,0.15); font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.5px;">PRAL Digital Invoicing v1.12</span>
                        <h2 style="color: #fff; margin: 8px 0 4px 0; font-size: 18px; font-weight: 800;">Official FBR Digital Fiscal Invoicing Gateway</h2>
                        <p style="color: #c7d2fe; margin: 0; font-size: 13px;">Automatically issue certified Pakistani digital invoices with QR codes on WooCommerce checkout.</p>
                    </div>
                    <div>
                        <a href="<?php echo esc_url($portal_url . '/register?source=woocommerce'); ?>" target="_blank" style="background: #4f46e5; color: #fff; font-weight: 700; font-size: 13px; text-decoration: none; padding: 10px 18px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.4);">
                            <span><?php echo $is_configured ? __('Manage Account & API Keys →', 'fbr-digital-invoicing') : __('Get API Key / Create Account →', 'fbr-digital-invoicing'); ?></span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Connection Status Bar -->
            <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span id="fbr-status-indicator" style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $is_configured ? '#10b981' : '#f59e0b'; ?>; display: inline-block;"></span>
                    <span style="font-size: 13px; font-weight: 600; color: #1e293b;">
                        <?php echo $is_configured ? __('Status: Configured (Ready to test)', 'fbr-digital-invoicing') : __('Status: API Key Missing (Action Required)', 'fbr-digital-invoicing'); ?>
                    </span>
                    <span id="fbr-auto-synced-client" style="font-size: 12px; color: #64748b; font-weight: 500;">
                        <?php 
                        $seller_name = get_option('fbr_di_seller_name');
                        if (!empty($seller_name)) {
                            echo '— Linked to: <strong>' . esc_html($seller_name) . '</strong> (NTN: ' . esc_html(get_option('fbr_di_seller_ntn')) . ')';
                        }
                        ?>
                    </span>
                </div>
                <div>
                    <button type="button" id="fbr-btn-test-connection" class="button button-secondary" style="font-weight: 600;">
                        🔄 <?php _e('Test Connection & Auto-Sync Profile', 'fbr-digital-invoicing'); ?>
                    </button>
                </div>
            </div>

            <?php woocommerce_admin_fields($this->get_settings()); ?>

        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#fbr-btn-test-connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                $btn.prop('disabled', true).text('Testing & Syncing...');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'fbr_di_test_connection',
                        nonce: '<?php echo wp_create_nonce('fbr_di_ajax_nonce'); ?>'
                    },
                    success: function(res) {
                        $btn.prop('disabled', false).text('🔄 Test Connection & Auto-Sync Profile');
                        if (res.success) {
                            $('#fbr-status-indicator').css('background', '#10b981');
                            $('#fbr-auto-synced-client').html('— Linked to: <strong>' + res.data.client_name + '</strong> (NTN: ' + res.data.seller_ntn + ')');
                            
                            // Auto-populate fields if empty
                            if ($('#fbr_di_seller_ntn').val() === '') $('#fbr_di_seller_ntn').val(res.data.seller_ntn);
                            if ($('#fbr_di_seller_name').val() === '') $('#fbr_di_seller_name').val(res.data.client_name);
                            if ($('#fbr_di_seller_province').val() === '') $('#fbr_di_seller_province').val(res.data.province);
                            if ($('#fbr_di_seller_address').val() === '') $('#fbr_di_seller_address').val(res.data.address);

                            alert('✓ Success! Gateway connection active.\n\nLinked Business: ' + res.data.client_name + '\nSeller NTN: ' + res.data.seller_ntn);
                        } else {
                            $('#fbr-status-indicator').css('background', '#ef4444');
                            alert('✗ Connection Failed: ' + (res.data ? res.data.message : 'Unknown error'));
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('🔄 Test Connection & Auto-Sync Profile');
                        alert('✗ Could not reach WordPress AJAX endpoint.');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function get_settings() {
        return array(
            'section_gateway_title' => array(
                'name' => __('1. Gateway Credentials & Environment', 'fbr-digital-invoicing'),
                'type' => 'title',
                'desc' => __('Enter your API Key obtained from your FBR Digital Invoicing merchant portal.', 'fbr-digital-invoicing'),
                'id'   => 'fbr_di_section_gateway'
            ),
            'base_url' => array(
                'name'     => __('Gateway Base API URL', 'fbr-digital-invoicing'),
                'type'     => 'text',
                'desc'     => __('Default: https://invoicehub.pk/api', 'fbr-digital-invoicing'),
                'id'       => 'fbr_di_base_url',
                'default'  => 'https://invoicehub.pk/api',
                'css'      => 'min-width: 400px;',
            ),
            'api_key' => array(
                'name'     => __('Client API Key (Bearer Token)', 'fbr-digital-invoicing'),
                'type'     => 'password',
                'desc'     => __('Your secret API key from the portal dashboard.', 'fbr-digital-invoicing'),
                'id'       => 'fbr_di_api_key',
                'css'      => 'min-width: 400px;',
            ),
            'environment' => array(
                'name'     => __('Environment Mode', 'fbr-digital-invoicing'),
                'type'     => 'select',
                'options'  => array(
                    'sandbox'    => __('PRAL Sandbox (Testing / Verification)', 'fbr-digital-invoicing'),
                    'production' => __('FBR Live Production Gateway', 'fbr-digital-invoicing'),
                ),
                'default'  => 'sandbox',
                'id'       => 'fbr_di_environment',
            ),
            'section_gateway_end' => array(
                'type' => 'sectionend',
                'id'   => 'fbr_di_section_gateway_end'
            ),

            'section_seller_title' => array(
                'name' => __('2. Seller Business Legal Information', 'fbr-digital-invoicing'),
                'type' => 'title',
                'desc' => __('These details will be populated automatically when clicking "Test Connection & Auto-Sync".', 'fbr-digital-invoicing'),
                'id'   => 'fbr_di_section_seller'
            ),
            'seller_ntn' => array(
                'name'     => __('Seller NTN / CNIC', 'fbr-digital-invoicing'),
                'type'     => 'text',
                'id'       => 'fbr_di_seller_ntn',
                'css'      => 'min-width: 300px;',
            ),
            'seller_name' => array(
                'name'     => __('Legal Business Name', 'fbr-digital-invoicing'),
                'type'     => 'text',
                'id'       => 'fbr_di_seller_name',
                'css'      => 'min-width: 400px;',
            ),
            'seller_province' => array(
                'name'     => __('Registered Province', 'fbr-digital-invoicing'),
                'type'     => 'select',
                'options'  => array(
                    'Punjab'                        => 'Punjab',
                    'Sindh'                         => 'Sindh',
                    'Khyber Pakhtunkhwa'            => 'Khyber Pakhtunkhwa',
                    'Balochistan'                   => 'Balochistan',
                    'Islamabad Capital Territory'   => 'Islamabad Capital Territory',
                    'Azad Jammu & Kashmir'          => 'Azad Jammu & Kashmir',
                    'Gilgit-Baltistan'              => 'Gilgit-Baltistan',
                ),
                'default'  => 'Punjab',
                'id'       => 'fbr_di_seller_province',
            ),
            'seller_address' => array(
                'name'     => __('Registered Business Address', 'fbr-digital-invoicing'),
                'type'     => 'textarea',
                'id'       => 'fbr_di_seller_address',
                'css'      => 'min-width: 400px; height: 60px;',
            ),
            'section_seller_end' => array(
                'type' => 'sectionend',
                'id'   => 'fbr_di_section_seller_end'
            ),

            'section_automation_title' => array(
                'name' => __('3. Order Automation & Checkout Rules', 'fbr-digital-invoicing'),
                'type' => 'title',
                'desc' => __('Configure automatic FBR invoice generation triggers and checkout fields.', 'fbr-digital-invoicing'),
                'id'   => 'fbr_di_section_automation'
            ),
            'trigger_status' => array(
                'name'     => __('Issue FBR Invoice On Status', 'fbr-digital-invoicing'),
                'type'     => 'select',
                'options'  => array(
                    'completed'  => __('Order Completed (Recommended for E-Commerce)', 'fbr-digital-invoicing'),
                    'processing' => __('Order Processing / Paid', 'fbr-digital-invoicing'),
                    'manual'     => __('Manual Submission Only (via Order Edit Screen)', 'fbr-digital-invoicing'),
                ),
                'default'  => 'completed',
                'id'       => 'fbr_di_trigger_status',
            ),
            'default_hs_code' => array(
                'name'     => __('Default HS Code (Fallback)', 'fbr-digital-invoicing'),
                'type'     => 'text',
                'desc'     => __('Fallback 8-digit HS Code if not defined on individual WooCommerce products. E.g. 8471.3010', 'fbr-digital-invoicing'),
                'default'  => '8471.3010',
                'id'       => 'fbr_di_default_hs_code',
            ),
            'default_tax_rate' => array(
                'name'     => __('Standard Sales Tax Rate (%)', 'fbr-digital-invoicing'),
                'type'     => 'number',
                'custom_attributes' => array('step' => '0.01', 'min' => '0', 'max' => '100'),
                'default'  => '18.00',
                'id'       => 'fbr_di_default_tax_rate',
            ),
            'enable_checkout_ntn' => array(
                'name'     => __('Add Buyer NTN/CNIC to Checkout', 'fbr-digital-invoicing'),
                'type'     => 'checkbox',
                'desc'     => __('Inject optional NTN/CNIC and Business Name fields on WooCommerce checkout page.', 'fbr-digital-invoicing'),
                'default'  => 'yes',
                'id'       => 'fbr_di_enable_checkout_ntn',
            ),
            'enable_email_qr' => array(
                'name'     => __('Embed FBR QR Code in Order Emails', 'fbr-digital-invoicing'),
                'type'     => 'checkbox',
                'desc'     => __('Appends official FBR verification QR code and FBR Invoice Number to customer receipt emails.', 'fbr-digital-invoicing'),
                'default'  => 'yes',
                'id'       => 'fbr_di_enable_email_qr',
            ),
            'section_automation_end' => array(
                'type' => 'sectionend',
                'id'   => 'fbr_di_section_automation_end'
            ),
        );
    }

    public function update_settings() {
        woocommerce_update_options($this->get_settings());
    }

    public function ajax_test_connection() {
        check_ajax_referer('fbr_di_ajax_nonce', 'nonce');

        $api_client = new FBR_DI_API_Client();
        $ping = $api_client->ping();

        if (!$ping['success']) {
            wp_send_json_error(array(
                'message' => $ping['message'] ?? 'Gateway ping failed. Check API Key and URL.',
            ));
        }

        $profile = $api_client->get_client_profile();
        $data = $profile['data'] ?? array();
        $client = $data['client'] ?? array();

        $client_name = $client['business_name'] ?? ($client['name'] ?? 'Active Tenant');
        $seller_ntn = $client['fbr_seller_ntn'] ?? ($client['ntn'] ?? '');
        $province = $client['fbr_province'] ?? 'Punjab';
        $address = $client['address'] ?? '';

        if (!empty($client_name)) update_option('fbr_di_seller_name', $client_name);
        if (!empty($seller_ntn)) update_option('fbr_di_seller_ntn', $seller_ntn);
        if (!empty($province)) update_option('fbr_di_seller_province', $province);
        if (!empty($address)) update_option('fbr_di_seller_address', $address);

        wp_send_json_success(array(
            'client_name' => $client_name,
            'seller_ntn'  => $seller_ntn,
            'province'    => $province,
            'address'     => $address,
        ));
    }
}

new FBR_DI_Settings();
