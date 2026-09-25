<?php
/**
 * Board Methods class
 */

if (! class_exists('PP_Board_Methods')) {
    class PP_Board_Methods extends PP_Module
    {

        /**
         * Usermeta key prefix
         */
        const USERMETA_KEY_PREFIX = 'PP_Content_Board_';

        /**
         * [$module description]
         *
         * @var [type]
         */
        public $module;

        public $module_url;

        /**
         * PP_Board_Settings constructor.
         *
         * @param array $args
         */
        public function __construct($args = [])
        {
            $this->module_url = $args['module_url'];
            $this->module = $args['module'];
        }

        public function updatePostStatus() {
            global $publishpress;

            $response['status']  = 'error';
            $response['content'] = esc_html__('An error occured', 'publishpress');


            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'content_board_action_nonce')) {
                $response['content'] = esc_html__('Error validating nonce. Please reload this page and try again.', 'publishpress');
            } elseif (empty($_POST['post_id']) || empty($_POST['post_status'])) {
                $response['content'] = esc_html__('Invalid form request.', 'publishpress');
            } else {
                $post_status  = sanitize_text_field($_POST['post_status']);
                $schedule_number = (int) $_POST['schedule_number'];
                $schedule_period = sanitize_text_field($_POST['schedule_period']);
                $post_id      = (int) $_POST['post_id'];
                $post_data    = get_post($post_id);
                if (!is_object($post_data) || !isset($post_data->post_type)) {
                    $response['content'] = esc_html__('Error fetching post data.', 'publishpress');
                } else {
                    $user_post_status = array_column( $this->getUserAuthorizedPostStatusOptions($post_data->post_type, $post_id), 'value');
                    if (in_array('publish', $user_post_status)) {
                        $user_post_status[] = 'future';
                        $user_post_status[] = 'private';
                    }

                    $post_type_object = get_post_type_object($post_data->post_type);
                    if (! $post_type_object || ! current_user_can('edit_post', $post_id)) {
                        $response['content'] = esc_html__('You do not have permission to edit selected post.', 'publishpress');
                    } elseif (!in_array($post_status, $user_post_status)) {
                        $response['content'] = esc_html__('You do not have permission to move post to selected post status.', 'publishpress');
                    } else {
                        $post_args = [
                            'ID' => $post_id,
                            'post_status' => $post_status
                        ];
                        if ($post_data->post_status === 'future') {
                            // set current date as published date if old post status is schedule
                            $current_date_time = current_time('mysql');
                            $post_args['post_date'] = $current_date_time;
                            $post_args['post_date_gmt'] = get_gmt_from_date($current_date_time);
                        } elseif ($post_status === 'future') {
                            // set future date if new status is schedule
                            $current_timestamp = time();
                            $timestamp         = strtotime($schedule_number . ' ' . $schedule_period, $current_timestamp);
                            $content_board_scheduled_date = [
                                'number' => $schedule_number,
                                'period' => $schedule_period
                            ];
                            $publishpress->update_module_option($this->module->name, 'content_board_scheduled_date', $content_board_scheduled_date);

                            $future_date = date('Y-m-d H:i:s', $timestamp);

                            $post_args['post_date'] = $future_date;
                            $post_args['post_date_gmt'] = get_gmt_from_date($future_date);
                        }

                        wp_update_post($post_args);

                        $response['status']  = 'success';
                        $response['content'] = esc_html__('Changes saved!', 'publishpress');
                    }
                }

            }

            wp_send_json($response);
        }
        public function updateSchedulePeriod() {
            global $publishpress;

            $response['status']  = 'error';
            $response['content'] = esc_html__('An error occured', 'publishpress');


            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'content_board_action_nonce')) {
                $response['content'] = esc_html__('Error validating nonce. Please reload this page and try again.', 'publishpress');
            } elseif (! current_user_can('manage_options')) {
                $response['content'] = esc_html__('You do not have permission to update schedule settings.', 'publishpress');
            } elseif (empty($_POST['schedule_number']) || empty($_POST['schedule_period'])) {
                $response['content'] = esc_html__('Invalid form request.', 'publishpress');
            } else {
                $schedule_number = (int) $_POST['schedule_number'];
                $schedule_period = sanitize_text_field($_POST['schedule_period']);
                $content_board_scheduled_date = [
                    'number' => $schedule_number,
                    'period' => $schedule_period
                ];
                $publishpress->update_module_option($this->module->name, 'content_board_scheduled_date', $content_board_scheduled_date);
                $response['status']  = 'success';
                $response['content'] = esc_html__('Changes saved!', 'publishpress');
            }

            wp_send_json($response);
        }

        /**
         * Update content board form action
         *
         * @return void
         */
        public function update_content_board_form_action() {
            global $publishpress;

            if (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'column_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_board_column_form_nonce')) {
                // Content Board column form
                $content_board_columns = !empty($_POST['content_board_columns']) ? array_map('sanitize_text_field', $_POST['content_board_columns']) : [];
                $content_board_columns_order = !empty($_POST['content_board_columns_order']) ? array_map('sanitize_text_field', $_POST['content_board_columns_order']) : [];
                $content_board_custom_columns = !empty($_POST['content_board_custom_columns']) ? map_deep($_POST['content_board_custom_columns'], 'sanitize_text_field') : [];

                // make sure enabled columns are saved in organized order
                $content_board_columns = array_intersect($content_board_columns_order, $content_board_columns);

                $publishpress->update_module_option($this->module->name, 'content_board_columns', $content_board_columns);
                $publishpress->update_module_option($this->module->name, 'content_board_custom_columns', $content_board_custom_columns);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo pp_planner_admin_notice(esc_html__('Card Data updated successfully.', 'publishpress'));
            } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'filter_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_board_filter_form_nonce')) {
                // Content Board filter form
                $content_board_filters = !empty($_POST['content_board_filters']) ? array_map('sanitize_text_field', $_POST['content_board_filters']) : [];
                $content_board_filters_order = !empty($_POST['content_board_filters_order']) ? array_map('sanitize_text_field', $_POST['content_board_filters_order']) : [];
                $content_board_custom_filters = !empty($_POST['content_board_custom_filters']) ? map_deep($_POST['content_board_custom_filters'], 'sanitize_text_field') : [];

                // make sure enabled filters are saved in organized order
                $content_board_filters = array_intersect($content_board_filters_order, $content_board_filters);

                $publishpress->update_module_option($this->module->name, 'content_board_filters', $content_board_filters);
                $publishpress->update_module_option($this->module->name, 'content_board_custom_filters', $content_board_custom_filters);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo pp_planner_admin_notice(esc_html__('Filter updated successfully.', 'publishpress'));
            } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'settings_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_board_settings_form_nonce')) {
                // Content Board filter form
                $posts_per_page = !empty($_POST['posts_per_page']) ? (int) $_POST['posts_per_page'] : 200;

                $publishpress->update_module_option($this->module->name, 'posts_per_page', $posts_per_page);

                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo pp_planner_admin_notice(esc_html__('Settings updated successfully.', 'publishpress'));
            } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && !empty($_POST['ptype']) && $_POST['co_form_action'] == 'post_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_board_post_form_nonce')) {
                $postType = sanitize_text_field($_POST['ptype']);
                $postTypeObject = get_post_type_object($postType);
                if (current_user_can($postTypeObject->cap->edit_posts)) {
                    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
                    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
                    // Sanitized by the wp_filter_post_kses function.
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $content = isset($_POST['content']) ? wp_filter_post_kses($_POST['content']) : '';
                    $authors = isset($_POST['authors']) ? (int) $_POST['authors'] : get_current_user_id();
                    $categories = isset($_POST['category']) ? array_map('sanitize_text_field', $_POST['category']) : [];
                    $tags = isset($_POST['post_tag']) ? array_map('sanitize_text_field', $_POST['post_tag']) : [];

                    $postArgs = [
                        'post_author' => $authors,
                        'post_title' => $title,
                        'post_content' => $content,
                        'post_type' => $postType,
                        'post_status' => $status
                    ];

                    // set post date
                    if ( ! empty( $_POST['mm'] ) ) {
                        $aa = sanitize_text_field($_POST['aa']);
                        $mm = sanitize_text_field($_POST['mm']);
                        $jj = sanitize_text_field($_POST['jj']);
                        $hh = sanitize_text_field($_POST['hh']);
                        $mn = sanitize_text_field($_POST['mn']);
                        $ss = sanitize_text_field($_POST['ss']);
                        $aa = ( $aa <= 0 ) ? gmdate( 'Y' ) : $aa;
                        $mm = ( $mm <= 0 ) ? gmdate( 'n' ) : $mm;
                        $jj = ( $jj > 31 ) ? 31 : $jj;
                        $jj = ( $jj <= 0 ) ? gmdate( 'j' ) : $jj;
                        $hh = ( $hh > 23 ) ? $hh - 24 : $hh;
                        $mn = ( $mn > 59 ) ? $mn - 60 : $mn;
                        $ss = ( $ss > 59 ) ? $ss - 60 : $ss;


                        $post_date = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $aa, $mm, $jj, $hh, $mn, $ss );
                        $valid_date = wp_checkdate( $mm, $jj, $aa, $post_date );
                        if ($valid_date ) {
                            $postArgs['post_date'] = $post_date;
                            $postArgs['post_date_gmt'] = get_gmt_from_date( $post_date );
                        }
                    }

                    $postId = wp_insert_post($postArgs);

                    if ($postId) {
                        if (! empty($categories)) {
                            $categoriesIdList = [];
                            foreach ($categories as $categorySlug) {
                                $category = get_term_by('slug', $categorySlug, 'category');

                                if (! $category || is_wp_error($category)) {
                                    $category = wp_create_category($categorySlug);
                                    $category = get_term($category);
                                }

                                if (! is_wp_error($category)) {
                                    $categoriesIdList[] = $category->term_id;
                                }
                            }
                            wp_set_post_terms($postId, $categoriesIdList, 'category');
                        }

                        if (! empty($tags)) {
                            foreach ($tags as $tagSlug) {
                                $tag = get_term_by('slug', $tagSlug, 'post_tag');

                                if (! $tag || is_wp_error($tag)) {
                                    wp_create_tag($tagSlug);
                                }
                            }
                            wp_set_post_terms($postId, $tags);
                        }
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo pp_planner_admin_notice(sprintf(__('%s created successfully. <a href="%s" target="_blank">Edit %s</a>', 'publishpress'), esc_html($postTypeObject->labels->singular_name), esc_url(get_edit_post_link($postId)), esc_html($postTypeObject->labels->singular_name)));
                    } else {
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo pp_planner_admin_notice(sprintf(esc_html__('%s could not be created', 'publishpress'), esc_html($postTypeObject->labels->singular_name)), false);
                    }

                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo pp_planner_admin_notice(sprintf(esc_html__('You do not have permission to add new %s', 'publishpress'), esc_html($postTypeObject->labels->singular_name)), false);
                }
            }

        }

        /**
        * Handle a form submission to change the user's date range on the budget
        *
        * @since 0.7
        */
        public function handle_form_date_range_change()
        {
            if (
                ! isset(
                    $_REQUEST['pp-content-board-start-date_hidden'],
                    $_REQUEST['pp-content-board-range-use-today'],
                    $_REQUEST['nonce']
                )
                || (
                    ! isset($_REQUEST['pp-content-board-range-submit'])
                    && $_REQUEST['pp-content-board-range-use-today'] == '0'
                )
            ) {
                return;
            }

            if (! wp_verify_nonce(sanitize_key($_REQUEST['nonce']), 'change-date')) {
                return;
            }

            $current_user = wp_get_current_user();
            $user_filters = $this->get_user_meta(
                $current_user->ID,
                self::USERMETA_KEY_PREFIX . 'filters',
                true
            );

            $use_today_as_start_date = (bool)$_REQUEST['pp-content-board-range-use-today'];

            $date_format = 'Y-m-d';
            $user_filters['start_date'] = $use_today_as_start_date
                ? date($date_format, strtotime('-5 weeks'))
                : date($date_format, strtotime(sanitize_text_field($_REQUEST['pp-content-board-start-date_hidden']))); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

            $user_filters['end_date'] = $_REQUEST['pp-content-board-end-date_hidden'];

            if ($use_today_as_start_date || (empty(trim($user_filters['end_date']))) || (strtotime($user_filters['start_date']) > strtotime($user_filters['end_date']))) {
                $user_filters['end_date'] = date($date_format, strtotime($user_filters['start_date'] . ' +10 weeks'));
            }

            $this->update_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', $user_filters);
        }

        /**
         * Enqueue necessary admin scripts only on the content board page.
         *
         * @uses enqueue_admin_script()
         */
        public function enqueue_admin_scripts()
        {
            global $pagenow;

            // Only load content board styles on the content board page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-content-board') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                $this->enqueue_datepicker_resources();

                wp_enqueue_script('jquery-ui-sortable');

                wp_enqueue_script(
                    'publishpress-admin',
                    PUBLISHPRESS_URL . 'common/js/publishpress-admin.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-content_board',
                    $this->module_url . 'lib/content-board.js',
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
                    'publishpress-content_board',
                    'PPContentBoard',
                    [
                        'nonce' => wp_create_nonce('content_board_action_nonce'),
                        'moduleUrl' => $this->module_url,
                        'publishpressUrl' => PUBLISHPRESS_URL,
                    ]
                );
            }
        }

        /**
         * Enqueue a screen and print stylesheet for the content board.
         */
        public function action_enqueue_admin_styles()
        {
            global $pagenow;

            // Only load calendar styles on the calendar page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-content-board') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style(
                    'pp-admin-css',
                    PUBLISHPRESS_URL . 'common/css/publishpress-admin.css',
                    ['publishpress-select2'],
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
                wp_enqueue_style(
                    'publishpress-content_board-styles',
                    $this->module_url . 'lib/content-board.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
                wp_enqueue_style(
                    'publishpress-content_board-print-styles',
                    $this->module_url . 'lib/content-board-print.css',
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

    }

}
