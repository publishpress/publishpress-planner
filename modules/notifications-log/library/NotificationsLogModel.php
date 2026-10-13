<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2022 PublishPress
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

namespace PublishPress\NotificationsLog;

use PublishPress\AsyncNotifications\WPCronAdapter;
use PublishPress\Notifications\Workflow\Workflow;
use WP_Comment;

/**
 * Class NotificationsLogModel
 *
 * @package PublishPress\NotificationsLog
 */
class NotificationsLogModel
{
    const COMMENT_TYPE = 'notification';

    const COMMENT_ACTIVE = 'active';

    const COMMENT_ARCHIVED = 'archived';

    const COMMENT_USER_AGENT = 'PublishPress/NotificationsLog';

    const COMMENT_AUTHOR = 'NotificationsLog';

    const META_NOTIF_RECEIVER = '_ppnotif_receiver';

    const META_NOTIF_RECEIVER_GROUP = '_ppnotif_receiver_group';

    const META_NOTIF_RECEIVER_SUBGROUP = '_ppnotif_receiver_subgroup';

    const META_NOTIF_WORKFLOW_ID = '_ppnotif_workflow_id';

    const META_NOTIF_OLD_STATUS = '_ppnotif_old_status';

    const META_NOTIF_NEW_STATUS = '_ppnotif_new_status';

    const META_NOTIF_CHANNEL = '_ppnotif_channel';

    const META_NOTIF_EVENT = '_ppnotif_event';

    const META_NOTIF_STATUS = '_ppnotif_status';

    const META_NOTIF_SUCCESS = '_ppnotif_success';

    const META_NOTIF_ERROR = '_ppnotif_error';

    const META_NOTIF_ASYNC = '_ppnotif_async';

    const META_NOTIF_COMMENT_ID = '_ppnotif_comment_id';

    const META_NOTIF_EVENT_ARGS = '_ppnotif_event_args';

    const META_NOTIF_USER_ID = '_ppnotif_user_id';

    const META_NOTIF_CRON_ID = '_ppnotif_cron_id';

    const META_NOTIF_BLOG_ID = '_ppnotif_blog_id';

    const META_NOTIF_PARENT_ID = '_ppnotif_parent_id';

    /**
     * @var int
     */
    public $id;

    /**
     * @var int
     */
    public $postId;

    /**
     * @var array
     */
    public $content;

    /**
     * @var int
     */
    public $workflowId;

    /**
     * @var string
     */
    public $workflowTitle;

    /**
     * @var string
     */
    public $event;

    /**
     * @var string
     */
    public $oldStatus;

    /**
     * @var string
     */
    public $newStatus;

    /**
     * @var string
     */
    public $channel;

    /**
     * @var string
     */
    public $receiver;

    /**
     * @var string
     */
    public $receiverGroup;

    /**
     * @var string
     */
    public $receiverSubgroup;

    /**
     * @var string
     */
    public $author;

    /**
     * @var string
     */
    public $date;

    /**
     * @var string
     */
    public $status;

    /**
     * @var bool
     */
    public $success;

    /**
     * @var string
     */
    public $error;

    /**
     * @var bool
     */
    public $async;

    /**
     * @var int
     */
    public $commentId;

    /**
     * @var string
     */
    public $postTitle;

    /**
     * @var array
     */
    public $eventArgs;

    /**
     * @var int
     */
    public $userId;

    /**
     * @var string
     */
    public $cronId;

    /**
     * @var int
     */
    public $blogId;

    /**
     * @var int
     */
    public $parentId;

    /**
     * @var bool
     */
    private $isFromAnotherBlogCache = null;

