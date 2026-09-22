<table class="psppno_workflow_user_fields psppno_workflows">
    <tr>
        <th class="psppno_workflow_column_header psppno_workflows"><?php echo esc_html($context['labels']['workflows']); ?></th>
        <th class="psppno_workflow_column_header psppno_channels"><?php echo esc_html($context['labels']['channels']); ?></th>
        <th class="psppno_workflow_column_header psppno_options"></th>
    </tr>

    <?php foreach ($context['workflows'] as $workflow) : ?>
        <tr class="psppno_workflow_<?php echo esc_attr($workflow->ID); ?>">
            <td class="psppno_workflow_title"><?php echo esc_html($workflow->post_title); ?></td>
            <td class="psppno_workflow_channel">
                <?php foreach ($context['channels'] as $channel) : ?>
                <div class="psppno_workflow_channel_field">
                    <input
                        type="radio"
                        id="psppno_workflow_channel_<?php echo esc_attr($workflow->ID); ?>_<?php echo esc_attr($channel->name); ?>"
                        name="publishpress_improved_notifications_options[default_channels][<?php echo esc_attr($workflow->ID); ?>]"
                        value="<?php echo esc_attr($channel->name); ?>"
                        data-workflow-id="<?php echo esc_attr($workflow->ID); ?>"
                        <?php checked( $channel->name, $context['selected_channels'][$workflow->ID]); ?> />

                    <label for="psppno_workflow_channel_<?php echo esc_attr($workflow->ID); ?>_<?php echo esc_attr($channel->name); ?>">
                        <img src="<?php echo esc_url($channel->icon); ?>" alt=""/>
                        <span><?php echo esc_html($channel->name); ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </td>

        </tr>
    <?php endforeach; ?>
</table>
