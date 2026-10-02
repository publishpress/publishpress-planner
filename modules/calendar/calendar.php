<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2022 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use PublishPress\Legacy\Util;
use PublishPress\Notifications\Traits\Dependency_Injector;

if (! class_exists('PP_Calendar')) {
    /**
     * class PP_Calendar
     * Threaded commenting in the admin for discussion between writers and editors
     *
     * @author batmoo
     */
    #[\AllowDynamicProperties]
    class PP_Calendar extends PP_Module
    {
        use Dependency_Injector;

        /**
         * Settings slug
         */
        const SETTINGS_SLUG = 'pp-calendar';

        /**
         * Usermeta key prefix
         */
        const USERMETA_KEY_PREFIX = 'pp_calendar_';

        /**
         * Default number of weeks to display in the calendar
         */
        const DEFAULT_NUM_WEEKS = 5;

        /**
         * Name of the transient option to flag the warning for selecting
         * at least one post type
         */
        const TRANSIENT_SHOW_ONE_POST_TYPE_WARNING = 'show_one_post_type_warning';

        /**
         * The menu slug.
         */
        const MENU_SLUG = 'pp-calendar';

        /**
         * Time 12h-format without leading zeroes.
         */
        const TIME_FORMAT_12H_NO_LEADING_ZEROES = 'ga';

        /**
         * Time 12h-format with leading zeroes.
         */
        const TIME_FORMAT_12H_WITH_LEADING_ZEROES = 'ha';

        /**
         * Time 24h-format with leading zeroes.
         */
        const TIME_FORMAT_24H = 'H';

        const VIEW_CAPABILITY = 'pp_view_calendar';

        /**
         * [$module description]
         *
         * @var [type]
         */
        public $module;
        
        public $module_url;

        /**
         * [$start_date description]
         *
         * @var string
         */
        public $start_date = '';

        /**
         * [$current_week description]
         *
         * @var int
         */
        public $current_week = 1;

        /**
         * Default number of weeks to show per screen
         *
         * @var int
         */
        public $total_weeks = 6;

        /**
         * Counter of hidden posts per date square
         *
         * @var int
         */
        public $hidden = 0;

        /**
         * Total number of posts to be shown per square before 'more' link
         *
         * @var int
         */
        public $default_max_visible_posts_per_date = 4;

        /**
         * [$post_date_cache description]
         *
         * @var array
         */
        private $post_date_cache = [];

        /**
         * [$post_li_html_cache_key description]
         *
         * @var string
         */
        private static $post_li_html_cache_key = 'pp_calendar_post_li_html';

        /**
         * Store default WordPress Date and Time formats for caching purposes.
         *
         * @var string
         */
        private $default_date_time_format = 'ha';

        /**
         * @var array
         */
        private $postTypeObjectCache = [];

        /**
         * @var \PublishPress\Utility\Date
         */
        private $dateUtil;
    
        /**
         * @var array
         */
        public $filters;
    
        /**
         * @var array
         */
        public $form_filters = [];
    
        /**
         * @var array
         */
        public $form_filter_list = [];
    
        /**
         * [$user_filters description]
         *
         * @var [type]
         */
        public $user_filters;
    
        /**
         * Custom methods
         *
         * @var array
         */
        private $terms_options = [];

        /**
         * Check if PublishPress Revisions is available for revision status fields.
         *
         * @return bool
         */
        private function is_revisions_active()
        {
            return defined('PUBLISHPRESS_REVISIONS_VERSION')
                || defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')
                || function_exists('rvy_in_revision_workflow');
        }
    
        /**
         * [$content_calendar_datas description]
         *
         * @var [type]
         */
        public $content_calendar_datas;

        /**
         * Content calendar methods
         *
         * @var [type]
         */
        private $content_calendar_methods;

        /**
         * Construct the PP_Calendar class
         */
        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);

            // Register the module with PublishPress
            $args = [
                'title' => __('Content Calendar', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-calendar-alt',
                'slug' => 'calendar',
                'post_type_support' => 'pp_calendar',
                'default_options' => [
                    'enabled' => 'on',
                    'post_types' => [
                        'post' => 'on',
                        'page' => 'off',
                    ],
                    'ics_subscription' => 'on',
                    'ics_subscription_public_visibility' => 'off',
                    'ics_secret_key' => wp_generate_password(),
                    'show_posts_publish_time' => ['publish' => 'on', 'future' => 'on'],
                    'default_publish_time' => '',
                    'show_calendar_posts_full_title' => 'off',
                    'calendar_today_in_first_row' => 'on',
                    // Leave default as non array to confirm if user save settings or not
                    'content_calendar_filters' => '',
                    'content_calendar_custom_filters' => '',
                ],
                'messages' => [
                    'post-date-updated' => __('Post date updated.', 'publishpress'),
                    'status-updated' => __('Post status updated.', 'publishpress'),
                    'update-error' => __(
                        'There was an error updating the post. Please try again.',
                        'publishpress'
                    ),
                    'published-post-ajax' => __(
                        "Updating the post date dynamically doesn't work for published content. Please <a href='%s'>edit the post</a>.",
                        'publishpress'
                    ),
                    'key-regenerated' => __(
                        'iCal secret key regenerated. Please inform all users they will need to resubscribe.',
                        'publishpress'
                    ),
                ],
                'configure_page_cb' => 'print_configure_view',
                'settings_help_tab' => [
                    'id' => 'pp-calendar-overview',
                    'title' => __('Overview', 'publishpress'),
                    'content' => __(
                        '<p>The calendar is a convenient week-by-week or month-by-month view into your content. Quickly see which stories are on track to being published on time, and which will need extra effort.</p>',
                        'publishpress'
                    ),
                ],
                'settings_help_sidebar' => __(
                    '<p><strong>For more information:</strong></p><p><a href="https://publishpress.com/features/calendar/">Calendar Documentation</a></p><p><a href="https://github.com/ostraining/PublishPress">PublishPress on Github</a></p>',
                    'publishpress'
                ),
                'show_configure_btn' => false,
                'options_page' => true,
                'page_link' => admin_url('admin.php?page=pp-calendar'),
            ];

            $this->dateUtil = new \PublishPress\Utility\Date();

            $this->module = PublishPress()->register_module('calendar', $args);

            // Load utilities files.
            $this->load_utilities_files();

            $this->content_calendar_methods = new PP_Calendar_Methods([
                'module' => $this->module,
                'module_url' => $this->module_url
            ]);
        }

        /**
         * Initialize all of our methods and such. Only runs if the module is active
         *
         * @uses add_action()
         */
        public function init()
        {
            
            add_action('template_include', [$this, 'handle_public_calendar_feed']);

            if (is_admin()) {
                $this->setDefaultCapabilities();
            }

            // Can view the calendar?
            if (! $this->currentUserCanViewCalendar()) {
                return false;
            }

            if (is_admin()) {
                // Menu
                add_filter('publishpress_admin_menu_slug', [$this, 'filter_admin_menu_slug']);
                add_action('publishpress_admin_menu_page', [$this, 'action_admin_menu_page']);
                add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu']);

                // .ics calendar subscriptions
                add_action('wp_ajax_pp_calendar_ics_subscription', [$this, 'handle_ics_subscription']);
                add_action('wp_ajax_nopriv_pp_calendar_ics_subscription', [$this, 'handle_ics_subscription']);

                // Define the create-post capability
                $this->create_post_cap = apply_filters('pp_calendar_create_post_cap', 'edit_posts');

                require_once PUBLISHPRESS_BASE_PATH . '/common/php/' . 'screen-options.php';

                add_action('admin_init', [$this->content_calendar_methods, 'register_settings']);
                add_action('admin_print_styles', [$this->content_calendar_methods, 'add_admin_styles']);
                add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

                add_action('wp_ajax_publishpress_calendar_search_authors', ['PP_Calendar_Utilities', 'searchAuthors']);
                add_action('wp_ajax_publishpress_calendar_search_terms', ['PP_Calendar_Utilities', 'searchTerms']);
                add_action('wp_ajax_publishpress_calendar_get_data', [$this, 'fetchCalendarDataJson']);
                add_action('wp_ajax_publishpress_calendar_move_item', [$this->content_calendar_methods, 'moveCalendarItemToNewDate']);
                add_action('wp_ajax_publishpress_calendar_get_post_data', [$this, 'getPostData']);
                add_action('wp_ajax_publishpress_calendar_get_post_type_fields', [$this, 'getPostTypeFields']);
                add_action('wp_ajax_publishpress_calendar_create_item', [$this->content_calendar_methods, 'createItem']);

                // Action to regenerate the calendar feed secret
                add_action('admin_init', [$this->content_calendar_methods, 'handle_regenerate_calendar_feed_secret']);

                add_filter('post_date_column_status', [$this->content_calendar_methods, 'filter_post_date_column_status'], 12, 4);

                add_filter('pp_calendar_after_form_submission_sanitize_title', [$this, 'sanitize_text_input'], 10, 1);
                add_filter('pp_calendar_after_form_submission_sanitize_content', [$this, 'sanitize_text_input'], 10, 1);
                add_filter('pp_calendar_after_form_submission_sanitize_author', [$this, 'sanitize_author_input'], 10, 1);
                add_filter('pp_calendar_after_form_submission_validate_author', [$this, 'validateAuthorForPost'], 10, 1);
                add_filter('admin_body_class', ['PP_Calendar_Utilities', 'add_admin_body_class']);
            }

            // Clear li cache for a post when post cache is cleared
            add_action('clean_post_cache', [$this, 'action_clean_li_html_cache']);

            // Cache WordPress default date/time formats.
            $this->default_date_time_format = get_option('date_format') . ' ' . get_option('time_format');
        }

        /**
         * @param $original_template
         *
         * @return mixed
         */
        public function handle_public_calendar_feed($original_template)
        {
            // Only do .ics subscriptions when the option is active
            if ('on' != $this->module->options->ics_subscription) {
                return $original_template;
            }

            // Confirm all of the arguments are present
            if (! isset($_GET['user'], $_GET['user_key'], $_GET['pp_action'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return $original_template;
            }

            // Confirm the action
            if ('pp_calendar_ics_feed' !== $_GET['pp_action']) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return $original_template;
            }

            add_filter('pp_calendar_total_weeks', [$this->content_calendar_methods, 'filter_calendar_total_weeks_public_feed'], 10, 3);
            add_filter(
                'pp_calendar_ics_subscription_start_date',
                [$this->content_calendar_methods, 'filter_calendar_start_date_public_feed'],
                10
            );

            $this->handle_ics_subscription();

            remove_filter('pp_calendar_total_weeks', [$this->content_calendar_methods, 'filter_calendar_total_weeks_public_feed']);
            remove_filter('pp_calendar_ics_subscription_start_date', [$this->content_calendar_methods, 'filter_calendar_start_date_public_feed']);
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
            if (empty($menu_slug) && $this->module_enabled('calendar')) {
                $menu_slug = self::MENU_SLUG;
            }

            return $menu_slug;
        }

        /**
         * Creates the admin menu if there is no menu set.
         */
        public function action_admin_menu_page()
        {
            $publishpress = $this->get_service('publishpress');

            if ($publishpress->get_menu_slug() !== self::MENU_SLUG) {
                return;
            }

            $publishpress->add_menu_page(
                esc_html__('Content Calendar', 'publishpress'),
                $this->getViewCapability(),
                self::MENU_SLUG,
                [$this, 'render_admin_page']
            );
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            $publishpress = $this->get_service('publishpress');

            // Main Menu
            add_submenu_page(
                $publishpress->get_menu_slug(),
                esc_html__('Content Calendar', 'publishpress'),
                esc_html__('Content Calendar', 'publishpress'),
                $this->getViewCapability(),
                self::MENU_SLUG,
                [$this, 'render_admin_page'],
                5
            );
        }

        /**
         * Load the capabilities onto users the first time the module is run
         *
         * @since 0.7
         */
        public function install()
        {
            
            $viewCapability = $this->getViewCapability();
            $eligible_roles = ['administrator', 'editor', 'author'];

            foreach ($eligible_roles as $eligible_role) {
                $role = get_role($eligible_role);
                if (is_object($role) && !$role->has_cap($viewCapability)) {
                    $role->add_cap($viewCapability);
                }
            }
        }

        /**
         * Upgrade our data in case we need to
         *
         * @since 0.7
         */
        public function upgrade($previous_version)
        {
            global $publishpress;

            // Upgrade path to v0.7
            if (version_compare($previous_version, '0.7', '<')) {
                // Migrate whether the calendar was enabled or not and clean up old option
                if ($enabled = get_option('publishpress_calendar_enabled')) {
                    $enabled = 'on';
                } else {
                    $enabled = 'off';
                }
                $publishpress->update_module_option($this->module->name, 'enabled', $enabled);
                delete_option('publishpress_calendar_enabled');

                // Technically we've run this code before so we don't want to auto-install new data
                $publishpress->update_module_option($this->module->name, 'loaded_once', true);
            }
        }

        /**
         * Check whether the user should have the ability to view the calendar.
         * Returns true if the user can view.
         *
         * @return bool
         */
        private function currentUserCanViewCalendar()
        {
            return current_user_can($this->getViewCapability());
        }

        /**
         * Add any necessary JS to the WordPress admin
         *
         * @since 0.7
         * @uses  wp_enqueue_script()
         */
        public function enqueue_admin_scripts()
        {
            global $pagenow;

            // Only load calendar scripts on the calendar page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-calendar') {
                
                // update content calendar form action early
                $this->update_content_calendar_form_action(false);

                $this->enqueue_datepicker_resources();

                $method_args                        = [];
                $method_args['content_calendar_datas'] = $this->get_content_calendar_datas();
                $method_args['userFilters']         = $this->get_filters();
                $method_args['postStatuses']        = $this->getPostStatusOptions();
                $method_args['selectedPostTypes']   = $this->get_selected_post_types();
                $method_args['timeFormat']          = $this->getCalendarTimeFormat();
                $method_args['proActive']           = Util::isPlannersProActive();
                $method_args['operator_labels']     = $this->meta_query_operator_label();
                $method_args['post_statuses']       = $this->get_post_statuses();
                $method_args['terms_options']       = $this->terms_options;
                $method_args['form_filters']        = $this->form_filters;
                $method_args['form_filter_list']    = $this->form_filter_list;
                $method_args['all_filters']         = $this->filters;
                $this->content_calendar_methods->enqueue_admin_scripts($method_args);
            }
        }

        /**
         * After checking that the request is valid, do an .ics file
         *
         * @since 0.8
         */
        public function handle_ics_subscription()
        {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended

            // Only do .ics subscriptions when the option is active
            if ('on' != $this->module->options->ics_subscription) {
                die();
            }

            // Confirm all the arguments are present
            if (! isset($_GET['user'], $_GET['user_key'])) {
                die();
            }

            // Confirm this is a valid request
            $user = sanitize_user($_GET['user']);
            $user_key = sanitize_user($_GET['user_key']);
            $ics_secret_key = $this->module->options->ics_secret_key;
            if (! $ics_secret_key || md5($user . $ics_secret_key) !== $user_key) {
                die(esc_html($this->module->messages['nonce-failed']));
            }

            // Set up the post data to be printed
            $post_query_args = [];
            $calendar_filters = PP_Calendar_Utilities::calendar_filters();
            foreach ($calendar_filters as $filter) {
                if (isset($_GET[$filter]) && false !== ($value = $this->sanitize_filter(
                        $filter,
                        sanitize_text_field(
                            $_GET[$filter]
                        )
                    ))) {
                    $post_query_args[$filter] = $value;
                }
            }

            // Set the start date for the posts_where filter
            $this->start_date = sanitize_text_field(
                apply_filters(
                    'pp_calendar_ics_subscription_start_date',
                    PP_Calendar_Utilities::get_beginning_of_week(date('Y-m-d', current_time('timestamp'))) // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                )
            );

            $this->total_weeks = sanitize_text_field(
                apply_filters(
                    'pp_calendar_total_weeks',
                    $this->total_weeks,
                    $this->start_date,
                    'ics_subscription'
                )
            );

            $vCalendar = new Sabre\VObject\Component\VCalendar(
                [
                    'PRODID' => '-//PublishPress//PublishPress ' . PUBLISHPRESS_VERSION . '//EN',
                ]
            );

            $timezoneString = $this->dateUtil->getTimezoneString();

            PP_Calendar_Utilities::generateVtimezone(
                $vCalendar,
                $timezoneString,
                strtotime($this->start_date),
                (int)(strtotime($this->start_date) + ($this->total_weeks * 7 * 24 * 60 * 60))
            );

            $timeZone = new DateTimeZone($timezoneString);

            for ($current_week = 1; $current_week <= $this->total_weeks; $current_week++) {
                // We need to set the object variable for our posts_where filter
                $this->current_week = $current_week;
                $week_posts = $this->get_calendar_posts_for_week($post_query_args, 'ics_subscription');
                foreach ($week_posts as $date => $day_posts) {
                    foreach ($day_posts as $num => $post) {
                        if (empty($post->post_date_gmt) || $post->post_date_gmt == '0000-00-00 00:00:00') {
                            $calendar_date = get_gmt_from_date($post->post_date);
                        } else {
                            $calendar_date = $post->post_date_gmt;
                        }

                        $start_date = new DateTime($calendar_date);
                        $start_date->setTimezone($timeZone);

                        $end_date = new DateTime(date('Y-m-d H:i:s', strtotime($calendar_date) + (5 * 60))); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                        $end_date->setTimezone($timeZone);

                        if (empty($post->post_modified_gmt) || $post->post_modified_gmt == '0000-00-00 00:00:00') {
                            $calendar_modified_date = get_gmt_from_date($post->post_modified);
                        } else {
                            $calendar_modified_date = $post->post_modified_gmt;
                        }

                        $last_modified = new DateTime($calendar_modified_date);
                        $last_modified->setTimezone($timeZone);

                        // Remove the convert chars and wptexturize filters from the title
                        remove_filter('the_title', 'convert_chars');
                        remove_filter('the_title', 'wptexturize');

                        // Description should include everything visible in the calendar popup
                        $information_fields = $this->get_post_information_fields($post);
                        $eventDescription = '';
                        if (! empty($information_fields)) {
                            foreach ($information_fields as $key => $values) {
                                $eventDescription .= $values['label'] . ': ' . $values['value'] . "\n";
                            }
                            $eventDescription = rtrim($eventDescription);
                        }

                        $vCalendar->add(
                            'VEVENT',
                            [
                                'UID' => $post->guid,
                                'SUMMARY' => PP_Calendar_Utilities::do_ics_escaping(apply_filters('the_title', $post->post_title))
                                    . ' - ' . $this->get_post_status_friendly_name(get_post_status($post->ID)),
                                'DTSTART' => $start_date,
                                'DTEND' => $end_date,
                                'LAST-MODIFIED' => $last_modified,
                                'URL' => get_post_permalink($post->ID),
                                'DESCRIPTION' => $eventDescription,
                            ]
                        );
                    }
                }
            }

            // Render the .ics template and set the content type
            header('Content-type: text/calendar; charset=utf-8');
            header('Content-Disposition: inline; filename=calendar.ics');
            echo $vCalendar->serialize();

            die();
            // phpcs:enable
        }

        /**
         * Get the user's filters for calendar, either with $_GET or from saved
         *
         * @return array $filters All of the set or saved calendar filters
         * @uses get_user_meta()
         */
        public function get_filters()
        {
            return $this->update_user_filters();
        }

        /**
         * Update the current user's filters for calendar display with the filters in $_GET($request_filter). The filters
         * in $_GET($request_filter) take precedence over the current users filters if they exist.
         * @param array $request_filter
         *
         * @return array $filters updated filter
         */
        public function update_user_filters($request_filter = [])
        {
            global $pp_calendar_user_filters;

            if (is_array($pp_calendar_user_filters)) {
                return $pp_calendar_user_filters;
            }

            $user_filters = [
                'weeks'         => '',
                'start_date'    => '',
                'me_mode'       => '',
                'hide_revision' => '',
                's'             => '',
                'post_status'   => '',
                'revision_status' => '',
            ];

            if (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'reset_filter' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_calendar_filter_rest_nonce')) {
                $user_filters['weeks'] = self::DEFAULT_NUM_WEEKS;
                $user_filters['start_date'] = date('Y-m-d', current_time('timestamp'));
                return $user_filters;
            }

            $current_user  = wp_get_current_user();

            if (empty($request_filter)) {
                $request_filter = $_GET;
            }

            // Get content calendar data
            $this->content_calendar_datas = $this->get_content_calendar_datas();
            
            $filters = $this->content_calendar_datas['content_calendar_filters'];

            /**
             * @param array $filters
             *
             * @return array
             */
            $this->filters = apply_filters('publishpress_content_calendar_filters', $filters, 'update_user_filters');

            $this->filters = array_merge([
                'weeks'         => __('Weeks', 'publishpress'),
                'start_date'    => __('Start Date', 'publishpress'),
                'me_mode'       => __('Me Mode', 'publishpress'),
                'hide_revision' => __('Show Revision', 'publishpress'),
                's'             =>  __('Search', 'publishpress'),
            ], $this->filters);
            
            $editorial_metadata = $this->terms_options;

            foreach ($this->filters as $filter_key => $filter_label) {
                if (array_key_exists($filter_key, $editorial_metadata)) {
                    //add metadata to filter
                    $meta_term = $editorial_metadata[$filter_key];
                    $meta_term_type = $meta_term['type'];
                    if ($meta_term_type === 'checkbox') {
                        if (! isset($request_filter[$filter_key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            $check_value = null;
                        } else {
                            $check_value = absint($this->filter_get_param($filter_key, $request_filter));
                        }
                        $user_filters[$filter_key] = $check_value;
                    } elseif ($meta_term_type === 'date') {
                        $user_filters[$filter_key]                   = $this->filter_get_param_text($filter_key, $request_filter);
                        $user_filters[$filter_key . '_start']        = $this->filter_get_param_text($filter_key . '_start', $request_filter);
                        $user_filters[$filter_key . '_end']          = $this->filter_get_param_text($filter_key . '_end', $request_filter);
                        $user_filters[$filter_key . '_start_hidden'] = $this->filter_get_param_text($filter_key . '_start_hidden', $request_filter);
                        $user_filters[$filter_key . '_end_hidden']   = $this->filter_get_param_text($filter_key . '_end_hidden', $request_filter);
                    }  elseif ($meta_term_type === 'user') {
                        if (empty($user_filters['me_mode'])) {
                            $user_filters[$filter_key] = $this->filter_get_param_text($filter_key, $request_filter);
                        }
                    } else {
                        $user_filters[$filter_key] = $this->filter_get_param_text($filter_key, $request_filter);
                    }
                } else {
                    // other filters
                    $user_filters[$filter_key] = $this->filter_get_param_text($filter_key, $request_filter);
                    if (in_array($filter_key, $this->content_calendar_datas['meta_keys']) || in_array($filter_key, ['ppch_co_yoast_seo__yoast_wpseo_linkdex', 'ppch_co_yoast_seo__yoast_wpseo_content_score'])) {
                        $user_filters[$filter_key . '_operator'] = $this->filter_get_param_text($filter_key . '_operator', $request_filter);
                    }
                }
            }

            $current_user_filters = [];
            $current_user_filters = $this->get_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', true);

            // If any of the $_GET vars are missing, then use the current user filter
            foreach ($user_filters as $key => $value) {
                if (is_null($value) && $value !== '0' && ! empty($current_user_filters[$key]) && ! is_null($current_user_filters[$key])) {
                    $user_filters[$key] = $current_user_filters[$key];
                } elseif (is_null($value) && $value !== '0' ) {
                    $user_filters[$key] = '';
                }
            }

            if (! $this->is_revisions_active()) {
                unset($user_filters['revision_status'], $user_filters['revision_status_operator'], $user_filters['hide_revision']);
            }

            // Fix week, if no specific week was set
            if (empty($user_filters['weeks'])) {
                $user_filters['weeks'] = self::DEFAULT_NUM_WEEKS;
            }

            // Fix start_date, if no specific date was set
            if (empty($user_filters['start_date'])) {
                $user_filters['start_date'] = date('Y-m-d', current_time('timestamp'));
            }

            // Set the start date as the beginning of the week, according to blog settings
            $user_filters['start_date'] = PP_Calendar_Utilities::get_beginning_of_week($user_filters['start_date']);

            if (!empty($user_filters['me_mode'])) {
                $user_filters['author'] = $current_user->ID;
            }

            $user_filters = apply_filters('pp_content_calendar_filter_values', $user_filters, $current_user_filters);

            $this->update_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', $user_filters);

            $pp_calendar_user_filters = $user_filters;

            return $user_filters;
        }

        /**
         * Get an array of the selected post types.
         *
         * @return array
         */
        protected function get_selected_post_types()
        {
            $return = [];

            if (! isset($this->module->options->post_types)) {
                $this->module->options->post_types = [];
            }

            if (! empty($this->module->options->post_types)) {
                foreach ($this->module->options->post_types as $type => $value) {
                    if ('on' === $value) {
                        $return[] = $type;
                    }
                }
            }

            return $return;
        }

        public function content_calendar_filters()
        {
            $select_filter_names = [];
    
            $editorial_metadata = $this->terms_options;
    
            foreach ($this->filters as $filter_key => $filter_label) {
                if (array_key_exists($filter_key, $editorial_metadata) && $editorial_metadata[$filter_key]['type'] === 'date') {
                    $select_filter_names[$filter_key . '_start']        = $filter_key . '_start';
                    $select_filter_names[$filter_key . '_end']          = $filter_key . '_end';
                    $select_filter_names[$filter_key . '_start_hidden'] = $filter_key . '_start_hidden';
                    $select_filter_names[$filter_key . '_end_hidden']   = $filter_key . '_end_hidden';
                }
                $select_filter_names[$filter_key] = $filter_key;
            }
    
            return apply_filters('PP_Content_Calendar_filter_names', $select_filter_names);
        }

        /**
         * Load utilities files
         * 
         * @return void
         */
        private function load_utilities_files() {
            require_once dirname(__FILE__) . "/library/calendar-utilities.php";
            require_once dirname(__FILE__) . "/library/calendar-methods.php";
        }

        /**
         * Return calendar filters
         * @return string
         */
        public function get_calendar_filters() {

            $args = [];
            $args['user_filters']               = $this->user_filters;
            $args['calendar_filters']           = $this->content_calendar_filters();
            $args['terms_options']              = $this->terms_options;
            $args['content_calendar_datas']     = $this->content_calendar_datas;
            $args['post_statuses']              = $this->get_post_statuses();
            $args['post_types']                 = $this->get_selected_post_types();
            $args['form_filter_list']           = $this->form_filter_list;
            $args['form_filters']               = $this->form_filters;
            $args['all_filters']                = $this->filters;
            $args['operator_labels']            = $this->meta_query_operator_label();

            $calendar_filters = PP_Calendar_Utilities::get_calendar_filters($args);

            return $calendar_filters;
        }

        /**
         * Update content calendar form action
         *
         * @return void
         */
        public function update_content_calendar_form_action($show_notice = true) {
            global $publishpress;
    
            if (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'filter_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_calendar_filter_form_nonce')) {
                // Content calendar filter form
                $content_calendar_filters = !empty($_POST['content_calendar_filters']) ? array_map('sanitize_text_field', $_POST['content_calendar_filters']) : [];
                $content_calendar_filters_order = !empty($_POST['content_calendar_filters_order']) ? array_map('sanitize_text_field', $_POST['content_calendar_filters_order']) : [];
                $content_calendar_custom_filters = !empty($_POST['content_calendar_custom_filters']) ? map_deep($_POST['content_calendar_custom_filters'], 'sanitize_text_field') : [];
    
                // make sure enabled filters are saved in organized order
                $content_calendar_filters = array_intersect($content_calendar_filters_order, $content_calendar_filters);
    
                $publishpress->update_module_option($this->module->name, 'content_calendar_filters', $content_calendar_filters);
                $publishpress->update_module_option($this->module->name, 'content_calendar_custom_filters', $content_calendar_custom_filters);
                if ($show_notice) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo pp_planner_admin_notice(esc_html__('Filter updated successfully.', 'publishpress'));
                }
            } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'reset_filter' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_calendar_filter_rest_nonce')) {
                // Content calendar filter reset
                $this->update_user_meta(get_current_user_id(), self::USERMETA_KEY_PREFIX . 'filters', []);
                if ($show_notice) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo pp_planner_admin_notice(esc_html__('Filter reset successfully.', 'publishpress'));
                }
            }
        }

        /**
         * Get content calendar data that's required on the 
         * content calendar page
         *
         * @return array
         */
        public function get_content_calendar_datas() {
            global $wpdb;
    
            if (is_array($this->content_calendar_datas)) {
                return $this->content_calendar_datas;
            }
            
            $datas = [];
    
            // add all meta keys
            $datas['meta_keys'] = [];
    
            // add editorial fields
            if (class_exists('PP_Editorial_Metadata')) {
                $additional_terms = get_terms(
                    [
                        'taxonomy' => PP_Editorial_Metadata::metadata_taxonomy,
                        'orderby' => 'name',
                        'order' => 'asc',
                        'hide_empty' => 0,
                        'parent' => 0,
                        'fields' => 'all',
                    ]
                );
    
                $metadatas = [];
                foreach ($additional_terms as $term) {
                    if (! is_object($term) || $term->taxonomy !== PP_Editorial_Metadata::metadata_taxonomy) {
                        continue;
                    }

                    if ('revision_status' === $term->slug && ! $this->is_revisions_active()) {
                        continue;
                    }

                    $metadatas[$term->slug] = $term->name;
    
                    $term_options = $this->get_unencoded_description($term->description);
                    $term_options['name'] = $term->name;
                    $term_options['slug'] = $term->slug;
                    $this->terms_options[$term->slug] = $term_options;
                }
    
                $datas['editorial_metadata'] = $metadatas;
            }
    
            // add taxononomies
            $taxonomies = $this->get_post_types_taxonomies($this->get_selected_post_types());
            $all_taxonomies = [];
            foreach ($taxonomies as $taxonomy) {
                if (in_array($taxonomy->name, ['post_status', 'post_status_core_wp_pp', 'post_visibility_pp', 'pp_revision_status'])) {
                    continue;
                }
                $all_taxonomies[$taxonomy->name] = $taxonomy->label;// . ' (' . $taxonomy->name . ')';
            }
            $datas['taxonomies'] = $all_taxonomies;
    
            // Add content calendar filters content
            $content_calendar_filters = $this->module->options->content_calendar_filters;
            $content_calendar_custom_filters = $this->module->options->content_calendar_custom_filters;
    
            $datas['content_calendar_filters'] = is_array($content_calendar_filters) ? $content_calendar_filters : [
                'post_status' => esc_html__('Status', 'publishpress'),
                'author' => esc_html__('Author', 'publishpress'), 
                'cpt' => esc_html__('Post Type', 'publishpress')
            ];
            if (! $this->is_revisions_active()) {
                unset($datas['content_calendar_filters']['revision_status']);
            }

            $datas['content_calendar_custom_filters'] = is_array($content_calendar_custom_filters) ? $content_calendar_custom_filters : [];
            if (! $this->is_revisions_active()) {
                unset($datas['content_calendar_custom_filters']['revision_status']);
            }
    
            /**
             * @param array $datas
             *
             * @return $datas
             */
            $datas = apply_filters('publishpress_content_calendar_datas', $datas, compact('content_calendar_filters', 'content_calendar_custom_filters'));
    
            $this->content_calendar_datas = $datas;
    
            return $datas;
        }
    
        
        /**
         * Get content calendar form filters
         * 
         * @return array
         */
        public function get_content_calendar_form_filters() {
    
            if (!empty($this->form_filters)) {
                return $this->form_filters;
            }
    
            $content_calendar_datas   = $this->content_calendar_datas;
    
            $args = [];
            $args['content_calendar_datas'] = $content_calendar_datas;
            $args['is_revisions_active'] = $this->is_revisions_active();
            
            $filters = PP_Calendar_Utilities::get_content_calendar_form_filters($args);
    
            $this->form_filters = $filters;
    
            return $filters;
        }

        /**
         * Renders the admin page
         */
        public function render_admin_page()
        {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended
            global $publishpress;

            // update content calendar form action
            $this->update_content_calendar_form_action();

            // Get content calendar data
            $this->content_calendar_datas = $this->get_content_calendar_datas();
            
            $filters = $this->content_calendar_datas['content_calendar_filters'];
            /**
             * @param array $filters
             *
             * @return array
             */
            $this->filters = apply_filters('publishpress_content_calendar_filters', $filters, 'render_admin_page');
    
            $this->form_filters = $this->get_content_calendar_form_filters();
            $this->form_filter_list = array_merge(...array_values(array_column($this->form_filters, 'filters')));

            // Get filters either from $_GET or from user settings
            $this->user_filters = $this->get_filters();

            // Total number of weeks to display on the calendar. Run it through a filter in case we want to override the
            // user's standard
            $this->total_weeks = empty($this->user_filters['weeks']) ? self::DEFAULT_NUM_WEEKS : $this->user_filters['weeks'];

            $this->start_date = empty($this->user_filters['start_date']) ? date('Y-m-d', current_time('timestamp')) :  $this->user_filters['start_date'];

            // Get the custom description for this page
            $description = '';

            // Should we display the subscribe button?
            if ('on' == $this->module->options->ics_subscription && $this->module->options->ics_secret_key) {
                // Prepare the download link
                $args = [
                    'action' => 'pp_calendar_ics_subscription',
                    'user' => wp_get_current_user()->user_login,
                    'user_key' => md5(wp_get_current_user()->user_login . $this->module->options->ics_secret_key),
                    '_wpnonce' => wp_create_nonce('publishpress_calendar_ics_sub'),
                ];

                // Prepare the subscribe link for the feed
                unset($args['action']);
                $args['post_status'] = 'unpublish';
                $args['pp_action'] = 'pp_calendar_ics_feed';
                $subscription_link = add_query_arg($args, site_url());

                $description .= '<div class="calendar-subscribe">';

                add_thickbox();

                ob_start(); ?>
                <?php
                PP_Calendar_Utilities::calendar_ics_subs_html($subscription_link);
                $description .= ob_get_clean();

                $description .= '</div>';
            }

            $publishpress->settings->print_default_header($publishpress->modules->calendar, $description); ?>
            <div class="wrap">
                <?php
                // Handle posts that have been trashed or untrashed
                if (isset($_GET['trashed']) || isset($_GET['untrashed'])) {
                    echo '<div id="trashed-message" class="updated"><p>';
                    if (isset($_GET['trashed']) && (int)$_GET['trashed']) {
                        printf(
                            _n(
                                'Post moved to the trash.',
                                '%d posts moved to the trash.',
                                (int)$_GET['trashed'],
                                'publishpress'
                            ),
                            number_format_i18n((int)$_GET['trashed'])
                        );
                        $ids = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : 0;
                        $pid = explode(',', $ids);
                        $post_type = get_post_type($pid[0]);
                        echo ' <a href="' . esc_url(
                                wp_nonce_url(
                                    esc_url_raw("edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids"),
                                    'bulk-posts'
                                )
                            ) . '">' . esc_html__(
                                'Undo',
                                'publishpress'
                            ) . ' <span class="dashicons dashicons-undo"></span></a><br />';
                        unset($_GET['trashed']);
                    }


                    if (isset($_GET['untrashed']) && (int)$_GET['untrashed']) {
                        printf(
                            _n(
                                'Post restored from the Trash.',
                                '%d posts restored from the Trash.',
                                (int)$_GET['untrashed'],
                                'publishpress'
                            ),
                            number_format_i18n((int)$_GET['untrashed'])
                        );
                        unset($_GET['undeleted']);
                    }
                    echo '</p></div>';
                } ?>

                <div class="publishpress-calendar-filter-bar">
                    <?php echo $this->get_calendar_filters(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>

                <div id="publishpress-calendar-wrap" class="publishpress-calendar-wrap">
                    <div class="publishpress-calendar-loader">
                        <div class="publishpress-calendar-loader-message">
                            <div class="sk-cube-grid">
                                <div class="sk-cube sk-cube1"></div>
                                <div class="sk-cube sk-cube2"></div>
                                <div class="sk-cube sk-cube3"></div>
                                <div class="sk-cube sk-cube4"></div>
                                <div class="sk-cube sk-cube5"></div>
                                <div class="sk-cube sk-cube6"></div>
                                <div class="sk-cube sk-cube7"></div>
                                <div class="sk-cube sk-cube8"></div>
                                <div class="sk-cube sk-cube9"></div>
                            </div>
                            <div><?php
                                echo __('Initializing the calendar. Please wait...', 'publishpress'); ?></div>
                        </div>
                        <div class="publishpress-calendar-loader-delayed-tip">
                            <?php
                            echo __(
                                'It seems like it is taking too long. Please, try reloading the page again and check the browser console looking for errors.',
                                'publishpress'
                            ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="pp-content-calendar-general-modal" style="display: none;">
                <div id="pp-content-calendar-general-modal-container" class="pp-content-calendar-general-modal-container publishpress-quick-modal"></div>
            </div>
            <?php
           wp_localize_script(
                'publishpress-calendar-js',
                'PPContentCalendar',
                [
                    'nonce' => wp_create_nonce('publishpress-calendar-get-data'),
                    'moduleUrl' => $this->module_url,
                    'publishpressUrl' => PUBLISHPRESS_URL,
                ]
            );

            $publishpress->settings->print_default_footer($publishpress->modules->calendar);
            // phpcs:enable
        }

        /**
         * Get the information fields to be presented with each post popup
         *
         * @param obj $post Post to gather information fields for
         *
         * @return array $information_fields All of the information fields to be presented
         * @since 0.8
         *
         */
        public function get_post_information_fields($post)
        {
            $information_fields = [];
            // Post author
            $authorsNames = apply_filters(
                'publishpress_post_authors_names',
                [get_the_author_meta('display_name', $post->post_author)],
                $post->ID
            );

            $information_fields['author'] = [
                'label' => _n('Author', 'Authors', count($authorsNames), 'publishpress'),
                'value' => implode(', ', $authorsNames),
                'type' => 'author',
            ];

            // If the calendar supports more than one post type, show the post type label
            if (count($this->get_post_types_for_module($this->module)) > 1) {
                $information_fields['post_type'] = [
                    'label' => __('Post Type', 'publishpress'),
                    'value' => get_post_type_object($post->post_type)->labels->singular_name,
                ];
            }
            // Publication time for published statuses
            if (in_array($post->post_status, $this->published_statuses)) {
                if ($post->post_status == 'future') {
                    $information_fields['post_date'] = [
                        'label' => __('Scheduled', 'publishpress'),
                        'value' => get_the_time(null, $post->ID),
                    ];
                } else {
                    $information_fields['post_date'] = [
                        'label' => __('Published', 'publishpress'),
                        'value' => get_the_time(null, $post->ID),
                    ];
                }
            }
            // Taxonomies and their values
            $args = [
                'post_type' => $post->post_type,
            ];
            $taxonomies = get_object_taxonomies($args, 'object');
            foreach ((array)$taxonomies as $taxonomy) {
                // Sometimes taxonomies skip by, so let's make sure it has a label too
                if (! $taxonomy->public || ! $taxonomy->label) {
                    continue;
                }

                $terms = get_the_terms($post->ID, $taxonomy->name);
                if (! $terms || is_wp_error($terms)) {
                    continue;
                }

                $key = 'tax_' . $taxonomy->name;
                if (count($terms)) {
                    $value = '';
                    foreach ((array)$terms as $term) {
                        $value .= $term->name . ', ';
                    }
                    $value = rtrim($value, ', ');
                } else {
                    $value = '';
                }
                // Used when editing editorial fields and post meta
                if (is_taxonomy_hierarchical($taxonomy->name)) {
                    $type = 'taxonomy hierarchical';
                } else {
                    $type = 'taxonomy';
                }

                $information_fields[$key] = [
                    'label' => $taxonomy->label,
                    'value' => $value,
                    'type' => $type,
                ];

                if ($post->post_type == 'page') {
                    $ed_cap = 'edit_page';
                } else {
                    $ed_cap = 'edit_post';
                }

                if (current_user_can($ed_cap, $post->ID)) {
                    $information_fields[$key]['editable'] = true;
                }
            }

            $information_fields = apply_filters('pp_calendar_item_information_fields', $information_fields, $post->ID);

            foreach ($information_fields as $field => $values) {
                // Allow filters to hide empty fields or to hide any given individual field. Hide empty fields by default.
                if ((apply_filters(
                            'pp_calendar_hide_empty_item_information_fields',
                            true,
                            $post->ID
                        ) && empty($values['value']))
                    || apply_filters("pp_calendar_hide_{$field}_item_information_field", false, $post->ID)) {
                    unset($information_fields[$field]);
                }
            }

            return $information_fields;
        }

        /**
         * Query to get all of the calendar posts for a given day
         *
         * @param array $args Any filter arguments we want to pass
         * @param string $request_context Where the query is coming from, to distinguish dashboard and subscriptions
         *
         * @return array $posts All of the posts as an array sorted by date
         */
        public function get_calendar_posts_for_week($args = [], $context = 'dashboard')
        {
            $supported_post_types = $this->get_post_types_for_module($this->module);
            $defaults = [
                'post_status' => null,
                'cat' => null,
                'tag' => null,
                'author' => null,
                'post_type' => $supported_post_types,
                'posts_per_page' => -1,
                'order' => 'ASC',
            ];

            $args = array_merge($defaults, $args);

            // Unpublished as a status is just an array of everything but 'publish'
            if ($args['post_status'] == 'unpublish' || $context == 'ics_subscription') {
                $args['post_status'] = '';
                $post_statuses = $this->get_post_statuses();
                foreach ($post_statuses as $post_status) {
                    $args['post_status'] .= $post_status->slug . ', ';
                }
                $args['post_status'] = rtrim($args['post_status'], ', ');
                // Optional filter to include scheduled content as unpublished
                if (apply_filters('pp_show_scheduled_as_unpublished', true)) {
                    $args['post_status'] .= ', future';
                }
                if ($context == 'ics_subscription') {
                    $args['post_status'] .= ', publish';
                }

                if (isset($this->module->options->ics_subscription_public_visibility) && 'on' === $this->module->options->ics_subscription_public_visibility) {
                    $args['suppress_filters'] = true;
                }
            }
            // The WP functions for printing the category and author assign a value of 0 to the default
            // options, but passing this to the query is bad (trashed and auto-draft posts appear!), so
            // unset those arguments.
            if ($args['cat'] === '0') {
                unset($args['cat']);
            }
            if ($args['tag'] === '0') {
                unset($args['tag']);
            } else {
                $args['tag_id'] = $args['tag'];
                unset($args['tag']);
            }

            if ($args['author'] === '0') {
                unset($args['author']);
            }

            if (empty($args['post_type']) || ! in_array($args['post_type'], $supported_post_types)) {
                $args['post_type'] = $supported_post_types;
            }

            // Filter for an end user to implement any of their own query args
            $args = apply_filters('pp_calendar_posts_query_args', $args, $context, $this->filters, $this->user_filters);

            add_filter('posts_where', [$this, 'posts_where_week_range']);
            $post_results = new WP_Query($args);
            remove_filter('posts_where', [$this, 'posts_where_week_range']);

            $posts = [];
            while ($post_results->have_posts()) {
                $post_results->the_post();
                global $post;
                $key_date = date('Y-m-d', strtotime($post->post_date));
                $posts[$key_date][] = $post;
            }

            return $posts;
        }

        /**
         * Filter the WP_Query so we can get a week range of posts
         *
         * @param string $where The original WHERE SQL query string
         *
         * @return string $where Our modified WHERE query string
         */
        public function posts_where_week_range($where = '')
        {
            global $wpdb;

            $beginning_date = PP_Calendar_Utilities::get_beginning_of_week($this->start_date, 'Y-m-d', $this->current_week);
            $ending_date = PP_Calendar_Utilities::get_ending_of_week($this->start_date, 'Y-m-d', $this->current_week);
            // Adjust the ending date to account for the entire day of the last day of the week
            $ending_date = date('Y-m-d', strtotime('+1 day', strtotime($ending_date)));
            $where = $where . $wpdb->prepare(
                    " AND ($wpdb->posts.post_date >= %s AND $wpdb->posts.post_date < %s)",
                    $beginning_date,
                    $ending_date
                );

            return $where;
        }

        private function getCalendarTimeFormat()
        {
            return ! isset($this->module->options->posts_publish_time_format) || is_null(
                $this->module->options->posts_publish_time_format
            )
                ? self::TIME_FORMAT_12H_NO_LEADING_ZEROES
                : $this->module->options->posts_publish_time_format;
        }

        /**
         * Validate the data submitted by the user in calendar settings
         *
         * @since 0.7
         */
        public function settings_validate($new_options)
        {
            $options = (array)$this->module->options;

            if (isset($new_options['post_types'])) {
                // Set post as default
                $empty = true;
                foreach ($options['post_types'] as $value) {
                    if ('on' === $value) {
                        $empty = false;
                        break;
                    }
                }

                if ($empty) {
                    // Check post by default
                    $options['post_types'] = ['post' => 'on'];

                    // Add flag to display a warning to the user
                    set_transient(static::TRANSIENT_SHOW_ONE_POST_TYPE_WARNING, 1, 300);
                } else {
                    $options['post_types'] = $this->clean_post_type_options(
                        $new_options['post_types'],
                        $this->module->post_type_support
                    );
                }
            } else {
                // Check post by default
                $options['post_types'] = ['post' => 'on'];

                // Add flag to display a warning to the user
                set_transient(static::TRANSIENT_SHOW_ONE_POST_TYPE_WARNING, 1, 300);
            }

            if ('on' != $new_options['ics_subscription']) {
                $options['ics_subscription'] = 'off';
            } else {
                $options['ics_subscription'] = 'on';
            }

            if (isset($new_options['show_posts_publish_time'])) {
                if ($new_options['show_posts_publish_time'] == 'on') {
                    $options['show_posts_publish_time'] = [
                        'publish' => 'on',
                        'future' => 'on',
                    ];
                } else {
                    $options['show_posts_publish_time'] = $new_options['show_posts_publish_time'];
                }
            } else {
                $options['show_posts_publish_time'] = [];
            }

            $options['posts_publish_time_format'] = isset($new_options['posts_publish_time_format'])
                ? $new_options['posts_publish_time_format']
                : self::TIME_FORMAT_12H_NO_LEADING_ZEROES;

            if (isset($new_options['show_calendar_posts_full_title'])) {
                $options['show_calendar_posts_full_title'] = 'on';
            } else {
                $options['show_calendar_posts_full_title'] = 'off';
            }

            if (isset($new_options['calendar_today_in_first_row'])) {
                $options['calendar_today_in_first_row'] = 'on';
            } else {
                $options['calendar_today_in_first_row'] = 'off';
            }

            if (isset($new_options['ics_subscription_public_visibility'])) {
                $options['ics_subscription_public_visibility'] = 'on';
            } else {
                $options['ics_subscription_public_visibility'] = 'off';
            }

            // Default publish time
            if (isset($new_options['default_publish_time'])) {
                $options['default_publish_time'] = sanitize_text_field($new_options['default_publish_time']);
            }

            // Sort by
            $options['sort_by'] = isset($new_options['sort_by'])
                ? sanitize_text_field($new_options['sort_by'])
                : 'time';

            // Max visible posts per date
            $options['max_visible_posts_per_date'] = isset($new_options['max_visible_posts_per_date'])
                ? (int)$new_options['max_visible_posts_per_date']
                : $this->default_max_visible_posts_per_date;

            return $options;
        }

        /**
         * Settings page for calendar
         */
        public function print_configure_view()
        {
            global $publishpress; ?>
            <form class="basic-settings"
                  action="<?php
                  echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
                <?php
                settings_fields($this->module->options_group_name); ?>
                <?php
                do_settings_sections($this->module->options_group_name); ?>
                <?php
                echo '<input id="publishpress_module_name" name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <p class="submit"><?php
                    submit_button(null, 'primary', 'submit', false); ?></p>
                <?php
                echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <?php
                wp_nonce_field('edit-publishpress-settings'); ?>
            </form>
            <?php
        }

        private function getPostTypeObject($postType)
        {
            if (! isset($this->postTypeObjectCache[$postType])) {
                $this->postTypeObjectCache[$postType] = get_post_type_object($postType);
            }

            return $this->postTypeObjectCache[$postType];
        }

        private function addTaxQueryToArgs($taxonomy, $termSlug, $args)
        {
            if (! isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }

            $args['tax_query'][] = [
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $termSlug
            ];

            return $args;
        }

        public function fetchCalendarDataJson()
        {
            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json([], 403);
            }

            $beginningDate = PP_Calendar_Utilities::get_beginning_of_week(sanitize_text_field($_GET['start_date']));
            $endingDate = PP_Calendar_Utilities::get_ending_of_week($beginningDate, 'Y-m-d', (int)$_GET['number_of_weeks']);

            //get and update filters
            $args = $this->get_filters();

            $method_args                        = [];
            $method_args['content_calendar_datas'] = $this->get_content_calendar_datas();
            $method_args['userFilters']         = $args;
            $method_args['postStatuses']        = $this->getPostStatusOptions();
            $method_args['selectedPostTypes']   = $this->get_selected_post_types();
            $method_args['timeFormat']          = $this->getCalendarTimeFormat();
            $method_args['proActive']           = Util::isPlannersProActive();
            $method_args['operator_labels']     = $this->meta_query_operator_label();
            $method_args['post_statuses']       = $this->get_post_statuses();
            $method_args['terms_options']       = $this->terms_options;
            $method_args['form_filters']        = $this->form_filters;
            $method_args['form_filter_list']    = $this->form_filter_list;
            $method_args['all_filters']         = $this->filters;

            wp_send_json(
                $this->content_calendar_methods->getCalendarData($beginningDate, $endingDate, $args, $method_args),
                200
            );
        }

        private function getPostTypeName($postType)
        {
            $postTypeObj = get_post_type_object($postType);

            return $postTypeObj->label;
        }

        private function getPostStatusName($postStatusSlug)
        {
            $postStatuses = $this->get_post_statuses();

            foreach ($postStatuses as $postStatus) {
                if ($postStatus->slug === $postStatusSlug) {
                    return $postStatus->label;
                }
            }

            return null;
        }

        private function getPostTerms($postId, $taxonomy)
        {
            $terms = wp_get_post_terms($postId, $taxonomy);

            $termsNames = [];
            foreach ($terms as $term) {
                $termsNames[] = $term->name;
            }

            return $termsNames;
        }

        private function getPostCategoriesNames($postId)
        {
            return $this->getPostTerms($postId, 'category');
        }

        private function getPostTagsNames($postId)
        {
            return $this->getPostTerms($postId, 'post_tag');
        }

        public function getPostData()
        {
            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json([], 403);
            }

            $id = (int)$_GET['id'];

            if (empty($id)) {
                wp_send_json(null, 404);
            }

            $post = get_post($id);

            if (empty($post) || is_wp_error($post)) {
                wp_send_json(null, 404);
            }

            $args = [];
            $args['id'] = $id;
            $args['post']   = $post;
            $args['type'] = $this->getPostTypeName($post->post_type);
            $args['date'] = get_the_date(get_option('date_format', 'Y-m-d H:i:s'), $post);
            $args['status'] = $this->getPostStatusName($post->post_status);
            $args['categories']   = $this->getPostCategoriesNames($id);
            $args['tags']   = $this->getPostTagsNames($id);

            $data = PP_Calendar_Utilities::getPostData($args);

            wp_send_json($data, 202);
        }

        public function getPostTypeFields()
        {
            global $publishpress;

            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json([], 403);
            }

            $postType = isset($_GET['postType']) ? sanitize_text_field($_GET['postType']) : 'post';
            $postTypeObject = get_post_type_object($postType);
            if (empty($postTypeObject) || is_wp_error($postTypeObject)) {
                wp_send_json([], 404);
            }

            $data = $this->content_calendar_methods->getPostTypeFields($this->getUserAuthorizedPostStatusOptions($postType));

            wp_send_json($data, 202);
        }

        private function formatDateFromString($date, $originalFormat = 'Y-m-d')
        {
            $datetime = date_create_immutable_from_format($originalFormat, $date);
            return $datetime->format(get_option('date_format', 'Y-m-d H:i:s'));
        }

        /**
         * Sanitize a $_GET or similar filter being used on the calendar
         *
         * @param string $key Filter being sanitized
         * @param string $dirty_value Value to be sanitized
         *
         * @return string $sanitized_value Safe to use value
         * @since 0.8
         *
         */
        public function sanitize_filter($key, $dirty_value)
        {
            switch ($key) {
                case 'post_status':
                    // Whitelist-based validation for this parameter
                    $valid_statuses = wp_list_pluck($this->get_post_statuses(), 'slug');
                    $valid_statuses[] = 'future';
                    $valid_statuses[] = 'unpublish';
                    $valid_statuses[] = 'publish';
                    $valid_statuses[] = 'trash';
                    if (in_array($dirty_value, $valid_statuses)) {
                        return $dirty_value;
                    } else {
                        return '';
                    }
                    break;
                case 'cpt':
                    $cpt = sanitize_key($dirty_value);
                    $supported_post_types = $this->get_post_types_for_module($this->module);
                    if ($cpt && in_array($cpt, $supported_post_types)) {
                        return $cpt;
                    } else {
                        return '';
                    }
                    break;
                case 'start_date':
                    return date('Y-m-d', strtotime($dirty_value));
                    break;
                case 'cat':
                case 'tag':
                case 'author':
                    return intval($dirty_value);
                    break;
                case 'weeks':
                    $weeks = intval($dirty_value);

                    return empty($weeks) ? self::DEFAULT_NUM_WEEKS : $weeks;
                    break;
                default:
                    return sanitize_text_field($dirty_value);
                    break;
            }
        }

        /**
         * When a post is updated, clean the <li> html post cache for it
         */
        public function action_clean_li_html_cache($post_id)
        {
            wp_cache_delete($post_id . 'can_modify', self::$post_li_html_cache_key);
            wp_cache_delete($post_id . 'read_only', self::$post_li_html_cache_key);
        }

        /**
         * Sanitizes a given string input.
         *
         * @param string $input_value
         *
         * @return  string
         */
        public static function sanitize_text_input($input_value = '')
        {
            return sanitize_text_field($input_value);
        }

        /**
         * Sanitizes a given author id.
         *
         * @param array $authorsIds
         *
         * @return  array
         */
        public static function sanitize_author_input($authorsIds = [])
        {
            return array_map('intval', $authorsIds);
        }

        /**
         * @param array $postAuthorIds
         *
         * @return  array
         *
         * @throws  Exception
         */
        public static function validateAuthorForPost($postAuthorIds = [])
        {
            if (empty($postAuthorIds)) {
                return null;
            }

            foreach ($postAuthorIds as $authorId) {
                $user = get_user_by('id', $authorId);

                $isValid = is_object($user) && ! is_wp_error($user) && $user->has_cap('edit_posts');

                if (! apply_filters('publishpress_author_can_edit_posts', $isValid, $authorId)) {
                    throw new Exception(
                        esc_html__(
                            "The selected user doesn't have enough permissions to be set as the post author.",
                            'publishpress'
                        )
                    );
                }
            }

            return $postAuthorIds;
        }

        public function setDefaultCapabilities()
        {
            $role = get_role('administrator');

            $viewCapability = $this->getViewCapability();

            if (! $role->has_cap($viewCapability)) {
                $role->add_cap($viewCapability);
            }
        }

        private function getViewCapability()
        {
            $viewCapability = apply_filters_deprecated(
                    'pp_view_calendar_cap',
                    [self::VIEW_CAPABILITY],
                '3.6.4',
                'publishpress_calendar_cap_view_calendar'
            );

            return apply_filters('publishpress_calendar_cap_view_calendar', $viewCapability);
        }
    }
}
