<?php
/**
 * Plugin Name: PublishPress Planner Free
 * Plugin URI: https://publishpress.com/
 * Description: PublishPress Planner helps you plan and publish content inside WordPress. Features include a content calendar, kanban board, and notifications.
 * Version: 4.9.1-beta.1
 * Author: PublishPress
 * Author URI: https://publishpress.com
 * Text Domain: publishpress
 * Domain Path: /languages
 * Requires at least: 5.5
 * Requires PHP: 8.2
 *
 * Copyright (c) 2026 PublishPress
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

global $wp_version;

$min_php_version = '8.2';
$min_wp_version  = '5.5';

if (version_compare(PHP_VERSION, $min_php_version, '<')) {
    return;
}

if (version_compare($wp_version, $min_wp_version, '<')) {
    return;
}

// If the plugin is already loaded, terminate the plugin execution.
if (defined('PUBLISHPRESS_LOADED')) {
    return;
}

define('PUBLISHPRESS_LOADED', true);

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
    $pluginCheckerConfig->pluginName    = 'PublishPress Planner Free';
    $pluginCheckerConfig->pluginFolder  = 'publishpress';

    $pluginChecker = new PublishPressInstanceProtection\InstanceChecker($pluginCheckerConfig);
}

$autoloadFilePath = PP_LIB_VENDOR_PATH . '/autoload.php';
if (! class_exists('ComposerAutoloaderInitPublishPressPlanner')
    && is_file($autoloadFilePath)
    && is_readable($autoloadFilePath)
) {
    require_once $autoloadFilePath;
}

if (! function_exists('publishpress_planner_load_plugin')) {
    function publishpress_planner_load_plugin() {

        require_once __DIR__ . '/includes.php';

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
            require_once __DIR__ . '/common/php/class-publishpress.php';
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

                        echo "<div class='notice notice-error is-dismissible' style='color:black'><p>" . wp_kses_post($msg) . '</p></div>';
                    },
                    5
                );
            }
        }
        require_once __DIR__ . '/core/Mcp/bootstrap.php';
        do_action('publishpress_planner_loaded');
    }
}

add_action('plugins_loaded', 'publishpress_planner_load_plugin', -10);

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
