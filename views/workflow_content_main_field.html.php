<div>
    <div>
        <label for="<?php echo esc_attr($context['input_id']); ?>subject"><?php echo esc_html($context['labels']['subject']); ?></label>
        <input
                type="text"
                name="<?php echo esc_attr($context['input_name']); ?>[subject]"
                id="<?php echo esc_attr($context['input_id']); ?>subject"
                value="<?php echo esc_attr($context['subject']); ?>"/>
    </div>

    <div>
        <label for="<?php echo esc_attr($context['input_id']); ?>"><?php echo esc_html($context['labels']['body']); ?></label>
        <?php wp_editor($context['body'], $context['input_id'], ['textarea_name' => $context['input_name'] . '[body]']); ?>
    </div>
</div>
