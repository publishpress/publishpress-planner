<?php
/**
 * Plugin Name: PublishPress Planner Free
 * Plugin URI: https://publishpress.com/
 * Description: PublishPress Planner helps you plan and publish content inside WordPress. Features include a content calendar, kanban board, and notifications.
 * Version: 4.8.1
 * Author: PublishPress
 * Author URI: https://publishpress.com
 * Text Domain: publishpress
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 7.2.5
 *
 * Copyright (c) 2025 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package     PublishPress
 * @author      PublishPress
 * @copyright   Copyright (C) 2024 PublishPress. All rights reserved.
 * @link        https://publishpress.com/
 * 
 */

use PPVersionNotices\Module\MenuLink\Module;
use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Notifications\Traits\PublishPress_Module;

global $wp_version;

$min_php_version = '7.2.5';
$min_wp_version  = '5.5';

$invalid_php_version = version_compare(phpversion(), $min_php_version, '<');
$invalid_wp_version = version_compare($wp_version, $min_wp_version, '<');

if ($invalid_php_version || $invalid_wp_version) {
    return;
}

if (! defined('PP_LIB_VENDOR_PATH')) {
    define('PP_LIB_VENDOR_PATH', __DIR__ . '/lib/vendor');
}

$instanceProtectionIncPath = PP_LIB_VENDOR_PATH . '/publishpress/instance-protection/include.php';
if (is_file($instanceProtectionIncPath) && is_readable($instanceProtectionIncPath)) {
    require_once $instanceProtectionIncPath;
}

$bundledTranslationsIncPath = PP_LIB_VENDOR_PATH . '/publishpress/bundled-translations/core/include.php';
if (is_file($bundledTranslationsIncPath) && is_readable($bundledTranslationsIncPath)) {
    require_once $bundledTranslationsIncPath;
}

if (class_exists('PublishPressInstanceProtection\\Config')) {
    $pluginCheckerConfig = new PublishPressInstanceProtection\Config();
    $pluginCheckerConfig->pluginSlug    = 'publishpress';
    $pluginCheckerConfig->pluginName    = 'PublishPress Planner';

    $pluginChecker = new PublishPressInstanceProtection\InstanceChecker($pluginCheckerConfig);
}

$autoloadFilePath = PP_LIB_VENDOR_PATH . '/autoload.php';
if (! class_exists('ComposerAutoloaderInitPublishPressPlanner')
    && is_file($autoloadFilePath)
    && is_readable($autoloadFilePath)
) {
    require_once $autoloadFilePath;
}

