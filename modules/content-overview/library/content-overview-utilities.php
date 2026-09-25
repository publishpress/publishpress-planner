<?php
/**
 * Content Overview Utilities class
 */

if (! class_exists('PP_Content_Overview_Utilities')) {
    class PP_Content_Overview_Utilities
    {

        public static function content_overview_customize_column_form($args) {
            
            ob_start();

            $content_overview_datas     = $args['content_overview_datas'];
            $enabled_columns            = array_keys($content_overview_datas['content_overview_columns']);
            $columns                    = $args['form_columns'];
            $meta_keys                  = $content_overview_datas['meta_keys'];

            $all_columns              = [];
            ?>
            <form method="POST" class="pp-content-overview-customize-form columns" id="pp-content-overview-column-form" data-form="columns">
                <input type="hidden" name="co_form_action" value="column_form"/>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_overview_column_form_nonce')); ?>"/>
                <div class="co-customize-tabs">
                    <div class="customize-tab enable-tab cc-active-tab" data-tab="enable-content"><?php esc_html_e('Enable Columns', 'publishpress'); ?></div>
                    <div class="customize-tab reorder-tab" data-tab="reorder-content"> <?php esc_html_e('Reorder Columns', 'publishpress'); ?> </div>
                </div>
                <div class="co-cc-content">
                    <div class="customize-content enable-content">
                        <div class="fixed-header">
                            <p class="description"><?php esc_html_e('Enable or Disable Content Overview table column.', 'publishpress'); ?></p>
                        </div>
                        <div class="scrollable-content">
                            <?php 
                            $column_index = 0;
                            foreach ($columns as $column_group => $column_datas) : 
                            $column_index++;
                            $hidden_style = empty($column_datas['columns']) && $column_group !== 'custom' ? 'display: none;' : '';
                            ?>
                                <div class="customize-group-title title-index-<?php echo esc_attr($column_index); ?> <?php echo esc_attr($column_group); ?>" style="<?php echo esc_attr($hidden_style); ?>">
                                    <div class="title-text"><?php echo esc_html($column_datas['title']); ?></div>
                                    <?php if ($column_group === 'custom') : ?>
                                        <div class="title-action new-item">
                                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Add New', 'publishpress'); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($column_group === 'custom') : ?>
                                    <div class="entry-item enable-item form-item" style="display: none;">
                                        <div class="new-fields">
                                            <div class="field">
                                                <input class="new-item-title" type="text" placeholder="<?php esc_attr_e('Column Title', 'publishpress'); ?>" />
                                            </div>
                                            <div class="field">
                                            <select class="new-item-metakey" data-nonce="<?php echo esc_attr(wp_create_nonce('publishpress-content-get-data')); ?>">
                                                <option value=""><?php esc_html_e('Search Metakey', 'publishpress'); ?></option>
                                            </select>
                                            </div>
                                        </div>
                                        <div class="new-submit">
                                            <?php esc_html_e('Add Column', 'publishpress'); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($column_datas['columns'])) : ?>
                                    <div class="item-group-empty <?php echo esc_attr($column_group); ?>" style="<?php echo esc_attr($hidden_style); ?>"><?php echo esc_html($column_datas['message']); ?></div>
                                <?php else : ?>
                                    <?php foreach ($column_datas['columns'] as $column_name => $column_label) : 
                                        $active_class = (in_array($column_name, $enabled_columns)) ? 'active-item' : '';
                                        $input_name   = (in_array($column_name, $enabled_columns)) ? 'content_overview_columns['. $column_name .']' : '';
    
                                        $all_columns[$column_name] = [
                                            'column_label' => $column_label,
                                            'column_group' => $column_group
                                        ];
                                        ?>
                                        <div class="entry-item enable-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($column_name); ?> <?php echo esc_attr($column_group); ?>" data-name="<?php echo esc_attr($column_name); ?>">
                                            <input class="customize-item-input" type="hidden" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($column_label); ?>" />
                                            <?php if ($column_group === 'custom') : ?>
                                                <input type="hidden" name="content_overview_custom_columns[<?php echo esc_attr($column_name); ?>]" value="<?php echo esc_attr($column_label); ?>" />
                                            <?php endif; ?>
                                            <div class="items-list-item-check checked">
                                                <svg><use xlink:href="<?php echo esc_url(PUBLISHPRESS_URL . 'common/icons/content-icon.svg#svg-sprite-cu2-check-2-fill'); ?>"></use></svg>
                                            </div>
                                            <div class="items-list-item-check unchecked">
                                                <svg><use xlink:href="<?php echo esc_url(PUBLISHPRESS_URL . 'common/icons/content-icon.svg#svg-sprite-x'); ?>"></use></svg>
                                            </div>
                                            <div class="items-list-item-name">
                                                <div class="items-list-item-name-text"><?php echo esc_html($column_label); ?> <?php if ($column_group === 'custom') : ?><span class="customize-item-info">(<?php echo esc_html($column_name); ?>)</span><?php endif; ?></div>
                                            </div>
                                            <?php if ($column_group === 'custom') : ?>
                                                <div class="delete-content-overview-item" data-meta="<?php echo esc_html($column_name); ?>">
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
                            <p class="description"><?php esc_html_e('Drag to change enabled columns order.', 'publishpress'); ?></p>
                        </div>
                        <div class="scrollable-content">
                            <?php 
                            // loop enabled column first so they can stay as ordered
                            $added_columns = [];
                            foreach ($enabled_columns as $enabled_column) {
                                $column_name    = $enabled_column;
                                if (!isset($all_columns[$column_name])) {
                                    continue;
                                }
                                $column_details = $all_columns[$column_name];
                                $column_label = $column_details['column_label'];
                                $column_group = $column_details['column_group'];
                                $active_class = (in_array($column_name, $enabled_columns)) ? 'active-item' : '';
                                $input_name   = (in_array($column_name, $enabled_columns)) ? '' : ''; ?>
                                <div class="entry-item reorder-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($column_name); ?>  <?php echo esc_attr($column_group); ?>" data-name="<?php echo esc_attr($column_name); ?>">
                                    <input class="customize-item-input" type="hidden" name="content_overview_columns_order[<?php echo esc_attr($column_name); ?>]" value="<?php echo esc_attr($column_label); ?>" />
                                    <?php echo esc_html($column_label); ?>
                                </div>
                                <?php
                                $added_columns[] = $column_name;
                            }
                            foreach ($all_columns as $column_name => $column_details) :
                                if (!in_array($column_name, $added_columns)) :
                                    $column_label = $column_details['column_label'];
                                    $column_group = $column_details['column_group'];
    
                                    $active_class = (in_array($column_name, $enabled_columns)) ? 'active-item' : '';
                                    $input_name   = (in_array($column_name, $enabled_columns)) ? '' : ''; ?>
                                    <div class="entry-item reorder-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($column_name); ?>  <?php echo esc_attr($column_group); ?>" data-name="<?php echo esc_attr($column_name); ?>">
                                        <input class="customize-item-input" type="hidden" name="content_overview_columns_order[<?php echo esc_attr($column_name); ?>]" value="<?php echo esc_attr($column_label); ?>" />
                                        <?php echo esc_html($column_label); ?>
                                    </div>
                                <?php $added_columns[] = $column_name; 
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
    
        
        public static function content_overview_customize_filter_form($args) {
            
            ob_start();

            $content_overview_datas   = $args['content_overview_datas'];
            $enabled_filters          = array_keys($content_overview_datas['content_overview_filters']);
            $filters                  = $args['form_filters'];
            $meta_keys                = $content_overview_datas['meta_keys'];

            $all_filters              = [];
            ?>
            <form method="POST" class="pp-content-overview-customize-form filters" id="pp-content-overview-filter-form" data-form="filters">
                <input type="hidden" name="co_form_action" value="filter_form"/>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_overview_filter_form_nonce')); ?>"/>
                <div class="co-customize-tabs">
                    <div class="customize-tab enable-tab cc-active-tab" data-tab="enable-content"><?php esc_html_e('Enable Filters', 'publishpress'); ?></div>
                    <div class="customize-tab reorder-tab" data-tab="reorder-content"> <?php esc_html_e('Reorder Filters', 'publishpress'); ?> </div>
                </div>
                <div class="co-cc-content">
                    <div class="customize-content enable-content">
                        <div class="fixed-header">
                            <p class="description"><?php esc_html_e('Enable or Disable Content Overview filter.', 'publishpress'); ?></p>
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
                                                <option value=""><?php esc_html_e('Select Metakey', 'publishpress'); ?></option>
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
                                        $input_name   = (in_array($filter_name, $enabled_filters)) ? 'content_overview_filters['. $filter_name .']' : '';
    
                                        $all_filters[$filter_name] = [
                                            'filter_label' => $filter_label,
                                            'filter_group' => $filter_group
                                        ];
                                        ?>
                                        <div class="entry-item enable-item <?php echo esc_attr($active_class); ?> customize-item-<?php echo esc_attr($filter_name); ?> <?php echo esc_attr($filter_group); ?>" data-name="<?php echo esc_attr($filter_name); ?>">
                                            <input class="customize-item-input" type="hidden" name="<?php echo esc_attr($input_name); ?>" value="<?php echo esc_attr($filter_label); ?>" />
                                            <?php if ($filter_group === 'custom') : ?>
                                                <input type="hidden" name="content_overview_custom_filters[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
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
                                                <div class="delete-content-overview-item" data-meta="<?php echo esc_html($filter_name); ?>">
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
                                    <input class="customize-item-input" type="hidden" name="content_overview_filters_order[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
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
                                        <input class="customize-item-input" type="hidden" name="content_overview_filters_order[<?php echo esc_attr($filter_name); ?>]" value="<?php echo esc_attr($filter_label); ?>" />
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

        public static function content_overview_get_post_form($args) {
            
            ob_start();
    
            $post_type = $args['default_post_type'];

            $postTypeObject = get_post_type_object($post_type);
            $post_fields    = self::getPostTypeFields($args);
            ?>
            <form method="POST" class="pp-content-overview-post-form" id="pp-content-overview-post-form">
                <input type="hidden" name="co_form_action" value="post_form"/>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_overview_post_form_nonce')); ?>"/>
                <div class="form-title">
                    <?php echo sprintf(esc_html__('Add New %s', 'publishpress'), esc_html($postTypeObject->labels->singular_name)); ?>
                </div>
                <hr />
                <div class="co-cc-content">
                    <div class="customize-content new-post">
                        <div class="scrollable-content">
                        <table class="content-overview-form-table fixed">
                            <tbody>
                                <?php foreach ($post_fields as $field_key => $field_options) : ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="publishpress-content-overview-field-<?php echo esc_attr($field_key); ?>">
                                                <?php echo esc_html($field_options['label']); ?>
                                                <?php if (!empty($field_options['required'])) : ?>
                                                    <span class="required">*</span>
                                                <?php endif; ?>
                                            </label>
                                        </th>
                                        <td>
                                            <?php if (!empty($field_options['html'])) : ?>
                                                <?php echo $field_options['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                            <?php else : ?>
                                                <?php
                                                switch ($field_options['type']) {
                                                    case 'status':
                                                        ?>
                                                        <select id="form_post_status" name="<?php echo esc_attr($field_key); ?>">
                                                            <?php
                                                            foreach (apply_filters('publishpress_content_overview_new_post_statuses', $field_options['options'], $args) as $post_status) {
                                                                echo "<option value='" . esc_attr($post_status['value']) . "' " . selected(
                                                                        $post_status['value'],
                                                                        $field_options['value']
                                                                    ) . ">" . esc_html($post_status['text']) . "</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                        <?php
                                                        break;
    
                                                        case 'taxonomy':
                                                            $taxonomy_name = $field_options['taxonomy'];
                                                            $taxonomySlug = $field_options['value'];
                                                            ?>
                                                            <select 
                                                                class="post_form_taxonomy" 
                                                                id="<?php echo esc_attr('post_form_taxonomy_' . $taxonomy_name); ?>" 
                                                                data-taxonomy="<?php echo esc_attr($taxonomy_name); ?>" 
                                                                name="<?php echo esc_attr($taxonomy_name); ?>[]"
                                                                multiple
                                                                >
                                                                <?php
                                                                if ($taxonomySlug) {
                                                                    $term = get_term_by('slug', $taxonomySlug, $taxonomy_name);
                                            
                                                                    echo "<option value='" . esc_attr($taxonomySlug) . "' selected='selected'>" . esc_html(
                                                                            $term->name
                                                                        ) . "</option>";
                                                                }
                                                                ?>
                                                            </select>
                                                            <?php
                                                            break;
                                            
                                                        case 'authors':
                                                            $authorId = (int) $field_options['value'];
                                                            ?>
                                                            <select id="post_form_author_<?php echo esc_attr($field_key); ?>" class="post_form_author <?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>">
                                                                <?php
                                                                if (! empty($authorId)) {
                                                                    $author = get_user_by('id', $authorId);
                                                                    $option = '';
                                            
                                                                    if (! empty($author)) {
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
                                                            ?>
                                                            <select id="post_form_post_type" name="<?php echo esc_attr($field_key); ?>">
                                                                <?php
                                                                foreach ($field_options['options'] as $option_key => $option_label) {
                                                                    echo '<option value="' . esc_attr($option_key) . '" ' . selected(
                                                                            $field_options['value'],
                                                                            $option_key
                                                                        ) . '>' . esc_html($option_label) . '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                            <?php
                                                            break;
                                            
                                                        case 'html':
                                                                ?>
                                                                <textarea 
                                                                    name="<?php echo esc_attr($field_key); ?>"><?php echo stripslashes_deep($field_options['value']); ?></textarea>
                                                                <?php
                                                        break;
    
                                                        default:
                                                        $required_html = !empty($field_options['required']) ? 'required' : '';
                                                            ?>
                                                            <input 
                                                                type="<?php echo esc_attr($field_options['type']); ?>" 
                                                                name="<?php echo esc_attr($field_key); ?>" 
                                                                value="<?php echo esc_attr($field_options['value']); ?>"
                                                                <?php echo esc_html($required_html); ?>
                                                            >
                                                            <?php
                                                        break;
                                                }
                                                ?>
                                                
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <div class="fixed-footer">
                        <div class="save-cc-changes save-new-post-form">
                            <?php echo sprintf(esc_html__('Create %s', 'publishpress'), esc_html($postTypeObject->labels->singular_name)); ?>
                        </div>
                        <input type="submit" name="submit" class="form-submit-button" value="<?php esc_html_e('Submit', 'publishpress'); ?>" style="display: none;"/>
                    </div>
                </div>
            </form>
    
            <div class="content-overview-form-loader">
                <span class="text">
                    <?php esc_html_e('Please, wait! Loading the form fields...', 'publishpress'); ?>
                </span>
                <span class="spinner is-active"></span>
            </div>
    
            <?php
            return ob_get_clean();
        }
    
        
        public static function getPostTypeFields($args)
        {
            global $publishpress;

            $postType               = $args['default_post_type'];
            $editablePostTypes      = $args['editable_post_types'];
            $postStatusOptions      = $args['post_status_options'];
            $publish_date_markup    = $args['publish_date_markup'];
    
            $postTypeObject = get_post_type_object($postType);
    
            $fields = [
                'ptype' => [
                    'label' => __('Post Type', 'publishpress'),
                    'value' => $postType,
                    'type' => 'post_type',
                    'options' => $editablePostTypes,
                ],
                'title' => [
                    'label' => __('Title', 'publishpress'),
                    'value' => null,
                    'type' => 'text',
                    'required' => 1,
                ],
                'status' => [
                    'label' => __('Post Status', 'publishpress'),
                    'value' => 'draft',
                    'type' => 'status',
                    'options' => $postStatusOptions
                ],
                'pdate' => [
                    'label' => __('Publish Date', 'publishpress'),
                    'value' => 'immediately',
                    'type' => 'pdate',
                    'html' => $publish_date_markup
                ]
            ];
    
            if (current_user_can($postTypeObject->cap->edit_others_posts)) {
                $fields['authors'] = [
                    'label' => __('Author', 'publishpress'),
                    'value' => get_current_user_id(),
                    'type' => 'authors',
                ];
            }
    
            $taxonomies = get_object_taxonomies($postType);
    
            if (in_array('category', $taxonomies)) {
                $fields['categories'] = [
                    'label' => __('Categories', 'publishpress'),
                    'value' => null,
                    'type' => 'taxonomy',
                    'taxonomy' => 'category',
                ];
            }
    
            if (in_array('post_tag', $taxonomies)) {
                $fields['tags'] = [
                    'label' => __('Tags', 'publishpress'),
                    'value' => null,
                    'type' => 'taxonomy',
                    'taxonomy' => 'post_tag',
                ];
            }
    
            $fields['content'] = [
                'label' => __('Content', 'publishpress'),
                'value' => null,
                'type' => 'html'
            ];
    
            if (class_exists('PP_Editorial_Metadata')) {
                $editorial_metadata_class = new PP_Editorial_Metadata;
                $editorial_metadata_terms = $publishpress->editorial_metadata->get_editorial_metadata_terms(['show_in_calendar_form' => true]);
                foreach ($editorial_metadata_terms as $term) {
                    if (isset($term->post_types) && is_array($term->post_types) && in_array($postType, $term->post_types)) {
                        $term_options = $editorial_metadata_class->get_editorial_metadata_term_by('id', $term->term_id);
                        $postmeta_key = esc_attr($editorial_metadata_class->get_postmeta_key($term));
                        $post_types = (isset($term->post_types) && is_array($term->post_types)) ? array_values($term->post_types) : [];
                        $post_types = join(" ", $post_types);
                        $term_data = [
                        'name' => $postmeta_key,
                        'label' => $term->name,
                        'description' => $term->description,
                        'term_options' => $term_options,
                    ];
                        $term_type = $term->type;
                        if ($term_type === 'user') {
                            $ajaxArgs    = [];
                            if (isset($term->user_role)) {
                                $ajaxArgs['user_role'] = $term->user_role;
                            }
                            $fields[$term_data['name']] = [
                            'metadata' => true,
                            'term'     => $term,
                            'label'    => $term->name,
                            'value'    => '',
                            'ajaxArgs' => $ajaxArgs,
                            'post_types' => $post_types,
                            'type'     => 'authors',
                            'multiple' => ''
                        ];
                        } elseif ($term_type === 'paragraph') {
                            $fields[$term_data['name']] = [
                            'metadata' => true,
                            'term'     => $term,
                            'label'    => $term->name,
                            'post_types' => $post_types,
                            'value'    => '',
                            'type'     => 'html'
                        ];
                        } else {
                            $html = apply_filters("pp_editorial_metadata_{$term->type}_get_input_html", $term_data, '');
                            $fields[$term_data['name']] = [
                            'metadata' => true,
                            'post_types' => $post_types,
                            'html'     => (is_object($html) || is_array($html)) ? '' : '<div class="pp-calendar-form-metafied '. $post_types .'">' . $html . '</div>',
                            'term'     => $term,
                            'label'    => $term->name,
                            'value'    => '',
                            'type'     => 'metafield'
                        ];
                        }
                    }
                }
            }
    
            $fields = apply_filters('publishpress_content_overview_get_post_type_fields', $fields, $postType);
    
            return $fields;
        }
    
        /**
         * Allow the user to define the date range in a new and exciting way
         *
         * @since 0.7
         */
        public static function content_overview_time_range($args)
        {
            $filtered_start_date = $args['user_filters']['start_date'];
            $filtered_start_date_timestamp = strtotime($filtered_start_date);

            $filtered_end_date = $args['user_filters']['end_date'];
            $filtered_end_date_timestamp = strtotime($filtered_end_date);
    
            $output = '<div class="metadata-item-filter">';
            $output .= '<div class="filter-title">';
            $output .= esc_html__('Show content from', 'publishpress');
            $output .= '</div>';
            $output .= '<div class="filter-content">';
    
            $date_format = get_option('date_format');
    
            $start_date_value = '<input type="text" id="pp-content-overview-start-date" name="pp-content-overview-start-date"'
                . ' class="date-pick" data-alt-field="pp-content-overview-start-date_hidden" data-alt-format="' . pp_convert_date_format_to_jqueryui_datepicker(
                    'Y-m-d'
                ) . '" value="'
                . esc_attr(date_i18n($date_format, $filtered_start_date_timestamp)) . '" />';
            $start_date_value .= '<input type="hidden" name="pp-content-overview-start-date_hidden" value="' . $filtered_start_date . '" />';
            $start_date_value .= '<span class="form-value hidden">';
    
            $start_date_value .= esc_html(date_i18n($date_format, $filtered_start_date_timestamp));
            $start_date_value .= '</span>';
    
            $end_date_value = '<input type="text" id="pp-content-overview-end-date" name="pp-content-overview-end-date"'
                . ' class="date-pick" data-alt-field="pp-content-overview-end-date_hidden" data-alt-format="' . pp_convert_date_format_to_jqueryui_datepicker(
                    'Y-m-d'
                ) . '" value="'
                . esc_attr(date_i18n($date_format, $filtered_end_date_timestamp)) . '" />';
            $end_date_value .= '<input type="hidden" name="pp-content-overview-end-date_hidden" value="' . $filtered_end_date . '" />';
            $end_date_value .= '<span class="form-value hidden">';
    
            $end_date_value .= esc_html(date_i18n($date_format, $filtered_end_date_timestamp));
            $end_date_value .= '</span>';
    
            $output .= sprintf(
                _x(
                    ' %1$s <div class="input-divider">to</div> %2$s',
                    '%1$s = start date, %2$s = end date',
                    'publishpress'
                ),
                $start_date_value,
                $end_date_value
            );
            $output .= '&nbsp;&nbsp;<span class="change-date-buttons">';
            $output .= '<input id="pp-content-overview-range-submit" name="pp-content-overview-range-submit" type="hidden" value="1"';
            $output .= ' class="button" value="' . esc_html__('Apply', 'publishpress') . '" />';
            $output .= '&nbsp;';
            $output .= '<input id="pp-content-overview-range-today-btn" name="pp-content-overview-range-today-btn" type="submit"';
            $output .= ' class="button button-secondary hidden" value="' . esc_attr__('Reset', 'publishpress') . '" />';
            $output .= '<input id="pp-content-overview-range-use-today" name="pp-content-overview-range-use-today" value="0" type="hidden" />';
            $output .= '&nbsp;';
            $output .= '<a class="change-date-cancel hidden" href="#">' . esc_html__('Cancel', 'publishpress') . '</a>';
            $output .= '<a class="change-date hidden" href="#">' . esc_html__('Change', 'publishpress') . '</a>';
            $output .= wp_nonce_field('change-date', 'nonce', 'change-date-nonce', false);
            $output .= '</span>';
            $output .= '<div class="filter-apply"><input type="submit" id="filter-submit" class="button button-primary" value="' . esc_html__('Apply', 'publishpress') . '"></div>';
    
            $output .= '</div>';
            $output .= '</div>';
    
            return $output;
        }

        public static function content_overview_filter_options($args)
        {
            $args = apply_filters('publishpress_content_overview_filter_options_args', $args);

            $select_id                  = $args['select_id'];
            $select_name                = $args['select_name'];
            $filters                    = $args['user_filters'];
            $terms_options              = $args['terms_options'];
            $content_overview_datas     = $args['content_overview_datas'];
            $post_statuses              = $args['post_statuses'];
            $post_types                 = $args['post_types'];
            $form_filter_list           = $args['form_filter_list'];
            $all_filters                = $args['all_filters'];
            $operator_labels            = $args['operator_labels'];
            
            $revision_statuses          = (!empty($args['revision_statuses'])) ? $args['revision_statuses'] : [];

            if (array_key_exists($select_id, $terms_options)) {
                $select_id = 'metadata_key';
            }
    
            if (array_key_exists($select_id, $content_overview_datas['taxonomies']) && taxonomy_exists($select_id)) {
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
    
                            $selected_value = $term->name;
    
                            echo "<option value='" . esc_attr($taxonomySlug) . "' selected='selected'>" . esc_html(
                                    $term->name
                                ) . "</option>";
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
    
                case 'ptype':
                    $selectedPostType = isset($filters['ptype']) ? sanitize_text_field($filters['ptype']) : '';
                    $filter_label   = esc_html__('Post Type', 'publishpress');
                    ?>
                    <select id="filter_post_type" name="ptype">
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
                    if ($filter_vars = apply_filters('publishpress_content_overview_custom_filter', false, $select_id, $args)) {
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
                        do_action('PP_Content_Overview_filter_display', $select_id, $select_name, $filters);
                    }
                    break;
            }
            
            return ['selected_value' => $selected_value, 'filter_label' => $filter_label, 'html' => ob_get_clean()];
        }
    

    }

}
