<?php include __DIR__ . '/workflow_receiver_checkbox_field.html.php'; ?>
<div class="<?php echo esc_attr($context['id']); ?>_filters publishpress-filter-checkbox-list" <?php if (!$context['meta']['selected']) : ?>style="display:none;"<?php endif; ?>>
    <label for="publishpress-custom-emails"><?php esc_html_e('Email addresses or post metadata shortcodes', 'publishpress'); ?></label>
    <textarea id="publishpress-custom-emails" name="publishpress_notif[custom_emails]"><?php echo esc_textarea($context['addresses']); ?></textarea>
    <p class="description"><?php esc_html_e('Separate addresses with commas or new lines. Example: editor@example.com, [psppno_post meta:customer_email]', 'publishpress'); ?></p>
</div>
