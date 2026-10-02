<?php if (! empty($context['options'])) : ?>
    <?php if (! empty($context['labels']['label'])) : ?>
        <label class="screen-reader-text" for="<?php echo esc_attr($context['id']); ?>"><?php echo esc_html($context['labels']['label']); ?></label>
    <?php endif; ?>
    <select multiple="multiple" class="<?php echo isset($context['list_class']) ? esc_attr($context['list_class']) : ''; ?>" name="<?php echo esc_attr($context['name']); ?>[]" id="<?php echo esc_attr($context['id']); ?>">
        <?php foreach ($context['options'] as $option) : ?>
            <option value="<?php echo esc_attr($option['value']); ?>"
                    <?php selected( $option['selected'], true ); ?>><?php echo esc_html($option['label']); ?></option>
        <?php endforeach; ?>
    </select>
<?php endif; ?>
