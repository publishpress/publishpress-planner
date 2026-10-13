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

// Retrieved from this blog post: http://w-shadow.com/blog/2010/06/29/adding-stuff-to-wordpress-screen-options/
if (!class_exists('wsScreenOptions10')) :

    /**
     * Class for adding new panels to the "Screen Options" box.
     *
     * Do not access this class directly. Instead, use the add_screen_options_panel() function.
     *
     * @author    Janis Elsts
     * @copyright 2010
     * @version   1.0
     * @access    public
     */
    class wsScreenOptions10
    {
        public $registered_panels; // List of custom "Screen Options" panels

        public $page_panels;       // Index of panels registered for each page ($page => array of panel ids).

        /**
         * Class constructor
         *
         * @return void
         */
        public function __construct()
        {
            $this->registered_panels = [];
            $this->page_panels       = [];

            add_filter('screen_settings', [$this, 'append_screen_settings'], 10, 2);
            add_action('admin_print_scripts', [$this, 'add_autosave_script']);
        }

        /**
         * Add a new settings panel to the "Screen Options" box.
         *
         * @param string $id String to use in the 'id' attribute of the settings panel. Should be unique.
         * @param string $title Title of the settings panel. Set to an empty string to omit title.
         * @param callback $callback Function that fills the panel with the desired content. Should return its output.
         * @param string|array $page The page(s) on which to show the panel (similar to add_meta_box()).
         * @param callback $save_callback Optional. Function that saves the settings.
         * @param bool $autosave Optional. If se, settings will be automatically saved (via AJAX) when the value of any input element in the panel changes. Defaults to false.
         *
         * @return void
         */
        public function add_screen_options_panel(
            $id,
            $title,
            $callback,
            $page,
            $save_callback = null,
            $autosave = false
        ) {
            if (!is_array($page)) {
                $page = [$page];
            }
            // Convert page hooks/slugs to screen IDs
            $page = array_map([$this, 'page_to_screen_id'], $page);
            $page = array_unique($page);

            $new_panel = [
                'title'         => $title,
                'callback'      => $callback,
                'page'          => $page,
                'save_callback' => $save_callback,
                'autosave'      => $autosave,
            ];

            if ($save_callback) {
                add_action('wp_ajax_save_settings-' . $id, [$this, 'ajax_save_callback']);
            }

            // Store the panel ID in each relevant page's list
            foreach ($page as $page_id) {
                if (!isset($this->page_panels[$page_id])) {
                    $this->page_panels[$page_id] = [];
                }
                $this->page_panels[$page_id][] = $id;
            }

            $this->registered_panels[$id] = $new_panel;
        }

        /**
         * Convert a page hook name to a screen ID.
         *
         * @param string $page
         *
         * @return string
         * @uses   convert_to_screen()
         * @access private
         *
         */
        public function page_to_screen_id($page)
        {
            if (function_exists('convert_to_screen')) {
                $screen = convert_to_screen($page);
                if (isset($screen->id)) {
                    return $screen->id;
                } else {
                    return '';
                }
            } else {
                return str_replace(['.php', '-new', '-add'], '', $page);
            }
        }

        /**
         * Append custom panel HTML to the "Screen Options" box of the current page.
         * Callback for the 'screen_settings' filter (available in WP 3.0 and up).
         *
         * @access private
         *
         * @param string $current
         * @param string $screen Screen object (undocumented).
         *
         * @return string The HTML code to append to "Screen Options"
         */
        public function append_screen_settings($current, $screen)
        {
            global $hook_suffix;

            // Sanity check
            if (!isset($screen->id)) {
                return $current;
            }

            // Are there any panels that want to appear on this page?
            $panels = $this->get_panels_for_screen($screen->id, $hook_suffix);
            if (empty($panels)) {
                return $current;
            }

            // Append all panels registered for this screen
            foreach ($panels as $panel_id) {
                $panel = $this->registered_panels[$panel_id];

                // Add panel title
                if (!empty($panel['title'])) {
                    $current .= "\n<h5>" . $panel['title'] . "</h5>\n";
                }
                // Generate panel contents
                if (is_callable($panel['callback'])) {
                    $contents = call_user_func($panel['callback']);
                    $classes  = [
                        'metabox-prefs',
                        'custom-options-panel',
                    ];
                    if ($panel['autosave']) {
                        $classes[] = 'requires-autosave';
                    }

                    $contents = sprintf(
                        '<div id="%s" class="%s"><input type="hidden" name="_wpnonce-%s" value="%s" />%s</div>',
                        esc_attr($panel_id),
                        esc_attr(implode(' ', $classes)),
                        esc_attr($panel_id),
                        wp_create_nonce('save_settings-' . $panel_id),
                        $contents
                    );

                    $current .= $contents;
                }
            }

            return $current;
        }

        /**
         * AJAX callback for the "Screen Options" autosave.
         *
         * @access private
         * @return void
         */
        public function ajax_save_callback()
        {
            if (empty($_POST['action'])) {
                die('0');
            }

            // The 'action' argument is in the form "save_settings-panel_id"
            $action_parts = explode('-', sanitize_text_field($_POST['action']), 2);
            $id = end($action_parts);

            // Basic security check.
            check_ajax_referer('save_settings-' . $id, '_wpnonce-' . $id);

            // Hand the request to the registered callback, if any
            if (!isset($this->registered_panels[$id])) {
                exit('0');
            }
            $panel = $this->registered_panels[$id];
            if (is_callable($panel['save_callback'])) {
                call_user_func($panel['save_callback'], $_POST);
                die('1');
            } else {
                die('0');
            }
        }

        /**
         * Add/enqueue supporting JavaScript for the autosave function of custom "Screen Options" panels.
         *
         * Checks if the current page is supposed to contain any autosave-enabled
         * panels and adds the script only if that's the case.
         *
         * @return void
         */
        public function add_autosave_script()
        {
            // Get the page id/hook/slug/whatever.
            global $hook_suffix;

            // Check if we have some panels with autosave registered for this page.
            $panels = $this->get_panels_for_screen('', $hook_suffix);
            if (empty($panels)) {
                return;
            }

            $got_autosave = false;
            foreach ($panels as $panel_id) {
                if ($this->registered_panels[$panel_id]['autosave']) {
                    $got_autosave = true;
                    break;
                }
            }

            if ($got_autosave) {
                // Enqueue the script itself
                $url = PUBLISHPRESS_URL . 'common/js/screen-options.js';
                wp_enqueue_script('screen-options-custom-autosave', $url, ['jquery'], PUBLISHPRESS_VERSION);
            }
        }

        /**
         * Get custom panels registered for a particular screen and/or page.
         *
         * @param string $screen_id Screen ID.
         * @param string $page Optional. Page filename or hook name.
         *
         * @return array Array of custom panels.
         */
        public function get_panels_for_screen($screen_id, $page = '')
        {
            if (isset($this->page_panels[$screen_id]) && !empty($this->page_panels[$screen_id])) {
                $panels = $this->page_panels[$screen_id];
            } else {
                $panels = [];
            }
            if (!empty($page)) {
                $page_as_screen = $this->page_to_screen_id($page);
                if (isset($this->page_panels[$page_as_screen]) && !empty($this->page_panels[$page_as_screen])) {
                    $panels = array_merge($panels, $this->page_panels[$page_as_screen]);
                }
            }

            return array_unique($panels);
        }
    }

    // All versions of the class are stored in a global array
    // and only the latest version is actually used.
    global $ws_screen_options_versions;
    if (!isset($ws_screen_options_versions)) {
        $ws_screen_options_versions = [];
    }
    $ws_screen_options_versions['1.0'] = 'wsScreenOptions10';

endif;

if (!function_exists('add_screen_options_panel')) {
    /**
     * Add a new settings panel to the "Screen Options" box.
     *
     * @param string $id String to use in the 'id' attribute of the settings panel. Should be unique.
     * @param string $title Title of the settings panel. Set to an empty string to omit title.
     * @param callback $callback Function that fills the panel with the desired content. Should return its output.
     * @param string|array $page The page(s) on which to show the panel (similar to add_meta_box()).
     * @param callback $save_callback Optional. Function that saves the settings contained in the panel.
     * @param bool $autosave Optional. If set, settings will be automatically saved (via AJAX) when the value of any input element in the panel changes. Defaults to false.
     *
     * @return void
     * @see wsScreenOptions10::add_screen_options_panel()
     *
     */
    function add_screen_options_panel($id, $title, $callback, $page, $save_callback = null, $autosave = false)
    {
        global $ws_screen_options_versions;

        static $instance = null;
        if (is_null($instance)) {
            // Instantiate the latest version of the wsScreenOptions class
            uksort($ws_screen_options_versions, 'version_compare');
            $className = end($ws_screen_options_versions);
            $instance  = new $className;
        }

        return $instance->add_screen_options_panel($id, $title, $callback, $page, $save_callback, $autosave);
    }
}
