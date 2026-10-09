<?php

if (!defined('ABSPATH')) {
    exit;
}

class FBR_DI_Receipt_Renderer {

    public function __construct() {
        if (get_option('fbr_di_enable_email_qr', 'yes') === 'yes') {
            // Embed into Customer Order Completed Email
            add_action('woocommerce_email_after_order_table', array($this, 'render_email_receipt_block'), 20, 4);

            // Embed into Customer View Order screen in My Account & Thank You page
            add_action('woocommerce_order_details_after_order_table', array($this, 'render_frontend_order_block'), 20, 1);
        }
    }

    public function render_email_receipt_block($order, $sent_to_admin, $plain_text, $email) {
        if ($plain_text) {
            $invoice_no = $order->get_meta('_fbr_invoice_number');
            if (!empty($invoice_no)) {
                echo "\n----------------------------------------\n";
                echo "FBR PRAL Digital Invoicing Certified (SRO 350/2024)\n";
                echo "FBR Invoice No: " . $invoice_no . "\n";
                echo "----------------------------------------\n\n";
            }
            return;
        }

        $this->render_html_block($order);
    }

    public function render_frontend_order_block($order) {
        $this->render_html_block($order);
    }

    private function render_html_block($order) {
        $status = $order->get_meta('_fbr_status');
        if ($status !== 'CERTIFIED') {
            return;
        }

        $invoice_number = $order->get_meta('_fbr_invoice_number');
        $qr_svg = $order->get_meta('_fbr_qr_svg');
        $qr_url = $order->get_meta('_fbr_qr_url');

        ?>
        <div class="fbr-receipt-qr-card" style="margin: 25px 0; padding: 16px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div style="flex: 1; min-width: 200px;">
                    <div style="display: inline-flex; align-items: center; gap: 6px; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 6px; margin-bottom: 8px;">
                        <span style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; display: inline-block;"></span>
                        <span style="font-size: 11px; font-weight: 700; color: #047857; text-transform: uppercase; letter-spacing: 0.5px;">Official FBR Digital Invoicing</span>
                    </div>
                    <h3 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 800; color: #0f172a;">Fiscal Verification &amp; Tax Compliance</h3>
                    <p style="margin: 0 0 8px 0; font-size: 12px; color: #64748b;">This invoice is digitally certified and registered under FBR PRAL SRO 350/2024.</p>
                    <div style="font-size: 11px; color: #334155;">
                        <span>FBR Invoice No.: </span>
                        <strong style="font-family: monospace; font-size: 12px; color: #0f172a;"><?php echo esc_html($invoice_number); ?></strong>
                    </div>
                </div>
                <?php if (!empty($qr_svg)): ?>
                    <div style="text-align: center; background: #fff; padding: 6px; border: 1px solid #cbd5e1; border-radius: 8px; shrink: 0;">
                        <div style="width: 90px; height: 90px; display: flex; align-items: center; justify-content: center;">
                            <?php echo $qr_svg; ?>
                        </div>
                        <span style="font-size: 8px; font-weight: 600; color: #64748b; display: block; margin-top: 2px;">Scan via Tax Asaan</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

new FBR_DI_Receipt_Renderer();