    /**
     * NotificationsLogModel constructor.
     *
     * @param WP_Comment $log
     */
    public function __construct(WP_Comment $log)
    {
        $this->id = (int)$log->comment_ID;
        $this->blogId = (int)get_comment_meta($this->id, self::META_NOTIF_BLOG_ID, true);
        $this->parentId = (int)get_comment_meta($this->id, self::META_NOTIF_PARENT_ID, true);

        if ($this->isFromAnotherBlog()) {
            $this->switchToTheBlog();

            $log = $this->getParentLog();
        }

        $this->postId = (int)$log->comment_post_ID;
        $this->author = self::COMMENT_AUTHOR;
        $this->content = maybe_unserialize($log->comment_content);
        $this->date = $log->comment_date;

        if ($log !== null) {
            $this->workflowId = (int)$this->getMeta(self::META_NOTIF_WORKFLOW_ID);
            $this->userId = (int)$this->getMeta(self::META_NOTIF_USER_ID);
            $this->event = $this->getMeta(self::META_NOTIF_EVENT);
            $this->oldStatus = $this->getMeta(self::META_NOTIF_OLD_STATUS);
            $this->newStatus = $this->getMeta(self::META_NOTIF_NEW_STATUS);
            $this->channel = $this->getMeta(self::META_NOTIF_CHANNEL);
            $this->receiver = $this->getMeta(self::META_NOTIF_RECEIVER);
            $this->receiverGroup = $this->getMeta(self::META_NOTIF_RECEIVER_GROUP);
            $this->receiverSubgroup = $this->getMeta(self::META_NOTIF_RECEIVER_SUBGROUP);
            $this->status = $this->getMeta(self::META_NOTIF_STATUS);
            $this->success = $this->getMeta(self::META_NOTIF_SUCCESS);
            $this->error = $this->getMeta(self::META_NOTIF_ERROR);
            $this->async = $this->getMeta(self::META_NOTIF_ASYNC);
            $this->commentId = (int)$this->getMeta(self::META_NOTIF_COMMENT_ID);
            $this->eventArgs = $this->getMeta(self::META_NOTIF_EVENT_ARGS);
            $this->cronId = $this->getMeta(self::META_NOTIF_CRON_ID);
        } else {
            $this->workflowId = null;
            $this->userId = null;
            $this->event = null;
            $this->oldStatus = null;
            $this->newStatus = null;
            $this->channel = null;
            $this->receiver = null;
            $this->receiverGroup = null;
            $this->receiverSubgroup = null;
            $this->status = 'error';
            $this->success = 0;
            $this->error = __('The parent log was deleted or probably rescheduled', 'publishpress');
            $this->async = null;
            $this->commentId = null;
            $this->eventArgs = null;
            $this->cronId = null;
        }

        if (! empty($this->eventArgs) && isset($this->eventArgs['postId'])) {
            $this->eventArgs['post'] = $this->getEventPost();
        }

        $this->workflowTitle = $this->getWorkflowPostTitle();
        $this->postTitle = $this->getPostTitle();

        if ($this->isFromAnotherBlog()) {
            $this->restoreCurrentBlog();
        }
    }

    public function isFromAnotherBlog()
    {
        if (is_null($this->isFromAnotherBlogCache)) {
            $this->isFromAnotherBlogCache = $this->blogId > 0 && $this->blogId !== get_current_blog_id();
        }

        return $this->isFromAnotherBlogCache;
    }

    public function switchToTheBlog()
    {
        if ($this->isFromAnotherBlog()) {
            switch_to_blog($this->blogId);
        }
    }

    public function restoreCurrentBlog()
    {
        if ($this->isFromAnotherBlog()) {
            restore_current_blog();
        }
    }

    private function getParentLog()
    {
        return get_comment($this->parentId);
    }

    private function getEventPost()
    {
        $post = get_post((int)$this->eventArgs['postId']);

        return $post;
    }

    private function getWorkflowPostTitle()
    {
        $workflow = get_post($this->workflowId);

        return (!empty($workflow) && !empty($workflow->post_title)) ? $workflow->post_title : '';
    }

    private function getPostTitle()
    {
        $post = get_post($this->postId);

        return (! empty($post) && ! is_wp_error($post)) ? $post->post_title : '';
    }

    private function getWorkflow()
    {
        $workflow = Workflow::load_by_id($this->workflowId);

        $workflow->event_args = $this->eventArgs;

        return $workflow;
    }

    public function getReceiversByGroup()
    {
        if ($this->status === 'scheduled') {
            $workflow = $this->getWorkflow();

            return $workflow->get_receivers_by_group();
        } else {
            return [
                $this->receiverGroup => [
                    [
                        'receiver' => $this->receiver,
                        'channel' => $this->channel,
                        'group' => $this->receiverGroup,
                        'subgroup' => $this->receiverSubgroup,
                    ],
                ]
            ];
        }
    }

    /**
     * @param $meta_key
     * @param bool $single
     * @return mixed
     */
    private function getMeta($meta_key, $single = true)
    {
        if ($this->isFromAnotherBlog()) {
            return get_comment_meta($this->parentId, $meta_key, $single);
        }

        return get_comment_meta($this->id, $meta_key, $single);
    }

    public function delete()
    {
        $cronTask = $this->getCronTask();

        if (! empty($cronTask)) {
            wp_clear_scheduled_hook(WPCronAdapter::SEND_NOTIFICATION_HOOK, $cronTask['args']);
        }

        wp_delete_comment($this->id, true);
    }

    public function archive()
    {
        wp_update_comment(
            [
                'comment_ID' => $this->id,
                'comment_approved' => self::COMMENT_ARCHIVED,
            ]
        );
    }

    public function getCronTask()
    {
        $cronArray = _get_cron_array();

        $expectedHooks = [WPCronAdapter::SEND_NOTIFICATION_HOOK,];

        if (! empty($cronArray)) {
            foreach ($cronArray as $time => $cronTasks) {
                foreach ($cronTasks as $hook => $dings) {
                    if (! in_array($hook, $expectedHooks)) {
                        continue;
                    }

                    if (array_key_exists($this->cronId, $dings)) {
                        $data = $dings[$this->cronId];

                        return [
                            'cronId' => $this->cronId,
                            'time' => $time,
                            'hook' => $hook,
                            'args' => $data['args'],
                        ];
                    }
                }
            }
        }

        return false;
    }
}
