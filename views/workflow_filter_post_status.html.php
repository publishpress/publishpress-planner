<label for="<?php echo esc_attr($context['id']); ?>_from"><?php echo esc_html($context['labels']['from']); ?></label>
<select multiple="multiple" class="<?php echo isset($context['list_class']) ? esc_attr($context['list_class']) : ''; ?>" name="<?php echo esc_attr($context['name']); ?>[from][]" id="<?php echo esc_attr($context['id']); ?>_from">
    <?php foreach ($context['options_from'] as $option) : ?>
        <option
                value="<?php echo esc_attr($option['value']); ?>"
                <?php selected( $option['selected'], true ); ?>><?php echo esc_html($option['label']); ?></option>
    <?php endforeach; ?>
</select>

<label for="<?php echo esc_attr($context['id']); ?>_to"><?php echo esc_html($context['labels']['to']); ?></label>
<select multiple="multiple" class="<?php echo isset($context['list_class']) ? esc_attr($context['list_class']) : ''; ?>" name="<?php echo esc_attr($context['name']); ?>[to][]" id="<?php echo esc_attr($context['id']); ?>_to">
    <?php foreach ($context['options_to'] as $option) : ?>
        <option
                value="<?php echo esc_attr($option['value']); ?>"
            <?php selected( $option['selected'], true ); ?>><?php echo esc_html($option['label']); ?></option>
    <?php endforeach; ?>
</select>
