<?php

if (!defined('ABSPATH')) {
    exit;
}

$status = $order->get_meta('_fbr_status');
$invoice_number = $order->get_meta('_fbr_invoice_number');
$qr_svg = $order->get_meta('_fbr_qr_svg');
$qr_url = $order->get_meta('_fbr_qr_url');
$issued_at = $order->get_meta('_fbr_issued_at');
$error_log = $order->get_meta('_fbr_error_log');

?>
<div class="fbr-order-metabox-content" style="font-size: 12px;">
    <?php if ($status === 'CERTIFIED'): ?>
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                <span style="display: inline-block; width: 8px; height: 8px; background: #16a34a; border-radius: 50%;"></span>
                <strong style="color: #166534; font-size: 13px;"><?php _e('FBR Certified & Verified', 'fbr-digital-invoicing'); ?></strong>
            </div>
            
            <div style="margin-bottom: 6px;">
                <span style="color: #64748b; font-size: 11px; display: block;"><?php _e('FBR Invoice Number:', 'fbr-digital-invoicing'); ?></span>
                <code style="font-weight: 700; color: #0f172a; font-size: 11px;"><?php echo esc_html($invoice_number); ?></code>
            </div>

            <?php if (!empty($issued_at)): ?>
                <div style="font-size: 10px; color: #64748b; margin-bottom: 8px;">
                    <?php echo sprintf(__('Certified on: %s', 'fbr-digital-invoicing'), esc_html($issued_at)); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($qr_svg)): ?>
                <div style="text-align: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-top: 8px;">
                    <div style="width: 100px; height: 100px; margin: 0 auto; display: flex; align-items: center; justify-content: center;">
                        <?php echo $qr_svg; ?>
                    </div>
                    <span style="font-size: 9px; color: #64748b; display: block; margin-top: 4px;">FBR Tax Asaan QR</span>
                </div>
            <?php endif; ?>
        </div>
    <?php elseif ($status === 'FAILED'): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
            <strong style="color: #991b1b; display: block; margin-bottom: 4px;">✗ <?php _e('FBR Submission Failed', 'fbr-digital-invoicing'); ?></strong>
            <p style="color: #7f1d1d; font-size: 11px; margin: 0; word-break: break-word;"><?php echo esc_html($error_log); ?></p>
        </div>
    <?php else: ?>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 12px; text-align: center;">
            <span style="color: #64748b; font-size: 12px;"><?php _e('Not submitted to FBR yet.', 'fbr-digital-invoicing'); ?></span>
        </div>
    <?php endif; ?>

    <div style="text-align: right;">
        <button type="button" id="fbr-btn-manual-submit" class="button button-primary" style="width: 100%; justify-content: center;">
            <?php echo ($status === 'CERTIFIED') ? __('⚡ Re-Verify / Resubmit to FBR', 'fbr-digital-invoicing') : __('⚡ Submit Invoice to FBR', 'fbr-digital-invoicing'); ?>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#fbr-btn-manual-submit').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.prop('disabled', true).text('Submitting to FBR Gateway...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'fbr_di_manual_submit_order',
                order_id: <?php echo $order->get_id(); ?>,
                nonce: '<?php echo wp_create_nonce('fbr_di_order_nonce'); ?>'
            },
            success: function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    $btn.prop('disabled', false).text('⚡ Submit Invoice to FBR');
                    alert('Submission Error:\n' + (res.data ? res.data.message : 'Unknown error'));
                }
            },
            error: function() {
                $btn.prop('disabled', false).text('⚡ Submit Invoice to FBR');
                alert('Could not communicate with WordPress server.');
            }
        });
    });
});
</script>
