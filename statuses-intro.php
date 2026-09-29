<?php

function publishpress_statuses_info() {
    static $return_val;

    if (!empty($return_val)) {
        return $return_val;
    }

    if (!function_exists('get_plugins')) {
        if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    $statuses_installed = false;

    if (function_exists('get_plugins')) {
        $plugins = get_plugins();
        $statuses_installed = !empty($plugins['publishpress-statuses/publishpress-statuses.php']);
    }

    if ($statuses_installed) {
        if (current_user_can('activate_plugins')) {
            if ($statuses_installed) {
                $_url = "plugins.php";
                $info_url = self_admin_url($_url);
            } else {
                $_url = "plugin-install.php?tab=plugin-information&plugin=publishpress-statuses&TB_iframe=true&width=600&height=800";
                $info_url = self_admin_url($_url);
            }
        } else {
            $info_url = 'https://wordpress.org/plugins/publishpress-statuses';
        }
    } else {
        if (current_user_can('install_plugins')) {
            if ($statuses_installed) {
                $_url = "plugins.php";
                $info_url = self_admin_url($_url);
            } else {
                $_url = "plugin-install.php?tab=plugin-information&plugin=publishpress-statuses&TB_iframe=true&width=600&height=800";
                $info_url = self_admin_url($_url);
            }
        } else {
            $info_url = 'https://wordpress.org/plugins/publishpress-statuses';
        }
    }

    $return_val = compact('info_url', 'statuses_installed');

    return $return_val;
}

if (is_admin()) {
    global $pagenow, $current_user;

    // Ensure any user sees one-time display of PublishPress Statuses notice on Posts / Pages screen
    if (!defined('PUBLISHPRESS_STATUSES_VERSION')) {
        add_action('upgrader_process_complete', function($package, $extra) {
            if (!empty($package->skin) && !empty($package->skin->result) && is_array($package->skin->result) && !empty($package->skin->result['destination_name']) 
            && ('publishpress-statuses' == $package->skin->result['destination_name'])
            ) {
                if (!empty($_REQUEST['action']) && ('install-plugin' == $_REQUEST['action'])  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                && !empty($_SERVER['HTTP_REFERER'])                                           // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                && false === strpos($_SERVER['HTTP_REFERER'], 'plugins.php')                  // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                && false === strpos($_SERVER['HTTP_REFERER'], 'plugin-install.php')           // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                ) {
                    activate_plugin('publishpress-statuses/publishpress-statuses.php');
                }
            }
        }, 10, 2);

        if ('admin.php' == $pagenow) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
            if (!empty($_REQUEST['page']) && ('pp-modules-settings' == $_REQUEST['page'])) {
                // Display an explanatory caption and install / activation link on Planner > Settings > Features

                $statuses_info = publishpress_statuses_info();
                
                if (empty($statuses_info['statuses_installed'])) {
                    add_action(
                        'admin_enqueue_scripts', 
                        function() {
                            add_thickbox();
                            wp_enqueue_script('updates');
                            
                            // @todo
                            /*
                            wp_enqueue_script(
                                'publishpress-statuses-installer-js',
                                PUBLISHPRESS_URL . 'common/js/pp_statuses_installer.js',
                                ['jquery'],
                                PUBLISHPRESS_VERSION,
                                true
                            );
                            */
                        }
                    );
                }
            }
        }

        if ('edit.php' == $pagenow) {
            if ($status_options = get_option('publishpress_custom_status_options')) {
                $status_options = maybe_unserialize($status_options);

                if (is_object($status_options) && !empty($status_options->enabled) && ('off' != $status_options->enabled)) {
                    if (!empty($status_options->post_types)) {
                        $post_types = maybe_unserialize($status_options->post_types);

                        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
                        $type = (!empty($_REQUEST['post_type'])) ? sanitize_key($_REQUEST['post_type']) : 'post';

                        if (is_array($post_types) && isset($post_types[$type]) && ('off' != $post_types[$type]) && !get_user_option('publishpress_planner_statuses_notice_done')) {
                            // Also ensure any user sees one-time display of PublishPress Statuses notice on Posts / Pages screen

                            $statuses_info = publishpress_statuses_info();
                            
                            if (empty($statuses_info['statuses_installed'])) {
                                add_action(
                                    'admin_enqueue_scripts', 
                                    function() {
                                        add_thickbox();
                                        wp_enqueue_script('updates');
                                    }
                                );
                            }
                        }
                    }
                }
            }
        }
    }
}
