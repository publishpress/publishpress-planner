<?php
/**
 * Calendar Methods class
 */

if (! class_exists('PP_Calendar_Methods')) {

    #[\AllowDynamicProperties]
    class PP_Calendar_Methods extends PP_Module
    {

        /**
         * Name of the transient option to flag the warning for selecting
         * at least one post type
         */
        const TRANSIENT_SHOW_ONE_POST_TYPE_WARNING = 'show_one_post_type_warning';

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

        /**
         * [$module description]
         *
         * @var [type]
         */
        public $module;

        public $module_url;

        private $create_post_cap;

        /**
         * @var array
         */
        private $postTypeObjectCache = [];

        /**
         * Total number of posts to be shown per square before 'more' link
         *
         * @var int
         */
        public $default_max_visible_posts_per_date = 4;

        /**
         * PP_Calendar_Settings constructor.
         *
         * @param array $args
         */
        public function __construct($args = [])
        {
            $this->module_url = $args['module_url'];
            $this->module = $args['module'];
            // Define the create-post capability
            $this->create_post_cap = apply_filters('pp_calendar_create_post_cap', 'edit_posts');
        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * We use the Settings API for form generation, but not saving because we have our
         * own way of handling the data.
         *
         * @since 0.7
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
                __('Post types to show', 'publishpress'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'calendar_today_in_first_row',
                __('Show today\'s date in the first row', 'publishpress'),
                [$this, 'settings_calendar_today_in_first_row_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'ics_subscription',
                __('Enable subscriptions in iCal or Google Calendar', 'publishpress'),
                [$this, 'settings_ics_subscription_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'ics_subscription_public_visibility',
                __('Allow public access to subscriptions in iCal or Google Calendar', 'publishpress'),
                [$this, 'settings_ics_subscription_public_visibility_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'show_posts_publish_time',
                __('Statuses to display publish time', 'publishpress'),
                [$this, 'settings_show_posts_publish_time_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'posts_publish_time_format',
                __('Posts publish time format', 'publishpress'),
                [$this, 'settings_posts_publish_time_format_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'default_publish_time',
                __('Default publish time for items created in the calendar', 'publishpress'),
                [$this, 'settings_default_publish_time_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'sort_by',
                __('Field used for sorting the calendar items in a day cell', 'publishpress'),
                [$this, 'settings_sort_by_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'max_visible_posts_per_date',
                __('Max visible posts per date', 'publishpress'),
                [$this, 'settings_max_visible_posts_per_date'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );

            add_settings_field(
                'show_calendar_posts_full_title',
                __('Always show complete post titles', 'publishpress'),
                [$this, 'settings_show_calendar_posts_full_title_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }


        /**
         * Choose the post types that should be displayed on the calendar
         *
         * @since 0.7
         */
        public function settings_post_types_option()
        {
            global $publishpress;
            $publishpress->settings->helper_option_custom_post_type($this->module);

            // Check if we need to display the message about selecting at lest one post type
            if (get_transient(static::TRANSIENT_SHOW_ONE_POST_TYPE_WARNING)) {
                echo '<p class="psppca_field_warning">' . __(
                        'At least one post type must be selected',
                        'publishpress'
                    ) . '</p>';

                delete_transient(static::TRANSIENT_SHOW_ONE_POST_TYPE_WARNING);
            }
        }

        /**
         * Option that define either Posts publish times are displayed or not.
         *
         * @since 1.20.0
         */
        public function settings_show_posts_publish_time_option()
        {
            global $publishpress;

            $field_name = esc_attr($this->module->options_group_name) . '[show_posts_publish_time]';

            $customStatuses = defined('PUBLISHPRESS_STATUSES_VERSION') ? $publishpress->getPostStatuses() : $publishpress->getCustomStatuses();

            if (empty($customStatuses)) {
                $statuses = [
                    'publish' => __('Publish'),
                    'future' => __('Scheduled'),
                ];
            } else {
                $statuses = [];

                foreach ($customStatuses as $status) {
                    $statuses[$status->slug] = ['title' => $status->label, 'status_obj' => $status, 'for_revision' => !empty($status->for_revision)];
                }
            }

            // Add support to the legacy value for this setting, where "on" means post and page selected.
            if ($this->module->options->show_posts_publish_time === 'on') {
                $this->module->options->show_posts_publish_time = [
                    'publish' => 'on',
                    'future' => 'on',
                ];
            }

            if (empty($customStatuses)) {
                foreach ($statuses as $status => $title) {
                    $id = esc_attr($status) . '-display-publish-time';

                    echo '<div><label for="' . $id . '">';
                    echo '<input id="' . $id . '" name="' . $field_name . '[' . esc_attr($status) . ']"';

                    if (isset($this->module->options->show_posts_publish_time[$status])) {
                        checked($this->module->options->show_posts_publish_time[$status], 'on');
                    }

                    // Defining post_type_supports in the functions.php file or similar should disable the checkbox
                    disabled(post_type_supports($status, $this->module->post_type_support), true);

                    echo ' type="checkbox" value="on" />&nbsp;'
                    . esc_html($title)
                    . '</span>'
                    . '</label>';
                }

                $show_statuses_prompt = true;

            } else {
                echo '<style>div.pp-calendar-settings div {padding: 4px 0 8px 0;} div.pp-calendar-settings a {vertical-align: bottom}</style>';

                echo '<div class="pp-calendar-settings">';

                foreach ($statuses as $status => $arr_status) {
                    $id = esc_attr($status) . '-display-publish-time';

                    if ($arr_status['for_revision'] && empty($in_revisions_section)) {
                        $style = 'margin-top: 30px;';
                        $in_revisions_section = true;
                    } else {
                        $style = '';
                    }

                    echo '<div style="' . esc_attr($style) . '"><label for="' . $id . '">';
                    echo '<input id="' . $id . '" name="' . $field_name . '[' . esc_attr($status) . ']"';

                    if (isset($this->module->options->show_posts_publish_time[$status])) {
                        checked($this->module->options->show_posts_publish_time[$status], 'on');
                    }

                    // Defining post_type_supports in the functions.php file or similar should disable the checkbox
                    disabled(post_type_supports($status, $this->module->post_type_support), true);

                    echo ' type="checkbox" value="on" />&nbsp;';

                    echo '<span class="dashicons ' . esc_html($arr_status['status_obj']->icon) . '"></span>&nbsp;';

                    $style = 'background:' . $arr_status['status_obj']->color . '; color:white';

                    echo '<span class="pp-status-color pp-status-color-title" style="' . esc_attr($style) . '">'
                    . esc_html($arr_status['title'])
                    . '</span>'
                    . '</label>';

                    if (class_exists('PublishPress_Statuses')) {
                        $_args = [
                            'action' => 'edit-status',
                            'return_module' => 'pp-calendar-settings',
                        ];

                        $_args['name'] = $arr_status['status_obj']->name;

                        $item_edit_link = esc_url(
                            PublishPress_Statuses::get_link(
                                $_args
                            )
                        );

                        echo ' <a href="' . $item_edit_link . '">' . __('edit', 'publishpress') . '</a>';
                    }

                    echo '</div>';
                }

                $show_statuses_pro_revisions_prompt = true;
            }

           if (defined('PUBLISHPRESS_REVISIONS_VERSION') && defined('PUBLISHPRESS_PRO_VERSION') && version_compare(PUBLISHPRESS_REVISIONS_VERSION, '3.6.0-rc', '<')) :
                ?>
                <div id="pp-revisions-plugin-prompt" class="activating pp-plugin-prompt">
                <?php
                $msg = (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION'))
                ? esc_html__('For Revisions integration on the Content Calendar, Overview and Content Board, please update %sPublishPress Revisions Pro%s.', 'publishpress')
                : esc_html__('For Revisions integration on the Content Calendar, Overview and Content Board, please update %sPublishPress Revisions%s.', 'publishpress');

                printf(
                    $msg,
                    '<a href="' . esc_url(self_admin_url('plugins.php')) . '" target="_blank">',
                    '</a>'
                );
                ?>
                </div>
            <?php endif;

            if (!empty($show_statuses_prompt)) {
                if (!defined('PUBLISHPRESS_STATUSES_VERSION')) :
                    ?>
                    <div id="pp-statuses-plugin-prompt" class="activating pp-plugin-prompt">
                    <?php
                    printf(
                        esc_html__('To refine your workflow with custom Post Statuses, install the %sPublishPress Statuses%s plugin.', 'publishpress'),
                        '<a href="' . esc_url(self_admin_url('plugin-install.php?s=publishpress-statuses&tab=search&type=term')) . '" target="_blank">',
                        '</a>'
                    );
                    ?>
                    </div>
                <?php endif;

            } elseif (!empty($show_statuses_pro_revisions_prompt)) {
                if (defined('PUBLISHPRESS_REVISIONS_VERSION') && defined('PUBLISHPRESS_STATUSES_VERSION') && !defined('PUBLISHPRESS_STATUSES_PRO_VERSION')) :
                    ?>
                    <div id="pp-statuses-pro-plugin-prompt" class="activating pp-plugin-prompt">
                    <?php
                    printf(
                        esc_html__('For custom Revision Statuses, upgrade to %sPublishPress Statuses Pro%s.', 'publishpress'),
                        '<a href="https://publishpress.com/statuses/" target="_blank">',
                        '</a>'
                    );
                    ?>
                    </div>
                <?php endif;
            }

            echo '</div>';
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
         * Define the time format for Posts publish date.
         *
         * @since 1.20.0
         */
        public function settings_posts_publish_time_format_option()
        {
            $timeFormats = [
                self::TIME_FORMAT_12H_NO_LEADING_ZEROES => '1-12 am/pm',
                self::TIME_FORMAT_12H_WITH_LEADING_ZEROES => '01-12 am/pm',
                self::TIME_FORMAT_24H => '00-23',
            ];

            $posts_publish_time_format = $this->getCalendarTimeFormat();

            echo '<div class="c-input-group c-pp-calendar-options-posts_publish_time_format">';

            foreach ($timeFormats as $timeFormat => $timeMockValue) {
                printf(
                    '
                    <div style="max-width: 175px; display: flex; flex-direction: row; justify-content: space-between; margin-bottom: 5px;">
                        <label>
                            <input
                                class="o-radio"
                                type="radio"
                                name="%s"
                                value="%s"
                                %s
                            />
                            <span>%s</span>
                        </label>
                        <code>%2$s</code>
                    </div>',
                    esc_attr($this->module->options_group_name) . '[posts_publish_time_format]',
                    $timeFormat,
                    $posts_publish_time_format === $timeFormat ? 'checked' : '',
                    $timeMockValue
                );
            }

            echo '</div>';
        }

        /**
         * @since 2.0.7
         */
        public function settings_default_publish_time_option()
        {
            echo '<div class="c-input-group">';

            echo sprintf(
                '<input type="text" name="%s" value="%s" class="time-pick" readonly>',
                esc_attr($this->module->options_group_name) . '[default_publish_time]',
                $this->module->options->default_publish_time
            );

            echo '</div>';
        }

        public function settings_sort_by_option()
        {
            $fields = [
                'time' => __('Publishing Time', 'publishpress'),
                'status' => __('Post Status', 'publishpress'),
            ];

            $sortByOptionValue = ! isset($this->module->options->sort_by) || is_null(
                $this->module->options->sort_by
            )
                ? 'time'
                : $this->module->options->sort_by;

            echo '<div class="c-input-group c-pp-calendar-options-sort_by">';

            foreach ($fields as $key => $label) {
                printf(
                    '
                    <div style="max-width: 175px; display: flex; flex-direction: row; justify-content: space-between; margin-bottom: 5px;">
                        <label>
                            <input
                                class="o-radio"
                                type="radio"
                                name="%s"
                                value="%s"
                                %s
                            />
                            <span>%s</span>
                        </label>
                    </div>',
                    esc_attr($this->module->options_group_name) . '[sort_by]',
                    $key,
                    $key === $sortByOptionValue ? 'checked' : '',
                    $label
                );
            }

            echo '</div>';
        }

        public function settings_max_visible_posts_per_date()
        {
            $maxVisiblePostsPerDate = ! isset($this->module->options->max_visible_posts_per_date) || is_null(
                $this->module->options->max_visible_posts_per_date
            )
                ? (int)$this->default_max_visible_posts_per_date
                : (int)$this->module->options->max_visible_posts_per_date;

            echo '<div class="c-input-group c-pp-calendar-options-max_visible_posts_per_date">';

            echo sprintf(
                '<select name="%s" id="%d">',
                esc_attr($this->module->options_group_name) . '[max_visible_posts_per_date]',
                'max_visible_posts_per_date'
            );

            echo sprintf(
                '<option value="-1" %s>%s</option>',
                selected($maxVisiblePostsPerDate, -1, false),
                __('All posts', 'publishpress')
            );

            for ($i = 4; $i <= 30; $i++) {
                echo sprintf(
                    '<option value="%2$d" %s>%2$d</option>',
                    selected($maxVisiblePostsPerDate, $i, false),
                    $i
                );
            }

            echo '</select></div>';
        }

        public function settings_show_calendar_posts_full_title_option()
        {
            echo '<div class="c-input-group">';

            echo sprintf(
                '<input type="checkbox" name="%s" value="on" %s>',
                esc_attr($this->module->options_group_name) . '[show_calendar_posts_full_title]',
                'on' === $this->module->options->show_calendar_posts_full_title ? 'checked' : ''
            );

            echo '</div>';
        }

        public function settings_calendar_today_in_first_row_option()
        {
            echo '<div class="c-input-group">';

            echo sprintf(
                '<input type="checkbox" name="%s" value="on" %s>',
                esc_attr($this->module->options_group_name) . '[calendar_today_in_first_row]',
                'on' === $this->module->options->calendar_today_in_first_row ? 'checked' : ''
            );

            echo '</div>';
        }

        /**
         * Enable calendar subscriptions via .ics in iCal or Google Calendar
         *
         * @since 0.8
         */
        public function settings_ics_subscription_option()
        {
            $options = [
                'off' => __('Disabled', 'publishpress'),
                'on' => __('Enabled', 'publishpress'),
            ];
            echo '<select id="ics_subscription" name="' . esc_attr(
                    $this->module->options_group_name
                ) . '[ics_subscription]">';
            foreach ($options as $value => $label) {
                echo '<option value="' . esc_attr($value) . '"';
                echo selected($this->module->options->ics_subscription, $value);
                echo '>' . esc_html($label) . '</option>';
            }
            echo '</select>';

            $regenerate_url = add_query_arg(
                'action',
                'pp_calendar_regenerate_calendar_feed_secret',
                admin_url('admin.php?page=pp-calendar')
            );
            $regenerate_url = wp_nonce_url($regenerate_url, 'pp-regenerate-ics-key');
            echo '&nbsp;&nbsp;&nbsp;<a href="' . esc_url($regenerate_url) . '">' . __(
                    'Regenerate calendar feed secret',
                    'publishpress'
                ) . '</a>';

            // If our secret key doesn't exist, create a new one
            if (empty($this->module->options->ics_secret_key)) {
                PublishPress()->update_module_option($this->module->name, 'ics_secret_key', wp_generate_password());
            }
        }

        /**
         * Enable calendar subscriptions via .ics in iCal or Google Calendar
         *
         * @since 0.8
         */
        public function settings_ics_subscription_public_visibility_option()
        {


            echo '<div class="c-input-group">';

            echo sprintf(
                '<input type="checkbox" name="%s" value="on" %s>',
                esc_attr($this->module->options_group_name) . '[ics_subscription_public_visibility]',
                'on' === $this->module->options->ics_subscription_public_visibility ? 'checked' : ''
            );

            echo '</div>';
        }

        /**
         * @param $weeks
         * @param $startDate
         * @param $context
         *
         * @return float|int
         */
        public function filter_calendar_total_weeks_public_feed($weeks, $startDate, $context)
        {
            if (! isset($_GET['end'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $end = 'm2';
            } else {
                $end = preg_replace('/[^wm0-9]/', '', sanitize_text_field($_GET['end'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }

            if (preg_match('/m[0-9]*/', $end)) {
                $weeks = (int)str_replace('m', '', $end) * 4;
            } else {
                $weeks = (int)str_replace('w', '', $end);
            }

            // Calculate the diff in weeks from start date until now
            $today = date('Y-m-d'); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

            $first = DateTime::createFromFormat('Y-m-d', $startDate);
            $second = DateTime::createFromFormat('Y-m-d', $today);

            $diff = floor($first->diff($second)->days / 7);

            $weeks += $diff;

            return $weeks;
        }

        /**
         * @param $startDate
         *
         * @return false|string
         */
        public function filter_calendar_start_date_public_feed($startDate)
        {
            if (! isset($_GET['start'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                // Current week
                $start = 0;
            } else {
                $start = (int)$_GET['start']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            }

            if ($start > 0) {
                $startDate = date('Y-m-d', strtotime('-' . $start . ' months', strtotime($startDate))); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
            }

            return $startDate;
        }

        /**
         * Add any necessary CSS to the WordPress admin
         *
         * @uses wp_enqueue_style()
         */
        public function add_admin_styles()
        {
            global $pagenow;

            // Only load calendar styles on the calendar page
            if ('admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'pp-calendar') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                wp_enqueue_style(
                    'publishpress-calendar-css',
                    $this->module_url . 'lib/calendar.css',
                    ['publishpress-select2'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_style(
                    'publishpress-async-calendar-theme-light-css',
                    $this->module_url . 'lib/async-calendar/styles/themes/theme-light.css',
                    [],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_style(
                    'publishpress-async-calendar-css',
                    $this->module_url . 'lib/async-calendar/styles/async-calendar.css',
                    ['publishpress-async-calendar-theme-light-css'],
                    PUBLISHPRESS_VERSION
                );

                if (isset($this->module->options->show_calendar_posts_full_title) && 'on' === $this->module->options->show_calendar_posts_full_title) {
                    $inline_style = '.publishpress-calendar .publishpress-calendar-item {
                        height: auto;
                        max-height: max-content;
                        white-space: break-spaces;
                    }';
                    wp_add_inline_style('publishpress-async-calendar-css', $inline_style);
                }

                wp_enqueue_style(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/css/select2-full.min.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'screen'
                );
            }
        }

        /**
         * Handle a request to regenerate the calendar feed secret
         *
         * @since 0.8
         */
        public function handle_regenerate_calendar_feed_secret()
        {
            if (! isset($_GET['action']) || 'pp_calendar_regenerate_calendar_feed_secret' != $_GET['action']) {
                return;
            }

            if (! current_user_can('manage_options')) {
                wp_die($this->module->messages['invalid-permissions']);
            }

            if (! isset($_GET['_wpnonce'])
                || ! wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'pp-regenerate-ics-key')
            ) {
                wp_die($this->module->messages['nonce-failed']);
            }

            PublishPress()->update_module_option($this->module->name, 'ics_secret_key', wp_generate_password());

            $args = [
                'page' => PP_Modules_Settings::SETTINGS_SLUG,
                'settings_module' => $this->module->settings_slug,
            ];

            wp_safe_redirect(
                add_query_arg(
                    'message',
                    'key-regenerated',
                    add_query_arg($args, admin_url('admin.php'))
                )
            );

            exit;
        }

        public function filterPostsOrderBy($orderBy)
        {
            if ($this->module->options->sort_by === 'status') {
                $orderBy = 'post_status ASC, post_date ASC';
            } else {
                $orderBy = 'post_date ASC';
            }

            return $orderBy;
        }

        /**
         * Check whether the current user should have the ability to modify the post
         *
         * @param object $post The post object we're checking
         *
         * @return bool $can Whether or not the current user can modify the post
         * @since 0.7
         *
         */
        public function current_user_can_modify_post($post)
        {
            if (! $post) {
                return false;
            }

            $post_type_object = get_post_type_object($post->post_type);

            // Is the current user an author of the post?
            $userId = (int)wp_get_current_user()->ID;
            $isAuthor = apply_filters(
                'publishpress_is_author_of_post',
                $userId === (int)$post->post_author,
                $userId,
                $post->ID
            );
            $isPublished = in_array($post->post_status, $this->published_statuses);
            $canPublish = current_user_can($post_type_object->cap->publish_posts, $post->ID);
            $passedPublishedPostRule = (! $isPublished || ($isPublished && $canPublish));

            // Published posts only can be updated by those who can publish posts.
            // Is the user an author for the content?
            if ($isAuthor && $passedPublishedPostRule) {
                return true;
            }

            // If the user can edit others_posts he can edits the posts depending on the status.
            if (current_user_can($post_type_object->cap->edit_others_posts, $post->ID) && $passedPublishedPostRule) {
                return true;
            }

            return false;
        }

        public function moveCalendarItemToNewDate()
        {
            if (! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-calendar-get-data')) {
                wp_send_json(['error' => __('Invalid nonce', 'publishpress')], 403);
            }

            $postId = isset($_POST['id']) ? (int)$_POST['id'] : null;
            $newYear = isset($_POST['year']) ? (int)$_POST['year'] : null;
            $newMonth = isset($_POST['month']) ? (int)$_POST['month'] : null;
            $newDay = isset($_POST['day']) ? (int)$_POST['day'] : null;

            if (empty($postId) || empty($newYear) || empty($newMonth) || empty($newDay)) {
                wp_send_json(['error' => __('Invalid input', 'publishpress')], 400);
            }

            $post = get_post($postId);

            if (empty($post) || is_wp_error($post)) {
                wp_send_json(['error' => __('Post not found', 'publishpress')], 404);
            }

            // Check that the user can modify the post
            if (! $this->current_user_can_modify_post($post)) {
                wp_send_json(['error' => __('No enough permissions', 'publishpress')], 403);
            }

            $oldPostDate = $post->post_date;
            $postDate = null;
            try {
                $postDate = new DateTime($post->post_date);
                $postDate->setDate($newYear, $newMonth, $newDay);
            } catch (Exception $e) {
                wp_send_json(['error' => __('Invalid date', 'publishpress')], 400);
            }

            $newDate = $postDate->format('Y-m-d H:i:s');

            wp_update_post(
                [
                    'ID' => $postId,
                    'post_date' => $newDate,
                    'post_date_gmt' => get_gmt_from_date($newDate),
                    'edit_date' => true,

                ]
            );

            /**
             * @param int $postId
             * @param string $newDate
             */
            do_action('publishpress_after_moving_calendar_item', $postId, $newDate, $oldPostDate);

            wp_send_json(
                true,
                200
            );
        }

        public function getPostTypeFields($post_status_options)
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

            $fields = apply_filters(
                'publishpress_calendar_post_type_fields',
                [
                    'title' => [
                        'label' => __('Title', 'publishpress'),
                        'value' => null,
                        'type' => 'text',
                    ],
                    'status' => [
                        'label' => __('Post Status', 'publishpress'),
                        'value' => 'draft',
                        'type' => 'status',
                        'options' => $post_status_options
                    ],
                    'time' => [
                        'label' => __('Publish Time', 'publishpress'),
                        'value' => null,
                        'type' => 'time',
                        'placeholder' => isset($this->module->options->default_publish_time) ? $this->module->options->default_publish_time : null,
                    ]
                ],
                $post_status_options
            );

            if (current_user_can($postTypeObject->cap->edit_others_posts)) {
                $fields['authors'] = [
                    'label' => __('Author', 'publishpress'),
                    'value' => null,
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

            $fields = apply_filters('publishpress_calendar_get_post_type_fields', $fields, $postType);

            $data = ['fields' => $fields];

            return $data;
        }

        /**
         * Filters the status text of the post. Fixing the text for future and past dates.
         *
         * @param string $status The status text.
         * @param WP_Post $post Post object.
         * @param string $column_name The column name.
         * @param string $mode The list display mode ('excerpt' or 'list').
         */
        public function filter_post_date_column_status($status, $post, $column_name, $mode)
        {
            if ('date' === $column_name) {
                if ('0000-00-00 00:00:00' === $post->post_date) {
                    $time_diff = 0;
                } else {
                    $time = get_post_time('G', true, $post);

                    $time_diff = time() - $time;
                }

                if ('future' === $post->post_status) {
                    if ($time_diff > 0) {
                        return '<strong class="error-message">' . esc_html__('Missed schedule') . '</strong>';
                    } else {
                        return esc_html__('Scheduled');
                    }
                }

                if ('publish' === $post->post_status) {
                    return esc_html__('Published');
                }

                return esc_html__('Publish on', 'publishpress');
            }

            return $status;
        }

        /**
         * @param $status
         *
         * @return  bool
         *
         * @access  private
         */
        public function showPostsPublishTime($status)
        {
            if ($this->module->options->show_posts_publish_time === 'on') {
                $this->module->options->show_posts_publish_time = [
                    'publish' => 'on',
                    'future' => 'on',
                ];
            }

            return isset($this->module->options->show_posts_publish_time[$status])
                && $this->module->options->show_posts_publish_time[$status] === 'on';
        }

        /**
         * Add any necessary JS to the WordPress admin
         *
         * @since 0.7
         * @uses  wp_enqueue_script()
         */
        public function enqueue_admin_scripts($method_args)
        {
            global $wp_scripts;

                $js_libraries = [
                    'jquery',
                    'jquery-ui-core',
                    'jquery-ui-sortable',
                    'jquery-ui-draggable',
                    'jquery-ui-droppable',
                    'clipboard-js',
                    'publishpress-select2'
                ];
                foreach ($js_libraries as $js_library) {
                    wp_enqueue_script($js_library);
                }
                wp_enqueue_script(
                    'clipboard-js',
                    $this->module_url . 'lib/clipboard.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION,
                    true
                );

                wp_enqueue_script(
                    'publishpress-admin',
                    PUBLISHPRESS_URL . 'common/js/publishpress-admin.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-calendar-js',
                    $this->module_url . 'lib/calendar.js',
                    $js_libraries,
                    PUBLISHPRESS_VERSION,
                    true
                );

                wp_enqueue_script(
                    'publishpress-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/js/select2-full.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );


                    if (! isset($wp_scripts->queue['react'])) {
                        wp_enqueue_script(
                            'react',
                            PUBLISHPRESS_URL . 'common/js/react.min.js',
                            [],
                            PUBLISHPRESS_VERSION,
                            true
                        );
                        wp_enqueue_script(
                            'react-dom',
                            PUBLISHPRESS_URL . 'common/js/react-dom.min.js',
                            ['react'],
                            PUBLISHPRESS_VERSION,
                            true
                        );
                    }

                    wp_enqueue_script(
                        'date_i18n',
                        PUBLISHPRESS_URL . 'common/js/date-i18n.js',
                        [],
                        PUBLISHPRESS_VERSION,
                        true
                    );

                    // TODO: Replace react and react-dom with the wp.element dependency
                    wp_enqueue_script(
                        'publishpress-async-calendar-js',
                        $this->module_url . 'lib/async-calendar/js/index.min.js',
                        [
                            'react',
                            'react-dom',
                            'jquery',
                            'jquery-ui-core',
                            'jquery-ui-sortable',
                            'jquery-ui-draggable',
                            'jquery-ui-droppable',
                            'wp-i18n',
                            'wp-element',
                            'date_i18n',
                        ],
                        PUBLISHPRESS_VERSION,
                        true
                    );

                    /*
                     * Filters
                     */
                    $userFilters             = $method_args['userFilters'];
                    $calendar_request_args   = $userFilters;
                    $calendar_request_filter = $userFilters;

                    $maxVisibleItemsOption = isset($this->module->options->max_visible_posts_per_date) && ! empty($this->default_max_visible_posts_per_date) ?
                        (int)$this->module->options->max_visible_posts_per_date : $this->default_max_visible_posts_per_date;

                    $postStatuses = $method_args['postStatuses'];

                    $postTypes = [];
                    $postTypesUserCanCreate = [];
                    foreach ($method_args['selectedPostTypes'] as $postTypeName) {
                        $postType = get_post_type_object($postTypeName);

                        $postTypes[] = [
                            'value' => esc_attr($postTypeName),
                            'text' => esc_html($postType->label)
                        ];

                        if (current_user_can($postType->cap->edit_posts)) {
                            $postTypesUserCanCreate[] = [
                                'value' => esc_attr($postTypeName),
                                'text' => esc_html($postType->labels->singular_name)
                            ];
                        }
                    }

                    $numberOfWeeksToDisplay = isset($calendar_request_filter['weeks']) ? // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        (int)$calendar_request_filter['weeks'] : self::DEFAULT_NUM_WEEKS; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

                    $firstDateToDisplay = (isset($calendar_request_filter['start_date']) ? // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            sanitize_text_field($calendar_request_filter['start_date']) : date('Y-m-d')) . ' 00:00:00'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.DateTime.RestrictedFunctions.date_date

                    if (isset($this->module->options->calendar_today_in_first_row) && 'on' === $this->module->options->calendar_today_in_first_row) {
                        $firstDateToDisplay = $calendar_request_filter['start_date'] = date('Y-m-d', current_time('timestamp'));
                    }

                    $firstDateToDisplay = PP_Calendar_Utilities::get_beginning_of_week($firstDateToDisplay);
                    $endDate = PP_Calendar_Utilities::get_ending_of_week(
                        $firstDateToDisplay,
                        'Y-m-d',
                        $numberOfWeeksToDisplay
                    );

                    $params = [
                        'requestFilter' => $calendar_request_filter,
                        'numberOfWeeksToDisplay' => $numberOfWeeksToDisplay,
                        'firstDateToDisplay' => esc_js($firstDateToDisplay),
                        'theme' => 'light',
                        'weekStartsOnSunday' => (int)get_option('start_of_week') === 0,
                        'todayDate' => esc_js(date('Y-m-d 00:00:00')), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
                        'dateFormat' => esc_js(get_option('date_format', 'Y-m-d H:i:s')),
                        'timeFormat' => esc_js($method_args['timeFormat']),
                        'maxVisibleItems' => $maxVisibleItemsOption,
                        'statuses' => $postStatuses,
                        'postTypes' => $postTypes,
                        'postTypesCanCreate' => $postTypesUserCanCreate,
                        'ajaxUrl' => esc_url(admin_url('admin-ajax.php')),
                        'nonce' => wp_create_nonce('publishpress-calendar-get-data'),
                        'userCanAddPosts' => count($postTypesUserCanCreate) > 0,
                        'items' => $this->getCalendarData($firstDateToDisplay, $endDate, $calendar_request_args, $method_args),
                        'allowAddingMultipleAuthors' => (bool)apply_filters(
                            'publishpress_calendar_allow_multiple_authors',
                            false
                        ),
                        'proActive' => $method_args['proActive'],
                        'strings' => [
                            'loading' => esc_js(__('Loading...', 'publishpress')),
                            'loadingItem' => esc_js(__('Loading item...', 'publishpress')),
                            'clickToAdd' => esc_js(__('Click to add', 'publishpress')),
                            'movingTheItem' => esc_js(__('Moving the item...', 'publishpress')),
                            'hideItems' => esc_js(__('Hide the %s last items', 'publishpress')),
                            'showMore' => esc_js(__('Show %s more', 'publishpress')),
                            'untitled' => esc_js(__('Untitled', 'publishpress')),
                            'refresh' => esc_js(__('Refresh', 'publishpress')),
                            'previousPage' => esc_js(__('Previous page', 'publishpress')),
                            'previousWeek' => esc_js(__('Previous week', 'publishpress')),
                            'nextWeek' => esc_js(__('Next week', 'publishpress')),
                            'nextPage' => esc_js(__('Next page', 'publishpress')),
                            'close' => esc_js(__('Close', 'publishpress')),
                            'save' => esc_js(__('Save', 'publishpress')),
                            'saving' => esc_js(__('Saving...', 'publishpress')),
                            'saveAndEdit' => esc_js(__('Save and edit', 'publishpress')),
                            'addContentFor' => esc_js(__('Add content for %s', 'publishpress')),
                            'postTypeNotFound' => esc_js(__('Post type not found', 'publishpress')),
                            'postType' => esc_js(__('Post type:', 'publishpress')),
                            'pleaseWaitLoadingFormFields' => esc_js(__('Please, wait! Loading the form fields...', 'publishpress')),
                            'weekDaySun' => esc_js(__('Sun', 'publishpress')),
                            'weekDayMon' => esc_js(__('Mon', 'publishpress')),
                            'weekDayTue' => esc_js(__('Tue', 'publishpress')),
                            'weekDayWed' => esc_js(__('Wed', 'publishpress')),
                            'weekDayThu' => esc_js(__('Thu', 'publishpress')),
                            'weekDayFri' => esc_js(__('Fri', 'publishpress')),
                            'weekDaySat' => esc_js(__('Sat', 'publishpress')),
                            'monthJan' => esc_js(__('Jan', 'publishpress')),
                            'monthFeb' => esc_js(__('Feb', 'publishpress')),
                            'monthMar' => esc_js(__('Mar', 'publishpress')),
                            'monthApr' => esc_js(__('Apr', 'publishpress')),
                            'monthMay' => esc_js(__('May', 'publishpress')),
                            'monthJun' => esc_js(__('Jun', 'publishpress')),
                            'monthJul' => esc_js(__('Jul', 'publishpress')),
                            'monthAug' => esc_js(__('Aug', 'publishpress')),
                            'monthSep' => esc_js(__('Sep', 'publishpress')),
                            'monthOct' => esc_js(__('Oct', 'publishpress')),
                            'monthNov' => esc_js(__('Nov', 'publishpress')),
                            'monthDec' => esc_js(__('Dec', 'publishpress')),
                            'allStatuses' => esc_js(__('All statuses', 'publishpress')),
                            'allCategories' => esc_js(__('All categories', 'publishpress')),
                            'allTags' => esc_js(__('All tags', 'publishpress')),
                            'allAuthors' => esc_js(__('All authors', 'publishpress')),
                            'allTypes' => esc_js(__('All types', 'publishpress')),
                            'xWeek' => esc_js(__('%d week', 'publishpress')),
                            'xWeeks' => esc_js(__('%d weeks', 'publishpress')),
                            'today' => esc_js(__('Today', 'publishpress')),
                            'noTerms' => esc_js(__('No terms', 'publishpress')),
                            'post_date_label'    => esc_html__('Post Date', 'publishpress'),
                            'edit_label'         => esc_html__('Edit', 'publishpress'),
                            'delete_label'       => esc_html__('Trash', 'publishpress'),
                            'preview_label'      => esc_html__('Preview', 'publishpress'),
                            'view_label'         => esc_html__('View', 'publishpress'),
                            'prev_label'         => esc_html__('Previous Post', 'publishpress'),
                            'next_label'         => esc_html__('Next Post', 'publishpress'),
                            'post_status_label'  => esc_html__('Post Status', 'publishpress'),
                            'update_label'       => esc_html__('Save Changes', 'publishpress'),
                            'empty_term'         => esc_html__('Taxonomy not set.', 'publishpress'),
                            'post_author'        => esc_html__('Author', 'publishpress'),
                            'date_format'        => pp_convert_date_format_to_jqueryui_datepicker(get_option('date_format')),
                            'week_first_day'     => esc_js(get_option('start_of_week')),
                        ]
                    ];
                    wp_localize_script('publishpress-async-calendar-js', 'publishpressCalendarParams', $params);

                    global $wp_locale;
                    $monthNames = array_map([&$wp_locale, 'get_month'], range(1, 12));
                    $monthNamesShort = array_map([&$wp_locale, 'get_month_abbrev'], $monthNames);
                    $dayNames = array_map([&$wp_locale, 'get_weekday'], range(0, 6));
                    $dayNamesShort = array_map([&$wp_locale, 'get_weekday_abbrev'], $dayNames);
                    wp_localize_script(
                        "date_i18n",
                        "DATE_I18N",
                        array(
                            "month_names" => $monthNames,
                            "month_names_short" => $monthNamesShort,
                            "day_names" => $dayNames,
                            "day_names_short" => $dayNamesShort
                        )
                    );
        }

        public function getCalendarData($beginningDate, $endingDate, $args = [], $method_args = [])
        {
            $post_query_args = [
                'post_status' => null,
                'post_type' => null,
                'author' => null,
                'date_query' => [
                    'column' => 'post_date',
                    'after' => $beginningDate,
                    'before' => $endingDate,
                    'inclusive' => true,
                ]
            ];

            $post_query_args = wp_parse_args($args, $post_query_args);

            if (isset($this->module->options->sort_by) && $this->module->options->sort_by === 'status') {
                $post_query_args['orderby'] = ['post_status' => 'ASC'];
            } else {
                $post_query_args['orderby'] = ['post_date' => 'ASC'];
            }

            /**
             * @param array $post_query_args The array with args passed to post query
             * @param string $beginningDate The beginning date showed in the calendar
             * @param string $endingDate The ending date showed in the calendar
             *
             * @return array
             */
            $post_query_args = apply_filters('publishpress_calendar_data_args', $post_query_args, $beginningDate, $endingDate);

            $postsList = $this->getCalendarDataForMultipleWeeks($post_query_args, 'dashboard', $method_args);

            $data = [];

            foreach ($postsList as $date => $posts) {
                if (! isset($data[$date])) {
                    $data[$date] = [];
                }

                foreach ($posts as $post) {
                    $data[$date][] = $this->extractPostDataForTheCalendar($post);
                }
            }

            return $data;
        }

        private function getPostTypeObject($postType)
        {
            if (! isset($this->postTypeObjectCache[$postType])) {
                $this->postTypeObjectCache[$postType] = get_post_type_object($postType);
            }

            return $this->postTypeObjectCache[$postType];
        }

        private function extractPostDataForTheCalendar($post)
        {
            //$module_class = new PP_Module();

            $filtered_title = apply_filters('pp_calendar_post_title_html', $post->post_title, $post);
            $post->filtered_title = $filtered_title;

            if (function_exists('rvy_in_revision_workflow') && rvy_in_revision_workflow($post)) {
                $_post_status = $post->post_mime_type;
            } else {
                $_post_status = $post->post_status;
            }

            $postTypeOptions = $this->get_post_status_options($_post_status);

            $postTypeObject = $this->getPostTypeObject($post->post_type);
            $canEdit = current_user_can($postTypeObject->cap->edit_post, $post->ID);

            $data = [
                'label' => $filtered_title,
                'id' => (int)$post->ID,
                'timestamp' => esc_attr($post->post_date),
                'icon' => esc_attr($postTypeOptions['icon']),
                'color' => esc_attr($postTypeOptions['color']),
                'showTime' => (bool)$this->showPostsPublishTime($post->post_status),
                'canEdit' => $canEdit,
            ];

            if (PublishPress\Legacy\Util::isPlannersProActive()) {
                $modal_data = $this->localize_post_data([], $post, $canEdit);

                $data['calendar_post_data'] = !empty($modal_data['posts'][0]) ? $modal_data['posts'][0] : [];
                $data['calendar_taxonomies_data'] = !empty($modal_data['taxonomies'][$post->ID]) ? $modal_data['taxonomies'][$post->ID] : [];
            } else {
                $data['calendar_post_data'] = [];
                $data['calendar_taxonomies_data'] = [];
            }

            return $data;
        }

        /**
         * Query to get all of the calendar posts for a given day
         *
         * @param array $args Any filter arguments we want to pass
         * @param string $context Where the query is coming from, to distinguish dashboard and subscriptions
         *
         * @return array $posts All of the posts as an array sorted by date
         */
        public function getCalendarDataForMultipleWeeks($args = [], $context = 'dashboard', $method_args = [])
        {
            $supported_post_types = PublishPress\Legacy\Util::get_post_types_for_module($this->module);
            $defaults = [
                'post_status' => null,
                'author' => null,
                'post_type' => $supported_post_types,
                'posts_per_page' => -1,
                'order' => 'ASC',
            ];

            $args = array_merge($defaults, $args);

            if (isset($args['s']) && ! empty($args['s'])) {
                $args['s'] = sanitize_text_field($args['s']);
            }

            $current_user_id = get_current_user_id();

            $user_filters = $method_args['userFilters'];

            // Get content calendar data
            $content_calendar_datas = $method_args['content_calendar_datas'];

            $filters = $content_calendar_datas['content_calendar_filters'];
            /**
             * @param array $filters
             *
             * @return array
             */
            $filters = apply_filters('publishpress_content_calendar_filters', $filters, 'get_calendar_data');

            $enabled_filters = array_keys($filters);
            $editorial_metadata = $method_args['terms_options'];

            if (!empty($args['cpt'])) {
                $args['post_type'] = $args['cpt'];
            }


            if (empty($args['post_type']) || ! in_array($args['post_type'], $supported_post_types)) {
                $args['post_type'] = $supported_post_types;
            }

            //remove inactive builtin filter
            if (!in_array('cpt', $enabled_filters)) {
                // show all post type
                $args['post_type'] = $supported_post_types;
            }

            if (!in_array('author', $enabled_filters)) {
                unset($args['author']);
            }

            $meta_query = $tax_query = ['relation' => 'AND'];
            $metadata_filter = $taxonomy_filter = false;
            $checklists_filters = [];

            // apply enabled filter
            foreach ($enabled_filters as $enabled_filter) {
                if (array_key_exists($enabled_filter, $editorial_metadata)) {
                    //metadata field filter
                    $meta_key = $enabled_filter;
                    $metadata_term = $editorial_metadata[$meta_key];
                    unset($args[$enabled_filter]);
                    if ($metadata_term['type'] === 'date') {
                        $date_type_metaquery = [];

                        if (! empty($user_filters[$meta_key . '_start'])) {
                            $date_type_metaquery[] = strtotime($user_filters[$meta_key . '_start_hidden']);
                        }
                        if (! empty($user_filters[$meta_key . '_end'])) {
                            $date_type_metaquery[] = strtotime($user_filters[$meta_key . '_end_hidden']);
                        }
                        if (count($date_type_metaquery) === 2) {
                            $metadata_filter = true;
                            $compare        = 'BETWEEN';
                            $meta_value     = $date_type_metaquery;
                        } elseif (count($date_type_metaquery) === 1) {
                            $metadata_filter = true;
                            $compare        = '=';
                            $meta_value     = $date_type_metaquery[0];
                        }

                        if (!empty($date_type_metaquery)) {
                            $metadata_filter = true;
                            $meta_query[] = array(
                                'key' => '_pp_editorial_meta_' . $metadata_term['type'] . '_' . $metadata_term['slug'],
                                'value' => $meta_value,
                                'compare' => $compare
                            );
                        }

                    } elseif (! empty($user_filters[$meta_key])) {
                        if ($metadata_term['type'] === 'date') {
                            continue;
                        } else {
                            $meta_value = sanitize_text_field($user_filters[$meta_key]);
                        }

                         $compare = '=';
                        if ($metadata_term['type'] === 'paragraph'
                            || ($metadata_term['type'] === 'select' && isset($metadata_term->select_type) && $metadata_term['select_type'] === 'multiple')
                        ) {
                            $compare = 'LIKE';
                        }
                        $metadata_filter = true;
                        $meta_query[] = array(
                            'key' => '_pp_editorial_meta_' . $metadata_term['type'] . '_' . $metadata_term['slug'],
                            'value' => $meta_value,
                            'compare' => $compare
                        );
                    }

                } elseif(
                    in_array($enabled_filter, $content_calendar_datas['meta_keys'])
                    && (
                        isset($user_filters[$enabled_filter])
                        &&
                            (
                                !empty($user_filters[$enabled_filter])
                                || $user_filters[$enabled_filter] == '0'
                                || (
                                    !empty($user_filters[$enabled_filter . '_operator'])
                                    && $user_filters[$enabled_filter . '_operator'] === 'not_exists'
                                    )
                            )
                        )
                    ) {
                    // metakey filter
                    unset($args[$enabled_filter]);
                    $meta_value = sanitize_text_field($user_filters[$enabled_filter]);
                    $meta_operator = !empty($user_filters[$enabled_filter . '_operator']) ? $user_filters[$enabled_filter . '_operator'] : 'equals';
                    $compare = $method_args['operator_labels'];

                    $metadata_filter = true;

                    if ($meta_operator == 'not_exists') {
                        $meta_query[] = array(
                            'relation' => 'OR',
                            array(
                                'key' => $enabled_filter,
                                'compare' => 'NOT EXISTS'
                            ),
                            array(
                                'key' => $enabled_filter,
                                'value' => '',
                                'compare' => '='
                            )
                        );
                    } else {
                        $meta_query[] = array(
                            'key' => $enabled_filter,
                            'value' => $meta_value,
                            'compare' => $compare
                        );
                    }
                } elseif (in_array($enabled_filter, ['ppch_co_yoast_seo__yoast_wpseo_linkdex', 'ppch_co_yoast_seo__yoast_wpseo_content_score']) && !empty($user_filters[$enabled_filter]) && array_key_exists($enabled_filter, $method_args['form_filter_list']) && class_exists('WPSEO_Meta')) {
                    // yoast seo filter
                    unset($args[$enabled_filter]);
                    $meta_value = sanitize_text_field($user_filters[$enabled_filter]);
                    $meta_key = str_replace('ppch_co_yoast_seo_', '', $enabled_filter);
                    $meta_operator = !empty($user_filters[$enabled_filter . '_operator']) ? $user_filters[$enabled_filter . '_operator'] : 'equals';
                    $compare = PP_Module::static_meta_query_operator_symbol($meta_operator);
                    $metadata_filter = true;
                    $meta_query[] = array(
                        'key' => $meta_key,
                        'value' => $meta_value,
                        'compare' => $compare
                    );

                } elseif(array_key_exists($enabled_filter, $content_calendar_datas['taxonomies']) && !empty($user_filters[$enabled_filter])) {
                    //taxonomy filter
                    unset($args[$enabled_filter]);
                    $tax_value = sanitize_text_field($user_filters[$enabled_filter]);
                    $taxonomy_filter = true;
                    $tax_query[] = array(
                          'taxonomy' => $enabled_filter,
                          'field'     => 'slug',
                          'terms'    => [$tax_value],
                          'include_children' => true,
                          'operator' => 'IN',
                    );
                } elseif(!empty($user_filters[$enabled_filter]) && strpos($enabled_filter, "ppch_co_checklist_") === 0 && array_key_exists($enabled_filter, $method_args['form_filter_list'])) {
                    // checklists filter
                    /**
                     * TODO: Implement metaquery filter when checklists started storing checklists status in meta_key
                     */
                    unset($args[$enabled_filter]);
                    $meta_value = sanitize_text_field($user_filters[$enabled_filter]);
                    $meta_key = str_replace('ppch_co_checklist_', '', $enabled_filter);
                    $checklists_filters[$meta_key] = $meta_value;
                }

            }

            if ($metadata_filter) {
                $args['meta_query'] = $meta_query;
            }

            if ($taxonomy_filter) {
                $args['tax_query'] = $tax_query;
            }

            // Unpublished as a status is just an array of everything but 'publish'
            if ($args['post_status'] == 'unpublish') {
                $args['post_status'] = '';
                $post_statuses = $method_args['post_statuses'];
                foreach ($post_statuses as $post_status) {
                    $args['post_status'] .= $post_status->slug . ', ';
                }
                $args['post_status'] = rtrim($args['post_status'], ', ');
                // Optional filter to include scheduled content as unpublished
                if (apply_filters('pp_show_scheduled_as_unpublished', true)) {
                    $args['post_status'] .= ', future';
                }
            }
            // unset legacy options
            if (isset($args['cat'])) {
                unset($args['cat']);
            }
            if (isset($args['tag'])) {
                unset($args['tag']);
            }

            if (!empty($args['me_mode']) && !empty($current_user_id)) {
                $args['author'] = $current_user_id;
            }

            // Filter by post_author if it's set
            if (isset($args['author']) && empty($args['author'])) {
                unset($args['author']);
            }

            // Filter for an end user to implement any of their own query args
            $args = apply_filters('pp_calendar_posts_query_args', $args, $context, $enabled_filters, $user_filters);

            if (isset($this->module->options->sort_by)) {
                add_filter('posts_orderby', [$this, 'filterPostsOrderBy'], 10);
            }

            $post_results = new WP_Query($args);

            $posts = [];
            while ($post_results->have_posts()) {
                $post_results->the_post();
                global $post;

                $add_post = true;

                if (!empty($checklists_filters)) {
                    $post_checklists = apply_filters('publishpress_checklists_requirement_list', [], $post);
                    foreach ($checklists_filters as $checklists_filter_name => $checklists_filter_check) {
                        if (!array_key_exists($checklists_filter_name, $post_checklists)) {
                            // post that doesn't have this requirement shouldn't show?
                            $add_post = false;
                        } elseif ($checklists_filter_check == 'passed' && empty($post_checklists[$checklists_filter_name]['status'])) {
                            // filter posts that failed when condition is passed
                            $add_post = false;
                        } elseif ($checklists_filter_check == 'failed' && !empty($post_checklists[$checklists_filter_name]['status'])) {
                            // filter out post that passed when condition is failed
                            $add_post = false;
                        }
                    }
                }

                if ($add_post) {
                    /**
                     * TODO: Should we require posts like x2 if results is empty due to $add_post been false for all?
                    */
                    $key_date = date('Y-m-d', strtotime($post->post_date));
                    $posts[$key_date][] = $post;
                }
            }

            if (isset($this->module->options->sort_by)) {
                remove_filter('posts_orderby', [$this, 'filterPostsOrderBy']);
            }

            return $posts;
        }

        /**
         * @throws Exception
         */
        public function createItem()
        {
            if (! wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'publishpress-calendar-get-data')) {
                $this->print_ajax_response('error', $this->module->messages['nonce-failed']);
            }

            // Check that the user has the right capabilities to add posts to the calendar (defaults to 'edit_posts')
            if (! current_user_can($this->create_post_cap)) {
                $this->print_ajax_response('error', $this->module->messages['invalid-permissions']);
            }

            $postType = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : null;
            $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : null;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
            $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            // Sanitized by the wp_filter_post_kses function.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $content = isset($_POST['content']) ? wp_filter_post_kses($_POST['content']) : '';
            $time = isset($_POST['time']) ? sanitize_text_field($_POST['time']) : '';
            $authors = isset($_POST['authors']) ? explode(',', sanitize_text_field($_POST['authors'])) : [];
            $categories = isset($_POST['categories']) ? explode(',', sanitize_text_field($_POST['categories'])) : [];
            $tags = isset($_POST['tags']) ? explode(',', sanitize_text_field($_POST['tags'])) : [];

            if (empty($date)) {
                $this->print_ajax_response('error', __('No date supplied.', 'publishpress'));
            }

            // Post type has to be visible on the calendar to create a placeholder
            if (empty($postType)) {
                $postType = 'post';
            }

            if (! in_array($postType, $this->get_post_types_for_module($this->module))) {
                $this->print_ajax_response(
                    'error',
                    __('The selected post type is not enabled for the calendar.', 'publishpress')
                );
            }

            $title = apply_filters('pp_calendar_after_form_submission_sanitize_title', $title);
            if (empty($title)) {
                $title = __('Untitled', 'publishpress');
            }

            $content = apply_filters('pp_calendar_after_form_submission_sanitize_content', $content);
            if (empty($content)) {
                $content = '';
            }

            $authors = apply_filters('pp_calendar_after_form_submission_sanitize_author', $authors);
            try {
                $authors = apply_filters('pp_calendar_after_form_submission_validate_author', $authors);
            } catch (Exception $e) {
                $this->print_ajax_response('error', $e->getMessage());
            }

            if (empty($authors)) {
                $authors = apply_filters('publishpress_calendar_default_author', get_current_user_id());
            }

            if (! is_array($authors)) {
                $authors = [$authors];
            }

            if (! $this->isPostStatusValid($status)) {
                $this->print_ajax_response('error', __('Invalid Status supplied.', 'publishpress'));
            }

            $categories = array_map('sanitize_text_field', $categories);
            $tags = array_map('sanitize_text_field', $tags);

            $dateTimestamp = strtotime($date);

            if (empty($time)) {
                $time = $this->module->options->default_publish_time;
            }

            if (! empty($time)) {
                $date = sprintf(
                    '%s %s',
                    $date,
                    ((function_exists('mb_strlen') ? mb_strlen($time) : strlen($time)) === 5)
                        ? "{$time}:" . date('s', $dateTimestamp)
                        : date('H:i:s', $dateTimestamp)
                );
            }

            $dateTimeInstance = new DateTime($date);
            if (! $dateTimeInstance) {
                $this->print_ajax_response('error', __('Invalid Publish Date supplied.', 'publishpress'));
            }
            unset($dateTimeInstance);

            // Set new post parameters
            $postPlaceholder = [
                'post_author' => $authors[0],
                'post_title' => $title,
                'post_content' => $content,
                'post_type' => $postType,
                'post_status' => $status,
                'post_date' => $date,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1),
            ];

            /*
             * By default, adding a post to the calendar will set the timestamp.
             * If the user don't desires that to be the behavior, they can set the result of this filter to 'false'
             * With how WordPress works internally, setting 'post_date_gmt' will set the timestamp.
             * But check the Custom Status module and the hook to "wp_insert_post_data". It will reset the date if not
             * publishing or scheduling.
             */

            if (apply_filters('pp_calendar_allow_ajax_to_set_timestamp', true)) {
                $postPlaceholder['post_date_gmt'] = get_gmt_from_date($date);
            }

            // Create the post
            add_filter('wp_insert_post_data', ['PP_Calendar_Utilities', 'alter_post_modification_time'], 99, 2);
            $postId = wp_insert_post($postPlaceholder);
            remove_filter('wp_insert_post_data', ['PP_Calendar_Utilities', 'alter_post_modification_time'], 99);

            do_action('publishpress_calendar_after_create_post', $postId, $authors);

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

                // announce success and send back the html to inject
                $this->print_ajax_response(
                    'success',
                    __('Post created successfully', 'publishpress'),
                    [
                        'postId' => $postId,
                        'link' => htmlspecialchars_decode(get_edit_post_link($postId)),
                    ]
                );
            } else {
                $this->print_ajax_response('error', __('Post could not be created', 'publishpress'));
            }
        }

        private function isPostStatusValid($subject)
        {
            foreach ($this->get_post_statuses() as $post_status) {
                $is_status_valid = $subject === $post_status->slug;
                if ($is_status_valid) {
                    return true;
                }
            }

            return false;
        }

    }

}
