<label for="publishpress-metadata-trigger-key"><?php esc_html_e('Metadata key', 'publishpress'); ?></label>
<input type="text" id="publishpress-metadata-trigger-key" name="<?php echo esc_attr($context['name']); ?>" value="<?php echo esc_attr($context['value']); ?>" />
<p class="description"><?php esc_html_e('Enter the exact field key, for example _pp_editorial_meta_user_assignment. Only changes to this field trigger the workflow.', 'publishpress'); ?></p>
