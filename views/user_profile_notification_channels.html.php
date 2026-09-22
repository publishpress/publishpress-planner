<h2><?php echo esc_html__('Editorial Notifications', 'publishpress'); ?></h2>

<table class="form-table psppno_workflow_user_fields">
    <tr>
        <th>
            <?php echo esc_html__(
                    'Choose the channels where each workflow will send notifications to:',
                    'publishpress');
            ?>
        </th>
        <td>
            <table class="psppno_workflows">
                <tr>
                    <th class="psppno_workflow_column_header psppno_workflows"><?php echo esc_html__('Workflows', 'publishpress'); ?></th>
                    <th class="psppno_workflow_column_header psppno_channels"><?php echo esc_html__('Channels', 'publishpress'); ?></th>
                    <th class="psppno_workflow_column_header psppno_options"></th>
                </tr>
                <?php 
                foreach ($context['channels'] as $channel) {                    
                    if (isset($channel->options) && ! empty($channel->options)) {
                        $any_options = true;
                    }
                }
                
                foreach ($context['workflows'] as $workflow) : ?>
                    <tr class="psppno_workflow_<?php echo esc_attr($workflow->ID); ?>">
                        <td class="psppno_workflow_title"><?php echo esc_html($workflow->post_title); ?></td>
                        <td class="psppno_workflow_channel">
                            <?php foreach ($context['channels'] as $channel) : ?>
                                <?php if (is_array($channel)) :
                                    $channel = (object)$channel;
                                    endif; ?>
                                <div class="psppno_workflow_channel_field">
                                    <input
                                            type="radio"
                                            id="psppno_workflow_channel_<?php echo esc_attr($workflow->ID); ?>_<?php echo esc_attr($channel->name); ?>"
                                            name="psppno_workflow_channel[<?php echo esc_attr($workflow->ID); ?>]"
                                            value="<?php echo esc_attr($channel->name); ?>"
                                            data-workflow-id="<?php echo esc_attr($workflow->ID); ?>"
                                            <?php checked( $channel->name, $context['workflow_channels'][$workflow->ID]); ?> />

                                    <label for="psppno_workflow_channel_<?php echo esc_attr($workflow->ID); ?>_<?php echo esc_attr($channel->name); ?>">
                                        <img src="<?php echo esc_url($channel->icon); ?>" alt=""/>
                                        <span><?php echo esc_html($channel->label); ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <?php if (!empty($any_options)): ?>
                        <td class="psppno_workflow_channel_options">
                            <?php foreach ($context['channels'] as $channel) : ?>
                                <?php if (isset($channel->options) && ! empty($channel->options)) : ?>
                                    <div class="psppno_workflow_<?php echo esc_attr($channel->name); ?>_options">
                                        <?php foreach ($channel->options as $channelOption) : ?>
                                            <div
                                                    class="psppno_workflow_channel_option_<?php echo esc_attr($channelOption->name); ?>"
                                                    data-channel="<?php echo esc_attr($channel->name); ?>">
                                                <?php
                                                $optionHtml = $channelOption->html;
                                                $optionHtml = str_replace('%workflow_id%', $workflow->ID, $optionHtml);
                                                $optionHtml = str_replace('%value%', $context['channels_options'][$workflow->ID][$channel->name][$channelOption->name], $optionHtml);
                                                ?>
                                                <?php echo $optionHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </td>
                        <?php endif;?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </td>
    </tr>
</table>
