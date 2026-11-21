<label for="publishpress-specific-posts"><?php esc_html_e('Post or page IDs', 'publishpress'); ?></label>
<input type="text" id="publishpress-specific-posts" name="<?php echo esc_attr($context['name']); ?>" value="<?php echo esc_attr($context['value']); ?>" placeholder="12, 34, 56" />
<p class="description"><?php esc_html_e('Enter the IDs of the posts or pages that can trigger this workflow, separated by commas. Other content will not trigger it.', 'publishpress'); ?></p>
