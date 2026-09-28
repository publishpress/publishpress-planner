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

/**
 * A notepad for the dashboard
 */
class PP_Dashboard_Notepad_Widget
{
    const notepad_post_type = 'dashboard-note';

    public $edit_cap = 'edit_others_posts';

    public function __construct()
    {
        // Silence is golden
    }

    public function init()
    {
        register_post_type(
            self::notepad_post_type,
            [
                'label' => __('Dashboard Note', 'publishpress'),
                'public' => false,
                'publicly_queryable' => false,
                'has_archive' => false,
                'rewrite' => false,
                'show_ui' => false,
                'query_var' => false,
                'hierarchical' => false,
                'can_export' => true,
                'exclude_from_search' => true,
            ]
        );

        $this->edit_cap = apply_filters('pp_dashboard_notepad_edit_cap', $this->edit_cap);

        add_action('admin_init', [$this, 'handle_notepad_update']);
    }

    /**
     * Handle a dashboard note being created or updated
     *
     * @since 0.8
     */
    public function handle_notepad_update()
    {
        global $pagenow;

        if ('index.php' != $pagenow
            || (empty($_REQUEST['action']) || 'dashboard-notepad' != $_REQUEST['action'])) {
            return;
        }

        check_admin_referer('dashboard-notepad');

        if (! isset($_REQUEST['notepad-id'])) {
            return;
        }

        if (! current_user_can($this->edit_cap)) {
            wp_die(esc_html(PublishPress()->dashboard->messages['invalid-permissions']));
        }

        if (isset($_REQUEST['note'])) {
            $note_content = wp_filter_nohtml_kses($_REQUEST['note']);
        } else {
            $note_content = '';
        }

        $current_id = (int)$_REQUEST['notepad-id'];
        $current_notepad = get_post($current_id);
        $new_note = [
            'post_content' => $note_content,
            'post_type' => self::notepad_post_type,
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ];

        if ($current_notepad
            && self::notepad_post_type == $current_notepad->post_type
            && ! isset($_REQUEST['create-note'])) {
            $new_note['ID'] = $current_id;
            wp_update_post($new_note);
        } else {
            wp_insert_post($new_note);
        }

        wp_safe_redirect(wp_get_referer());
        exit;
    }

    /**
     * Notepad Widget
     * Editors can leave notes in the dashboard for authors and contributors
     *
     * @since 0.8
     */
    public function notepad_widget()
    {
        $args = [
            'posts_per_page' => 1,
            'post_status' => 'draft',
            'post_type' => self::notepad_post_type,
        ];
        $posts = get_posts($args);
        $current_note = (! empty($posts[0]->post_content)) ? $posts[0]->post_content : '';
        $current_id = (! empty($posts[0]->ID)) ? $posts[0]->ID : 0;
        $current_post = (! empty($posts[0])) ? $posts[0] : false;

        if ($current_post) {
            $last_updated_escaped = '<span id="dashboard-notepad-last-updated">' . sprintf(
                    esc_html__(
                        '%1$s last updated on %2$s',
                        'publishpress'
                    ),
                    esc_html(get_user_by('id', $current_post->post_author)->display_name),
                    esc_html(
                        get_the_time(
                            get_option('date_format') . ' ' . get_option('time_format'),
                            $current_post
                        )
                    )
                ) . '</span>';
        } else {
            $last_updated_escaped = '';
        }

        if (current_user_can($this->edit_cap)) {
            echo '<form id="dashboard-notepad">';
            echo '<input type="hidden" name="action" value="dashboard-notepad" />';
            echo '<input type="hidden" name="notepad-id" value="' . esc_attr($current_id) . '" />';
            echo '<label class="screen-reader-text" for="dashboard-notepad-note">' . esc_html__('Dashboard note', 'publishpress') . '</label>';
            echo '<textarea id="dashboard-notepad-note" style="width:100%" rows="10" name="note">';
            echo esc_textarea(trim($current_note));
            echo '</textarea>';
            echo '<p class="submit">';
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $last_updated_escaped;
            // phpcs:enable
            echo '<span id="dashboard-notepad-submit-buttons">';
            submit_button(esc_html__('Update Note', 'publishpress'), 'primary', 'update-note', false);
            echo '</span>';
            echo '<div style="clear:both;"></div>';
            wp_nonce_field('dashboard-notepad');
            echo '</form>';
        } else {
            echo '<form id="dashboard-notepad">';
            echo '<label class="screen-reader-text" for="dashboard-notepad-note">' . esc_html__('Dashboard note', 'publishpress') . '</label>';
            echo '<textarea id="dashboard-notepad-note" style="width:100%" rows="10" name="note" disabled="disabled">';
            echo esc_textarea(trim($current_note));
            echo '</textarea>';
            // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $last_updated_escaped;
            // phpcs:enable
            echo '</form>';
        }
    }
}
