<?php
/**
 * Overview Methods class
 */

if (! class_exists('PP_Overview_Methods')) {
    class PP_Overview_Methods extends PP_Module
    {
        /**
         * [$module description]
         *
         * @var [type]
         */
        public $module;

        public $module_url;

        /**
         * PP_Overview_Settings constructor.
         *
         * @param array $args
         */
        public function __construct($args = [])
        {
            $this->module_url = $args['module_url'];
            $this->module = $args['module'];
        }

        public function add_admin_body_class($classes) {
            global $pagenow;
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-content-overview') {
                $classes .= ' pp-content-overview-page';
            }
            return $classes;
        }

        /**
         * Enqueue necessary admin scripts only on the content overview page.
         *
         * @uses enqueue_admin_script()
         */
        public function enqueue_admin_scripts()
        {
            global $pagenow;

            // Only load content overview styles on the content overview page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-content-overview') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                $this->enqueue_datepicker_resources();

                wp_enqueue_script(
                    'publishpress-admin',
                    PUBLISHPRESS_URL . 'common/js/publishpress-admin.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-content_overview',
                    $this->module_url . 'lib/content-overview.js',
                    ['jquery', 'publishpress-date_picker', 'publishpress-select2', 'jquery-ui-sortable'],
                    PUBLISHPRESS_VERSION,
                    true
                );

                    wp_enqueue_script(
                        'publishpress-select2',
                        PUBLISHPRESS_URL . 'common/libs/select2/js/select2-full.min.js',
                        ['jquery'],
                        PUBLISHPRESS_VERSION
                    );


                wp_enqueue_script(
                    'publishpress-floating-scroll',
                    PUBLISHPRESS_URL . 'common/libs/floating-scroll/js/jquery.floatingscroll.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_localize_script(
                    'publishpress-content_overview',
                    'PPContentOverview',
                    [
                        'nonce' => wp_create_nonce('content_overview_filter_nonce'),
                        'moduleUrl' => $this->module_url,
                        'publishpressUrl' => PUBLISHPRESS_URL,
                    ]
                );
            }
        }

        /**
         * Enqueue a screen and print stylesheet for the content overview.
         */
        public function action_enqueue_admin_styles()
        {
            global $pagenow;

            // Only load calendar styles on the calendar page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-content-overview') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style(
                    'pp-admin-css',
                    PUBLISHPRESS_URL . 'common/css/publishpress-admin.css',
                    ['publishpress-select2'],
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
                wp_enqueue_style(
                    'publishpress-content_overview-styles',
                    $this->module_url . 'lib/content-overview.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
                wp_enqueue_style(
                    'publishpress-content_overview-print-styles',
                    $this->module_url . 'lib/content-overview-print.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'print'
                );

                wp_enqueue_style(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/css/select2-full.min.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );

                wp_enqueue_style(
                    'publishpress-floating-scroll',
                    PUBLISHPRESS_URL . 'common/libs/floating-scroll/css/jquery.floatingscroll.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
            }
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving)
         *
         * @since 0.7
         * @uses  add_settings_section(), add_settings_field()
         */
        public function register_settings()
        {
            add_settings_section(
                $this->module->options_group_name . '_general',
                false,
                '__return_false',
                $this->module->options_group_name
            );

            add_settings_field(
                'post_types',
                esc_html__('Post types to show:', 'publishpress'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }

        /**
         * Choose the post types for editorial fields
         *
         * @since 0.7
         */
        public function settings_post_types_option()
        {
            global $publishpress;
            $publishpress->settings->helper_option_custom_post_type($this->module);
        }

        /**
         * Filters the menu slug.
         *
         * @param $menu_slug
         *
         * @return string
         */
        public function filter_admin_menu_slug($menu_slug)
        {
            if (empty($menu_slug) && $this->module_enabled('content_overview')) {
                $menu_slug = 'pp-content-overview';
            }

            return $menu_slug;
        }

        /**
         * Print any messages that should appear based on the action performed
         */
        public function print_messages()
        {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            if (isset($_GET['trashed']) || isset($_GET['untrashed'])) {
                echo '<div id="trashed-message" class="updated"><p>';

                // Following mostly stolen from edit.php
                if (isset($_GET['trashed']) && (int)$_GET['trashed']) {
                    $count = (int)$_GET['trashed'];

                    echo esc_html(_n('Item moved to the trash.', '%d items moved to the trash.', $count, 'publishpress'));
                    $ids = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : 0;
                    echo ' <a href="' . esc_url(
                            wp_nonce_url(
                                "edit.php?post_type=post&doaction=undo&action=untrash&ids=$ids",
                                "bulk-posts"
                            )
                        ) . '">' . esc_html__('Undo', 'publishpress') . '</a><br />';
                    unset($_GET['trashed']);
                }

                if (isset($_GET['untrashed']) && (int)$_GET['untrashed']) {
                    $count = (int)$_GET['untrashed'];

                    echo esc_html(_n(
                        'Item restored from the Trash.',
                        '%d items restored from the Trash.',
                        $count,
                        'publishpress'
                    ));
                    unset($_GET['undeleted']);
                }

                echo '</p></div>';
            }
            // phpcs:enable
        }

        /**
         * Print the table navigation and filter controls, using the current user's filters if any are set.
         */
        public static function table_navigation($args)
        {
            $editable_post_types    = $args['editable_post_types'];
            $user_filters           = $args['user_filters'];
            $overview_filters       = $args['overview_filters'];
            $posts_per_page         = $args['posts_per_page'];
            ?>
            <div class="pp-content-overview-manage">
                <div class="left-items">
                        <?php
                            $modal_id = 0;
                            $me_mode = (int) $user_filters['me_mode'];
                            $active_me_mode = !empty($me_mode) ? 'active-filter' : '';
                        ?>
                    <div class="item action me-mode-action <?php echo esc_attr($active_me_mode); ?>">
                        <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e('Me Mode', 'publishpress'); ?>
                    </div>
                    <?php do_action('pp_content_overview_filter_after_me_mode', $user_filters); ?>
                    <div class="item action co-filter" data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>">
                        <span class="dashicons dashicons-editor-table"></span> <?php esc_html_e('Customize Columns', 'publishpress'); ?>
                    </div>
                    <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="customize-customize-item-modal content-overview-modal" style="display: none;">
                        <div class="content-overview-modal-content">
                            <span class="close">&times;</span>
                            <?php echo PP_Content_Overview_Utilities::content_overview_customize_column_form($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                    <?php $modal_id++; ?>
                    <div class="item action co-filter" data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e('Customize Filters', 'publishpress'); ?>
                    </div>
                    <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="customize-customize-item-modal content-overview-modal" style="display: none;">
                        <div class="content-overview-modal-content">
                            <span class="close">&times;</span>
                            <?php echo PP_Content_Overview_Utilities::content_overview_customize_filter_form($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                    <div class="item action" id="print_link">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e('Print', 'publishpress'); ?>
                    </div>
                    <?php $modal_id++; ?>
                    <div data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="co-filter item action">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </div>
                    <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="content-overview-modal" style="display: none;">
                        <div class="content-overview-modal-content">
                            <span class="close">&times;</span>
                            <div>
                                <div class="metadata-item-filter custom-filter">
                                    <label class="filter-title" for="pp_posts_per_page">
                                        <?php esc_html_e('Maximum number of posts to display', 'publishpress'); ?>
                                    </label>
                                    <div class="filter-content">
                                        <form method="POST">
                                            <input type="hidden" name="co_form_action" value="settings_form"/>
                                            <input type="hidden" name="_nonce" value="<?php echo esc_attr(wp_create_nonce('content_overview_settings_form_nonce')); ?>"/>
                                            <input required type="number" step="1" min="1" max="999" id="pp_posts_per_page" name="posts_per_page" value="<?php echo esc_attr($posts_per_page); ?>">
                                            <div class="filter-apply">
                                                <input type="submit" id="filter-submit" class="button button-primary" value="<?php esc_attr_e('Apply Changes', 'publishpress'); ?>">
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="right-items">
                    <?php if (!empty($editable_post_types)) : ?>
                        <?php $modal_id++; ?>
                        <div class="item action co-filter new-post" data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>">
                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('New Post', 'publishpress'); ?>
                        </div>
                        <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="customize-customize-item-modal content-overview-modal new-post-modal" style="display: none;">
                            <div class="content-overview-modal-content">
                                <span class="close">&times;</span>
                                <div class="content-overview-modal-form">
                                    <?php echo PP_Content_Overview_Utilities::content_overview_get_post_form($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="item">
                        <div class="search-bar">
                            <input type="search" id="co-searchbox-search-input" name="s" value="<?php _admin_search_query(); ?>" placeholder="<?php esc_attr_e('Search box', 'publishpress'); ?>" aria-label="<?php esc_attr_e('Search content', 'publishpress'); ?>" />
                            <?php submit_button(esc_html__('Search', 'publishpress'), '', '', false, ['id' => 'co-searchbox-search-submit']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
            <form method="GET" id="pp-content-filters">
                <input type="hidden" name="page" value="pp-content-overview"/>
                <input type="hidden" name="me_mode" id="content_overview_me_mode" value="<?php echo esc_attr($me_mode); ?>" />
                <?php do_action('pp_content_overview_filter_hidden_fields', $user_filters); ?>
                <div class="pp-content-overview-filters">
                    <?php
                    $filtered_start_date = $user_filters['start_date'];
                    $filtered_end_date = $user_filters['end_date'];
                    $selected_date = ': ' . date_i18n("F j, Y", strtotime($filtered_start_date)) . ' '. esc_html__('to', 'publishpress').' ' . date_i18n("F j, Y", strtotime($filtered_end_date));
                    $modal_id++;
                    ?>
                    <button data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="co-filter active-filter">
                        <?php esc_html_e('Date', 'publishpress'); ?><?php echo esc_html($selected_date); ?>
                    </button>
                    <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="content-overview-modal" style="display: none;">
                        <div class="content-overview-modal-content">
                            <span class="close">&times;</span>
                            <div><?php echo PP_Content_Overview_Utilities::content_overview_time_range($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                        </div>
                    </div>
                    <?php

                    foreach ($overview_filters as $select_id => $select_name) {
                        $modal_id++;
                        $args['select_id']      = $select_id;
                        $args['select_name']    = $select_name;
                        $filter_data = PP_Content_Overview_Utilities::content_overview_filter_options($args);
                        $active_class = !empty($filter_data['selected_value']) ? 'active-filter' : '';
                        $button_label = $filter_data['filter_label'];
                        $button_label .= !empty($filter_data['selected_value']) ? ': ' . $filter_data['selected_value'] : '';
                        ?>
                        <?php if (!empty($button_label)) : ?>
                            <button
                                data-target="#content_overview_modal_<?php echo esc_attr($modal_id); ?>"
                                class="co-filter <?php echo esc_attr($active_class); ?> <?php echo esc_attr($select_id); ?> me-mode-status-<?php echo esc_attr($me_mode); ?>"><?php echo esc_html($button_label); ?></button>
                            <div id="content_overview_modal_<?php echo esc_attr($modal_id); ?>" class="content-overview-modal" style="display: none;">
                                <div class="content-overview-modal-content">
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

            <form method="GET" id="pp-content-filters-hidden">
                    <input type="hidden" name="page" value="pp-content-overview"/>
                    <input type="hidden" name="post_status" value=""/>
                    <input type="hidden" name="cat" value=""/>
                    <input type="hidden" name="author" value=""/>
                    <input type="hidden" name="me_mode" value=""/>
                    <?php do_action('pp_content_overview_filter_reset_hidden_fields', $user_filters); ?>
                    <input type="hidden" name="orderby" value="<?php
                        echo (isset($_GET['orderby']) && ! empty($_GET['orderby'])) ?
                            esc_attr(sanitize_key($_GET['orderby'])) : 'post_date'; ?>"/>
                    <input type="hidden" name="order" value="<?php
                        echo (isset($_GET['order']) && ! empty($_GET['order'])) ? esc_attr(sanitize_key($_GET['order'])) : 'ASC'; ?>"/>
                    <?php
                    foreach ($overview_filters as $select_id => $select_name) {
                        echo '<input type="hidden" name="' . esc_attr($select_name) . '" value="" />';
                    } ?>
                    <?php
                    $date_format = 'Y-m-d';
                    $reset_start_date = date($date_format, strtotime('-5 weeks'));
                    $reset_end_date   = date($date_format, strtotime($reset_start_date . ' +10 weeks'));

                    $filtered_start_date = $reset_start_date;
                    $filtered_start_date_timestamp = strtotime($filtered_start_date);

                    $filtered_end_date = $reset_end_date;
                    $filtered_end_date_timestamp = strtotime($filtered_end_date);

                    $start_date_value = '<input type="hidden" name="pp-content-overview-start-date" value="' . esc_attr(date_i18n($date_format, $filtered_start_date_timestamp)) . '" />';
                    $start_date_value .= '<input type="hidden" name="pp-content-overview-start-date_hidden" value="' . $filtered_start_date . '" />';

                    $end_date_value = '<input type="hidden" name="pp-content-overview-end-date" value="' . esc_attr(date_i18n($date_format, $filtered_end_date_timestamp)) . '" />';
                    $end_date_value .= '<input type="hidden" name="pp-content-overview-end-date_hidden" value="' . $filtered_end_date . '" />';

                    $nonce = wp_nonce_field('change-date', 'nonce', 'change-date-nonce', false);

                    echo $start_date_value . $end_date_value . $nonce;
                    ?>
                    <input type="hidden" name="pp-content-overview-range-use-today" value="1"/>
            </form>
            <?php
            // phpcs:enable
        }

    }
}
