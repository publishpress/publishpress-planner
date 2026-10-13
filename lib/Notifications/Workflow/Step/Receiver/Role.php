<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

use PublishPress\Notifications\Traits\Dependency_Injector;
use PublishPress\Notifications\Traits\PublishPress_Module;

class Role extends Simple_Checkbox implements Receiver_Interface
{
    use PublishPress_Module;

    const META_KEY = '_psppno_torole';

    const META_LIST_KEY = '_psppno_torolelist';

    const META_VALUE = 'role';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'role';
        $this->label       = __('Roles', 'publishpress');
        $this->option_name = 'receiver_role_checkbox';

        parent::__construct();

        $this->view_name = 'workflow_receiver_role_field';
    }

    /**
     * Method called when a notification workflow is saved.
     *
     * @param int $id
     * @param WP_Post $post
     */
    public function save_metabox_data($id, $post)
    {
        parent::save_metabox_data($id, $post);

        $publishpress_notif = isset($_POST['publishpress_notif']) && is_array($_POST['publishpress_notif'])
            ? wp_unslash($_POST['publishpress_notif'])
            : [];

        $values = [];
        if (isset($publishpress_notif['receiver_role'])) {
            $values = array_map('sanitize_key', (array)$publishpress_notif['receiver_role']);
        }

        $this->update_metadata_array($id, static::META_LIST_KEY, $values);
    }

    /**
     * Filters the context sent to the view template in the metabox
     *
     * @param array $template_context
     */
    public function filter_workflow_metabox_context($template_context)
    {
        // Get Roles
        $roles = get_editable_roles();

        $selected_roles = (array)$this->get_metadata(static::META_LIST_KEY);
        if (empty($selected_groups)) {
            $selected_groups = [];
        }
        foreach ($roles as $role => &$data) {
            $data = (object)$data;
            if (in_array($role, $selected_roles)) {
                $data->selected = true;
            }
        }

        $template_context['name']       = 'publishpress_notif[receiver_role_checkbox]';
        $template_context['id']         = 'publishpress_notif_role';
        $template_context['value']      = static::META_VALUE;
        $template_context['roles']      = $roles;
        $template_context['list_class'] = 'publishpress_notif_role_list';
        $template_context['input_name'] = 'publishpress_notif[receiver_role][]';
        $template_context['input_id']   = 'publishpress_notif_roles';

        $template_context = parent::filter_workflow_metabox_context($template_context);

        return $template_context;
    }

    /**
     * Filters the list of receivers for the workflow. Returns the list of IDs.
     *
     * @param array $receivers
     * @param WP_Post $workflow
     * @param array $args
     *
     * @return array
     */
    public function filter_workflow_receivers($receivers, $workflow, $args)
    {
        // If checked, add the authors to the list of receivers
        if ($this->is_selected($workflow->ID)) {
            // Get the users from the selected roles in the workflow
            $roles     = get_post_meta($workflow->ID, static::META_LIST_KEY);
            $receivers = array_merge($receivers, $this->get_users_from_roles($roles));

            /**
             * Filters the list of receivers, but triggers only when the authors are selected.
             *
             * @param array $receivers
             * @param WP_Post $workflow
             * @param array $args
             */
            $receivers = apply_filters('publishpress_notif_workflow_receiver_role', $receivers, $workflow, $args);
        }

        return $receivers;
    }

    /**
     * Returns an array with a list of users' ids from the given roles.
     *
     * @param array $roles
     *
     * @return array
     */
    protected function get_users_from_roles($roles)
    {
        $users = [];

        if (!empty($roles)) {
            foreach ((array)$roles as $role_name) {
                $role_users = get_users(
                    [
                        'role' => $role_name,
                    ]
                );

                if (!empty($role_users)) {
                    foreach ($role_users as $user) {
                        $users[] = [
                            'receiver' => $user->ID,
                            'group'    => self::META_VALUE,
                            'subgroup' => $role_name
                        ];
                    }
                }
            }
        }

        return $users;
    }

    /**
     * Add the respective value to the column in the workflow list
     *
     * @param array $values
     * @param int $post_id
     *
     * @return array
     */
    public function filter_receivers_column_value($values, $post_id)
    {
        if ($this->is_selected($post_id)) {
            $items = get_post_meta($post_id, static::META_LIST_KEY);

            if (!empty($items)) {
                $count = count($items);

                $values[] = sprintf(
                    _n('%d Role', "%d Roles", count($items), 'publishpress'),
                    count($items)
                );
            }
        }

        return $values;
    }
}
