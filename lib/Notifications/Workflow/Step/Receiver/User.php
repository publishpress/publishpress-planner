<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

class User extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_touser';

    const META_LIST_KEY = '_psppno_touserlist';

    const META_VALUE = 'user';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'user';
        $this->label       = __('Users', 'publishpress');
        $this->option_name = 'receiver_user_checkbox';

        parent::__construct();

        $this->view_name = 'workflow_receiver_user_field';
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

        if (!isset($publishpress_notif['receiver_user'])) {
            // Assume it is disabled
            $values = [];
        } else {
            $values = array_map(
                'sanitize_key',
                (array)$publishpress_notif['receiver_user']
            );
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
        // Get Users
        $args  = [
            'fields'  => [
                'ID',
                'display_name',
                'user_email',
            ],
            'orderby' => 'display_name',
        ];
        $args  = apply_filters('publishpress_notif_users_select_form_get_users_args', $args);
        $users = get_users($args);

        $selected_users = (array)$this->get_metadata(static::META_LIST_KEY);
        foreach ($users as $user) {
            if (in_array($user->ID, $selected_users)) {
                $user->selected = true;
            }
        }

        $template_context['name']       = 'publishpress_notif[receiver_user_checkbox]';
        $template_context['id']         = 'publishpress_notif_user';
        $template_context['value']      = static::META_VALUE;
        $template_context['users']      = $users;
        $template_context['list_class'] = 'publishpress_notif_user_list';
        $template_context['input_name'] = 'publishpress_notif[receiver_user][]';
        $template_context['input_id']   = 'publishpress_notif_user_list';

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
            // Get the users selected in the workflow
            $users = get_post_meta($workflow->ID, static::META_LIST_KEY);

            if (!empty($users)) {
                foreach ($users as $user) {
                    $receivers[] = [
                        'receiver' => (int)$user,
                        'group'    => self::META_VALUE
                    ];
                }
            }

            /**
             * Filters the list of receivers, but triggers only when users are selected.
             *
             * @param array $receivers
             * @param WP_Post $workflow
             * @param array $args
             */
            $receivers = apply_filters('publishpress_notif_workflow_receiver_user', $receivers, $workflow, $args);
        }

        return $receivers;
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
                    _n('%d User', "%d Users", count($items), 'publishpress'),
                    count($items)
                );
            }
        }

        return $values;
    }

    /**
     * Method to return a list of fields to display in the filter area
     *
     * @param array
     *
     * @return array
     */
    protected function get_filters($filters = [])
    {
        if (!empty($this->cache_filters)) {
            return $this->cache_filters;
        }

        $step_name = $this->attr_prefix . '_' . $this->name;

        $filters[] = new Filter\User($step_name);

        return parent::get_filters($filters);
    }
}
