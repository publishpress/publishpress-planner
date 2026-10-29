<?php include __DIR__ . '/workflow_receiver_checkbox_field.html.php'; ?>
<div class="<?php echo esc_attr($context['id']); ?>_filters publishpress-filter-checkbox-list" <?php if (!$context['meta']['selected']) : ?>style="display:none;"<?php endif; ?>>
    <label for="publishpress-email-meta-key"><?php esc_html_e('Post metadata key containing recipient email addresses', 'publishpress'); ?></label>
    <input type="text" id="publishpress-email-meta-key" name="publishpress_notif[email_metadata_key]" value="<?php echo esc_attr($context['meta_key']); ?>" />
    <p class="description"><?php esc_html_e('For example: customer_email. Multiple addresses may be separated by commas.', 'publishpress'); ?></p>
</div>
