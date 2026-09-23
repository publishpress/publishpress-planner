<?php
/**
 * Calendar Utilities class
 */

if (! class_exists('PP_Calendar_Utilities')) {
    class PP_Calendar_Utilities
    {

        /**
         * Default number of weeks to display in the calendar
         */
        const DEFAULT_NUM_WEEKS = 5;

        public static function content_calendar_filter_options($args)
        {
            $args = apply_filters('publishpress_calendar_filter_options_args', $args);

            $select_id                  = $args['select_id'];
            $select_name                = $args['select_name'];
            $filters                    = $args['user_filters'];
            $terms_options              = $args['terms_options'];
            $content_calendar_datas     = $args['content_calendar_datas'];
            $post_statuses              = $args['post_statuses'];
            $post_types                 = $args['post_types'];
            $form_filter_list           = $args['form_filter_list'];
            $all_filters                = $args['all_filters'];
            $operator_labels            = $args['operator_labels'];

            $revision_statuses          = (!empty($args['revision_statuses'])) ? $args['revision_statuses'] : [];

            if (array_key_exists($select_id, $terms_options)) {
                $select_id = 'metadata_key';
            }
    
            if (array_key_exists($select_id, $content_calendar_datas['taxonomies']) && taxonomy_exists($select_id)) {
                $select_id = 'taxonomy';
            }
    
            $filter_label   = '';
            $selected_value = '';
    
            ob_start();
    
            switch ($select_id) {
                case 'post_status':
                    $filter_label   = esc_html__('Post Status', 'publishpress');
                    ?>
                    <select id="post_status" name="post_status"><!-- Status selectors -->
                        <option value=""><?php
                            _e('All statuses', 'publishpress'); ?></option>
                        
                        <?php if (!empty($revision_statuses)) {
                            $hide_all_label = __('(Hide all)', 'publishpress');

                            if ('_' == $filters['post_status']) {
                                $selected_value = $hide_all_label;
                            }

                            echo "<option value='_' " . selected(
                                        '_',
                                        $filters['post_status']
                                    ) . ">" . $hide_all_label . "</option>";
                        }
                        
                        foreach ($post_statuses as $post_status) {
                            if ($post_status->slug == $filters['post_status']) {
                                $selected_value = $post_status->label;
                            }
                            echo "<option value='" . esc_attr($post_status->slug) . "' " . selected(
                                    $post_status->slug,
                                    $filters['post_status']
                                ) . ">" . esc_html($post_status->label) . "</option>";
                        }
                        ?>
                    </select>
                    <?php
                    break;
    
                case 'taxonomy':
                    $taxonomySlug = isset($filters[$select_name]) ? sanitize_key($filters[$select_name]) : '';
                    $taxonomy = get_taxonomy($select_name);
                    $filter_label   = esc_html($taxonomy->label);
                    ?>
                    <select 
                        class="filter_taxonomy" 
                        id="<?php echo esc_attr('filter_taxonomy_' . $select_name); ?>" 
                        data-taxonomy="<?php echo esc_attr($select_name); ?>" 
                        name="<?php echo esc_attr($select_name); ?>"
                        data-placeholder="<?php printf(esc_attr__('All %s', 'publishpress'), esc_html($taxonomy->label)); ?>"
                        >
                        <option value="">
                            <?php echo sprintf(esc_html__('All %s', 'publishpress'), esc_html($taxonomy->label)); ?>
                        </option>
                        <?php
                        if ($taxonomySlug) {
                            $term = get_term_by('slug', $taxonomySlug, $select_name);
                            if (is_object($term) && isset($term->name)) {
        
                                $selected_value = $term->name;
        
                                echo "<option value='" . esc_attr($taxonomySlug) . "' selected='selected'>" . esc_html(
                                        $term->name
                                    ) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <?php
                    break;
    
                case 'author':
                    $authorId = isset($filters['author']) ? (int)$filters['author'] : 0;
                    $selectedOptionAll = empty($authorId) ? 'selected="selected"' : '';
                    $filter_label   = esc_html__('Author', 'publishpress');
                    ?>
                    <select id="filter_author" name="author" data-placeholder="<?php esc_attr_e('All authors', 'publishpress'); ?>">
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <option value="" <?php echo $selectedOptionAll; ?>>
                            <?php esc_html_e('All authors', 'publishpress'); ?>
                        </option>
                        <?php
                        if (! empty($authorId)) {
                            $author = get_user_by('id', $authorId);
                            $option = '';
    
                            if (! empty($author)) {
                                $selected_value = $author->display_name;
                                $option = '<option value="' . esc_attr($authorId) . '" selected="selected">' . esc_html(
                                        $author->display_name
                                    ) . '</option>';
                            }
    
                            $option = apply_filters('publishpress_author_filter_selected_option', $option, $authorId);
    
                            echo $option; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </select>
                    <?php
                    break;
    
                case 'post_type':
                case 'cpt':
                    $selectedPostType = isset($filters['cpt']) ? sanitize_text_field($filters['cpt']) : '';
                    $filter_label   = esc_html__('Post Type', 'publishpress');
                    ?>
                    <select id="post_type" name="cpt">
                        <option value=""><?php
                            _e('All post types', 'publishpress'); ?></option>
                        <?php
                        foreach ($post_types as $postType) {
                            $postTypeObject = get_post_type_object($postType);
                            if ($selectedPostType == $postType) {
                                $selected_value = $postTypeObject->label;
                            }
                            echo '<option value="' . esc_attr($postType) . '" ' . selected(
                                    $selectedPostType,
                                    $postType
                                ) . '>' . esc_html($postTypeObject->label) . '</option>';
                        }
                        ?>
                    </select>
                    <?php
                    break;
    
                    case 'search_box':
                        ?>
                        <input type="hidden" id="<?php echo esc_attr($select_id . '-search-input'); ?>" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search box', 'publishpress'); ?>" />
                        <?php
                        break;
    
                case 'metadata_key':
                    $metadata_value = isset($filters[$select_name]) ? sanitize_text_field($filters[$select_name]) : '';
                    $metadata_term  = $terms_options[$select_name];
    
                    $metadata_type  = $metadata_term['type'];
                    $selected_value = $metadata_value;
                    $filter_label   = $metadata_term['name'];
                    
                    ?>
                    <div class="metadata-item-filter">
                        <div class="filter-title">
                            <?php echo esc_html($metadata_term['name']); ?>
                        </div>
                        <div class="filter-content">
                        <?php
                        if (in_array($metadata_type, ['paragraph', 'location', 'text', 'number'])) { 
                            ?>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr('metadata_key_' . $select_name); ?>" 
                                name="<?php echo esc_attr($select_name); ?>" 
                                value="<?php echo esc_attr($metadata_value); ?>" 
                                placeholder=""
                                />
                            <div class="filter-apply">
                                <?php submit_button(esc_html__('Apply', 'publishpress'), 'button-primary', '', false, ['id' => 'filter-submit']); ?>
                            </div>
                        <?php
                        } elseif (in_array($metadata_type, ['select'])) {
                            ?>
                            <?php if (!empty($metadata_term['select_options']) && is_array($metadata_term['select_options']) && !empty($metadata_term['select_options']['values'])) : 
                                $option_values     = $metadata_term['select_options']['values'];
                                $option_labels     = $metadata_term['select_options']['labels'];
                                ?>
                            <select 
                                id="<?php echo esc_attr('metadata_key_' . $select_name); ?>" 
                                name="<?php echo esc_attr($select_name); ?>">
                                <option value="">
                                    <?php esc_html_e('Select option...', 'publishpress'); ?>
                                </option>
                                <?php
                                foreach ($option_values as $index => $value) {
                                    echo '<option value="' . esc_attr($value) . '" '. selected($metadata_value, $value, false) .'>' . esc_html($option_labels[$index]) . '</option>';
                                }
                                ?>
                            </select>
                            <?php else : ?>
                            <input 
                                type="text" 
                                id="<?php echo esc_attr('metadata_key_' . $select_name); ?>" 
                                name="<?php echo esc_attr($select_name); ?>" 
                                value="<?php echo esc_attr($metadata_value); ?>" 
                                placeholder=""
                                />
                            <?php endif; ?>
                        <?php
                        } elseif ($metadata_type === 'date') { ?>
                            <?php
                            $metadata_start_value           = isset($filters[$select_name . '_start']) ? sanitize_text_field($filters[$select_name . '_start']) : '';
                            $metadata_end_value             = isset($filters[$select_name . '_end']) ? sanitize_text_field($filters[$select_name . '_end']) : '';
    
                            $metadata_start_value_hidden    = isset($filters[$select_name . '_start_hidden']) ? sanitize_text_field($filters[$select_name . '_start_hidden']) : '';
                            $metadata_end_value_hidden      = isset($filters[$select_name . '_end_hidden']) ? sanitize_text_field($filters[$select_name . '_end_hidden']) : '';
    
                            $metadata_start_name            = $select_name . '_start';
                            $metadata_end_name              = $select_name . '_end';
    
                            $selected_value = '';
                            if (!empty($metadata_start_value)) {
                                $selected_value .= $metadata_start_value;
                            }
                            if (!empty($metadata_start_value) && !empty($metadata_end_value)) {
                                $selected_value .= ' ' . esc_html__('to', 'publishpress') . ' ';
                            }
                            if (!empty($metadata_end_value)) {
                                $selected_value .= $metadata_end_value;
                            }
                            ?>
                            <?php 
                            printf(
                                '<input
                                    type="text"
                                    id="%s"
                                    name="%1$s"
                                    value="%2$s"
                                    class="date-time-pick"
                                    data-alt-field="%1$s_hidden"
                                    data-alt-format="%3$s"
                                    placeholder="%4$s"
                                    autocomplete="off"
                                />',
                                esc_attr($metadata_start_name),
                                esc_attr($metadata_start_value),
                                esc_attr(pp_convert_date_format_to_jqueryui_datepicker('Y-m-d')),
                                ''
                            );
                            printf(
                                '<input
                                    type="hidden"
                                    name="%s_hidden"
                                    value="%s"
                                />',
                                esc_attr($metadata_start_name),
                                esc_attr($metadata_start_value_hidden)
                            ); 
                            ?>
                            <div class="input-divider"><?php echo esc_html__('to', 'publishpress'); ?></div>
                            <?php 
                            printf(
                                '<input
                                    type="text"
                                    id="%s"
                                    name="%1$s"
                                    value="%2$s"
                                    class="date-time-pick"
                                    data-alt-field="%1$s_hidden"
                                    data-alt-format="%3$s"
                                    placeholder="%4$s"
                                    autocomplete="off"
                                />',
                                esc_attr($metadata_end_name),
                                esc_attr($metadata_end_value),
                                esc_attr(pp_convert_date_format_to_jqueryui_datepicker('Y-m-d')),
                                ''
                            );
                            printf(
                                '<input
                                    type="hidden"
                                    name="%s_hidden"
                                    value="%s"
                                />',
                                esc_attr($metadata_end_name),
                                esc_attr($metadata_end_value_hidden)
                            ); 
                            ?>
                            <div class="filter-apply">
                                <?php submit_button(esc_html__('Apply', 'publishpress'), 'button-primary', '', false, ['id' => 'filter-submit']); ?>
                            </div>
                        <?php
                        } elseif ($metadata_type === 'user') { 
                            if (!empty($metadata_value)) {
                                $user_info = get_user_by('id', $metadata_value);
                                if (! empty($user_info)) {
                                    $selected_value = $user_info->display_name;
                                }
                            }
                            $user_dropdown_args = [
                                'show_option_all' => $metadata_term['name'],
                                'name' => $select_name,
                                'selected' => $metadata_value,
                                'class' => 'pp-custom-select2'
                            ];
                            $user_dropdown_args = apply_filters('pp_editorial_metadata_user_dropdown_args', $user_dropdown_args);
                                wp_dropdown_users($user_dropdown_args);
                        } elseif ($metadata_type === 'checkbox') { 
                            if ($metadata_value == '1') {
                                $selected_value = esc_html__('Checked', 'publishpress');
                            } else {
                                $selected_value = '';
                            }
                            ?>
                            <input 
                                type="hidden" 
                                name="<?php echo esc_attr($select_name); ?>" 
                                value="0"
                                />
                            <input 
                                type="checkbox" 
                                id="<?php echo esc_attr('metadata_key_' . $select_name); ?>" 
                                name="<?php echo esc_attr($select_name); ?>" 
                                value="1"
                                <?php checked($metadata_value, 1); ?>
                                />
                            <div class="filter-apply">
                                <?php submit_button(esc_html__('Apply', 'publishpress'), 'button-primary', '', false, ['id' => 'filter-submit']); ?>
                            </div>
                        <?php
                        }
                    echo '</div></div>';
                    break;
    
                default:
                    if ($filter_vars = apply_filters('publishpress_calendar_custom_filter', false, $select_id, $args)) {
                        $filter_vars = (array) $filter_vars;

                        $filter_label = (isset($filter_vars['filter_label'])) ? $filter_vars['filter_label'] : '';
                        $selected_value = (isset($filter_vars['selected_value'])) ? $filter_vars['selected_value'] : '';

                    } elseif (array_key_exists($select_name, $form_filter_list)) {
                        $selected_value_meta = isset($filters[$select_name]) ? sanitize_text_field($filters[$select_name]) : '';
                        $filter_label   = $all_filters[$select_name];
                        $selected_value = $selected_value_meta;
    
                        if (strpos($select_name, "ppch_co_checklist_") === 0) {
                            ?>
                            <select id="filter_<?php echo esc_attr($select_name); ?>" name="<?php echo esc_attr($select_name); ?>">
                                <option value=""><?php
                                    _e('All status', 'publishpress'); ?></option>
                                <?php
                                $all_options = [
                                    'passed' => __('Passed', 'publishpress'),
                                    'failed' => __('Failed', 'publishpress')
                                ];
                                foreach ($all_options as $option_key => $option_label) {
                                    if ($selected_value_meta == $option_key) {
                                        $selected_value = $option_label;
                                    }
                                    echo '<option value="' . esc_attr($option_key) . '" ' . selected(
                                            $selected_value_meta,
                                            $option_key
                                        ) . '>' . esc_html($option_label) . '</option>';
                                }
                                ?>
                            </select>
                            <?php
                        } else {
                            
                            $operator_value = isset($filters[$select_name . '_operator']) ? sanitize_text_field($filters[$select_name . '_operator']) : '';
    
                            if (empty($operator_value)) {
                                $operator_value = 'equals';
                            }
    
                            if (in_array($select_name, ['ppch_co_yoast_seo__yoast_wpseo_linkdex', 'ppch_co_yoast_seo__yoast_wpseo_content_score'])) {
                                $input_type = 'number';
                            } else {
                                $input_type = 'text';
                            }
    
                            ?>
                            <div class="metadata-item-filter custom-filter">
                                <div class="filter-title">
                                    <?php echo esc_html($filter_label); ?>
                                </div>
                                <div class="filter-content">
                                <select class="non-trigger-select" id="filter_<?php echo esc_attr($select_name); ?>_operator" name="<?php echo esc_attr($select_name); ?>_operator">
                                <?php
                                foreach ($operator_labels as $option_key => $option_label) {
                                    if (
                                        ($operator_value == $option_key && !empty($selected_value))
                                        || ($operator_value == $option_key && $selected_value == '0')
                                        || ($operator_value == $option_key && 'not_exists' === $option_key)
                                    ) {
                                        $selected_value = $option_label . $selected_value;
                                    }
                                    echo '<option value="' . esc_attr($option_key) . '" ' . selected(
                                            $operator_value,
                                            $option_key
                                        ) . '>' . esc_html($option_label) . '</option>';
                                }
                                ?>
                                </select>
                                <input 
                                type="<?php echo esc_attr($input_type); ?>" 
                                id="<?php echo esc_attr('custom_metadata_key_' . $select_name); ?>" 
                                name="<?php echo esc_attr($select_name); ?>" 
                                value="<?php echo esc_attr($selected_value_meta); ?>" 
                                placeholder=""
                                />
                                <div class="filter-apply">
                                    <?php submit_button(esc_html__('Apply', 'publishpress'), 'button-primary', '', false, ['id' => 'filter-submit']); ?>
                                </div>
    
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        do_action('PP_Content_Calendar_filter_display', $select_id, $select_name, $filters);
                    }
                    break;
            }
            
            return ['selected_value' => $selected_value, 'filter_label' => $filter_label, 'html' => ob_get_clean()];
        }

        /**
         * Return calendar filters
         * @return string
         */
        public static function get_calendar_filters($args) {
            ob_start();

            $user_filters       = $args['user_filters'];
            $calendar_filters   = $args['calendar_filters'];
            ?>
            <div class="pp-content-calendar-manage">
                <div class="left-items">
                        <?php
                            $modal_id = 0;
                            $me_mode = (int) $user_filters['me_mode'];
                            $active_me_mode = !empty($me_mode) ? 'active-filter' : '';
                        ?>
                    <div class="item action me-mode-action <?php echo esc_attr($active_me_mode); ?>"
                        data-label="<?php esc_html_e('Me Mode', 'publishpress'); ?>">
                        <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Me Mode', 'publishpress'); ?>
                    </div>
                    <?php do_action('pp_content_calendar_filter_after_me_mode', $user_filters); ?>
                    <?php $modal_id++; ?>
                    <div class="item action co-filter" data-target="#content_calendar_modal_<?php echo esc_attr($modal_id); ?>">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e('Customize Filters', 'publishpress'); ?>
                    </div>
                    <div id="content_calendar_modal_<?php echo esc_attr($modal_id); ?>" class="customize-customize-item-modal content-calendar-modal" style="display: none;">
                        <div class="content-calendar-modal-content">
                            <span class="close">&times;</span>
                            <?php echo self::content_calendar_customize_filter_form($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </div>
                <div class="right-items">
                    <div class="item">
                        <div class="search-bar">
                            <input type="search" id="co-searchbox-search-input" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search box', 'publishpress'); ?>" aria-label="<?php esc_attr_e('Search content', 'publishpress'); ?>" />
                            <?php submit_button(esc_html__('Search', 'publishpress'), '', '', false, ['id' => 'co-searchbox-search-submit']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
            <form method="GET" id="pp-content-filters" class="pp-content-filters">
                <input type="hidden" name="page" value="pp-content-calendar"/>
                <input type="hidden" name="me_mode" id="content_calendar_me_mode" value="<?php echo esc_attr($me_mode); ?>" />
                <?php do_action('pp_content_calendar_filter_hidden_fields', $user_filters); ?>
                <div class="pp-content-calendar-filters">
                    <?php
                    $filter_weeks = isset($user_filters['weeks']) ? (int)$user_filters['weeks'] : self::DEFAULT_NUM_WEEKS;
                    $modal_id++;
                    ?>
                    <button data-target="#content_calendar_modal_<?php echo esc_attr($modal_id); ?>" class="co-filter active-filter"
                    data-label="<?php esc_html_e('Period', 'publishpress'); ?>">
                        <?php esc_html_e('Period', 'publishpress'); ?>: <?php echo sprintf(esc_html__('%1s weeks', 'publishpress'), $filter_weeks); ?>
                    </button>
                    <div id="content_calendar_modal_<?php echo esc_attr($modal_id); ?>" class="content-calendar-modal" style="display: none;">
                        <div class="content-calendar-modal-content">
                            <span class="close">&times;</span>
                            <div>
                                <select name="weeks" id="weeks" class="calendar-weeks-filter">
                                    <?php 
                                        for ($i = 1; $i <= 12; $i++) {
                                            $selected = ($i == $filter_weeks) ? ' selected' : '';
                                            $week_text = sprintf(_n('%d week', '%d weeks', $i, 'publishpress'), $i);
                                            echo '<option value="' . $i . '"' . $selected . '>' . $week_text . '</option>';
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php 

                    $calendar_filters = apply_filters('publishpress_calendar_get_filters', $calendar_filters, $args);

                    foreach ($calendar_filters as $select_id => $select_name) {
                        $modal_id++;
                        $args['select_id']      = $select_id;
                        $args['select_name']    = $select_name;
                        $filter_data = self::content_calendar_filter_options($args);
                        $active_class = !empty($filter_data['selected_value']) ? 'active-filter' : '';
                        $button_label = $filter_data['filter_label'];
                        $button_label .= !empty($filter_data['selected_value']) ? ': ' . $filter_data['selected_value'] : '';
                        ?>
                        <?php if (!empty($button_label)) : ?>
                            <button 
                                data-target="#content_calendar_modal_<?php echo esc_attr($modal_id); ?>"
                                data-label="<?php echo esc_attr($filter_data['filter_label']); ?>"
                                class="co-filter <?php echo esc_attr($active_class); ?> <?php echo esc_attr($select_id); ?> me-mode-status-<?php echo esc_attr($me_mode); ?>"
                                <?php if ('revision_status' == $select_id && !empty($user_filters['hide_revision'])) echo ' style="display: none;"';?>><?php echo esc_html($button_label); ?></button>
                            <div id="content_calendar_modal_<?php echo esc_attr($modal_id); ?>" class="content-calendar-modal" style="display: none;">
                                <div class="content-calendar-modal-content">
                                    <span class="close">&times;</span>
                                    <div><?php echo $filter_data['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                </div>
                            </div>
                        <?php elseif (!empty($filter_data['html'])) : ?>
                            <?php echo $filter_data['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php endif; ?>
                        <?php
                    } 
                    ?>
                    <button class="clear-filter">
                        <span class="dashicons dashicons-dismiss"></span> <?php esc_html_e('Reset Filters', 'publishpress'); ?>
                        <input style="display: none;" type="submit" id="post-query-clear" value="<?php echo esc_attr(__('Reset', 'publishpress')); ?>" class="button-secondary button"/>
                    </button>
                </div>
            </form>
                
            <form method="POST" id="pp-content-filters-hidden">
                <input type="hidden" name="co_form_action" value="reset_filter"/>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_calendar_filter_rest_nonce')); ?>"/>
            </form>
            <?php
            return ob_get_clean();
        }

        public static function content_calendar_customize_filter_form($args) {
            
            ob_start();
    
            $content_calendar_datas   = $args['content_calendar_datas'];
            $enabled_filters          = array_keys($content_calendar_datas['content_calendar_filters']);
            $filters                  = $args['form_filters'];
            $meta_keys                = $content_calendar_datas['meta_keys'];
    
            $all_filters              = [];
            ?>
            <form method="POST" class="pp-content-calendar-customize-form filters" id="pp-content-calendar-filter-form" data-form="filters">
                <input type="hidden" name="co_form_action" value="filter_form"/>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_calendar_filter_form_nonce')); ?>"/>
                <div class="co-customize-tabs">
                    <div class="customize-tab enable-tab cc-active-tab" data-tab="enable-content"><?php esc_html_e('Enable Filters', 'publishpress'); ?></div>
                    <div class="customize-tab reorder-tab" data-tab="reorder-content"> <?php esc_html_e('Reorder Filters', 'publishpress'); ?> </div>
                </div>
                <div class="co-cc-content">
                    <div class="customize-content enable-content">
                        <div class="fixed-header">
                            <p class="description"><?php esc_html_e('Enable or Disable Content calendar filter.', 'publishpress'); ?></p>
                        </div>
                        <div class="scrollable-content">
                            <?php 
                            $filter_index = 0;
                            foreach ($filters as $filter_group => $filter_datas) : 
                            $filter_index++;
                            $hidden_style = empty($filter_datas['filters']) ? 'display: none;' : '';
                            ?>
                                <div class="customize-group-title title-index-<?php echo esc_attr($filter_index); ?> <?php echo esc_attr($filter_group); ?>" style="<?php echo esc_attr($hidden_style); ?>">
                                    <div class="title-text"><?php echo esc_html($filter_datas['title']); ?></div>
                                    <?php if ($filter_group === 'custom') : ?>
                                        <div class="title-action new-item">
                                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add New', 'publishpress'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($filter_group === 'custom') : ?>
                                    <div class="entry-item enable-item form-item" style="display: none;">
                                        <div class="new-fields">
                                            <div class="field">
                                                <input class="new-item-title" type="text" placeholder="<?php esc_attr_e('Filter Title', 'publishpress'); ?>" />
                                            </div>
                                            <div class="field">
                                            <select class="new-item-metakey" data-nonce="<?php echo esc_attr(wp_create_nonce('publishpress-content-get-data')); ?>">
                                                <option value=""><?php esc_html_e('Search Metakey', 'publishpress'); ?></option>
                                            </select>
                                            </div>
                                        </div>
                                        <div class="new-submit">
                                            <?php esc_html_e('Add Filter', 'publishpress'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($filter_datas['filters'])) : ?>
                                    <div class="item-group-empty <?php echo esc_attr($filter_group); ?>" style="<?php echo esc_attr($hidden_style); ?>"><?php echo esc_html($filter_datas['message']); ?></div>
                                <?php else : ?>
                                    <?php foreach ($filter_datas['filters'] as $filter_name => $filter_label) : 
                                        $active_class = (in_array($filter_name, $enabled_filters)) ? 'active-item' : '';
                                        $input_name   = (in_array($filter_name, $enabled_filters)) ? 'content_calendar_filters['. $filter_name .']' : '';
    
                                        $all_filters[$filter_name] = [
                                            'filter_label' => $filter_label,
                                            'filter_group' => $filter_group
                                        ];
                                        ?>
                                        <div class="entry-item enable-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($filter_name); ?> <?php echo esc_attr($filter_group); ?>" data-name="<?php echo esc_attr($filter_name); ?>">
                                            <input class="customize-item-input" type="hidden" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($filter_label); ?>" />
                                            <?php if ($filter_group === 'custom') : ?>
                                                <input type="hidden" name="content_calendar_custom_filters[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
                                            <?php endif; ?>
                                            <div class="items-list-item-check checked">
                                                <svg><use xlink:href="<?php echo esc_url(PUBLISHPRESS_URL . 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill'); ?>"></use></svg>
                                            </div>
                                            <div class="items-list-item-check unchecked">
                                                <svg><use xlink:href="<?php echo esc_url(PUBLISHPRESS_URL . 'common/icons/content-icon.svg#svg-sprite-x'); ?>"></use></svg>
                                            </div>
                                            <div class="items-list-item-name">
                                                <div class="items-list-item-name-text"><?php echo esc_html($filter_label); ?> <?php if ($filter_group === 'custom') : ?><span class="customize-item-info">(<?php echo esc_html($filter_name); ?>)</span><?php endif; ?></div>
                                            </div>
                                            <?php if ($filter_group === 'custom') : ?>
                                                <div class="delete-content-calendar-item" data-meta="<?php echo esc_html($filter_name); ?>">
                                                    <svg><use xlink:href="<?php echo esc_url(PUBLISHPRESS_URL . 'common/icons/content-icon.svg#svg-sprite-cu2-menu-trash'); ?>"></use></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="customize-content reorder-content" style="display: none;">
                        <div class="fixed-header">
                            <p class="description"><?php esc_html_e('Drag to change enabled filters order.', 'publishpress'); ?></p>
                        </div>
                        <div class="scrollable-content">
                            <?php 
                            // loop enabled filter first so they can stay as ordered
                            $added_filters = [];
                            foreach ($enabled_filters as $enabled_filter) {
                                $filter_name    = $enabled_filter;
                                if (!isset($all_filters[$filter_name])) {
                                    continue;
                                }
                                $filter_details = $all_filters[$filter_name];
                                $filter_label = $filter_details['filter_label'];
                                $filter_group = $filter_details['filter_group'];
                                $active_class = (in_array($filter_name, $enabled_filters)) ? 'active-item' : '';
                                $input_name   = (in_array($filter_name, $enabled_filters)) ? '' : ''; ?>
                                <div class="entry-item reorder-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($filter_name); ?>  <?php echo esc_attr($filter_group); ?>" data-name="<?php echo esc_attr($filter_name); ?>">
                                    <input class="customize-item-input" type="hidden" name="content_calendar_filters_order[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
                                    <?php echo esc_html($filter_label); ?>
                                </div>
                                <?php
                                $added_filters[] = $filter_name;
                            }
                            foreach ($all_filters as $filter_name => $filter_details) :
                                if (!in_array($filter_name, $added_filters)) :
                                    $filter_label = $filter_details['filter_label'];
                                    $filter_group = $filter_details['filter_group'];
    
                                    $active_class = (in_array($filter_name, $enabled_filters)) ? 'active-item' : '';
                                    $input_name   = (in_array($filter_name, $enabled_filters)) ? '' : ''; ?>
                                    <div class="entry-item reorder-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($filter_name); ?>  <?php echo esc_attr($filter_group); ?>" data-name="<?php echo esc_attr($filter_name); ?>">
                                        <input class="customize-item-input" type="hidden" name="content_calendar_filters_order[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
                                        <?php echo esc_html($filter_label); ?>
                                    </div>
                                <?php $added_filters[] = $filter_name; 
                                endif;
                            endforeach; ?>
                        </div>
                    </div>
                    <div class="fixed-footer">
                        <div class="save-cc-changes save-customize-item-form">
                            <?php esc_html_e('Apply Changes', 'publishpress'); ?>
                        </div>
                    </div>
                </div>
            </form>
            <?php
            return ob_get_clean();
            
        }

        /**
         * Allow altering modified date when creating posts. WordPress by default
         * doesn't allow that. We need it to fix an issue where the post_modified
         * field is saved with the post_date value. That is a problem when you save
         * a post with post_date to the future. For scheduled posts.
         *
         * @param array $data
         * @param array $postarr
         *
         * @return array
         */
        public static function alter_post_modification_time($data, $postarr)
        {
            if (! empty($postarr['post_modified']) && ! empty($postarr['post_modified_gmt'])) {
                $data['post_modified'] = $postarr['post_modified'];
                $data['post_modified_gmt'] = $postarr['post_modified_gmt'];
            }

            return $data;
        }

        public static function calendar_filters()
        {
            $select_filter_names = [];

            $select_filter_names['post_status'] = 'post_status';
            $select_filter_names['revision_status'] = 'revision_status';
            $select_filter_names['cat'] = 'cat';
            $select_filter_names['tag'] = 'tag';
            $select_filter_names['author'] = 'author';
            $select_filter_names['type'] = 'cpt';
            $select_filter_names['weeks'] = 'weeks';

            return apply_filters('pp_calendar_filter_names', $select_filter_names);
        }

        public static function searchAuthors()
        {
            header('Content-type: application/json;');

            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json([]);
            }

            $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $user_roles = (isset($_GET['user_role']) && is_array($_GET['user_role'])) ? array_map('sanitize_text_field', $_GET['user_role']) : [];

            if (empty($user_roles)) {
                /**
                 * @param array $results
                 * @param string $searchText
                 */
                $results = apply_filters('publishpress_search_authors_results_pre_search', [], $queryText);
            } else {
                /**
                 * @param array $results
                 * @param array $args
                 */
                $results = apply_filters(
                    'publishpress_search_authors_with_args_results_pre_search',
                    [],
                    ['search' => $queryText, 'role__in' => $user_roles]
                );
            }

            if (! empty($results)) {
                wp_send_json($results);
            }

            $user_args = [
                'number' => 20,
                'orderby' => 'display_name',
            ];

            if (!empty($user_roles)) {
                $user_args['role__in'] = $user_roles;
            } else {
                $user_args['capability'] = 'edit_posts';
            }

            if (! empty($queryText)) {
                $user_args['search'] = '*' . $queryText . '*';
            }

            $users = get_users($user_args);

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->ID,
                    'text' => $user->display_name,
                ];
            }

            wp_send_json($results);
        }

        public static function searchTerms()
        {
            header('Content-type: application/json;');

            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json([]);
            }

            $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field($_GET['taxonomy']) : '';
            global $wpdb;

            $queryResult = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT t.slug AS id, t.name AS text
                FROM {$wpdb->term_taxonomy} as tt
                INNER JOIN {$wpdb->terms} as t ON (tt.term_id = t.term_id)
                WHERE tt.taxonomy = %s AND t.name LIKE %s
                ORDER BY 2
                LIMIT 20",
                    $taxonomy,
                    '%' . $wpdb->esc_like($queryText) . '%'
                )
            );

            $queryResult = map_deep($queryResult, 'html_entity_decode');

            wp_send_json($queryResult);
        }

        /**
         * Returns a VTIMEZONE component for a Olson timezone identifier
         * with daylight transitions covering the given date range.
         *
         * @param \Sabre\VObject\Component\VCalendar
         * @param string $tzid Timezone ID as used in PHP's Date functions
         * @param int $from Unix timestamp with first date/time in this timezone
         * @param int $to Unix timestap with last date/time in this timezone
         *
         * @return mixed A Sabre\VObject\Component object representing a VTIMEZONE definition
         *               or false if no timezone information is available
         */
        public static function generateVTimeZone(&$calendar, $tzid, $from = 0, $to = 0)
        {
            if (! $from) {
                $from = time();
            }
            if (! $to) {
                $to = $from;
            }

            try {
                $tz = new DateTimeZone($tzid);
            } catch (Exception $e) {
                return false;
            }

            // get all transitions for one year back/ahead
            $year = 86400 * 360;
            $transitions = $tz->getTransitions($from - $year, $to + $year);

            $vTimeZone = $calendar->add(
                'VTIMEZONE',
                [
                    'TZID' => $tz->getName(),
                ]
            );

            $standard = null;
            $daylight = null;
            $t_std = null;
            $t_dst = null;
            $tzfrom = 0;
            if (is_array($transitions) || is_object($transitions)) {
                foreach ($transitions as $i => $trans) {
                    if ($i == 0) {
                        $tzfrom = $trans['offset'] / 3600;
                        continue;
                    }

                    // daylight saving time definition
                    if ($trans['isdst']) {
                        $t_dst = $trans['ts'];
                        $dt = new DateTime($trans['time']);
                        $offset = $trans['offset'] / 3600;

                        $daylight = $vTimeZone->add(
                            'DAYLIGHT',
                            [
                                'DTSTART' => $dt->format('Ymd\THis'),
                                'TZOFFSETFROM' => sprintf(
                                    '%s%02d%02d',
                                    $tzfrom >= 0 ? '+' : '',
                                    floor($tzfrom),
                                    ($tzfrom - floor($tzfrom)) * 60
                                ),
                                'TZOFFSETTO' => sprintf(
                                    '%s%02d%02d',
                                    $offset >= 0 ? '+' : '',
                                    floor($offset),
                                    ($offset - floor($offset)) * 60
                                ),
                            ]
                        );

                        // add abbreviated timezone name if available
                        if (! empty($trans['abbr'])) {
                            $daylight->add('TZNAME', [$trans['abbr']]);
                        }

                        $tzfrom = $offset;
                    } else {
                        $t_std = $trans['ts'];
                        $dt = new DateTime($trans['time']);
                        $offset = $trans['offset'] / 3600;

                        $standard = $vTimeZone->add(
                            'STANDARD',
                            [
                                'DTSTART' => $dt->format('Ymd\THis'),
                                'TZOFFSETFROM' => sprintf(
                                    '%s%02d%02d',
                                    $tzfrom >= 0 ? '+' : '',
                                    floor($tzfrom),
                                    ($tzfrom - floor($tzfrom)) * 60
                                ),
                                'TZOFFSETTO' => sprintf(
                                    '%s%02d%02d',
                                    $offset >= 0 ? '+' : '',
                                    floor($offset),
                                    ($offset - floor($offset)) * 60
                                ),
                            ]
                        );

                        // add abbreviated timezone name if available
                        if (! empty($trans['abbr'])) {
                            $standard->add('TZNAME', [$trans['abbr']]);
                        }

                        $tzfrom = $offset;
                    }

                    // we covered the entire date range
                    if ($standard && $daylight && min($t_std, $t_dst) < $from && max($t_std, $t_dst) > $to) {
                        break;
                    }
                }
            }

            // add X-MICROSOFT-CDO-TZID if available
            $microsoftExchangeMap = array_flip(Sabre\VObject\TimeZoneUtil::$microsoftExchangeMap);
            if (array_key_exists($tz->getName(), $microsoftExchangeMap)) {
                $vTimeZone->add('X-MICROSOFT-CDO-TZID', $microsoftExchangeMap[$tz->getName()]);
            }

            return $vTimeZone;
        }

        /**
         * Perform the encoding necessary for ICS feed text.
         *
         * @param string $text The string that needs to be escaped
         *
         * @return string The string after escaping for ICS.
         * @since 0.8
         * */

        public static function do_ics_escaping($text)
        {
            $text = str_replace(',', '\,', $text);
            $text = str_replace(';', '\:', $text);
            $text = str_replace('\\', '\\\\', $text);

            return $text;
        }


        /**
         * Given a day in string format, returns the day at the beginning of that week, which can be the given date.
         * The end of the week is determined by the blog option, 'start_of_week'.
         *
         * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
         *
         * @param string $date String representing a date
         * @param string $format Date format in which the end of the week should be returned
         * @param int $week Number of weeks we're offsetting the range
         *
         * @return string $formatted_start_of_week End of the week
         */
        public static function get_beginning_of_week($date, $format = 'Y-m-d', $week = 1)
        {
            $date = strtotime($date);
            $start_of_week = (int)get_option('start_of_week');
            $day_of_week = date('w', $date);
            $date += (($start_of_week - $day_of_week - 7) % 7) * 60 * 60 * 24 * $week;
            $additional = 3600 * 24 * 7 * ($week - 1);
            $formatted_start_of_week = date($format, $date + $additional);

            return $formatted_start_of_week;
        }

        /**
         * Given a day in string format, returns the day at the end of that week, which can be the given date.
         * The end of the week is determined by the blog option, 'start_of_week'.
         *
         * @see http://www.php.net/manual/en/datetime.formats.date.php for valid date formats
         *
         * @param string $date String representing a date
         * @param string $format Date format in which the end of the week should be returned
         * @param int $week Number of weeks we're offsetting the range
         *
         * @return string $formatted_end_of_week End of the week
         */
        public static function get_ending_of_week($date, $format = 'Y-m-d', $week = 1)
        {
            $date = strtotime($date);
            $end_of_week = (int)get_option('start_of_week') - 1;
            $day_of_week = date('w', $date);
            $date += (($end_of_week - $day_of_week + 7) % 7) * 60 * 60 * 24;
            $additional = 3600 * 24 * 7 * ($week - 1);
            $formatted_end_of_week = date($format, $date + $additional);

            return $formatted_end_of_week;
        }

        public static function getPostData($args) {
            $id     = $args['id'];
            $post   = $args['post'];
            $type   = $args['type'];
            $date   = $args['date'];
            $status   = $args['status'];
            $categories   = $args['categories'];
            $tags   = $args['tags'];

            $authorsNames = apply_filters(
                'publishpress_post_authors_names',
                [get_the_author_meta('display_name', $post->post_author)],
                $id
            );

            $data = [
                'id' => $id,
                'status' => $post->post_status,
                'fields' => [
                    'type' => [
                        'label' => __('Post Type', 'publishpress'),
                        'value' => $type,
                        'type' => 'type',
                    ],
                    'id' => [
                        'label' => __('ID', 'publishpress'),
                        'value' => $id,
                        'type' => 'number',
                    ],
                    'date' => [
                        'label' => __('Date', 'publishpress'),
                        'value' => $post->post_date,
                        'valueString' => $date,
                        'type' => 'date',
                    ],
                    'status' => [
                        'label' => __('Post Status', 'publishpress'),
                        'value' => $status,
                        'type' => 'status',
                    ],
                    'authors' => [
                        'label' => _n('Author', 'Authors', count($authorsNames), 'publishpress'),
                        'value' => $authorsNames,
                        'type' => 'authors',
                    ],
                    'categories' => [
                        'label' => _n('Category', 'Categories', count($categories), 'publishpress'),
                        'value' => $categories,
                        'type' => 'taxonomy',
                    ],
                    'tags' => [
                        'label' => _n('Tag', 'Tags', count($tags), 'publishpress'),
                        'value' => $tags,
                        'type' => 'taxonomy',
                    ],
                ],
                'links' => []
            ];

            $postTypeObject = get_post_type_object($post->post_type);

            if (current_user_can($postTypeObject->cap->edit_post, $post->ID)) {
                $data['links']['edit'] = [
                    'label' => __('Edit', 'publishpress'),
                    'url' => htmlspecialchars_decode(get_edit_post_link($id))
                ];
            }

            if (current_user_can($postTypeObject->cap->delete_post, $post->ID)) {
                $data['links']['trash'] = [
                    'label' => __('Trash', 'publishpress'),
                    'url' => htmlspecialchars_decode(get_delete_post_link($id)),
                ];
            }

            if (current_user_can($postTypeObject->cap->read_post, $post->ID)) {
                if ($post->post_status === 'publish') {
                    $label = __('View', 'publishpress');
                    $link = get_permalink($id);
                } else {
                    $label = __('Preview', 'publishpress');
                    $link = get_preview_post_link($id);
                }

                $data['links']['view'] = [
                    'label' => $label,
                    'url' => htmlspecialchars_decode($link),
                ];
            }

            $data = apply_filters('publishpress_calendar_get_post_data', $data, $post);

            return $data;
        }

        public static function add_admin_body_class($classes) {
            global $pagenow;
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-calendar') {
                $classes .= ' pp-content-calendar-page';
            }
            return $classes;
        }

        public static function getTimezoneString()
        {
            $timezoneString = get_option('timezone_string');

            if (empty($timezoneString)) {
                $offset = get_option('gmt_offset');

                if ($offset > 0) {
                    $offset = '+' . $offset;
                }

                if (2 === strlen($offset)) {
                    $offset .= ':00';
                }

                $timezoneString = new DateTimeZone($offset);
                $timezoneString = $timezoneString->getName();
            }

            return $timezoneString;
        }

        /**
         * Set all post types as selected, to be used as the default option.
         *
         * @return array
         */
        public static function pre_select_all_post_types()
        {
            $list = get_post_types(null, 'objects');

            foreach ($list as $type => $value) {
                $list[$type] = 'on';
            }

            return $list;
        }

        public static function get_content_calendar_form_filters($args)
        {
            $content_calendar_datas = $args['content_calendar_datas'];
            // custom filters
            $filters['custom'] = [
                'title'     => esc_html__('Custom filters', 'publishpress'),
                'message'   => esc_html__('Click the "Add New" button to create new filters.', 'publishpress'),
                'filters'   => $content_calendar_datas['content_calendar_custom_filters']
            ];
    
            // default filters
            $filters['default'] = [
                'title'     => esc_html__('Inbuilt filters', 'publishpress'),
                'filters'   => [
                    'post_status' => esc_html__('Post Status', 'publishpress'),
                    'revision_status' => esc_html__('Revision Status', 'publishpress'),
                    'author' => esc_html__('Author', 'publishpress'),
                    'cpt' => esc_html__('Post Type', 'publishpress')
                ]
            ];
            
            // editorial fields filters
            if (isset($content_calendar_datas['editorial_metadata'])) {
                $filters['editorial_metadata'] = [
                    'title'     => esc_html__('Editorial Fields', 'publishpress'),
                    'message'   => esc_html__('You do not have any editorial fields enabled', 'publishpress'),
                    'filters'   => $content_calendar_datas['editorial_metadata']
                ];
            }
    
            $filters['taxonomies'] = [
                'title'     => esc_html__('Taxonomies', 'publishpress'),
                'message'   => esc_html__('You do not have any public taxonomies', 'publishpress'),
                'filters'   => $content_calendar_datas['taxonomies']
            ];
    
            /**
            * @param array $filters
            * @param array $content_calendar_datas
            *
            * @return $filters
            */
            $filters = apply_filters('publishpress_content_calendar_form_filters', $filters, $content_calendar_datas);

            return $filters;
        }

        public static function calendar_ics_subs_html($subscription_link) {
            ?>

                <div id="publishpress-calendar-ics-subs" style="display:none;">
                    <h3><?php
                        echo esc_html__('PublishPress', 'publishpress'); ?>
                        - <?php
                        echo esc_html__('Subscribe in iCal or Google Calendar', 'publishpress'); ?>
                    </h3>

                    <div>
                        <h4><?php
                            echo esc_html__('Start date', 'publishpress'); ?></h4>
                        <select id="publishpress-start-date">
                            <option value="0"
                                    selected="selected"><?php
                                echo esc_html__('Current week', 'publishpress'); ?></option>
                            <option value="1"><?php
                                echo esc_html__('One month ago', 'publishpress'); ?></option>
                            <option value="2"><?php
                                echo esc_html__('Two months ago', 'publishpress'); ?></option>
                            <option value="3"><?php
                                echo esc_html__('Three months ago', 'publishpress'); ?></option>
                            <option value="4"><?php
                                echo esc_html__('Four months ago', 'publishpress'); ?></option>
                            <option value="5"><?php
                                echo esc_html__('Five months ago', 'publishpress'); ?></option>
                            <option value="6"><?php
                                echo esc_html__('Six months ago', 'publishpress'); ?></option>
                        </select>

                        <br/>

                        <h4><?php
                            echo esc_html__('End date', 'publishpress'); ?></h4>
                        <select id="publishpress-end-date">
                            <optgroup label="<?php
                            echo esc_attr__('Weeks', 'publishpress'); ?>">
                                <option value="w1"><?php
                                    echo esc_html__('One week', 'publishpress'); ?></option>
                                <option value="w2"><?php
                                    echo esc_html__('Two weeks', 'publishpress'); ?></option>
                                <option value="w3"><?php
                                    echo esc_html__('Three weeks', 'publishpress'); ?></option>
                                <option value="w4"><?php
                                    echo esc_html__('Four weeks', 'publishpress'); ?></option>
                            </optgroup>

                            <optgroup label="<?php
                            echo esc_attr__('Months', 'publishpress'); ?>">
                                <option value="m1"><?php
                                    echo esc_html__('One month', 'publishpress'); ?></option>
                                <option value="m2"
                                        selected="selected"><?php
                                    echo esc_html__('Two months', 'publishpress'); ?></option>
                                <option value="m3"><?php
                                    echo esc_html__('Three months', 'publishpress'); ?></option>
                                <option value="m4"><?php
                                    echo esc_html__('Four months', 'publishpress'); ?></option>
                                <option value="m5"><?php
                                    echo esc_html__('Five months', 'publishpress'); ?></option>
                                <option value="m6"><?php
                                    echo esc_html__('Six months', 'publishpress'); ?></option>
                                <option value="m7"><?php
                                    echo esc_html__('Seven months', 'publishpress'); ?></option>
                                <option value="m8"><?php
                                    echo esc_html__('Eight months', 'publishpress'); ?></option>
                                <option value="m9"><?php
                                    echo esc_html__('Nine months', 'publishpress'); ?></option>
                                <option value="m10"><?php
                                    echo esc_html__('Ten months', 'publishpress'); ?></option>
                                <option value="m11"><?php
                                    echo esc_html__('Eleven months', 'publishpress'); ?></option>
                                <option value="m12"><?php
                                    echo esc_html__('Twelve months', 'publishpress'); ?></option>
                            </optgroup>
                        </select>
                    </div>

                    <br/>

                    <a href="<?php
                    echo esc_url($subscription_link); ?>" id="publishpress-ics-download"
                       style="margin-right: 20px;" class="button">
                        <span class="dashicons dashicons-download" style="text-decoration: none"></span>
                        <?php
                        echo esc_html__('Download .ics file', 'publishpress'); ?></a>

                    <button data-clipboard-text="<?php
                    echo esc_attr($subscription_link); ?>" id="publishpress-ics-copy"
                            class="button-primary">
                        <span class="dashicons dashicons-clipboard" style="text-decoration: none"></span>
                        <?php
                        echo esc_html__('Copy to the clipboard', 'publishpress'); ?>
                    </button>
                </div>

                <a href="#TB_inline?width=550&height=270&inlineId=publishpress-calendar-ics-subs" class="thickbox">
                    <?php
                    echo esc_html__('Click here to subscribe in iCal or Google Calendar', 'publishpress'); ?>
                </a>
            <?php
        }

    }

}
