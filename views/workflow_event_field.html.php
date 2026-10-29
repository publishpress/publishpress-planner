<div>
    <input
            type="checkbox"
            name="<?php echo esc_attr($context['name']); ?>"
            id="<?php echo esc_attr($context['id']); ?>"
            value="<?php echo esc_attr($context['value']); ?>"
            <?php checked($context['meta']['selected'], 1); ?> />

    <label for="<?php echo esc_attr($context['id']); ?>" class="noselect"><?php echo esc_html($context['label']); ?></label>

    <?php if (! empty($context['event_filters'])) : ?>
        <ul class="<?php echo esc_attr($context['id']); ?>_filters publishpress-filter-checkbox-list"
            <?php if (! $context['meta']['selected']) : ?>style="display:none;"<?php endif; ?>>
            <?php foreach ($context['event_filters'] as $event_filter) : ?>
                <li>
                    <?php echo $event_filter->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The filter renders escaped form markup. ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
