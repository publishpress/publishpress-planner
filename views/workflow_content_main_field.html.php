<div>
    <div>
        <label for="<?php echo esc_attr($context['input_id']); ?>subject"><?php echo esc_html($context['labels']['subject']); ?> <span class="psppno-required-marker"><?php esc_html_e('(required)', 'publishpress'); ?></span></label>
        <input
                type="text"
                name="<?php echo esc_attr($context['input_name']); ?>[subject]"
                id="<?php echo esc_attr($context['input_id']); ?>subject"
                aria-required="true"
                value="<?php echo esc_attr($context['subject']); ?>"/>
    </div>

    <div>
        <label for="<?php echo esc_attr($context['input_id']); ?>"><?php echo esc_html($context['labels']['body']); ?> <span class="psppno-required-marker"><?php esc_html_e('(required)', 'publishpress'); ?></span></label>
        <?php wp_editor($context['body'], $context['input_id'], ['textarea_name' => $context['input_name'] . '[body]']); ?>
    </div>
</div>
