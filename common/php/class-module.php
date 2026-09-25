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

if (!class_exists('PP_Module')) {
    /**
     * PP_Module
     */
    class PP_Module
    {
        /**
         * @var \PublishPress\Core\ViewInterface
         */
        protected $view;

        protected $debug = false;

        public $options;

        /**
         * Public URL for the module assets.
         *
         * @var string
         */
        public $module_url = '';

        public $published_statuses = [
            'publish',
            // 'future',
            'private',
        ];

        protected $viewsPath;

        public function __construct()
        {
            if (!empty($this->viewsPath)) {
                $this->view = new \PublishPress\Core\View();
            }

            foreach(get_post_stati(['public' => true, 'private' => true], 'names', 'OR') as $status) {
                if (!in_array($status, $this->published_statuses)) {
                    $this->published_statuses []= $status;
                }
            }
        }

        /**
         * Returns whether the module with the given name is enabled.
         *
         * @param string module Slug of the module to check
         *
         * @return <code>true</code> if the module is enabled, <code>false</code> otherwise
         * @since  0.7
         *
         */
        public function module_enabled($slug)
        {
            global $publishpress;

            if ('custom_status' == $slug) {
                return class_exists('PublishPress_Statuses');
            }

            return isset($publishpress->$slug) && $publishpress->$slug->module->options->enabled == 'on';
        }

        /**
         * Gets an array of allowed post types for a module
         *
         * @return array post-type-slug => post-type-label
         */
        public function get_all_post_types($module = null)
        {
            $allowed_post_types = [
                'post' => __('Post'),
                'page' => __('Page'),
            ];

            if (is_null($module)) {
                $module = $this;
            }

            $custom_post_types = $this->get_supported_post_types_for_module($module);

            foreach ($custom_post_types as $custom_post_type => $args) {
                $allowed_post_types[$custom_post_type] = $args->label;
            }

            return $allowed_post_types;
        }

        /**
         * Cleans up the 'on' and 'off' for post types on a given module (so we don't get warnings all over)
         * For every post type that doesn't explicitly have the 'on' value, turn it 'off'
         * If add_post_type_support() has been used anywhere (legacy support), inherit the state
         *
         * @param array $module_post_types Current state of post type options for the module
         * @param string $post_type_support What the feature is called for post_type_support (e.g. 'pp_calendar')
         *
         * @return array $normalized_post_type_options The setting for each post type, normalized based on rules
         *
         * @since 0.7
         */
        public function clean_post_type_options($module_post_types = [], $post_type_support = null)
        {
            $normalized_post_type_options = [];
            $all_post_types               = array_keys($this->get_all_post_types());
            foreach ($all_post_types as $post_type) {
                if ((isset($module_post_types[$post_type]) && $module_post_types[$post_type] == 'on') || post_type_supports(
                        $post_type,
                        $post_type_support
                    )) {
                    $normalized_post_type_options[$post_type] = 'on';
                } else {
                    $normalized_post_type_options[$post_type] = 'off';
                }
            }

            return $normalized_post_type_options;
        }

        /**
         * Get all of the possible post types that can be used with a given module
         *
         * @param object $module The full module
         *
         * @return array $post_types An array of post type objects
         *
         * @since 0.7.2
         */
        public function get_supported_post_types_for_module($module = null)
        {
            $pt_args = [
                '_builtin' => false,
                'show_ui'  => true,
            ];
            $pt_args = apply_filters('publishpress_supported_module_post_types_args', $pt_args, $module);

            $postTypes = get_post_types($pt_args, 'objects');

            $postTypes = apply_filters('publishpress_supported_module_post_types', $postTypes);

            // Hide notification workflows from the list
            if (isset($postTypes['psppnotif_workflow'])) {
                unset($postTypes['psppnotif_workflow']);
            }

            return $postTypes;
        }

        /**
         * Collect all of the active post types for a given module
         *
         * @param object $module Module's data
         *
         * @return array $post_types All of the post types that are 'on'
         *
         * @since 0.7
         */
        public function get_post_types_for_module($module)
        {
            return PublishPress\Legacy\Util::get_post_types_for_module($module);
        }

        /**
         * Get all of the currently available post statuses
         *
         * @return array $post_statuses All of the post statuses that aren't a published state
         *
         * @since 0.7
         */
        public function get_post_statuses()
        {
            global $publishpress;

            return apply_filters('publishpress_calendar_post_statuses', $publishpress->getPostStatuses());
        }

        /**
         * Returns the CSS class name and color for the given custom status.
         * It reutrns an array with the following keys:
         *     - icon
         *     - color
         *
         * @param string $post_status
         *
         * @return array
         */
        public function get_post_status_options($post_status)
        {
            global $publishpress;

            // Check if we have a custom icon for this post_status
            $term = $publishpress->getPostStatusBy('slug', $post_status);

            // Icon
            $icon = null;
            if (! empty($term->icon)) {
                $icon = $term->icon;
            } else {
                // Add an icon for the items
                $default_icons = [
                    'publish' => 'dashicons-yes',
                    'future' => 'dashicons-calendar-alt',
                    'private' => 'dashicons-lock',
                    'draft' => 'dashicons-media-default',
                    'pending' => 'dashicons-clock',
                    'auto-draft' => 'dashicons-edit',
                ];

                $icon = isset($default_icons[$post_status]) ? $default_icons[$post_status] : 'dashicons-edit';
            }

            // Color
            if (! empty($term->color)) {
                $color = $term->color;
            } else {
                $default_status_colors = [
                    'pitch' => '#887618',
                    'assigned' => '#009ba0',
                    'in-progress' => '#8c5400',
                    'draft' => '#767676',
                    'pending' => '#b95c00',
                    'private' => '#b40000',
                    'future' => '#8440f0',
                    'publish' => '#207720',

                    'draft-revision' => '#767676',
                    'pending-revision' => '#b95c00',
                    'future-revision' => '#8440f0',
                ];

                if (isset($default_status_colors[$post_status])) {
                    $color = $default_status_colors[$post_status];
                } else {
                    $color = (class_exists('PublishPress_Statuses')) ? \PublishPress_Statuses::DEFAULT_COLOR : '#78645a';
                }
            }

            return [
                'color' => $color,
                'icon' => $icon,
            ];
        }

        /**
         * Get core's 'draft' and 'pending' post statuses, but include our special attributes
         *
         * @return array
         * @since 0.8.1
         *
         */
        protected function get_core_post_statuses()
        {
            global $publishpress;

            return $publishpress->getCorePostStatuses();
        }

        /**
         * Back compat for existing code calling $publishpress->custom_status->get_custom_status_by()
         *
         * @return object
         *
         * @since 4.0
         */
        public function get_custom_status_by($field, $value) {
            global $publishpress;

            return $publishpress->getPostStatusBy($field, $value);
        }

        /**
         * Filter to all posts with a given post status (can be a custom status or a built-in status) and optional custom post type.
         *
         * @param string $slug The slug for the post status to which to filter
         * @param string $post_type Optional post type to which to filter
         *
         * @return an edit.php link to all posts with the given post status and, optionally, the given post type
         * @since 0.7
         *
         */
        public function filter_posts_link($slug, $post_type = 'post')
        {
            $filter_link = add_query_arg('post_status', $slug, get_admin_url(null, 'edit.php'));
            if ($post_type != 'post' && in_array($post_type, get_post_types('', 'names'))) {
                $filter_link = add_query_arg('post_type', $post_type, $filter_link);
            }

            return $filter_link;
        }

        /**
         * Returns the friendly name for a given status
         *
         * @param string $status The status slug
         *
         * @return string $status_friendly_name The friendly name for the status
         * @since 0.7
         *
         */
        public function get_post_status_friendly_name($status, $post = false)
        {
            global $publishpress;

            $status = ($post && function_exists('rvy_in_revision_workflow') && rvy_in_revision_workflow($post)) ? $post->post_mime_type : $status;

            $status_friendly_name = '';

            $builtin_stati = [
                'publish' => __('Published', 'publishpress'),
                'draft'   => __('Draft', 'publishpress'),
                'future'  => __('Scheduled', 'publishpress'),
                'private' => __('Private', 'publishpress'),
                'pending' => __('Pending Review', 'publishpress'),
                'trash'   => __('Trash', 'publishpress'),
            ];

            // Custom statuses only handles workflow statuses
            if (!in_array($status, ['publish', 'future', 'private', 'trash'])) {
                $status_object = $publishpress->getPostStatusBy('slug', $status);

                if ($status_object && !is_wp_error($status_object)) {
                    $status_friendly_name = $status_object->label;
                }
            } elseif (array_key_exists($status, $builtin_stati)) {
                $status_friendly_name = $builtin_stati[$status];
            }

            return $status_friendly_name;
        }

        /**
         * Enqueue any resources (CSS or JS) associated with datepicker functionality
         *
         * @since 0.7
         */
        public function enqueue_datepicker_resources()
        {
            // Datepicker is available WordPress 3.3. We have to register it ourselves for previous versions of WordPress
            wp_enqueue_script('jquery-ui-datepicker');

            // Timepicker needs to come after jquery-ui-datepicker and jquery
            wp_enqueue_script(
                'publishpress-timepicker',
                PUBLISHPRESS_URL . 'common/libs/timepicker-v1.6.3.1/jquery-ui-timepicker-addon.min.js',
                ['jquery', 'jquery-ui-datepicker'],
                PUBLISHPRESS_VERSION,
                true
            );

            wp_enqueue_script(
                'publishpress-date_picker',
                PUBLISHPRESS_URL . 'common/js/pp_date.js',
                ['jquery', 'jquery-ui-datepicker', 'publishpress-timepicker'],
                PUBLISHPRESS_VERSION,
                true
            );

            // Now styles
            wp_enqueue_style(
                'publishpress-timepicker',
                PUBLISHPRESS_URL . 'common/libs/timepicker-v1.6.3.1/jquery-ui-timepicker-addon.min.css',
                ['wp-jquery-ui-dialog'],
                PUBLISHPRESS_VERSION,
                'screen'
            );
            wp_enqueue_style(
                'jquery-ui-datepicker',
                PUBLISHPRESS_URL . 'common/css/jquery.ui.datepicker.css',
                ['wp-jquery-ui-dialog'],
                PUBLISHPRESS_VERSION,
                'screen'
            );
            wp_enqueue_style(
                'jquery-ui-theme',
                PUBLISHPRESS_URL . 'common/css/jquery.ui.theme.css',
                false,
                PUBLISHPRESS_VERSION,
                'screen'
            );

            wp_localize_script(
                'publishpress-date_picker',
                'objectL10ndate',
                [
                    'date_format' => pp_convert_date_format_to_jqueryui_datepicker(get_option('date_format')),
                    'week_first_day' => esc_js(get_option('start_of_week')),
                ]
            );
        }

        /**
         * Checks for the current post type
         *
         * @return string|null $post_type The post type we've found, or null if no post type
         * @since 0.7
         */
        public function get_current_post_type()
        {
            return PublishPress\Legacy\Util::get_current_post_type();
        }

        /**
         * Wrapper for the get_user_meta() function so we can replace it if we need to
         *
         * @param int $user_id Unique ID for the user
         * @param string $key Key to search against
         * @param bool $single Whether or not to return just one value
         *
         * @return string|bool|array $value Whatever the stored value was
         * @since 0.7
         *
         */
        public function get_user_meta($user_id, $key, $string = true)
        {
            $response = null;
            $response = apply_filters('pp_get_user_meta', $response, $user_id, $key, $string);
            if (!is_null($response)) {
                return $response;
            }

            return get_user_meta($user_id, $key, $string);
        }

        /**
         * Wrapper for the update_user_meta() function so we can replace it if we need to
         *
         * @param int $user_id Unique ID for the user
         * @param string $key Key to search against
         * @param string|bool|array $value Whether or not to return just one value
         * @param string|bool|array $previous (optional) Previous value to replace
         *
         * @return bool $success Whether we were successful in saving
         * @since 0.7
         *
         */
        public function update_user_meta($user_id, $key, $value, $previous = null)
        {
            $response = null;
            $response = apply_filters('pp_update_user_meta', $response, $user_id, $key, $value, $previous);
            if (!is_null($response)) {
                return $response;
            }

            return update_user_meta($user_id, $key, $value, $previous);
        }

        /**
         * Take a status and a message, JSON encode and print
         *
         * @param string $status Whether it was a 'success' or an 'error'
         * @param string $message
         * @param array $data
         *
         * @since 0.7
         *
         */
        public function print_ajax_response($status, $message = '', $data = null)
        {
            header('Content-type: application/json;');

            $result = [
                'status'  => $status,
                'message' => $message,
            ];

            if (!is_null($data)) {
                $result['data'] = $data;
            }

            echo json_encode($result);

            exit;
        }

        /**
         * Whether or not the current page is a user-facing PublishPress View
         *
         * @param string $module_name (Optional) Module name to check against
         *
         * @since 0.7
         *
         * @todo  Think of a creative way to make this work
         *
         */
        protected function is_whitelisted_functional_view($module_name = null)
        {
            $whitelisted_pages = ['pp-calendar', 'pp-content-overview', 'pp-content-board', 'pp-notif-log', 'pp-modules-settings'];

            if (isset($_GET['page']) &&  in_array($_GET['page'], $whitelisted_pages)) {
                return true;
            }

            return false;
        }

        /**
         * Whether or not the current page is an PublishPress settings view (either main or module)
         * Determination is based on $pagenow, $_GET['page'], and the module's $settings_slug
         * If there's no module name specified, it will return true against all PublishPress settings views
         *
         * @param string $module_name (Optional) Module name to check against
         *
         * @return bool $is_settings_view Return true if it is
         * @since 0.7
         *
         */
        public function is_whitelisted_settings_view($module_name = null)
        {
            global $pagenow, $publishpress;

            // All of the settings views are based on admin.php and a $_GET['page'] parameter
            if ($pagenow != 'admin.php' || !isset($_GET['page'])) {
                return false;
            }

            if (isset($_GET['page']) && ($_GET['page'] === 'pp-modules-settings' || $_GET['page'] === 'pp-editorial-metadata')) {

                if (empty($module_name)) {
                    return true;
                }

                if (!isset($_GET['settings_module']) || $_GET['settings_module'] === 'pp-modules-settings-settings') {
                    if (in_array($module_name, ['editorial_comments', 'notifications', 'dashboard', 'editorial_metadata'])) {
                        return true;
                    }
                }

                $slug = str_replace('_', '-', $module_name);
                if (isset($_GET['settings_module']) && $_GET['settings_module'] === 'pp-' . $slug . '-settings') {
                    return true;
                }
            }

            return false;
        }

        /**
         * Encode all of the given arguments as JSON.
         * Used to store extra data in a term's description field.
         *
         * @param array $args The arguments to encode
         *
         * @return string Arguments encoded as JSON
         * @since 0.7
         *
         */
        public function get_encoded_description($args = [])
        {
            return wp_json_encode($args);
        }

        /**
         * If given an encoded string from a term's description field,
         * return an array of values. Otherwise, return the original string
         *
         * @param string $string_to_unencode Possibly encoded string
         *
         * @return array Array if string was encoded, otherwise the string as the 'description' field
         * @since 0.7
         *
         */
        public function get_unencoded_description($string_to_unencode)
        {
            $string_to_unencode = stripslashes(htmlspecialchars_decode($string_to_unencode));
            $decoded_array      = json_decode($string_to_unencode, true);

            if (is_array($decoded_array)) {
                return $decoded_array;
            }

            // Legacy Planner versions stored term descriptions as base64-encoded serialized arrays.
            $legacy_payload = base64_decode($string_to_unencode, true);
            if (false === $legacy_payload || ! is_serialized($legacy_payload)) {
                return $string_to_unencode;
            }

            $legacy_array = @unserialize($legacy_payload, ['allowed_classes' => false]);

            return is_array($legacy_array) ? $legacy_array : $string_to_unencode;
        }

        public function get_path_base()
        {
            return PUBLISHPRESS_BASE_PATH;
        }

        public function get_publishpress_url()
        {
            return PUBLISHPRESS_URL;
        }

        /**
         * This method looks redundant but it is just an abstraction needed to make it possible to
         * test Windows paths on a *unix machine. If not used and overridden in stubs on the tests
         * it always return "." because Windows paths are not valid on *unix machines.
         *
         * @param $file
         * @return string
         */
        public function dirname($file)
        {
            return dirname($file);
        }

        /**
         * Get the publicly accessible URL for the module based on the filename
         *
         * @param string $filepath File path for the module
         *
         * @return string $module_url Publicly accessible URL for the module
         * @since 0.7
         *
         */
        public function get_module_url($file)
        {
            $file = str_replace($this->get_path_base(), '', $this->dirname($file));
            $module_url = untrailingslashit($this->get_publishpress_url()) . $file;

            return str_replace('\\', '/', trailingslashit($module_url));
        }

        /**
         * Produce a human-readable version of the time since a timestamp
         *
         * @param int $original The UNIX timestamp we're producing a relative time for
         *
         * @return string $relative_time Human-readable version of the difference between the timestamp and now
         */
        public function timesince($original)
        {
            // array of time period chunks
            $chunks = [
                [60 * 60 * 24 * 365, 'year'],
                [60 * 60 * 24 * 30, 'month'],
                [60 * 60 * 24 * 7, 'week'],
                [60 * 60 * 24, 'day'],
                [60 * 60, 'hour'],
                [60, 'minute'],
                [1, 'second'],
            ];

            $today = time(); /* Current unix time  */
            $since = $today - $original;

            if ($since > $chunks[2][0] || $original > $today) {
                $dateFormat = get_option('date_format', 'Y-m-d');

                $print = date($dateFormat, $original);

                if ($since > $chunks[0][0]) { // Seconds in a year
                    $print .= ', ' . date('Y', $original);
                }

                return $print;
            }

            // $j saves performing the count function each time around the loop
            for ($i = 0, $j = count($chunks); $i < $j; $i++) {
                $seconds = $chunks[$i][0];
                $name    = $chunks[$i][1];

                // finding the biggest chunk (if the chunk fits, break)
                if (($count = floor($since / $seconds)) != 0) {
                    break;
                }
            }

            return sprintf(_n("1 $name ago", "$count {$name}s ago", $count), $count);
        }

        /**
         * Displays a list of users that can be selected!
         *
         * @param ???
         * @param ???
         *
         * @since 0.7
         *
         * @todo  Add pagination support for blogs with billions of users
         *
         */
        public function users_select_form($selected = null, $args = null)
        {
            // Set up arguments
            $defaults    = [
                'list_class' => 'pp-users-select-form',
                'input_id'   => 'pp-selected-users',
            ];
            $parsed_args = wp_parse_args($args, $defaults);
            extract($parsed_args, EXTR_SKIP);

            $args = [
                'fields'  => [
                    'ID',
                    'display_name',
                    'user_email',
                ],
                'orderby' => 'display_name',
            ];
            $args = apply_filters('pp_users_select_form_get_users_args', $args);

            /**
             * Filters the list of users available for notification.
             *
             * @param array $users
             * @param int $post_id
             */
            $post_id = isset($_GET['post']) ? (int)$_GET['post'] : null;
            $users   = apply_filters('publishpress_notification_users_meta_box', get_users($args), $post_id);

            if (!is_array($selected)) {
                $selected = [];
            }

            // Extract emails from the selected list, if there is any.
            $emails = [];
            if (!empty($selected)) {
                foreach ($selected as $item) {
                    if (strpos($item, '@') > 0) {
                        $emails[] = $item;
                    }
                }
            }

            /**
             * Filters the list of roles available for notification.
             *
             * @param array $roles
             * @param int $post_id
             */
            $roles = apply_filters('publishpress_notification_roles_meta_box', get_editable_roles(), $post_id);

            $groups = [];

            if (class_exists('PublishPress\Permissions\API')) {
                $groups = \PublishPress\Permissions\API::getGroups('pp_group', ['include_metagroups' => false]);
            }
            ?>

            <?php if (!empty($users) || !empty($roles) || !empty($emails)) : ?>
            <select id="to_notify" class="chosen-select" name="to_notify[]" multiple="multiple">
                <?php if (!empty($roles)) : ?>
                    <optgroup label="<?php echo esc_attr__('Roles', 'publishpress'); ?>">
                        <?php foreach ($roles as $role => $data) : ?>
                            <?php $attrSelected = (in_array($role, $selected)) ? 'selected="selected"' : ''; ?>
                            <option value="<?php echo esc_attr($role); ?>" <?php echo $attrSelected; ?>><?php echo __(
                                    'Role',
                                    'publishpress'
                                ); ?>: <?php echo $data['name']; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
                <?php if (!empty($groups)) : ?>
                    <optgroup label="<?php echo esc_attr__('Groups', 'publishpress'); ?>">
                        <?php foreach ($groups as $group_id => $group) : ?>
                            <?php $attrSelected = (in_array("group-$group_id", $selected)) ? 'selected="selected"' : ''; ?>
                            <option value="group-<?php echo esc_attr($group_id); ?>" <?php echo $attrSelected; ?>><?php echo __(
                                    'Group',
                                    'publishpress'
                                ); ?>: <?php echo $group->name; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
                <?php if (!empty($users)) : ?>
                    <optgroup label="<?php echo __('Users', 'publishpress'); ?>">
                        <?php foreach ($users as $user) : ?>
                            <?php $attrSelected = (in_array($user->ID, $selected)) ? 'selected="selected"' : ''; ?>
                            <option value="<?php echo esc_attr(
                                $user->ID
                            ); ?>" <?php echo $attrSelected; ?>><?php echo $user->display_name; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
                <?php if (!empty($emails)) : ?>
                    <optgroup label="<?php echo __('E-mails', 'publishpress'); ?>">
                        <?php foreach ($emails as $email) : ?>
                            <?php $attrSelected = (in_array($email, $selected)) ? 'selected="selected"' : ''; ?>
                            <option value="<?php echo esc_attr(
                                $email
                            ); ?>" <?php echo $attrSelected; ?>><?php echo $email; ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
        <?php endif; ?>
            <?php
        }


        /**
         * @param $role
         * @param $caps
         *
         * @deprecated 1.9.8 Use PublishPress\Util class instead
         */
        public function add_caps_to_role($role, $caps)
        {
            PublishPress\Legacy\Util::add_caps_to_role($role, $caps);
        }

        /**
         * Add settings help menus to our module screens if the values exist
         * Auto-registered in PublishPress::register_module()
         *
         * @since 0.7
         */
        public function action_settings_help_menu()
        {
            $screen = get_current_screen();

            if (!method_exists($screen, 'add_help_tab')) {
                return;
            }

            if ($screen->id != 'planner_page_' . $this->module->settings_slug) {
                return;
            }

            // Make sure we have all of the required values for our tab
            if (isset($this->module->settings_help_tab['id'], $this->module->settings_help_tab['title'], $this->module->settings_help_tab['content'])) {
                $screen->add_help_tab($this->module->settings_help_tab);

                if (isset($this->module->settings_help_sidebar)) {
                    $screen->set_help_sidebar($this->module->settings_help_sidebar);
                }
            }
        }

        /**
         * Upgrade the term descriptions for all of the terms in a given taxonomy
         */
        public function upgrade_074_term_descriptions($taxonomy)
        {
            $args = [
                'hide_empty' => false,
            ];

            $terms = get_terms($taxonomy, $args);
            foreach ($terms as $term) {
                // If the description already contains structured data, keep it as-is.
                $unencoded_description = $this->get_unencoded_description($term->description);
                if (is_array($unencoded_description)) {
                    continue;
                }

                $description_args = ['description' => $term->description];

                $new_description = $this->get_encoded_description($description_args);
                wp_update_term(
                    $term->term_id,
                    $taxonomy,
                    [
                        'description' => $new_description,
                    ]
                );
            }
        }

        public static function isPublishPressModuleEnabled($module_slug)
        {
            global $publishpress;

            return isset($publishpress->{$module_slug})
                && $publishpress->{$module_slug}->module->options->enabled === 'on';
        }

        public function getUserAuthorizedPostStatusOptions($postType, $post = false)
        {
            global $pp_post_type_status_options;

            if (!is_array($pp_post_type_status_options)) {
                $pp_post_type_status_options = [];
            }

            if (isset($pp_post_type_status_options[$postType])) {
                return apply_filters('publishpress_user_post_status_options', $pp_post_type_status_options[$postType], $postType, $post);
            }

            $postStatuses = $this->getPostStatusOptions($postType, $post);

            foreach ($postStatuses as $index => $status) {
                // Filter publishing posts if the post type is set
                if (in_array($status['value'], ['publish', 'future', 'private'])) {
                    $postTypeObj = get_post_type_object($postType);
                    if (! current_user_can($postTypeObj->cap->publish_posts)) {
                        unset($postStatuses[$index]);
                    }
                }
            }

            $pp_post_type_status_options[$postType] = $postStatuses;

            return apply_filters('publishpress_user_post_status_options', $postStatuses, $postType, $post);
        }

        public function getPostStatusOptions($postType = '', $post = false)
        {
            if (!$defined_statuses = apply_filters('publishpress_defined_post_statuses', [], $postType, $post)) {
                $postStatuses = [];
                $post_statuses_terms       = get_terms('post_status', ['hide_empty' => false]);
                $post_statuses_terms_slugs = (!is_wp_error($post_statuses_terms)) ? array_column($post_statuses_terms, 'slug') : [];
                $defined_statuses = $this->get_post_statuses();
            }
            
            foreach ($defined_statuses as $status) {
                //add support for capabilities custom statuses
                if (defined('PUBLISHPRESS_CAPS_PRO_VERSION')
                    && !empty(get_option('cme_custom_status_control'))
                    && in_array($status->slug, $post_statuses_terms_slugs)
                    && !current_user_can('status_change_' . $status->slug)
                ) {
                    continue;
                }
                $postStatuses[] = [
                    'value' => esc_attr($status->slug),
                    'text' => esc_html($status->label),
                ];
            }

            return apply_filters('publishpress_post_status_options', $postStatuses, $postType, $post);
        }

        /**
         * Retrieve wordpress registered taxonomy
         *
         * Private taxonomy are excluded
         *
         * @since 3.7.0
         */
        public function get_all_taxonomies()
        {

            //category and post tag are not included in public taxonomy
            $category   = get_taxonomies(['name' => 'category'], 'objects');
            $post_tag   = get_taxonomies(['name' => 'post_tag'], 'objects');

            $public     = get_taxonomies(['_builtin' => false, 'public'   => true], 'objects');

            $taxonomies = array_merge($category, $post_tag, $public);

            return $taxonomies;
        }

        /**
         * Retrieve post types taxonomies
         *
         * @param array $post_types
         *
         * @return array
         */
        public function get_post_types_taxonomies($post_types)
        {
            $taxonomies = array_map(
                function ( $post_type ) {
                    return get_object_taxonomies( $post_type, 'objects' );
                },
                $post_types
            );
            
            // Make sure there's no duplicate
            $taxonomies = call_user_func_array( 'array_merge', $taxonomies );

            // Keep only those where show_ui is not empty.
            $taxonomies = array_filter( $taxonomies, function( $taxonomy ) {
                return ! empty( $taxonomy->show_ui );
            });
            
            return $taxonomies;
        }

        /**
         *
         * @param string $param The parameter to look for in $_GET
         *
         * @return mixed null if the parameter is not set in $_GET, empty string if the parameter is empty in $_GET,
         *                      or a sanitized version of the parameter from $_GET if set and not empty
         */
        public function filter_get_param($param, $request_filter = false)
        {
            if (!$request_filter) {
                $request_filter = $_GET;
            }

            // Sure, this could be done in one line. But we're cooler than that: let's make it more readable!
            if (! isset($request_filter[$param])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return null;
            } elseif (empty($request_filter[$param])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return '';
            }

            return sanitize_key($request_filter[$param]); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        /**
         * This function is an alternative to filter_get_param() that's stripping out date characters
         *
         * @param string $param The parameter to look for in $_GET
         *
         * @return mixed null if the parameter is not set in $_GET, empty string if the parameter is empty in $_GET,
         *                      or a sanitized version of the parameter from $_GET if set and not empty
         */
        public function filter_get_param_text($param, $request_filter = false)
        {
            if (!$request_filter) {
                $request_filter = $_GET;
            }

            // Sure, this could be done in one line. But we're cooler than that: let's make it more readable!
            if (! isset($request_filter[$param])) {
                return null;
            } elseif ($request_filter[$param] == '0') {
                return 0;
            } elseif (empty($request_filter[$param])) {
                return '';
            }

            return sanitize_text_field($request_filter[$param]);
        }

        public function meta_query_operator_label($operator = false) {
            $operators = [
                'equals'                    => __('Equals (=)', 'publishpress'),
                'not_equals'                => __('Does not equal (!=)', 'publishpress'),
                'greater_than'              => __('Greater than (>)', 'publishpress'),
                'greater_than_or_equals'    => __('Greater than or equals (>=)', 'publishpress'),
                'less_than'                 => __('Less than (<)', 'publishpress'),
                'less_than_or_equals'       => __('Less than or equals (<=)', 'publishpress'),
                'like'                      => __('Like/Contains', 'publishpress'),
                'not_like'                  => __('Not Like', 'publishpress'),
                'not_exists'                => __('Not Exists/Empty', 'publishpress'),
            ];

            if ($operator) {
                $return = array_key_exists($operator, $operators) ? $operators[$operator] : $operator;
            } else {
                $return = $operators;
            }

            return $return;
        }

        public function meta_query_operator_symbol($operator = false) {

            return self::meta_query_operator_symbol($operator = false);
        }

        public static function static_meta_query_operator_symbol($operator = false) {
            $operators = [
                'equals'                    => '=',
                'not_equals'                => '!=',
                'greater_than'              => '>',
                'greater_than_or_equals'    => '>=',
                'less_than'                 => '<',
                'less_than_or_equals'       => '<=',
                'like'                      => 'LIKE',
                'not_like'                  => 'NOT LIKE',
                'not_exists'                => 'NOT EXISTS',
            ];

            if ($operator) {
                $return = array_key_exists($operator, $operators) ? $operators[$operator] : $operator;
            } else {
                $return = $operators;
            }

            return $return;
        }

        public function localize_post_data($localized_post_data, $post, $can_edit_post) {
            global $publishpress;

            // add taxonomies
            $taxonomies = get_object_taxonomies($post->post_type, 'object');
            foreach ($taxonomies as $taxonomy => $tax_object ) {
                if (!empty($tax_object->public)
                    && !in_array($taxonomy, ['author', 'post_format', 'post_status', 'post_status_core_wp_pp', 'post_visibility_pp', 'pp_revision_status'])) {
                    $terms = get_the_terms($post->ID, $taxonomy);
                    //add post content to localized data
                    $localized_post_data['taxonomies'][$post->ID][$taxonomy] = [
                        'post_id'          => $post->ID,
                        'taxonomy'         => $taxonomy,
                        'taxonomy_label'   => $tax_object->label,
                        'taxonomy_placeholder' => sprintf(esc_attr__('Select %s', 'publishpress'), esc_html($tax_object->label)),
                        'terms'            => (!empty($terms) && !is_wp_error($terms)) ? $terms : []
                    ];
                }
            }

            $post_title = _draft_or_post_title($post->ID);

            //add post content to localized data
            $localized_post_data['posts'][] = [
                'post_id'          => $post->ID,
                'post_title'       => $post_title,
                'raw_title'        => $post->post_title,
                'filtered_title'   => isset($post->filtered_title) ? $post->filtered_title : $post->post_title,
                'post_status'      => (function_exists('rvy_in_revision_workflow') && rvy_in_revision_workflow($post->ID)) ? $post->post_mime_type : $post->post_status,
                'status_label'     => $this->get_post_status_friendly_name($post->post_status),
                'status_options'   => $this->getUserAuthorizedPostStatusOptions($post->post_type, $post),
                'status_field_label'   => (function_exists('rvy_in_revision_workflow') && rvy_in_revision_workflow($post->ID)) ? esc_html__('Revision Status', 'publishpress') : esc_html__('Post Status', 'publishpress'),
                'can_edit_post'    => $can_edit_post ? 1 : 0,
                'date_markup'      => $can_edit_post ? $this->get_date_markup($post) : get_the_time(get_option("date_format"), $post->ID) . " " . get_the_time(get_option("time_format"), $post->ID),
                'action_links'     => $this->get_post_action_links($post, $can_edit_post),
                'author_markup'    => $this->get_author_markup($post, $can_edit_post),
                'post_content'     => apply_filters('the_content', $post->post_content)
            ];

            return $localized_post_data;
        }

        public function get_date_markup($post) {
            $date_name = 'content_board_post_date';
            $date_markup =
            sprintf(
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
                esc_attr($date_name),
                esc_attr(date_i18n('F j, Y H:i', strtotime($post->post_date))),
                esc_attr(pp_convert_date_format_to_jqueryui_datepicker('Y-m-d H:i:s')),
                ''
            );
            $date_markup .= sprintf(
                '<input
                    type="hidden"
                    class="%s_hidden"
                    name="%s_hidden"
                    value="%s"
                />',
                esc_attr($date_name),
                esc_attr($date_name),
                esc_attr(date_i18n('Y-m-d H:i:s', strtotime($post->post_date)))
            );

            return $date_markup;
        }

        public function get_post_action_links($post, $can_edit_post) {

            $post_type_object = get_post_type_object($post->post_type);

            $item_actions = [
                'edit' => '',
                'trash' => '',
                'view' => '',
                'previewpost' => '',
            ];

            if ($can_edit_post) {
                $item_actions["edit"] = esc_url(get_edit_post_link($post->ID));
            }

            if (EMPTY_TRASH_DAYS > 0 && current_user_can($post_type_object->cap->delete_post, $post->ID)) {
                $item_actions["trash"] = esc_url(get_delete_post_link($post->ID));
            }

            if (in_array($post->post_status, ["publish"])) {
                $item_actions["view"] = esc_url(get_permalink($post->ID));
            } elseif ($can_edit_post) {
                $item_actions["previewpost"] = esc_url(
                    apply_filters(
                        "preview_post_link",
                        add_query_arg("preview", "true", get_permalink($post->ID)),
                        $post
                    )
                );
            }

            return apply_filters('publishpress_item_action_links', $item_actions, $post, $can_edit_post);
        }

        public function get_author_markup($post, $can_edit_post) {

            if ($can_edit_post) {
                ob_start();
                $authorId = (int) $post->post_author;
                if (function_exists('get_post_authors')) {
                    $authors = get_post_authors($post);
                    $multiple = 'multiple';
                } else {
                    $authors = [$authorId];
                    $multiple = '';
                }
                ?>
                <select class="pp-modal-form-author" <?php echo esc_attr($multiple); ?>>
                    <?php
                    if (! empty($authors)) {
                        foreach ($authors as $author) {
                            $author = is_object($author) ? $author : get_user_by('id', $author);
                            $option = '';

                            if (! empty($author)) {
                                $option = '<option value="' . esc_attr($author->ID) . '" selected="selected">' . esc_html(
                                        $author->display_name
                                    ) . '</option>';
                            }

                            $option = apply_filters('publishpress_author_filter_selected_option', $option, $authorId);

                            echo $option; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    }
                    ?>
                </select>
                <?php
                return ob_get_clean();
            } else {
                $post_author = get_userdata($post->post_author);
                $author_name = is_object($post_author) ? $post_author->display_name : '';
                $author_name = apply_filters('the_author', $author_name);

                $author_name = apply_filters('publishpress_content_overview_author_column', $author_name, $post);
                return $author_name;
            }
        }

        public function get_publish_date_markup() {
            ob_start();
            ?>
			<div class="misc-pub-section curtime misc-pub-curtime">
				<fieldset id="timestampdiv">
					<?php $this->touch_time(); ?>
				</fieldset>
			</div>
            <?php
            $publish_date_markup = ob_get_clean();
            return $publish_date_markup;
        }

        public function touch_time( $tab_index = 0, $multi = 0 ) {
            global $wp_locale;

            $tab_index_attribute = '';
            if ( (int) $tab_index > 0 ) {
                $tab_index_attribute = " tabindex=\"$tab_index\"";
            }

            $jj        = current_time( 'd' );
            $mm        = current_time( 'm' );
            $aa        = current_time( 'Y' );
            $hh        = current_time( 'H' );
            $mn        = current_time( 'i' );
            $ss        = current_time( 's' );

            $cur_jj = current_time( 'd' );
            $cur_mm = current_time( 'm' );
            $cur_aa = current_time( 'Y' );
            $cur_hh = current_time( 'H' );
            $cur_mn = current_time( 'i' );

            $month = '<label><span class="screen-reader-text">' .
                /* translators: Hidden accessibility text. */
                __( 'Month' ) .
            '</span><select class="form-required" ' . ( $multi ? '' : 'id="mm" ' ) . 'name="mm"' . $tab_index_attribute . ">\n";
            for ( $i = 1; $i < 13; $i = $i + 1 ) {
                $monthnum  = zeroise( $i, 2 );
                $monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
                $month    .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '" ' . selected( $monthnum, $mm, false ) . '>';
                /* translators: 1: Month number (01, 02, etc.), 2: Month abbreviation. */
                $month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
            }
            $month .= '</select></label>';

            $day = '<label><span class="screen-reader-text">' .
                /* translators: Hidden accessibility text. */
                __( 'Day' ) .
            '</span><input type="text" ' . ( $multi ? '' : 'id="jj" ' ) . 'name="jj" value="' . $jj . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
            $year = '<label><span class="screen-reader-text">' .
                /* translators: Hidden accessibility text. */
                __( 'Year' ) .
            '</span><input type="text" ' . ( $multi ? '' : 'id="aa" ' ) . 'name="aa" value="' . $aa . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
            $hour = '<label><span class="screen-reader-text">' .
                /* translators: Hidden accessibility text. */
                __( 'Hour' ) .
            '</span><input type="text" ' . ( $multi ? '' : 'id="hh" ' ) . 'name="hh" value="' . $hh . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';
            $minute = '<label><span class="screen-reader-text">' .
                /* translators: Hidden accessibility text. */
                __( 'Minute' ) .
            '</span><input type="text" ' . ( $multi ? '' : 'id="mn" ' ) . 'name="mn" value="' . $mn . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" class="form-required" /></label>';

            echo '<div class="timestamp-wrap">';
            /* translators: 1: Month, 2: Day, 3: Year, 4: Hour, 5: Minute. */
            printf( __( '%1$s %2$s, %3$s at %4$s:%5$s' ), $month, $day, $year, $hour, $minute );

            echo '</div><input type="hidden" id="ss" name="ss" value="' . $ss . '" />';

            if ( $multi ) {
                return;
            }

            echo "\n\n";

            $map = array(
                'mm' => array( $mm, $cur_mm ),
                'jj' => array( $jj, $cur_jj ),
                'aa' => array( $aa, $cur_aa ),
                'hh' => array( $hh, $cur_hh ),
                'mn' => array( $mn, $cur_mn ),
            );

            foreach ( $map as $timeunit => $value ) {
                list( $unit, $curr ) = $value;

                echo '<input type="hidden" id="hidden_' . $timeunit . '" name="hidden_' . $timeunit . '" value="' . $unit . '" />' . "\n";
                $cur_timeunit = 'cur_' . $timeunit;
                echo '<input type="hidden" id="' . $cur_timeunit . '" name="' . $cur_timeunit . '" value="' . $curr . '" />' . "\n";
            }
            ?>
            <?php
        }


    }
}
