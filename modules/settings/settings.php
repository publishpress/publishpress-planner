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

use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Legacy\Util;

if (! class_exists('PP_Settings')) {
    #[\AllowDynamicProperties]
    class PP_Settings extends PP_Module
    {
        use Dependency_Injector;

        const SETTINGS_SLUG = 'pp-settings';

        /**
         * @var string
         */
        const MENU_SLUG = 'pp-modules-settings';

        public $module;

        /**
         * Register the module with PublishPress but don't do anything else
         */
        public function __construct()
        {
            $this->viewsPath = __DIR__ . '/views';

            parent::__construct();

            // Register the module with PublishPress
            $this->module_url = $this->get_module_url(__FILE__);
            $args             = [
                'title'                => __('PublishPress', 'publishpress'),
                'extended_description' => false,
                'module_url'           => $this->module_url,
                'icon_class'           => 'dashicons dashicons-admin-settings',
                'slug'                 => 'settings',
                'settings_slug'        => self::SETTINGS_SLUG,
                'default_options'      => [
                    'enabled' => 'on',
                ],
                'configure_page_cb' => 'print_default_settings',
                'autoload'          => true,
                'add_menu' => true,
            ];

            $this->module = PublishPress()->register_module('settings', $args);
        }

        /**
         * Initialize the rest of the stuff in the class if the module is active
         */
        public function init()
        {
            if (is_admin()) {
                add_action('admin_init', [$this, 'helper_settings_validate_and_save'], 100);

                add_filter('publishpress_admin_menu_slug', [$this, 'filter_admin_menu_slug'], 990);
                add_action('publishpress_admin_menu_page', [$this, 'action_admin_menu_page'], 990);
                add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu'], 990);

                add_action('admin_print_styles', [$this, 'action_admin_print_styles']);
                add_action('admin_print_scripts', [$this, 'action_admin_print_scripts']);
                add_action('admin_enqueue_scripts', [$this, 'action_admin_enqueue_scripts']);
            }
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
            if (empty($menu_slug) && $this->module_enabled('settings')) {
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
                esc_html__('settings', 'publishpress'),
                apply_filters('pp_view_settings_cap', 'manage_options'),
                self::MENU_SLUG,
                [$this, 'options_page_controller']
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
                esc_html__('PublishPress Settings', 'publishpress'),
                esc_html__('Settings', 'publishpress'),
                apply_filters('pp_view_settings_cap', 'manage_options'),
                self::MENU_SLUG,
                [$this, 'options_page_controller'],
                200
            );
        }

        public function action_admin_enqueue_scripts()
        {
            if ($this->is_whitelisted_settings_view()) {
                // Enqueue scripts
            }
        }

        /**
         * Add settings styles to the settings page
         */
        public function action_admin_print_styles()
        {
            if ($this->is_whitelisted_settings_view()) {
                wp_enqueue_style('publishpress-settings-css', $this->module_url . 'lib/settings.css', false, PUBLISHPRESS_VERSION);
            }
        }

        /**
         * Extra data we need on the page for transitions, etc.
         *
         * @since 0.7
         */
        public function action_admin_print_scripts()
        {
            ?>
			<script type="text/javascript">
				var pp_admin_url = '<?php echo esc_js(get_admin_url()); ?>';
			</script>
			<?php
        }

        /**
         *
         */
        public function print_default_header($current_module, $custom_text = null)
        {
            $display_text = '';

            // If there's been a message, let's display it
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
            } elseif (isset($_REQUEST['message'])) {
                $message = sanitize_text_field($_REQUEST['message']);
            } elseif (isset($_POST['message'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
                $message = sanitize_text_field(wp_unslash($_POST['message'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
            } else {
                $message = false;
            }

            if ($message && isset($current_module->messages[$message])) {
                $display_text .= '<div class="is-dismissible notice notice-info"><p>' . esc_html($current_module->messages[$message]) . '</p></div>';
            }

            // If there's been an error, let's display it
            if (isset($_GET['error'])) {
                $error = sanitize_text_field($_GET['error']);
            } elseif (isset($_REQUEST['error'])) {
                $error = sanitize_text_field($_REQUEST['error']);
            } elseif (isset($_POST['error'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
                $error = sanitize_text_field(wp_unslash($_POST['error'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
            } else {
                $error = false;
            }
            if ($error && isset($current_module->messages[$error])) {
                $display_text .= '<div class="is-dismissible notice notice-error"><p>' . esc_html($current_module->messages[$error]) . '</p></div>';
            }
            ?>

			<div class="publishpress-admin pressshack-admin-wrapper wrap <?php echo esc_attr($current_module->slug); ?>">
				<header>
					<h1 class="wp-heading-inline"><?php echo wp_kses_post($current_module->title); ?></h1>

					<?php echo wp_kses_post($display_text); ?>
					<?php // We keep the H2 tag to keep notices tied to the header?>
					<h2>

						<?php if ($current_module->short_description && empty($custom_text)): ?>
							<?php echo wp_kses_post($current_module->short_description); ?>
						<?php endif; ?>

						<?php if (!empty($custom_text)) : ?>
							<?php echo wp_kses($custom_text, array_merge(wp_kses_allowed_html('post'), [
                                'select' => ['id' => true, 'name' => true, 'class' => true],
                                'option' => ['value' => true, 'selected' => true],
                                'optgroup' => ['label' => true],
                                'input' => ['id' => true, 'name' => true, 'type' => true, 'value' => true, 'class' => true, 'readonly' => true],
                                'button' => ['id' => true, 'type' => true, 'class' => true, 'data-clipboard-target' => true, 'data-clipboard-text' => true],
                            ])); ?>
						<?php endif; ?>
					</h2>

				</header>
			<?php
        }

        /**
         * Adds Settings page for PublishPress.
         */
        public function print_default_settings()
        {
            ?>
			<div class="publishpress-modules">
				<?php $this->print_modules(); ?>
			</div>
			<?php
        }

        /**
         * Echo or returns the default footer
         *
         * @param object $current_module
         * @param bool   $echo
         *
         * @return string
         */
        public function print_default_footer($current_module, $echo = true)
        {
            if (apply_filters('publishpress_show_footer', true)) {
                $html = $this->view->render(
                    'footer-base',
                    [
                        'current_module' => $current_module,
                        'plugin_name'    => __('PublishPress Planner', 'publishpress'),
                        'plugin_slug'    => 'publishpress',
                        'plugin_url'     => PUBLISHPRESS_URL,
                        'rating_message' => __('If you like %s please leave us a %s rating. Thank you!', 'publishpress'),
                    ],
                    $this->viewsPath
                );

                if (! $echo) {
                    return $html;
                }

                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The footer template escapes its dynamic values.
            }

            return '';
        }

        public function print_modules()
        {
            global $publishpress;

            if (!count($publishpress->modules)) {
                echo '<div class="message error">' . esc_html__('There are no PublishPress modules registered', 'publishpress') . '</div>';
            } else {
                foreach ($publishpress->modules as $mod_name => $mod_data) {
                    $add_menu = isset($mod_data->add_menu) && $mod_data->add_menu === true;

                    if ($mod_data->autoload || !$add_menu) {
                        continue;
                    }

                    if ($mod_data->options->enabled !== 'off') {
                        $url = '';

                        if ($mod_data->configure_page_cb && (!isset($mod_data->show_configure_btn) || $mod_data->show_configure_btn === true)) {
                            $url = add_query_arg('page', $mod_data->settings_slug, get_admin_url(null, 'admin.php'));
                        } elseif ($mod_data->page_link) {
                            $url = $mod_data->page_link;
                        }

                        $this->view->display(
                            'module',
                            [
                                'has_config_link' => isset($mod_data->configure_page_cb) && !empty($mod_data->configure_page_cb),
                                'slug'            => $mod_data->slug,
                                'icon_class'      => isset($mod_data->icon_class) ? $mod_data->icon_class : false,
                                'form_action'     => get_admin_url(null, 'options.php'),
                                'title'           => $mod_data->title,
                                'description'     => wp_kses($mod_data->short_description, 'a'),
                                'url'             => $url,
                            ],
                            $this->viewsPath
                        );
                    }
                }
            }
        }

        /**
         * Given a form field and a description, prints either the error associated with the field or the description.
         *
         * @param string $field The form field for which to check for an error
         * @param string $description Unlocalized string to display if there was no error with the given field
         *
         *@since 0.7
         *
         */
        public function helper_print_error_or_description($field, $description)
        {
            if (isset($_REQUEST['form-errors'][$field])): ?>
				<div class="form-error">
					<p><?php echo esc_html($_REQUEST['form-errors'][$field]); ?></p>
				</div>
			<?php else: ?>
				<p class="description"><?php echo esc_html($description); ?></p>
			<?php endif;
        }

        /**
         * Generate an option field to turn post type support on/off for a given module
         *
         * @param object $module      PublishPress module we're generating the option field for
         * @param array  $post_types  If empty, we consider all post types
         *
         * @since 0.7
         */
        public function helper_option_custom_post_type($module, $post_types = [])
        {
            if (empty($post_types)) {
                $post_types = [
                    'post' => __('Posts'),
                    'page' => __('Pages'),
                ];
                $custom_post_types = $this->get_supported_post_types_for_module($module);
                if (count($custom_post_types)) {
                    foreach ($custom_post_types as $custom_post_type => $args) {
                        $post_types[$custom_post_type] = $args->label;
                    }
                }
            }

            foreach ($post_types as $post_type => $title) {
                echo '<label for="' . esc_attr($post_type) . '-' . esc_attr($module->slug) . '">';
                echo '<input id="' . esc_attr($post_type) . '-' . esc_attr($module->slug) . '" name="'
                    . esc_attr($module->options_group_name) . '[post_types][' . esc_attr($post_type) . ']"';
                if (isset($module->options->post_types[$post_type])) {
                    checked($module->options->post_types[$post_type], 'on');
                }
                // Defining post_type_supports in the functions.php file or similar should disable the checkbox
                disabled(post_type_supports($post_type, $module->post_type_support), true);
                echo ' type="checkbox" value="on" />&nbsp;&nbsp;&nbsp;' . esc_html($title) . '</label>';
                // Leave a note to the admin as a reminder that add_post_type_support has been used somewhere in their code
                if (post_type_supports($post_type, $module->post_type_support)) {
                    echo '&nbsp&nbsp;&nbsp;<span class="description">' . esc_html(sprintf(__('Disabled because add_post_type_support(\'%1$s\', \'%2$s\') is included in a loaded file.', 'publishpress'), $post_type, $module->post_type_support)) . '</span>';
                }
                echo '<br />';
            }
        }

        /**
         * Validation and sanitization on the settings field
         * This method is called automatically/ doesn't need to be registered anywhere
         *
         * @since 0.7
         */
        public function helper_settings_validate_and_save()
        {
            if (!isset($_POST['action'], $_POST['_wpnonce'], $_POST['option_page'], $_POST['_wp_http_referer'], $_POST['publishpress_module_name'], $_POST['submit']) || !is_admin()) {
                return false;
            }

            if ($_POST['action'] != 'update'
                || !isset($_GET['page']) || $_GET['page'] != 'pp-modules-settings') {
                return false;
            }

            if (!current_user_can('manage_options') || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'edit-publishpress-settings')) {
                wp_die(esc_html__('Cheatin&#8217; uh?', 'publishpress'));
            }

            global $publishpress;

            foreach ($_POST['publishpress_module_name'] as $moduleSlug) {
                $module_name = sanitize_key(PublishPress\Legacy\Util::sanitize_module_name($moduleSlug));

                $new_options = (isset($_POST[$publishpress->$module_name->module->options_group_name])) ? $_POST[$publishpress->$module_name->module->options_group_name] : [];

                /**
                 * Legacy way to validate the settings. Hook to the filter
                 * publishpress_validate_module_settings instead.
                 *
                 * @deprecated
                 */
                if (method_exists($publishpress->$module_name, 'settings_validate')) {
                    $new_options = $publishpress->$module_name->settings_validate($new_options);
                }

                // New way to validate settings
                $new_options = apply_filters('publishpress_validate_module_settings', $new_options, $module_name);

                // Cast our object and save the data.
                $new_options = (object)array_merge((array)$publishpress->$module_name->module->options, $new_options);
                $publishpress->update_all_module_options($publishpress->$module_name->module->name, $new_options);

                // Check if the module has a custom save method
                if (method_exists($publishpress->$module_name, 'settings_save')) {
                    $publishpress->$module_name->settings_save($new_options);
                }
            }

            // Redirect back to the settings page that was submitted without any previous messages
            $goback = add_query_arg('message', 'settings-updated', remove_query_arg(['message'], wp_get_referer()));
            wp_safe_redirect($goback);

            exit;
        }

        public function options_page_controller()
        {
            global $publishpress;

            $default_module = '';


            $all_modules = (array)$publishpress->modules;

            foreach ($all_modules as $mod_name => $mod_data) {
                // Set first module as default module
                if (empty($default_module) && !empty($mod_data->options_page) && $mod_data->options->enabled === 'on') {
                    $default_module = $mod_data->settings_slug;
                }

                //force notification tab if dependent tab is enabled
                if ($mod_data->slug == 'async-notifications' && $mod_data->options->enabled === 'on') {
                    $all_modules['notifications']->options->enabled = 'on';
                }

                if (isset($mod_data->notification_options)) {
                    if ($all_modules['notifications']->options->enabled === 'on') {
                        $all_modules['improved_notifications']->options->enabled = 'on';
                        $all_modules['notifications']->options->enabled = 'on';
                    } else {
                        $all_modules['improved_notifications']->options->enabled = 'off';
                        $all_modules['notifications']->options->enabled = 'off';
                    }
                    break;
                }
            }

            if (empty($default_module)) {
                $default_module = 'pp-modules-settings-settings';
            }

            $module_settings_slug = isset($_GET['settings_module']) && !empty($_GET['settings_module'])
                ? sanitize_text_field($_GET['settings_module'])
                : $default_module;

            // Custom Statuses are no longer defined by a Planner module
            if ('pp-custom-status-settings' === $module_settings_slug) {
                $module_settings_slug = $default_module;
            }

            $requested_module = $publishpress->get_module_by('settings_slug', $module_settings_slug);
            $display_text     = '';

            // If there's been a message, let's display it
            if (isset($_GET['message'])) {
                $message = sanitize_text_field($_GET['message']);
            } elseif (isset($_REQUEST['message'])) {
                $message = sanitize_text_field($_REQUEST['message']);
            } elseif (isset($_POST['message'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
                $message = sanitize_text_field(wp_unslash($_POST['message'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
            } else {
                $message = false;
            }

            if ($message && isset($requested_module->messages[$message])) {
                $display_text .= '<div class="is-dismissible notice notice-info"><p>' . esc_html($requested_module->messages[$message]) . '</p></div>';
            }

            // If there's been an error, let's display it
            if (isset($_GET['error'])) {
                $error = sanitize_text_field($_GET['error']);
            } elseif (isset($_REQUEST['error'])) {
                $error = sanitize_text_field($_REQUEST['error']);
            } elseif (isset($_POST['error'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
                $error = sanitize_text_field(wp_unslash($_POST['error'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reads a notice identifier for display; no state is changed.
            } else {
                $error = false;
            }

            if ($error && isset($requested_module->messages[$error])) {
                $display_text .= '<div class="is-dismissible notice notice-error"><p>' . esc_html($requested_module->messages[$error]) . '</p></div>';
            }

            $this->print_default_header($requested_module);

            // Get module output
            ob_start();

            $requested_module_name = !empty($requested_module->name)
                ? $requested_module->name
                : null;

            if (empty($requested_module_name)) {
                $requested_module = $publishpress->get_module_by('settings_slug', $module_settings_slug);
                $requested_module_name = $requested_module->name ?? '';
            }

            $requested_module_obj = isset($publishpress->$requested_module_name)
                ? $publishpress->$requested_module_name
                : null;

            $configure_callback = $requested_module->configure_page_cb ?? null;

            if (is_callable($configure_callback)) {
                call_user_func($configure_callback);
            } elseif (
                is_object($requested_module_obj) &&
                is_string($configure_callback) &&
                method_exists($requested_module_obj, $configure_callback)
            ) {
                $requested_module_obj->{$configure_callback}();
            }

            $module_output = ob_get_clean();

            $this->view->display(
                'settings',
                [
                    'modules'        => $all_modules,
                    'settings_slug'  => $module_settings_slug,
                    'slug'           => PP_Modules_Settings::SETTINGS_SLUG,
                    'module_output'  => $module_output,
                    'sidebar_output' => '',
                    'text'           => $display_text,
                    'show_sidebar'   => false,
                    'pro_active'     => Util::isPlannersProActive(),
                    'pro_sidebar'    => Util::pp_pro_sidebar(false),
                ],
                $this->viewsPath
            );

            $this->print_default_footer($requested_module);
        }

    }
}
