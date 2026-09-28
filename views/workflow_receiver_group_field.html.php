<?php if (!empty($context['id'])):?>
    <?php include __DIR__ . '/workflow_receiver_checkbox_field.html.php'; ?>

    <div
            id="publishpress_notif_group_list_filter"
            <?php if (! $context['meta']['selected']) : ?>style="display:none;"<?php endif; ?>
            class="<?php echo esc_attr($context['id']); ?>_filters publishpress-filter-checkbox-list">

        <?php if (! empty($context['groups'])) : ?>
            <label class="screen-reader-text" for="<?php echo esc_attr($context['input_id']); ?>"><?php printf(esc_html__('Select %s', 'publishpress'), esc_html($context['label'])); ?></label>
            <select multiple="multiple" class="<?php echo esc_attr($context['list_class']); ?>"
                    name="<?php echo esc_attr($context['input_name']); ?>" id="<?php echo esc_attr($context['input_id']); ?>">
                <?php foreach ($context['groups'] as $group => $group_object) : ?>
                    <?php $selected = isset($group_object->selected) ? $group_object->selected : false; ?>
                    <option value="<?php echo esc_attr($group); ?>" <?php selected($selected, true); ?>><?php echo $group_object->name; ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
<?php endif;?>
