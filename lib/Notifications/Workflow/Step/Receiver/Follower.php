<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Workflow\Step\Receiver;

use PublishPress\Legacy\Util;
use PublishPress\Notifications\Traits\Dependency_Injector;
use WP_Post;

class Follower extends Simple_Checkbox implements Receiver_Interface
{
    const META_KEY = '_psppno_tofollower';

    const META_VALUE = 'follower';

    protected $option = 'receiver_follower';

    /**
     * The constructor
     */
    public function __construct()
    {
        $this->name        = 'follower';
        $this->label       = __('Users who selected "Notify me" for the content', 'publishpress');
        $this->option_name = 'receiver_follower';

        parent::__construct();
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
        global $publishpress;

        // If checked, add the authors to the list of receivers
        if ($this->is_selected($workflow->ID)) {
            $post_id = $args['params']['post_id'];

            if (empty($post_id)) {
                return $receivers;
            }

            $followers = [];

            // Check if we are saving the post and use that data instead of the stored taxonomies/metadata.
            $method = Util::getRequestMethod();

            $roles  = [];
            $users  = [];
            $emails = [];

            $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';

            if ('POST' === $method && 'editpost' === $action) {
                $toNotify = isset($_POST['to_notify']) ?
                    array_map('sanitize_text_field', (array)wp_unslash($_POST['to_notify'])) : false;

                if (!empty($toNotify)) {
                    foreach ($toNotify as $item) {
                        if (is_numeric($item)) {
                            $users[] = $item;
                        } elseif (strpos($item, '@') > 0) {
                            $emails[] = $item;
                        } elseif (0 === strpos($item, 'group-')) {
                            $groups[] = $item;
                        } else {
                            $roles[] = $item;
                        }
                    }
                }
            } else {
                // Get following users and roles.
                $roles  = $publishpress->notifications->get_roles_to_notify($post_id, 'slugs');
                $users  = $publishpress->notifications->get_users_to_notify($post_id, 'id');
                $emails = $publishpress->notifications->get_emails_to_notify($post_id);

                if (defined('PRESSPERMIT_VERSION')) {
                    $groups = $publishpress->notifications->get_groups_to_notify($post_id, 'slugs');
                } else {
                    $groups = [];
                }
            }

            // Extract users from roles.
            if (!empty($roles)) {
                foreach ($roles as $role) {
                    $roleUsers = get_users(['role' => $role,]);

                    if (!empty($roleUsers)) {
                        foreach ($roleUsers as $user) {
                            if (is_user_member_of_blog($user->ID)) {
                                $followers[] = [
                                    'receiver' => $user->ID,
                                    'group'    => self::META_VALUE,
                                    'subgroup' => sprintf(
                                        __('role:%s', 'publishpress'),
                                        ' ' . $role
                                    )
                                ];
                            }
                        }
                    }
                }
            }

            // Extract users from groups.
            if (!empty($groups) && class_exists('PublishPress\Permissions\API')) {
                foreach ((array)$groups as $group_term_slug) {
                    if (0 === strpos($group_term_slug, 'group-')) {
                        $group_id = str_replace('group-', '', $group_term_slug);
                    }

                    if ($group = \PublishPress\Permissions\API::getGroup($group_id)) {
                        if ($group_users = \PublishPress\Permissions\API::getGroupMembers($group_id, 'pp_group')) {
                            foreach ($group_users as $user) {
                                $followers[] = [
                                    'receiver' => $user->ID,
                                    'group'    => self::META_VALUE,
                                    'subgroup' => sprintf(
                                        __('group %d: %s', 'publishpress'),
                                        $group_id,
                                        $group->name
                                    )
                                ];
                            }
                        }
                    }
                }
            }

            // Process the selected users.
            if (!empty($users)) {
                foreach ($users as $user) {
                    if (is_object($user)) {
                        $user = $user->ID;
                    }

                    $followers[] = [
                        'receiver' => $user,
                        'group'    => self::META_VALUE,
                        'subgroup' => __('user', 'publishpress')
                    ];
                }
            }

            // Merge the emails.
            if (!empty($emails)) {
                foreach ($emails as $email) {
                    // Do we have a name?
                    $emailFragments = explode('/', $email);

                    $item = [
                        'receiver' => preg_replace('/^email:/', '', $emailFragments[0]),
                        'channel'  => 'email',
                        'group'    => self::META_VALUE,
                        'subgroup' => __('email', 'publishpress')
                    ];

                    if (isset($emailFragments[1])) {
                        $item['name'] = $emailFragments[1];
                    }

                    $followers[] = $item;
                }
            }

            /**
             * Filters the list of followers.
             *
             * @param array $followers
             * @param WP_Post $workflow
             * @param array $args
             */
            $followers = apply_filters(
                'publishpress_notif_workflow_receiver_post_followers',
                $followers,
                $workflow,
                $args
            );

            $receivers = array_merge($receivers, $followers);
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
            $values[] = __('"Notify me"', 'publishpress');
        }

        return $values;
    }
}
