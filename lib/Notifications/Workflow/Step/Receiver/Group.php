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

class Group extends Simple_Checkbox implements Receiver_Interface
{
    use PublishPress_Module;

    const META_KEY = '_psppno_togroup';

    const META_LIST_KEY = '_psppno_togrouplist';

    const META_VALUE = 'group';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'group';
        $this->label       = __('Permission Groups', 'publishpress');
        $this->option_name = 'receiver_group_checkbox';

        parent::__construct();

        $this->view_name = 'workflow_receiver_group_field';
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
        if (isset($publishpress_notif['receiver_group'])) {
            $values = array_map('sanitize_key', (array)$publishpress_notif['receiver_group']);
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
        $groups = [];

        if (class_exists('PublishPress\Permissions\API')) {
            global $post;

            if (defined('PUBLISHPRESS_REVISIONS_PRO_VERSION') && get_option('rvy_use_publishpress_notifications')) {
                $groups = \PublishPress\Permissions\API::getGroups('pp_group', ['skip_meta_types' => ['wp_role']]);

                foreach ($groups as $k => $group) {
                    if (empty($group->metagroup_id)) {
                        continue;
                    }
                    
                    switch ($group->metagroup_id) {
                        case 'rvy_scheduled_rev_notice':
                            // Don't offer Scheduled Change Notifications metagroup for standard Planner notifications, or for Revisions notifications related to creation, submission and moderation 
                            $excludes = ['new-revision-created', 'revision-is-submitted', 'revision-status-changed', 'revision-is-scheduled', 'revision-deferred-or-rejected', 'revision-is-applied', 
                            'new-post-is-created-in-draft-status', 'new-post-is-published', 'notify-when-content-is-published', 'existing-post-is-updated', 'notify-on-editorial-comments'];

                            foreach ($excludes as $exclude_notif) {
                                if (0 === strpos($post->post_name, $exclude_notif)) {
                                    unset($groups[$k]);
                                    continue 3;
                                }
                            }

                            break;

                        case 'rvy_pending_rev_notice':
                            // Don't offer Change Request Notifications metagroup for standard Planner notifications, or for Revisions notifications related to publication 
                            $excludes = ['scheduled-revision-is-published', 'revision-is-published',
                            'new-post-is-created-in-draft-status', 'new-post-is-published', 'notify-when-content-is-published', 'existing-post-is-updated', 'notify-on-editorial-comments'];

                            foreach ($excludes as $exclude_notif) {
                                if (0 === strpos($post->post_name, $exclude_notif)) {
                                    unset($groups[$k]);
                                    continue 3;
                                }
                            }

                            break;
                    }

                    if (class_exists('PublishPress\Permissions\DB\Groups') && method_exists('PublishPress\Permissions\DB\Groups', 'getMetagroupName')) {
                        $groups[$k]->name = \PublishPress\Permissions\DB\Groups::getMetagroupName($group->metagroup_type, $group->metagroup_id);
                    }
                }
            } else {
                $groups = \PublishPress\Permissions\API::getGroups('pp_group', ['include_metagroups' => false]);
            }
        }

        if (empty($groups)) {
            return [];
        }

        $selected_groups = (array)$this->get_metadata(static::META_LIST_KEY);
        if (empty($selected_groups)) {
            $selected_groups = [];
        }
        
        foreach (array_keys($groups) as $group_id) {
            if (in_array($group_id, $selected_groups)) {
                $groups[$group_id]->selected = true;
            }
        }

        $template_context['name']       = 'publishpress_notif[receiver_group_checkbox]';
        $template_context['id']         = 'publishpress_notif_group';
        $template_context['value']      = static::META_VALUE;
        $template_context['groups']      = $groups;
        $template_context['list_class'] = 'publishpress_notif_group_list';
        $template_context['input_name'] = 'publishpress_notif[receiver_group][]';
        $template_context['input_id']   = 'publishpress_notif_groups';

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
            $groups     = get_post_meta($workflow->ID, static::META_LIST_KEY);
            $receivers = array_merge($receivers, $this->get_users_from_groups($groups));

            /**
             * Filters the list of receivers, but triggers only when the authors are selected.
             *
             * @param array $receivers
             * @param WP_Post $workflow
             * @param array $args
             */
            $receivers = apply_filters('publishpress_notif_workflow_receiver_group', $receivers, $workflow, $args);
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
    protected function get_users_from_groups($groups)
    {
        $users = [];

        if (!empty($groups) && class_exists('PublishPress\Permissions\API')) {
            foreach ((array)$groups as $group_id) {
                if ($group_users = \PublishPress\Permissions\API::getGroupMembers($group_id, 'pp_group')) {
                    if ($group = \PublishPress\Permissions\API::getGroup($group_id)) {
                        foreach ($group_users as $user) {
                            $users[] = [
                                'receiver' => $user->ID,
                                'group'    => self::META_VALUE,
                                'subgroup' => "{$group_id}: $group->name"
                            ];
                        }
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
                    _n('%d Group', "%d Groups", count($items), 'publishpress'),
                    count($items)
                );
            }
        }

        return $values;
    }
}
