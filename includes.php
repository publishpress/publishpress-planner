<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2025 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c ) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option ) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use PPVersionNotices\Module\MenuLink\Module;
use PublishPress\Legacy\Auto_loader;

if (! defined('PP_LOADED')) {
    if (! defined('PUBLISHPRESS_VERSION')) {
        // Define constants
        define('PUBLISHPRESS_VERSION', '4.8.1');
        define('PUBLISHPRESS_BASE_PATH', __DIR__);
        define('PUBLISHPRESS_VIEWS_PATH', __DIR__ . '/views');
        define('PUBLISHPRESS_FILE_PATH', PUBLISHPRESS_BASE_PATH . '/publishpress.php');
        define('PUBLISHPRESS_LIBRARIES_PATH', PUBLISHPRESS_BASE_PATH . '/lib');


        if (! defined('PUBLISHPRESS_INTERNAL_VENDORPATH')) {
            /**
             * @deprecated 3.12.0 Use PP_LIB_VENDOR_PATH instead.
             */
            define('PUBLISHPRESS_INTERNAL_VENDORPATH', PP_LIB_VENDOR_PATH);
        }

        if (! defined('PUBLISHPRESS_ACTION_PRIORITY_INIT')) {
            define('PUBLISHPRESS_ACTION_PRIORITY_INIT', 10);
        }

        if (! defined('PUBLISHPRESS_ACTION_PRIORITY_INIT_LATE')) {
            define('PUBLISHPRESS_ACTION_PRIORITY_INIT_LATE', 1100);
        }

        if (! defined('PUBLISHPRESS_ACTION_PRIORITY_INIT_ADMIN')) {
            define('PUBLISHPRESS_ACTION_PRIORITY_INIT_ADMIN', 1010);
        }

        $relativePath = PUBLISHPRESS_BASE_PATH;

        if (defined('PUBLISHPRESS_CUSTOM_VENDOR_PATH') && defined('PUBLISHPRESS_CUSTOM_VENDOR_URL')) {
            $relativePath = str_replace(PUBLISHPRESS_CUSTOM_VENDOR_PATH, '', $relativePath);
            define('PUBLISHPRESS_URL', PUBLISHPRESS_CUSTOM_VENDOR_URL . $relativePath . '/');
        } else {
            define('PUBLISHPRESS_URL', plugins_url('/', __FILE__));
        }

        $settingsPage = add_query_arg(
            [
                'page' => 'pp-modules-settings',
                'settings_module' => 'pp-modules-settings-settings',
            ],
            get_admin_url(null, 'admin.php')
        );
        define('PUBLISHPRESS_SETTINGS_PAGE', $settingsPage);

        /**
         * Use PUBLISHPRESS_BASE_PATH instead.
         *
         * @deprecated
         */
        define('PUBLISHPRESS_ROOT', PUBLISHPRESS_BASE_PATH);

        // Define the Priority for the notification/notification_status_change method
        // Added to allow users select a custom priority
        if (! defined('PP_NOTIFICATION_PRIORITY_STATUS_CHANGE')) {
            define('PP_NOTIFICATION_PRIORITY_STATUS_CHANGE', 10);
        }

        define('PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW', 'psppnotif_workflow');
    }
    
    // Register the legacy autoloader
    if (! class_exists('\\PublishPress\\Legacy\\Auto_loader')) {
        require_once PUBLISHPRESS_LIBRARIES_PATH . '/Legacy/Auto_loader.php';
    }


    Auto_loader::register('PublishPress\\Core\\', __DIR__ . '/core/');
    Auto_loader::register('PublishPress\\Legacy\\', __DIR__ . '/lib/Legacy');
    Auto_loader::register('PublishPress\\Notifications\\', __DIR__ . '/lib/Notifications');
    Auto_loader::register('PublishPress\\Utility\\', __DIR__ . '/lib/Utility');

    require_once PUBLISHPRESS_BASE_PATH . '/deprecated.php';


    if (! defined('PUBLISHPRESS_NOTIF_LOADED')) {
        define('PUBLISHPRESS_NOTIF_MODULE_PATH', __DIR__ . '/modules/improved-notifications');
        define('PUBLISHPRESS_NOTIF_VIEWS_PATH', PUBLISHPRESS_BASE_PATH . '/views');
        define('PUBLISHPRESS_NOTIF_LOADED', 1);
    }

    // Load the improved notifications
    $plugin = new PublishPress\Notifications\Plugin();
    $plugin->init();

    if (is_admin() && ! defined('PUBLISHPRESS_SKIP_VERSION_NOTICES')) {
        if (current_user_can('install_plugins')) {
            add_filter(
                \PPVersionNotices\Module\TopNotice\Module::SETTINGS_FILTER,
                function ($settings) {
                    $settings['publishpress'] = [
                        'message' => __('You\'re using PublishPress Planner Free. The Pro version has more features and support. %sUpgrade to Pro%s', 'publishpress'),
                        'link' => 'https://publishpress.com/links/publishpress-banner',
                        'screens' => [
                            ['base' => 'planner_page_pp-modules-settings'],
                            ['base' => 'planner_page_pp-manage-roles'],
                            ['base' => 'planner_page_pp-editorial-metadata'],
                            ['base' => 'planner_page_pp-editorial-comments'],
                            ['base' => 'planner_page_publishpress_debug_log'],
                            ['base' => 'admin_page_pp-notif-log'],
                            ['base' => 'edit', 'id' => 'edit-psppnotif_workflow'],
                            ['base' => 'post', 'id' => 'psppnotif_workflow'],
                            ['base' => 'planner_page_pp-content-overview'],
                            ['base' => 'planner_page_pp-content-board'],
                            ['base' => 'toplevel_page_pp-calendar', 'id' => 'toplevel_page_pp-calendar'],
                        ]
                    ];

                    return $settings;
                }
            );

            add_filter(
                Module::SETTINGS_FILTER,
                function ($settings) {
                    $settings['publishpress'] = [
                        'parent' => [
                            'pp-calendar',
                            'pp-content-overview',
                            'pp-content-board',
                            'edit.php?post_type=psppnotif_workflow',
                            'pp-notif-log',
                            'pp-manage-roles',
                            'pp-modules-settings',
                        ],
                        'label' => __('Upgrade to Pro', 'publishpress'),
                        'link' => 'https://publishpress.com/links/publishpress-menu',
                    ];

                    return $settings;
                }
            );
        }
    }

    if (is_admin() && !defined('PUBLISHPRESS_STATUSES_VERSION')) {
        require_once PUBLISHPRESS_BASE_PATH . '/statuses-intro.php';
    }

    define('PP_LOADED', 1);
}