add_action('plugins_loaded', function () {

    require_once 'includes.php';

    if (class_exists('PublishPress\BundledTranslations\BundledTranslations')) {
        $bundledTranslations = new PublishPress\BundledTranslations\BundledTranslations(
            'publishpress',
            PUBLISHPRESS_BASE_PATH . '/languages',
            PUBLISHPRESS_FILE_PATH
        );
        $bundledTranslations->init();
    }

    // Core class
    if (! class_exists('publishpress')) {
    #[\AllowDynamicProperties]
    class publishpress
        {
            use Dependency_Injector, PublishPress_Module;

            // Unique identified added as a prefix to all options
            /**
             * @var PublishPress The one true PublishPress
             */
            private static $instance;

            public $options_group = 'publishpress_';

            public $options_group_name = 'publishpress_options';

            /**
             * @var stdClass
             */
            public $modules;

            public $custom_status; // back compat

            /**
             * @var array
             */
            public $class_names;

            protected $added_menu_page = false;

            protected $menu_slug;

            protected $loadedModules = [];

            /**
             * Main PublishPress Instance
             *
             * Insures that only one instance of PublishPress exists in memory at any one
             * time. Also prevents needing to define globals all over the place.
             *
             * @return publishpress
             */
            public static function instance()
            {
                if (! isset(self::$instance)) {
                    self::$instance = new publishpress();
                    self::$instance->setup_globals();
                    self::$instance->setup_actions();
                    // Backwards compat for when we promoted use of the $publishpress global
                    global $publishpress;
                    $publishpress = self::$instance;
                }

                return self::$instance;
            }

            private function setup_globals()
            {
                $this->modules = new stdClass();
            }

            /**
             * Setup the default hooks and actions
             *
             * @since  PublishPress 0.7.4
             * @access private
             * @uses   add_action() To add various actions
             */
            private function setup_actions()
            {
                add_action('init', [$this, 'action_init'], PUBLISHPRESS_ACTION_PRIORITY_INIT);
                add_action('init', [$this, 'action_init_after'], PUBLISHPRESS_ACTION_PRIORITY_INIT_LATE);
                add_action('init', [$this, 'action_ini_for_admin'], PUBLISHPRESS_ACTION_PRIORITY_INIT_ADMIN);
                add_action('admin_menu', [$this, 'action_admin_menu'], 9);

                add_action('admin_enqueue_scripts', [$this, 'register_scripts_and_styles']);

                // Fix the order of the submenus
                add_filter('custom_menu_order', [$this, 'filter_custom_menu_order']);

                do_action_ref_array('publishpress_after_setup_actions', [$this]);

                add_filter('debug_information', [$this, 'filterDebugInformation']);

                add_filter('cme_plugin_capabilities', [$this, 'filterCapabilities'], 11);
                // Redirect on plugin activation
                add_action('admin_init', [$this, 'redirect_on_activate'], 2000);
                // Add meta keys search
                add_action('wp_ajax_publishpress_content_search_meta_keys', [$this, 'searchMetaKeys']);
            }

            /**
             * The capabilities need to be set before the modules are loaded,
             * so the submenu items can be displayed correctly right after activate.
             * Otherwise we only see the submenus after visiting the PublishPress settings
             * menu for the first time.
             *
             */
            public static function activation_hook()
            {
                // @todo: This should be executed only when it is an upgrade, for specific versions, otherwise it overwrites the user's customizations.
                // Add necessary capabilities to allow management of calendar, content overview, notifications
                $genericCaps = [
                    'pp_view_calendar',
                    'pp_view_content_overview',
                    'pp_view_content_board',
                    'edit_post_subscriptions',
                    'pp_set_notification_channel',
                    'pp_delete_editorial_comment',
                    'pp_delete_others_editorial_comment',
                    'pp_edit_editorial_comment',
                    'pp_edit_others_editorial_comment',
                ];

                $roles = [
                    'administrator' => $genericCaps,
                    'editor' => $genericCaps,
                    'author' => $genericCaps,
                    'contributor' => $genericCaps,
                ];

                foreach ($roles as $role => $caps) {
                    PublishPress\Legacy\Util::add_caps_to_role($role, $caps);
                }

                // Additional capabilities
                $roles = [
                    'administrator' => [apply_filters('pp_manage_roles_cap', 'pp_manage_roles')],
                ];

                foreach ($roles as $role => $caps) {
                    PublishPress\Legacy\Util::add_caps_to_role($role, $caps);
                }
            }

            /**
             * Inititalizes the PublishPress!
             * Loads options for each registered module and then initializes it if it's active
             */
            public function action_init()
            {
                load_plugin_textdomain('publishpress', null, plugin_basename(PUBLISHPRESS_BASE_PATH) . '/languages/');

                $this->load_modules();

                // Load all of the module options
                $this->load_module_options();

                $this->checkBlockEditor();

                // Load all of the modules that are enabled.
                // Modules won't have an options value if they aren't enabled
                foreach ($this->modules as $mod_name => $mod_data) {
                    if (isset($mod_data->options->enabled) && $mod_data->options->enabled == 'on') {
                        $this->$mod_name->init();
                    }
                }

                do_action('pp_init');
            }

            /**
             * Include the common resources to PublishPress and dynamically load the modules
             */
            private function load_modules()
            {
                // We use the WP_List_Table API for some of the table gen
                if (! class_exists('WP_List_Table')) {
                    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
                }

                // PublishPress base module
                if (! class_exists('PP_Module')) {
                    require_once(PUBLISHPRESS_BASE_PATH . '/common/php/class-module.php');
                }

                $module_dirs = $this->getModulesDirs();

                $class_names = [];

                foreach ($module_dirs as $module_dir => $base_path) {
                    if (file_exists("{$base_path}/modules/{$module_dir}/{$module_dir}.php")) {
                        include_once "{$base_path}/modules/{$module_dir}/{$module_dir}.php";

                        // Prepare the class name because it should be standardized
                        $tmp = explode('-', $module_dir);
                        $class_name = '';
                        $slug_name = '';

                        foreach ($tmp as $word) {
                            $class_name .= ucfirst($word) . '_';
                            $slug_name .= $word . '_';
                        }

                        $slug_name = rtrim($slug_name, '_');
                        $class_names[$slug_name] = 'PP_' . rtrim($class_name, '_');
                    }
                }

                // Instantiate PP_Module as $helpers for back compat and so we can
                // use it in this class
                $this->helpers = new PP_Module();

                // Other utils
                require_once(PUBLISHPRESS_BASE_PATH . '/common/php/util.php');

                // Instantiate all of our classes onto the PublishPress object
                // but make sure they exist too
                foreach ($class_names as $slug => $class_name) {
                    if (class_exists($class_name)) {
                        $slug = PublishPress\Legacy\Util::sanitize_module_name($slug);
                        $module_instance = new $class_name();

                        $this->$slug = $module_instance;

                        // If there's a Help Screen registered for the module, make sure we auto-load it
                        $args = null;
                        if (isset($this->modules->$slug)) {
                            $args = $this->modules->$slug;
                        }

                        if (! is_null($args) && ! empty($args->settings_help_tab)) {
                            add_action(
                                'load-publishpress_page_' . $args->settings_slug,
                                [$module_instance, 'action_settings_help_menu']
                            );
                        }

                        $this->loadedModules[] = $slug;
                    }
                }

                $this->class_names = $class_names;

                // back compat for any existing $publishpress->custom_status->get_custom_status_by() calls
                if (class_exists('PublishPress_Statuses')) {
                    $this->custom_status = \PublishPress_Statuses::instance();
                } else {
                    $this->custom_status = new \PP_Module();
                }

                // Supplementary plugins can hook into this, include their own modules
                // and add them to the $publishpress object
                do_action('pp_modules_loaded');
            }

            /**
             * @return array
             */
            private function getModulesDirs()
            {
                // Scan the modules directory and include any modules that exist there
                $defaultDirs = [
                    'calendar' => PUBLISHPRESS_BASE_PATH,
                    'content-overview' => PUBLISHPRESS_BASE_PATH,
                    'content-board' => PUBLISHPRESS_BASE_PATH,
                    'notifications' => PUBLISHPRESS_BASE_PATH,
                    'improved-notifications' => PUBLISHPRESS_BASE_PATH,
                    'async-notifications' => PUBLISHPRESS_BASE_PATH,
                    'notifications-log' => PUBLISHPRESS_BASE_PATH,
                    'editorial-metadata' => PUBLISHPRESS_BASE_PATH,
                    'editorial-comments' => PUBLISHPRESS_BASE_PATH,
                    'efmigration' => PUBLISHPRESS_BASE_PATH,
                    'debug' => PUBLISHPRESS_BASE_PATH,
                    'reviews' => PUBLISHPRESS_BASE_PATH,
                    'theeventscalendar-integration' => PUBLISHPRESS_BASE_PATH,
                    'dashboard' => PUBLISHPRESS_BASE_PATH,
                    'modules-settings' => PUBLISHPRESS_BASE_PATH,
                    'settings' => PUBLISHPRESS_BASE_PATH,
                ];

                // Add filters to extend the modules
                return apply_filters('pp_module_dirs', $defaultDirs);
            }

            /**
             * Load all of the module options from the database
             * If a given option isn't yet set, then set it to the module's default (upgrades, etc.)
             */
            public function load_module_options()
            {
                foreach ($this->modules as $mod_name => $mod_data) {
                    $this->modules->$mod_name->options = get_option(
                        $this->options_group . $mod_name . '_options',
                        new stdClass()
                    );
                    foreach ($mod_data->default_options as $default_key => $default_value) {
                        if (! isset($this->modules->$mod_name->options->$default_key)) {
                            $this->modules->$mod_name->options->$default_key = $default_value;
                        }
                    }
                    $this->$mod_name->module = $this->modules->$mod_name;
                }

                do_action('pp_module_options_loaded');
            }

            /**
             * Check if need to restrict the use of the block editor, or Gutenberg.
             */
            protected function checkBlockEditor()
            {
                // If version is > 5+, check if the classical editor is installed, if not, ask to install.
                add_filter('use_block_editor_for_post_type', [$this, 'canUseBlockEditorForPostType'], 5, 2);
                add_filter('gutenberg_can_edit_post_type', [$this, 'canUseBlockEditorForPostType'], 5, 2);

                add_action('add_meta_boxes', [$this, 'removeEditorMetaBox']);
            }

            /**
             * @param array $debugInfo
             *
             * @return array
             */
            public function filterDebugInformation($debugInfo)
            {
                $constDisableWpCron = 'undefined';
                if (defined('DISABLE_WP_CRON')) {
                    $constDisableWpCron = DISABLE_WP_CRON;
                }

                $constWpDebug = 'undefined';
                if (defined('WP_DEBUG')) {
                    $constWpDebug = WP_DEBUG;
                }

                $constWpDebugLog = 'undefined';
                if (defined('WP_DEBUG_LOG')) {
                    $constWpDebugLog = WP_DEBUG_LOG;
                }

                $constWpDisplay = 'undefined';
                if (defined('WP_DEBUG_DISPLAY')) {
                    $constWpDisplay = WP_DEBUG_DISPLAY;
                }

                $debugInfo['publishpress'] = [
                    'label' => 'PublishPress Planner',
                    'description' => '',
                    'show_count' => false,
                    'fields' => [
                        'PUBLISHPRESS_VERSION' => [
                            'label' => __('PUBLISHPRESS_VERSION'),
                            'value' => PUBLISHPRESS_VERSION,
                        ],
                        'PUBLISHPRESS_BASE_PATH' => [
                            'label' => __('PUBLISHPRESS_BASE_PATH'),
                            'value' => PUBLISHPRESS_BASE_PATH,
                        ],
                        'PUBLISHPRESS_FILE_PATH' => [
                            'label' => __('PUBLISHPRESS_FILE_PATH'),
                            'value' => PUBLISHPRESS_FILE_PATH,
                        ],
                        'PUBLISHPRESS_URL' => [
                            'label' => __('PUBLISHPRESS_URL'),
                            'value' => PUBLISHPRESS_URL,
                        ],
                        'PUBLISHPRESS_SETTINGS_PAGE' => [
                            'label' => __('PUBLISHPRESS_SETTINGS_PAGE'),
                            'value' => PUBLISHPRESS_SETTINGS_PAGE,
                        ],
                        'PUBLISHPRESS_LIBRARIES_PATH' => [
                            'label' => __('PUBLISHPRESS_LIBRARIES_PATH'),
                            'value' => PUBLISHPRESS_LIBRARIES_PATH,
                        ],
                        'WP_CONTENT_DIR' => [
                            'label' => __('WP_CONTENT_DIR'),
                            'value' => WP_CONTENT_DIR,
                        ],
                        'WP_CONTENT_URL' => [
                            'label' => __('WP_CONTENT_URL'),
                            'value' => WP_CONTENT_URL,
                        ],
                        'DISABLE_WP_CRON' => [
                            'label' => __('DISABLE_WP_CRON'),
                            'value' => $constDisableWpCron,
                        ],
                        'WP_DEBUG' => [
                            'label' => __('WP_DEBUG'),
                            'value' => $constWpDebug,
                        ],
                        'WP_DEBUG_LOG' => [
                            'label' => __('WP_DEBUG_LOG'),
                            'value' => $constWpDebugLog,
                        ],
                        'WP_DEBUG_DISPLAY' => [
                            'label' => __('WP_DEBUG_DISPLAY'),
                            'value' => $constWpDisplay,
                        ],
                        'option::date_format' => [
                            'label' => __('WP Date Format'),
                            'value' => get_option('date_format'),
                        ],
                        'option::time_format' => [
                            'label' => __('WP Time Format'),
                            'value' => get_option('time_format'),
                        ],
                        'option::timezone_string' => [
                            'label' => __('WP Timezone String'),
                            'value' => get_option('timezone_string'),
                        ],
                        'option::gmt_offset' => [
                            'label' => __('WP GMT Offset'),
                            'value' => get_option('gmt_offset'),
                        ],
                        'php::date_default_timezone_get' => [
                            'label' => __('date_default_timezone_get'),
                            'value' => date_default_timezone_get(),
                        ],
                    ],
                ];


                // Modules
                $modules = [];
                $modulesDirs = $this->getModulesDirs();

                foreach ($this->loadedModules as $module) {
                    $dashCaseModule = str_replace('_', '-', $module);

                    $status = isset($this->{$module}) && isset($this->{$module}->module->options->enabled) ? $this->{$module}->module->options->enabled : 'on';

                    $modules[$module] = [
                        'label' => $module,
                        'value' => $status . ' [' . $modulesDirs[$dashCaseModule] . '/modules/' . $module . ']',
                    ];
                }

                $debugInfo['publishpress-modules'] = [
                    'label' => 'PublishPress Modules',
                    'description' => '',
                    'show_count' => true,
                    'fields' => $modules,
                ];

                return $debugInfo;
            }

            /**
             * Initialize the plugin for the admin
             */
            public function action_ini_for_admin()
            {
                // Upgrade if need be but don't run the upgrade if the plugin has never been used
                $previous_version = get_option($this->options_group . 'version');
                if ($previous_version && version_compare($previous_version, PUBLISHPRESS_VERSION, '<')) {
                    foreach ($this->modules as $mod_name => $mod_data) {
                        if (method_exists($this->$mod_name, 'upgrade')) {
                            $this->$mod_name->upgrade($previous_version);
                        }
                    }
                }

                update_option($this->options_group . 'version', PUBLISHPRESS_VERSION);

                // For each module that's been loaded, auto-load data if it's never been run before
                foreach ($this->modules as $mod_name => $mod_data) {
                    // If the module has never been loaded before, run the install method if there is one
                    if (! isset($mod_data->options->loaded_once) || ! $mod_data->options->loaded_once) {
                        if (method_exists($this->$mod_name, 'install')) {
                            $this->$mod_name->install();
                        }
                        $this->update_module_option($mod_name, 'loaded_once', true);
                    }
                }
            }

            /**
             * Update the $publishpress object with new value and save to the database
             */
            public function update_module_option($mod_name, $key, $value)
            {
                if (false === $this->modules->$mod_name->options) {
                    $this->modules->$mod_name->options = new stdClass();
                }

                $this->modules->$mod_name->options->$key = $value;
                $this->$mod_name->module = $this->modules->$mod_name;

                return update_option($this->options_group . $mod_name . '_options', $this->modules->$mod_name->options);
            }

            /**
             * @param        $page_title
             * @param        $menu_title
             * @param        $capability
             * @param        $menu_slug
             * @param string $function
             * @param string $icon_url
             * @param null $position
             */
            public function add_menu_page($page_title, $capability, $menu_slug, $function = '')
            {
                if ($this->added_menu_page) {
                    return;
                }

                add_menu_page(
                    $page_title,
                    esc_html__('Planner', 'publishpress'),
                    $capability,
                    $menu_slug,
                    $function,
                    'dashicons-calendar-alt',
                    26
                );

                $this->added_menu_page = true;
                $this->menu_slug = $menu_slug;
            }

            /**
             * Returns true if the menu page was already created. Returns false if not.
             *
             * @return bool
             */
            public function is_menu_page_created()
            {
                return (bool)$this->added_menu_page;
            }

            /**
             * Returns the menu slug for the menu page.
             *
             * @return string
             */
            public function get_menu_slug()
            {
                return $this->menu_slug;
            }

            /**
             * Add the menu page and call an action for modules add submenus
             */
            public function action_admin_menu()
            {
                /**
                 * Filters the menu slug. By default, each filter should only set a menu slug if it is empty.
                 * To determine the precedence of menus, use different priorities among the filters.
                 *
                 * @param string $menu_slug
                 */
                $this->menu_slug = apply_filters('publishpress_admin_menu_slug', $this->menu_slug);

                /**
                 * Action for adding menu pages.
                 */
                do_action('publishpress_admin_menu_page');

                /**
                 * @deprecated
                 */
                do_action('publishpress_admin_menu');

                /**
                 * Action for adding submenus.
                 */
                do_action('publishpress_admin_submenu');
            }

            /**
             * Register a new module with PublishPress
             */
            public function register_module($name, $args = [])
            {
                // A title and name is required for every module
                if (! isset($args['title'], $name)) {
                    return false;
                }

                $defaults = [
                    'title' => '',
                    'short_description' => '',
                    'extended_description' => '',
                    'icon_class' => 'dashicons dashicons-calendar-alt',
                    'slug' => '',
                    'post_type_support' => '',
                    'default_options' => [],
                    'options' => false,
                    'configure_page_cb' => false,
                    'configure_link_text' => __('Configure', 'publishpress'),
                    // These messages are applied to modules and can be overridden if custom messages are needed
                    'messages' => [
                        'form-error' => __('Please correct your form errors below and try again.', 'publishpress'),
                        'nonce-failed' => __('Cheatin&#8217; uh?', 'publishpress'),
                        'invalid-permissions' => __(
                            'You do not have necessary permissions to complete this action.',
                            'publishpress'
                        ),
                        'missing-post' => __('Post does not exist', 'publishpress'),
                    ],
                    'autoload' => false, // autoloading a module will remove the ability to enable or disable it
                ];
                if (isset($args['messages'])) {
                    $args['messages'] = array_merge((array)$args['messages'], $defaults['messages']);
                }
                $args = array_merge($defaults, $args);
                $args['name'] = $name;
                $args['options_group_name'] = $this->options_group . $name . '_options';

                if (! isset($args['settings_slug'])) {
                    $args['settings_slug'] = 'pp-' . $args['slug'] . '-settings';
                }

                if (empty($args['post_type_support'])) {
                    $args['post_type_support'] = 'pp_' . $name;
                }

                // If there's a Help Screen registered for the module, make sure we
                // auto-load it
                if (! empty($args['settings_help_tab'])) {
                    add_action(
                        'load-publishpress_page_' . $args['settings_slug'],
                        [&$this->$name, 'action_settings_help_menu']
                    );
                }

                $this->modules->$name = (object)$args;
                do_action('pp_module_registered', $name);

                return $this->modules->$name;
            }

            /**
             * Load the post type options again so we give add_post_type_support() a chance to work
             *
             * @see https://publishpress.com/2011/11/17/publishpress-v0-7-alpha2-notes/#comment-232
             */
            public function action_init_after()
            {
                foreach ($this->modules as $mod_name => $mod_data) {
                    if (isset($this->modules->$mod_name->options->post_types)) {
                        $this->modules->$mod_name->options->post_types = $this->helpers->clean_post_type_options(
                            $this->modules->$mod_name->options->post_types,
                            $mod_data->post_type_support
                        );
                    }

                    $this->$mod_name->module = $this->modules->$mod_name;
                }
            }

            /**
             * Get a module by one of its descriptive values
             */
            public function get_module_by($key, $value)
            {
                $module = false;
                foreach ($this->modules as $mod_name => $mod_data) {
                    if ($key == 'name' && $value == $mod_name) {
                        $module = $this->modules->$mod_name;
                    } else {
                        foreach ($mod_data as $mod_data_key => $mod_data_value) {
                            if ($mod_data_key == $key && $mod_data_value == $value) {
                                $module = $this->modules->$mod_name;
                            }
                        }
                    }
                }

                return $module;
            }

            public function update_all_module_options($mod_name, $new_options)
            {
                if (is_array($new_options)) {
                    $new_options = (object)$new_options;
                }

                $this->modules->$mod_name->options = $new_options;
                $this->$mod_name->module = $this->modules->$mod_name;

                return update_option($this->options_group . $mod_name . '_options', $this->modules->$mod_name->options);
            }

            /**
             * Registers commonly used scripts + styles for easy enqueueing
             *
             * @var  string $hook
             */
            public function register_scripts_and_styles()
            {
                global $pagenow, $typenow;
                $publishpress_pages = [
                    'pp-calendar',
                    'pp-content-overview',
                    'pp-content-board',
                    'pp-notif-log',
                    'pp-manage-roles',
                    'pp-modules-settings',
                    'pp-editorial-comments',
                    'pp-editorial-metadata',
                ];

                $is_pp_page_param      = isset($_GET['page']) && in_array(sanitize_key($_GET['page']), $publishpress_pages);
                $is_pp_post_type_param = isset($_GET['post_type']) && sanitize_key($_GET['post_type']) === 'psppnotif_workflow';
                $is_pp_edit_page_param = isset($_GET['post']) && (isset($pagenow) && $pagenow === 'post.php') && (isset($typenow) && $typenow === 'psppnotif_workflow');
                $is_publishpress_page  = $is_pp_page_param || $is_pp_post_type_param || $is_pp_edit_page_param;

                wp_register_style(
                    'jquery-listfilterizer',
                    PUBLISHPRESS_URL . 'common/css/jquery.listfilterizer.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'all'
                );

                if ($is_publishpress_page) {
                    wp_enqueue_style(
                        'pressshack-admin-css',
                        PUBLISHPRESS_URL . 'common/css/pressshack-admin.css',
                        [],
                        PUBLISHPRESS_VERSION,
                        'all'
                    );

                    wp_enqueue_style(
                        'pp-admin-css',
                        PUBLISHPRESS_URL . 'common/css/publishpress-admin.css',
                        ['pressshack-admin-css'],
                        PUBLISHPRESS_VERSION,
                        'all'
                    );
                }

                wp_register_script(
                    'jquery-listfilterizer',
                    PUBLISHPRESS_URL . 'common/js/jquery.listfilterizer.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION,
                    true
                );

                wp_register_script(
                    'jquery-quicksearch',
                    PUBLISHPRESS_URL . 'common/js/jquery.quicksearch.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION,
                    true
                );

                // @compat 3.3
                // Register jQuery datepicker plugin if it doesn't already exist. Datepicker plugin was added in WordPress 3.3
                global $wp_scripts;
                if (! isset($wp_scripts->registered['jquery-ui-datepicker'])) {
                    wp_register_script(
                        'jquery-ui-datepicker',
                        PUBLISHPRESS_URL . 'common/js/jquery.ui.datepicker.min.js',
                        ['jquery', 'jquery-ui-core'],
                        '1.8.16',
                        true
                    );
                }


                // Load on all admin pages to fix the menu, except the customize
                global $pagenow;
            }

            public function filter_custom_menu_order($menu_ord)
            {
                global $submenu, $publishpress;

                $parentMenuSlug = $publishpress->get_menu_slug();

                if (isset($submenu[$parentMenuSlug])) {
                    $currentSubmenu = $submenu[$parentMenuSlug];
                    $newSubmenu = [];
                    $upgradeMenuSlugs = [];

                    $menuItemCalendar = 'pp-calendar';
                    $menuItemContentOverview = 'pp-content-overview';
                    $menuItemContentBoard = 'pp-content-board';
                    $menuItemNotifications = 'edit.php?post_type=psppnotif_workflow';
                    $menuItemNotificationsLog = 'pp-notif-log';
                    $menuItemRoles = 'pp-manage-roles';
                    $menuItemSettings = 'pp-modules-settings';

                    // Get the index for the menus.
                    $itemsToSort = [
                        $menuItemCalendar => null,
                        $menuItemContentOverview => null,
                        $menuItemContentBoard => null,
                        $menuItemNotifications => null,
                        $menuItemNotificationsLog => null,
                        $menuItemRoles => null,
                        $menuItemSettings => null,
                    ];

                    if (! defined('PUBLISHPRESS_SKIP_VERSION_NOTICES')) {
                        $suffix = Module::MENU_SLUG_SUFFIX;
                        $upgradeMenuSlugs = [
                            $menuItemCalendar . $suffix => null,
                            $menuItemContentOverview . $suffix => null,
                            $menuItemContentBoard . $suffix => null,
                            $menuItemNotifications . $suffix => null,
                            $menuItemNotificationsLog . $suffix => null,
                            $menuItemRoles . $suffix => null,
                            $menuItemSettings . $suffix => null,
                        ];

                        $itemsToSort = array_merge($itemsToSort, $upgradeMenuSlugs);
                    }

                    foreach ($currentSubmenu as $index => $item) {
                        if (array_key_exists($item[2], $itemsToSort)) {
                            $itemsToSort[$item[2]] = $index;
                        }
                    }

                    // Calendar
                    if (isset($itemsToSort[$menuItemCalendar]) && ! is_null($itemsToSort[$menuItemCalendar])) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemCalendar]];

                        unset($currentSubmenu[$itemsToSort[$menuItemCalendar]]);
                    }

                    // Content Overview
                    if (isset($itemsToSort[$menuItemContentOverview]) && ! is_null(
                            $itemsToSort[$menuItemContentOverview]
                        )) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemContentOverview]];

                        unset($currentSubmenu[$itemsToSort[$menuItemContentOverview]]);
                    }

                    // Content Board
                    if (isset($itemsToSort[$menuItemContentBoard]) && ! is_null(
                            $itemsToSort[$menuItemContentBoard]
                        )) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemContentBoard]];

                        unset($currentSubmenu[$itemsToSort[$menuItemContentBoard]]);
                    }

                    // Notifications
                    // Check if we have the menu as a main menu
                    if (isset($submenu[$menuItemNotifications])) {
                        $firstKey = array_keys($submenu[$menuItemNotifications])[0];
                        $newSubmenu[] = $submenu[$menuItemNotifications][$firstKey];

                        unset($submenu[$menuItemNotifications]);
                        remove_menu_page($menuItemNotifications);
                    } else {
                        if (isset($itemsToSort[$menuItemNotifications]) && ! is_null(
                                $itemsToSort[$menuItemNotifications]
                            )) {
                            $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemNotifications]];

                            unset($currentSubmenu[$itemsToSort[$menuItemNotifications]]);
                        }
                    }

                    // Notification logs
                    if (isset($itemsToSort[$menuItemNotificationsLog]) && ! is_null(
                            $itemsToSort[$menuItemNotificationsLog]
                        )) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemNotificationsLog]];

                        unset($currentSubmenu[$itemsToSort[$menuItemNotificationsLog]]);
                    }

                    // Roles
                    if (isset($itemsToSort[$menuItemRoles]) && ! is_null($itemsToSort[$menuItemRoles])) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemRoles]];

                        unset($currentSubmenu[$itemsToSort[$menuItemRoles]]);
                    }

                    // Permissions - Role Capabilities
                    if (isset($itemsToSort['pp-manage-capabilities']) && ! is_null(
                            $itemsToSort['pp-manage-capabilities']
                        )) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort['pp-manage-capabilities']];

                        unset($itemsToSort[$itemsToSort['pp-manage-capabilities']]);
                    }

                    // Add the additional items
                    foreach ($currentSubmenu as $index => $item) {
                        if (! in_array($index, $itemsToSort)) {
                            $newSubmenu[] = $item;
                            unset($currentSubmenu[$index]);
                        }
                    }

                    // Settings
                    if (isset($itemsToSort[$menuItemSettings]) && ! is_null($itemsToSort[$menuItemSettings])) {
                        $newSubmenu[] = $currentSubmenu[$itemsToSort[$menuItemSettings]];

                        unset($currentSubmenu[$itemsToSort[$menuItemSettings]]);
                    }

                    // Upgrade to Pro
                    if (! defined('PUBLISHPRESS_SKIP_VERSION_NOTICES')) {
                        foreach ($upgradeMenuSlugs as $index => $item) {
                            if (! is_null($itemsToSort[$index])) {
                                $newSubmenu[] = $currentSubmenu[$itemsToSort[$index]];
                            }
                        }
                    }

                    $submenu[$parentMenuSlug] = $newSubmenu;
                }

                return $menu_ord;
            }

            /**
             * @return bool
             */
            public function hasMissedRequirements()
            {
                return $this->isBlockEditorActive() && ! $this->isClassicEditorInstalled();
            }

            /**
             * Based on Edit Flow's \Block_Editor_Compatible::should_apply_compat method.
             *
             * @return bool
             */
            public function isBlockEditorActive()
            {
                // Check if Revisionary lower than v1.3 is installed. It disables Gutenberg.
                if (is_plugin_active('revisionary/revisionary.php')
                    && defined('RVY_VERSION')
                    && version_compare(RVY_VERSION, '1.3', '<')) {
                    return false;
                }

                $pluginsState = [
                    'classic-editor' => is_plugin_active('classic-editor/classic-editor.php'),
                    'gutenberg' => is_plugin_active('gutenberg/gutenberg.php'),
                    'gutenberg-ramp' => is_plugin_active('gutenberg-ramp/gutenberg-ramp.php'),
                ];


                if (function_exists('get_post_type')) {
                    $postType = get_post_type();
                }

                if (! isset($postType) || empty($postType)) {
                    $postType = 'post';
                }

                /**
                 * If show_in_rest is not true for the post type, the block editor is not available.
                 */
                if (
                    ($postTypeObject = get_post_type_object($postType))
                    && empty($postTypeObject->show_in_rest)
                ) {
                    return false;
                }

                $conditions = [];

                /**
                 * 5.0:
                 *
                 * Classic editor either disabled or enabled (either via an option or with GET argument).
                 * It's a hairy conditional :(
                 */
                // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.Security.NonceVerification.NoNonceVerification
                $conditions[] = $this->isWp5()
                    && ! $pluginsState['classic-editor']
                    && ! $pluginsState['gutenberg-ramp']
                    && apply_filters('use_block_editor_for_post_type', true, $postType, PHP_INT_MAX);

                $conditions[] = $this->isWp5()
                    && $pluginsState['classic-editor']
                    && (get_option('classic-editor-replace') === 'block'
                        && ! isset($_GET['classic-editor__forget']));

                $conditions[] = $this->isWp5()
                    && $pluginsState['classic-editor']
                    && (get_option('classic-editor-replace') === 'classic'
                        && isset($_GET['classic-editor__forget']));

                /**
                 * < 5.0 but Gutenberg plugin is active.
                 */
                $conditions[] = ! $this->isWp5() && ($pluginsState['gutenberg'] || $pluginsState['gutenberg-ramp']);

                // Returns true if at least one condition is true.
                return count(
                        array_filter(
                            $conditions,
                            function ($c) {
                                return (bool)$c;
                            }
                        )
                    ) > 0;
            }

            /**
             * Returns true if is a beta or stable version of WP 5.
             *
             * @return bool
             */
            public function isWp5()
            {
                global $wp_version;

                return version_compare($wp_version, '5.0', '>=') || substr($wp_version, 0, 2) === '5.';
            }

            /**
             * @return mixed
             */
            public function isClassicEditorInstalled()
            {
                return is_plugin_active('classic-editor/classic-editor.php');
            }

            /**
             *
             */
            public function removeEditorMetaBox()
            {
                $isClassicEditor = isset($_GET['classic-editor']);
                $postType = $this->getCurrentPostType();

                if ($this->isWp5() && $isClassicEditor && $this->postTypeRequiresClassicEditor($postType)) {
                    remove_meta_box('classic-editor-switch-editor', null, 'side');
                }
            }

            /**
             * @return string|null
             */
            public function getCurrentPostType()
            {
                global $post, $typenow, $current_screen, $pagenow;

                if ($post && $post->post_type) {
                    // We have a post so we can just get the post type from that.
                    return $post->post_type;
                } elseif ($typenow) {
                    // Check the global $typenow - set in admin.php.
                    return $typenow;
                } elseif ($current_screen && $current_screen->post_type) {
                    // Check the global $current_screen object - set in screen.php.
                    return $current_screen->post_type;
                } elseif (isset($_REQUEST['post_type'])) {
                    // Check the post_type querystring.
                    return sanitize_key($_REQUEST['post_type']);
                } elseif (isset($_REQUEST['post'])) {
                    // Lastly check if post ID is in query string.
                    return get_post_type((int)$_REQUEST['post']);
                } elseif ($pagenow === 'edit.php') {
                    // The edit page without post_type param is always "post".
                    return 'post';
                }

                // We do not know the post type!
                return null;
            }

            /**
             * @param $postType
             *
             * @return bool
             */
            protected function postTypeRequiresClassicEditor($postType)
            {
                $specialPostTypes = $this->getPostTypesWhichRequiresClassicEditor();

                return in_array($postType, $specialPostTypes);
            }

            /**
             * @return array
             */
            protected function getPostTypesWhichRequiresClassicEditor()
            {
                global $publishpress;

                $postTypes = [];
                $modules = [];

                /**
                 * @param array $modules
                 */
                $modules = apply_filters('publishpress_modules_require_classic_editor', $modules);

                if (! empty($modules)) {
                    // Get the post types activated for each module.
                    foreach ($modules as $module) {
                        // Check if the plugin is active.
                        if (! isset($publishpress->{$module}) || $publishpress->{$module}->module->options->enabled != 'on') {
                            continue;
                        }

                        $modulePostTypes = PublishPress\Legacy\Util::get_post_types_for_module(
                            $publishpress->modules->{$module}
                        );

                        $postTypes = array_merge($postTypes, $modulePostTypes);
                    }
                }

                return $postTypes;
            }

            /**
             * Disable Gutenberg/Block Editor for post types.
             *
             * @param bool $useBlockEditor
             * @param string $postType
             *
             * @return bool
             */
            public function canUseBlockEditorForPostType($useBlockEditor, $postType)
            {
                // Short-circuit in case any other plugin disabled the block editor.
                if (! $useBlockEditor) {
                    return false;
                }

                return $this->postTypeRequiresClassicEditor($postType) ? false : $useBlockEditor;
            }

            /**
             * @param array $capabilities
             *
             * @return array
             */
            public function filterCapabilities($pluginCaps)
            {
                $caps = [
                    'pp_view_content_board',
                    'pp_view_calendar',
                    'pp_view_content_overview',
                    'pp_set_notification_channel',
                    'edit_post_subscriptions',
                    'pp_edit_editorial_metadata',
                    'pp_view_editorial_metadata',
                    'pp_delete_editorial_comment',
                    'pp_delete_others_editorial_comment',
                    'pp_edit_editorial_comment',
                    'pp_edit_others_editorial_comment',
                    'delete_pp_notif_workflow',
                    'edit_pp_notif_workflow',
                    'read_pp_notif_workflow',
                ];
                
                $pluginCaps['PublishPress Planner'] = $caps;

                return $pluginCaps;
            }

            /**
             * Returns the a single status object based on ID, title, or slug
             *
             * @param string|int $string_or_int The status to search for, either by slug, name or ID
             *
             * @return object|WP_Error|false $status The object for the matching status
             */
            public function getPostStatusBy($field, $value) {
                if (class_exists('PublishPress_Statuses')) {
                    return \PublishPress_Statuses::getStatusBy($field, $value);
                }

                if (! in_array($field, ['id', 'slug', 'name', 'label'])) {
                    return false;
                }

                if (in_array($field, ['id', 'slug'])) {
                    $field = 'name';
                }

                // New and auto-draft do not exists as status. So we map them to draft for now.
                if ('name' === $field && in_array($value, ['new', 'auto-draft'])) {
                    $value = 'draft';
                }

                $status = wp_filter_object_list($this->getCorePostStatuses(), [$field => $value]);

                if (!empty($status)) {
                    return array_shift($status);
                } else {
                    if ($status = get_post_status_object($value)) {
                        return $status;
                    }
                }

                return false;
            }

            public function getPostStatuses()
            {
                if (class_exists('PublishPress_Statuses')) {
                    return \PublishPress_Statuses::instance()->getPostStatuses([], 'object');   // note: static method is getPostStati()
                } else {
                    return $this->getCorePostStatuses();
                }
            }

            public function getCustomStatuses() {
                if (class_exists('PublishPress_Statuses')) {
                    $customStatuses = \PublishPress_Statuses::getCustomStatuses(['for_revision' => false], 'object');
    
                    if (defined('PUBLISHPRESS_REVISIONS_VERSION') || defined('PUBLISHPRESS_REVISIONS_PRO_VERSION')) {
                        $customStatuses = array_merge(
                            $customStatuses,
                            \PublishPress_Statuses::getCustomStatuses(['for_revision' => true], 'object')
                        );
                    }

                    return $customStatuses;
                } else {
                    return [];
                }
            }

            public function getCorePostStatuses() {
                return [
                    (object)[
                        'label'        => __('Draft'),
                        'description' => '',
                        'name'        => 'draft',
                        'slug'        => 'draft',  // include slug property as a back compat fallback
                        'position'    => 1,
                    ],
                    (object)[
                        'label'        => __('Pending Review'),
                        'description' => '',
                        'name'        => 'pending',
                        'slug'        => 'pending',
                        'position'    => 2,
                    ],
                    (object)[
                        'label'        => __('Published'),
                        'description' => '',
                        'name'        => 'publish',
                        'slug'        => 'publish',
                        'position'    => 3,
                    ],
                    (object)[
                        'label'        => __('Scheduled'),
                        'description' => '',
                        'name'        => 'future',
                        'slug'        => 'future',
                        'position'    => 4,
                    ],
                ];
            }     

            /**
            * Redirect user on plugin activation
            *
            * @return void
            */
            public function redirect_on_activate()
            {
                if (get_option('pp_planner_activated')) {
                    delete_option('pp_planner_activated');
                    wp_safe_redirect(admin_url("admin.php?page=pp-calendar"));
                    exit;
                }
            }

            /**
             * Meta keys search ajax callback
             *
             * @return void
             */
            public function searchMetaKeys()
            {
                global $wpdb;

                header('Content-type: application/json;');

                if (empty($_GET['nonce']) || ! wp_verify_nonce(sanitize_text_field($_GET['nonce']), 'publishpress-content-get-data')) {
                    wp_send_json([]);
                }

                $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

                $like = '%' . $wpdb->esc_like($queryText) . '%';

                $queryResults = $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s ORDER BY meta_key ASC LIMIT 20",
                        $like
                    )
                );

                $results = [];
                if (!empty($queryResults)) {
                    foreach ($queryResults as $queryResult) {
                        $results[] = [
                            'id' => $queryResult,
                            'text' => $queryResult,
                        ];
                    }
                }

                wp_send_json($results);
            }


        }
    }

    /**
     * Registered here so the Notifications submenu is displayed right after the
     * plugin is activate.
     *
     * @since 1.9.8
     */
    if (! function_exists('publishPressRegisterImprovedNotificationsPostTypes')) {
        function publishPressRegisterImprovedNotificationsPostTypes()
        {
            global $publishpress;

            // Check if the notification module is enabled, before register the post type.
            $options = get_option('publishpress_improved_notifications_options', null);

            if (! is_object($options)) {
                return;
            }

            if (! isset($options->enabled) || $options->enabled !== 'on') {
                return;
            }

            // Create the post type if not exists
            if (! post_type_exists(PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW)) {
                // Notification Workflows
                register_post_type(
                    PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
                    [
                        'labels' => [
                            'name' => __('Notifications', 'publishpress'),
                            'singular_name' => __('Notification', 'publishpress'),
                            'add_new_item' => __('Add New Notification', 'publishpress'),
                            'edit_item' => __('Edit Notification', 'publishpress'),
                            'search_items' => __('Search Notifications', 'publishpress'),
                            'menu_name' => __('Notifications', 'publishpress'),
                            'name_admin_bar' => __('Notification', 'publishpress'),
                            'not_found' => __('No notification found', 'publishpress'),
                            'not_found_in_trash' => __('No notification found', 'publishpress'),
                        ],
                        'public' => false,
                        'publicly_queryable' => false,
                        'has_archive' => false,
                        'rewrite' => false,
                        'show_ui' => true,
                        'show_in_rest' => true,
                        'query_var' => true,
                        'capability_type' => 'pp_notif_workflow',
                        'hierarchical' => false,
                        'can_export' => true,
                        'show_in_admin_bar' => true,
                        'exclude_from_search' => true,
                        'show_in_menu' => $publishpress->get_menu_slug(),
                        'menu_position' => '30',
                        'supports' => [
                            'title',
                        ],
                    ]
                );
            }
        }
    }



    if (! function_exists('PublishPress')) {
        function PublishPress()
        {
            return publishpress::instance();
        }
    }

    if (! defined('PUBLISHPRESS_HOOKS_REGISTERED')) {
        PublishPress();
        add_action('init', 'publishPressRegisterImprovedNotificationsPostTypes');
        // currently not working inside plugins_loaded
       // register_activation_hook(__FILE__, ['publishpress', 'activation_hook']);
        define('PUBLISHPRESS_HOOKS_REGISTERED', 1);
    } else {
        $message = __('PublishPress Planner tried to load multiple times. Please, deactivate and remove other instances of PublishPress, specially if you are using PublishPress Pro.', 'publishpress');

        if (is_admin()) {
            add_action(
                'admin_notices',
                function () use ($message) {
                    $msg = sprintf(
                        '<strong>%s:</strong> %s',
                        esc_html__('Warning', 'publishpress'),
                        esc_html($message)
                    );

                    echo "<div class='notice notice-error is-dismissible' style='color:black'><p>" . $msg . '</p></div>';
                },
                5
            );
        }
    }
    do_action('publishpress_planner_loaded');
}, -10);

register_activation_hook(
    __FILE__,
    function () {
        global $wp_roles;
        
        $genericCaps = [
            'pp_view_calendar',
            'pp_view_content_overview',
            'pp_view_content_board',
            'edit_post_subscriptions',
            'pp_set_notification_channel',
            'pp_delete_editorial_comment',
            'pp_delete_others_editorial_comment',
            'pp_edit_editorial_comment',
            'pp_edit_others_editorial_comment',
        ];

        $roles = [
            'administrator' => $genericCaps,
            'editor' => $genericCaps,
            'author' => $genericCaps,
            'contributor' => $genericCaps,
        ];

        foreach ($roles as $role => $caps) {
            if ($wp_roles->is_role($role)) {
                $role = get_role($role);
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }

        // Additional capabilities
        $roles = [
            'administrator' => [apply_filters('pp_manage_roles_cap', 'pp_manage_roles')],
        ];

        foreach ($roles as $role => $caps) {
            if ($wp_roles->is_role($role)) {
                $role = get_role($role);
                foreach ($caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }

        update_option('pp_planner_activated', true);
    }
);
