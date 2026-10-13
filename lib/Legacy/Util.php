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

namespace PublishPress\Legacy;

class Util
{
    /**
     * Checks for the current post type
     *
     * @return string|null $post_type The post type we've found, or null if no post type
     */
    public static function get_current_post_type()
    {
        global $post, $typenow, $pagenow, $current_screen;

        // get_post() needs a variable
        $post_id = isset($_REQUEST['post']) ? (int)$_REQUEST['post'] : false;

        if ($post && $post->post_type) {
            $post_type = $post->post_type;
        } elseif ($typenow) {
            $post_type = $typenow;
        } elseif ($current_screen && !empty($current_screen->post_type)) {
            $post_type = $current_screen->post_type;
        } elseif (isset($_REQUEST['post_type'])) {
            $post_type = sanitize_key($_REQUEST['post_type']);
        } elseif ('post.php' == $pagenow
            && $post_id
            && !empty(get_post($post_id)->post_type)) {
            $post_type = get_post($post_id)->post_type;
        } elseif ('edit.php' == $pagenow && empty($_REQUEST['post_type'])) {
            $post_type = 'post';
        } else {
            $post_type = null;
        }

        return $post_type;
    }

    /**
     * Collect all of the active post types for a given module
     *
     * @param object $module Module's data
     *
     * @return array $post_types All of the post types that are 'on'
     */
    public static function get_post_types_for_module($module)
    {
        $post_types = [];

        if (isset($module->options->post_types) && is_array($module->options->post_types)) {
            foreach ($module->options->post_types as $post_type => $value) {
                if ('on' == $value) {
                    $post_types[] = $post_type;
                }
            }
        }

        return $post_types;
    }

    /**
     * Sanitizes the module name, making sure we always have only
     * valid chars, replacing - with _.
     *
     * @param string $name
     *
     * @return string
     */
    public static function sanitize_module_name($name)
    {
        return str_replace('-', '_', $name);
    }

    /**
     * Adds an array of capabilities to a role.
     *
     * @param string $role A standard WP user role like 'administrator' or 'author'
     * @param array $caps One or more user caps to add
     *
     * @since 1.9.8
     *
     */
    public static function add_caps_to_role($role, $caps)
    {
        // In some contexts, we don't want to add caps to roles
        if (apply_filters('pp_kill_add_caps_to_role', false, $role, $caps)) {
            return;
        }

        global $wp_roles;

        if ($wp_roles->is_role($role)) {
            $role = get_role($role);

            foreach ($caps as $cap) {
                $role->add_cap($cap);
            }
        }
    }

    /**
     * @return bool
     */
    public static function isGutenbergEnabled()
    {
        $isEnabled = defined('GUTENBERG_VERSION');

        // Is WordPress 5?
        if (!$isEnabled) {
            $wpVersion = get_bloginfo('version');

            $isEnabled = version_compare($wpVersion, '5.0', '>=');
        }

        return $isEnabled;
    }

    /**
     * @return mixed|string
     */
    public static function getRequestMethod()
    {
        if (isset($_SERVER) && isset($_SERVER['REQUEST_METHOD'])) {
            return sanitize_key($_SERVER['REQUEST_METHOD']);
        }

        if (function_exists('getenv')) {
            $method = strtoupper(getenv('REQUEST_METHOD'));

            if (!empty($method)) {
                return $method;
            }
        }

        if (isset($_POST) && !empty($_POST)) {
            return 'POST';
        }

        return 'GET';
    }

    /**
     * Check if Planner's pro is active
     */
    public static function isPlannersProActive()
    {
        if (class_exists('PublishPressPro\\PluginInitializer')) {
            return true;
        }

        return false;
    }

    /**
     * Load pro sidebar
     *
     * @param boolean $echo
     * 
     * @return mixed
     */
    public static function pp_pro_sidebar($echo = true)
    {
        ob_start();
        ?>
        <div class="pp-advertisement-right-sidebar">
            <div class="advertisement-box-content postbox pp-advert">
                <div class="postbox-header pp-advert">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Upgrade to PublishPress Planner Pro', 'publishpress'); ?></span>
                    </h3>
                </div>

                <div class="inside pp-advert">
                    <p><?php echo esc_html__('Enhance the power of PublishPress Planner with the Pro version:', 'publishpress'); ?>
                    </p>
                    <ul>
                        <li><?php echo esc_html__('PublishPress Revisions integration', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Slack integration for notifications', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Send reminder notifications', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Use post meta in notifications', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Modals so you can edit posts without changing screen', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Integration with SEO plugins', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Remove PublishPress ads and branding', 'publishpress'); ?></li>
                        <li><?php echo esc_html__('Fast, professional support', 'publishpress'); ?></li>
                    </ul>
                    <div class="upgrade-btn">
                        <a href="https://publishpress.com/links/publishpress-banner" target="__blank" aria-label="<?php echo esc_attr__('Upgrade to Pro', 'publishpress'); ?>"><?php echo esc_html__('Upgrade to Pro', 'publishpress'); ?></a>
                    </div>
                </div>
            </div>
            <div class="advertisement-box-content postbox pp-advert">
                <div class="postbox-header pp-advert">
                    <h3 class="advertisement-box-header hndle is-non-sortable">
                        <span><?php echo esc_html__('Need PublishPress Planner Support?', 'publishpress'); ?></span>
                    </h3>
                </div>

                <div class="inside pp-advert">
                    <p><?php echo esc_html__('If you need help or have a new feature request, let us know.', 'publishpress'); ?>
                        <a class="advert-link" href="https://wordpress.org/support/plugin/publishpress/" target="_blank" aria-label="<?php echo esc_attr__('Request Support', 'publishpress'); ?>">
                        <?php echo esc_html__('Request Support', 'publishpress'); ?> 
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                <path
                                    d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"
                                ></path>
                            </svg>
                        </a>
                    </p>
                    <p>
                    <?php echo esc_html__('Detailed documentation is also available on the plugin website.', 'publishpress'); ?> 
                        <a class="advert-link" href="https://publishpress.com/knowledge-base/start-planner/" target="_blank" aria-label="<?php echo esc_attr__('View Knowledge Base', 'publishpress'); ?>">
                        <?php echo esc_html__('View Knowledge Base', 'publishpress'); ?> 
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" class="linkIcon">
                                <path
                                    d="M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"
                                ></path>
                            </svg>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <?php
        if (!$echo) {
            return ob_get_clean();
        }
    }
}
