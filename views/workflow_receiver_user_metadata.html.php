<?php include __DIR__ . '/workflow_receiver_checkbox_field.html.php'; ?>
<div class="<?php echo esc_attr($context['id']); ?>_filters publishpress-filter-checkbox-list" <?php if (!$context['meta']['selected']) : ?>style="display:none;"<?php endif; ?>>
    <label for="publishpress-user-meta-key"><?php esc_html_e('Post metadata key containing user IDs', 'publishpress'); ?></label>
    <input type="text" id="publishpress-user-meta-key" name="publishpress_notif[user_metadata_key]" value="<?php echo esc_attr($context['meta_key']); ?>" />
    <p class="description"><?php esc_html_e('For example: _pp_editorial_meta_user_manufacturer. The selected users receive notifications through their preferred channel.', 'publishpress'); ?></p>
</div>
