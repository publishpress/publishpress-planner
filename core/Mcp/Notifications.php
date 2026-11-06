<?php

/** Editorial discussions, native post followers, user preferences and delivery logs. */

namespace PublishPress\Core\Mcp;

use PublishPress\NotificationsLog\NotificationsLogModel as Log;

defined('ABSPATH') || exit;
class Notifications
{
    public static function register(): void
    {
        $comments = static fn() => (bool) Registry::module('editorial_comments');
        Registry::add(
            'list-editorial-comments',
            __('Read an editorial discussion', 'publishpress'),
            __('Read a bounded page of private editorial comments across permitted posts or on one editable/readable post. Cross-post browsing requires edit_posts; search and author_id filters are supported. Returns author IDs and permitted attachment IDs without email/IP/user-agent data. Requires Editorial Comments enabled for this post type.', 'publishpress'),
            Registry::object(Registry::page() + ['post_id' => Registry::id(), 'author_id' => Registry::id(), 'search' => Registry::text(200)]),
            [self::class, 'comments'],
            $comments
        );
        Registry::add(
            'save-editorial-comment',
            __('Add or edit an editorial comment', 'publishpress'),
            __('Add a comment/reply or edit an existing editorial comment through the native lifecycle and notification hooks. Editing requires the native own/others comment capability and expected_hash. Attachments must be readable/editable existing media. dry_run checks without saving, sending notifications or altering files.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'post_id' => Registry::id(), 'content' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 50000],
                'parent_id' => Registry::id(), 'attachment_ids' => Registry::listOf(Registry::id(), 20),
                'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['post_id', 'content']),
            [self::class, 'saveComment'],
            $comments,
            true
        );
        Registry::add(
            'delete-editorial-comment',
            __('Delete an editorial comment', 'publishpress'),
            __('Trash one native editorial comment, or permanently delete an already trashed comment, with expected_hash and own/others deletion capability. Native comments with replies cannot be deleted. dry_run validates without changing the discussion.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'operation' => ['type' => 'string', 'enum' => ['trash', 'delete']], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['id', 'expected_hash']),
            [self::class, 'deleteComment'],
            $comments,
            true,
            true
        );
        $followers = static fn() => Registry::module('notifications') && current_user_can(Registry::module('notifications')->edit_post_subscriptions_cap);
        Registry::add(
            'get-post-followers',
            __('Read post notification followers', 'publishpress'),
            __('Read native user, role, group and email follow selections for one editable post. Native email recipient addresses are included only for an authorized editable post. Includes expected_hash for supported follower patches.', 'publishpress'),
            Registry::object(['post_id' => Registry::id()], ['post_id']),
            [self::class, 'followers'],
            $followers
        );
        Registry::add(
            'update-post-followers',
            __('Update post notification followers', 'publishpress'),
            __('Patch native user, role, group or email follower lists using expected_hash. Omitted follower selections remain unchanged. Every ID/role is validated before native taxonomy relationship writes. Requires edit_post_subscriptions and object edit/read access. dry_run previews without writing.', 'publishpress'),
            Registry::object(['post_id' => Registry::id(), 'users' => Registry::listOf(Registry::id()),
                'roles' => Registry::listOf(['type' => 'string', 'enum' => array_keys(wp_roles()->roles)]),
                'groups' => Registry::listOf(Registry::id()), 'emails' => Registry::listOf(['type' => 'string', 'format' => 'email']),
                'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['post_id', 'expected_hash']),
            [self::class, 'updateFollowers'],
            $followers,
            true
        );
        $preferences = static fn() => Registry::module('improved_notifications') && current_user_can('pp_set_notification_channel');
        Registry::add(
            'get-notification-preferences',
            __('Read user notification channels', 'publishpress'),
            __('Read a user’s native workflow channel preferences. Requires pp_set_notification_channel and edit_user for that user. Includes available email/mute and active Pro Slack choices, with no OAuth tokens.', 'publishpress'),
            Registry::object(Registry::page() + ['user_id' => Registry::id()], ['user_id']),
            [self::class, 'preferences'],
            $preferences
        );
        Registry::add(
            'update-notification-preferences',
            __('Update user notification channels', 'publishpress'),
            __('Patch native per-workflow user channel choices using expected_hash. Every workflow, channel and user permission is checked before writes. Use email, mute, or Slack when available; Slack destinations require active Pro. Omitted preferences remain. dry_run sends no messages and stores nothing.', 'publishpress'),
            Registry::object(Registry::page() + ['user_id' => Registry::id(), 'preferences' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100,
                'items' => Registry::object(['workflow_id' => Registry::id(), 'channel' => ['type' => 'string', 'enum' => ['default', 'email', 'mute', 'slack']],
                    'slack_channel' => Registry::text(200)], ['workflow_id', 'channel'])], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['user_id', 'preferences', 'expected_hash']),
            [self::class, 'updatePreferences'],
            $preferences,
            true
        );
        $logs = static fn() => Registry::module('notifications_log') && current_user_can(apply_filters('pp_view_notifications_cap', 'read_pp_notif_workflow'));
        Registry::add(
            'list-notification-logs',
            __('Read Planner delivery status', 'publishpress'),
            __('Read a bounded page of native notification log status. Every referenced post/workflow requires object access; cross-site records are excluded. Message bodies, email addresses, raw errors, credentials and cron payloads are never returned.', 'publishpress'),
            Registry::object(Registry::page() + ['post_id' => Registry::id(), 'status' => ['type' => 'string', 'enum' => ['active', 'archived']]]),
            [self::class, 'logs'],
            $logs
        );
        Registry::add(
            'manage-notification-logs',
            __('Archive or delete selected notification logs', 'publishpress'),
            __('Archive or delete up to 50 explicitly selected native delivery logs with their revisions. All permissions and objects are validated before changes. Deletion uses the native model and cancels that log’s associated queued task. dry_run does not cancel or delete anything. Partial failures report completed IDs.', 'publishpress'),
            Registry::object(['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => Registry::object(['id' => Registry::id(), 'expected_hash' => Registry::revision()], ['id', 'expected_hash'])],
                'operation' => ['type' => 'string', 'enum' => ['archive', 'delete']], 'dry_run' => ['type' => 'boolean']], ['items', 'operation']),
            [self::class, 'manageLogs'],
            static fn() => $logs() && current_user_can('manage_options'),
            true,
            true
        );
    }

    private static function post(int $id, string $module)
    {
        $post = get_post($id);
        $feature = Registry::module($module);
        if (!$post || !$feature || !current_user_can('edit_post', $id) || !current_user_can('read_post', $id)) {
            return null;
        }
        return in_array($post->post_type, $feature->get_post_types_for_module($feature->module), true) ? $post : null;
    }

    private static function commentState($comment): array
    {
        return ['comment' => array_intersect_key((array) $comment, array_flip(['comment_ID', 'comment_post_ID', 'comment_parent', 'user_id', 'comment_content', 'comment_approved'])),
            'attachments' => get_comment_meta($comment->comment_ID, '_pp_editorial_comment_files', true)];
    }

    public static function comments(array $input)
    {
        if ((isset($input['post_id']) && !self::post($input['post_id'], 'editorial_comments')) || (!isset($input['post_id']) && !current_user_can('edit_posts'))) {
            return Registry::denied();
        }
        $size = $input['per_page'] ?? 20;
        $comments = pp_get_comments_plus(['post_id' => $input['post_id'] ?? 0, 'user_id' => $input['author_id'] ?? '', 'search' => $input['search'] ?? '', 'type' => \PP_Editorial_Comments::comment_type,
            'status' => \PP_Editorial_Comments::comment_type, 'number' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size, 'orderby' => 'comment_date', 'order' => 'ASC']);
        $items = [];
        foreach (array_slice($comments, 0, $size) as $comment) {
            if (!self::post((int) $comment->comment_post_ID, 'editorial_comments')) {
                continue;
            }
            $files = array_filter(array_map('absint', explode(',', get_comment_meta($comment->comment_ID, '_pp_editorial_comment_files', true))));
            $files = array_values(array_filter($files, static fn($id) => current_user_can('read_post', $id)));
            $items[] = ['id' => (int) $comment->comment_ID, 'post_id' => (int) $comment->comment_post_ID,
                'parent_id' => (int) $comment->comment_parent, 'author_id' => (int) $comment->user_id,
                'content' => $comment->comment_content, 'attachment_ids' => $files, 'expected_hash' => Registry::hash(self::commentState($comment))];
        }
        return ['items' => $items, 'has_more' => count($comments) > $size];
    }

    public static function saveComment(array $input)
    {
        $post = self::post($input['post_id'], 'editorial_comments');
        if (!$post) {
            return Registry::denied();
        }
        $module = Registry::module('editorial_comments');
        $old = isset($input['id']) ? get_comment($input['id']) : null;
        if (isset($input['id'])) {
            if (!$old || (int) $old->comment_post_ID !== $post->ID || !$module->canManageEditorialComment($old, 'edit')) {
                return Registry::denied();
            }
            $fresh = Registry::stale($input, self::commentState($old));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
        }
        if (isset($input['parent_id'])) {
            $parent = get_comment($input['parent_id']);
            if (!$parent || $parent->comment_approved !== \PP_Editorial_Comments::comment_type || $parent->comment_type !== \PP_Editorial_Comments::comment_type || (int) $parent->comment_post_ID !== $post->ID) {
                return Registry::error();
            }
            if ($old && (int) $old->comment_parent !== $input['parent_id']) {
                return Registry::error();
            }
        }
        $files = $input['attachment_ids'] ?? ($old ? array_filter(array_map('absint', explode(',', get_comment_meta($old->comment_ID, '_pp_editorial_comment_files', true)))) : []);
        foreach ($files as $id) {
            if (get_post_type($id) !== 'attachment' || !current_user_can('read_post', $id) || !current_user_can('edit_post', $id)) {
                return Registry::denied();
            }
        }
        $content = $old ? trim(sanitize_text_field($input['content'])) : esc_html(trim($input['content']));
        if ($content === '') {
            return Registry::error();
        }
        if (!empty($input['dry_run'])) {
            return ['id' => $old ? (int) $old->comment_ID : 0, 'content' => $content, 'attachment_ids' => array_values($files), 'dry_run' => true];
        }
        $user = wp_get_current_user();
        $data = $old ? ['comment_ID' => (int) $old->comment_ID, 'comment_content' => $content] : [
            'comment_post_ID' => $post->ID, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email,
            'comment_author_url' => $user->user_url, 'user_id' => $user->ID, 'comment_content' => $content,
            'comment_parent' => $input['parent_id'] ?? 0, 'comment_type' => \PP_Editorial_Comments::comment_type,
            'comment_approved' => \PP_Editorial_Comments::comment_type, 'comment_date' => current_time('mysql'), 'comment_date_gmt' => current_time('mysql', true)];
        try {
            $saved = $module->persistEditorialComment(wp_slash($data), implode(',', $files));
        } catch (\Throwable $error) {
            return Registry::unconfirmed();
        }
        if (is_wp_error($saved)) {
            return $saved;
        }
        $storedFiles = array_values(array_filter(array_map('absint', explode(',', get_comment_meta($saved->comment_ID, '_pp_editorial_comment_files', true)))));
        if ((int) $saved->comment_post_ID !== $post->ID || $saved->comment_content !== $content || array_diff($files, $storedFiles) || array_diff($storedFiles, $files)) {
            return new \WP_Error('planner_comment_unconfirmed', __('Some editorial comment changes could not be confirmed. Read this discussion before retrying.', 'publishpress'), ['status' => 500, 'id' => (int) $saved->comment_ID]);
        }
        return ['id' => (int) $saved->comment_ID, 'post_id' => (int) $saved->comment_post_ID, 'content' => $saved->comment_content,
            'expected_hash' => Registry::hash(self::commentState($saved)), 'dry_run' => false];
    }

    public static function deleteComment(array $input)
    {
        $comment = get_comment($input['id']);
        $module = Registry::module('editorial_comments');
        if (!$module || !$comment || !self::post((int) $comment->comment_post_ID, 'editorial_comments') || !$module->canManageEditorialComment($comment, 'delete')) {
            return Registry::denied();
        }
        $fresh = Registry::stale($input, self::commentState($comment));
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        if (pp_get_comments_plus(['post_id' => $comment->comment_post_ID, 'parent' => $comment->comment_ID, 'number' => 1, 'type' => \PP_Editorial_Comments::comment_type, 'status' => \PP_Editorial_Comments::comment_type])) {
            return Registry::error(__('A comment with replies cannot be deleted.', 'publishpress'));
        }
        $force = ($input['operation'] ?? 'trash') === 'delete';
        if (!$force && defined('EMPTY_TRASH_DAYS') && !EMPTY_TRASH_DAYS) {
            return Registry::error(__('Trash is disabled. Use native administration for permanent deletion.', 'publishpress'));
        }
        if ($force && $comment->comment_approved !== 'trash') {
            return Registry::error(__('Trash the comment before permanent deletion.', 'publishpress'));
        }
        if (empty($input['dry_run'])) {
            $result = $module->deleteEditorialComment($comment->comment_ID, $force);
            if (is_wp_error($result) || !$result) {
                return is_wp_error($result) ? $result : Registry::unconfirmed();
            }
        }
        return ['id' => $input['id'], 'deleted' => empty($input['dry_run']), 'dry_run' => !empty($input['dry_run'])];
    }

    public static function followers(array $input)
    {
        if (!self::post($input['post_id'], 'notifications') || !current_user_can(Registry::module('notifications')->edit_post_subscriptions_cap)) {
            return Registry::denied();
        }
        $module = Registry::module('notifications');
        $users = $module->get_users_to_notify($input['post_id'], 'id');
        $roles = $module->get_roles_to_notify($input['post_id'], 'slugs');
        $raw = [];
        foreach ([$module->notify_user_taxonomy, $module->notify_role_taxonomy, $module->notify_group_taxonomy, $module->notify_email_taxonomy] as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $raw[$taxonomy] = wp_get_object_terms($input['post_id'], $taxonomy, ['fields' => 'ids']);
            }
        }
        return ['post_id' => $input['post_id'], 'users' => array_map('intval', (array) $users), 'roles' => array_values((array) $roles),
            'groups' => class_exists('PublishPress\\Permissions\\API') ? array_map(static fn($slug) => (int) str_replace('group-', '', $slug), $module->get_groups_to_notify($input['post_id'], 'slugs')) : [],
            'emails' => array_values($module->get_emails_to_notify($input['post_id'])), 'expected_hash' => Registry::hash($raw)];
    }

    public static function updateFollowers(array $input)
    {
        $before = self::followers($input);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($before['expected_hash'], $input['expected_hash'])) {
            return Registry::error(__('Followers changed. Read them again.', 'publishpress'), 409);
        }
        if (!array_intersect(['users', 'roles', 'groups', 'emails'], array_keys($input))) {
            return Registry::error();
        }
        foreach ($input['users'] ?? [] as $id) {
            if (!get_userdata($id) || !is_user_member_of_blog($id)) {
                return Registry::error();
            }
        }
        foreach ($input['groups'] ?? [] as $id) {
            if (!class_exists('PublishPress\\Permissions\\API') || !current_user_can('pp_manage_settings') || !\PublishPress\Permissions\API::getGroup($id)) {
                return Registry::denied();
            }
        }
        if (isset($input['groups']) && (!class_exists('PublishPress\\Permissions\\API') || !current_user_can('pp_manage_settings'))) {
            return Registry::denied();
        }
        if (!empty($input['dry_run'])) {
            return array_merge($before, array_intersect_key($input, ['users' => true, 'roles' => true, 'groups' => true, 'emails' => true]), ['dry_run' => true]);
        }
        $module = Registry::module('notifications');
        foreach (['users', 'roles', 'groups', 'emails'] as $kind) {
            if (array_key_exists($kind, $input)) {
                $method = 'post_set_' . $kind . '_to_notify';
                $result = $module->$method($input['post_id'], $kind === 'groups' ? array_map('strval', $input[$kind]) : $input[$kind], false);
                if (is_wp_error($result) || !$result) {
                    return Registry::unconfirmed();
                }
            }
        }
        $saved = self::followers($input);
        if (is_wp_error($saved)) {
            return Registry::unconfirmed();
        }
        foreach (['users', 'roles', 'groups', 'emails'] as $kind) {
            if (isset($input[$kind]) && (array_diff($input[$kind], $saved[$kind]) || array_diff($saved[$kind], $input[$kind]))) {
                return Registry::unconfirmed();
            }
        }
        return $saved + ['dry_run' => false];
    }

    public static function preferences(array $input)
    {
        if (!get_userdata($input['user_id']) || !current_user_can('edit_user', $input['user_id']) || !current_user_can('pp_set_notification_channel')) {
            return Registry::denied();
        }
        $items = [];
        $raw = [];
        $size = $input['per_page'] ?? 100;
        $posts = get_posts(['post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW, 'post_status' => 'publish', 'numberposts' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size, 'orderby' => 'ID', 'order' => 'ASC']);
        foreach (array_slice($posts, 0, $size) as $post) {
            if (!current_user_can('read_post', $post->ID)) {
                continue;
            }
            $channel = get_user_meta($input['user_id'], 'psppno_workflow_channel_' . $post->ID, true);
            $slack = Registry::pro() ? get_user_meta($input['user_id'], 'psppno_workflow_' . $post->ID . '_slack_channel', true) : '';
            $items[] = ['workflow_id' => $post->ID, 'channel' => $channel ?: 'default', 'slack_channel' => $slack];
            $raw[$post->ID] = [$channel, $slack];
        }
        return ['user_id' => $input['user_id'], 'preferences' => $items, 'has_more' => count($posts) > $size, 'channels' => array_merge(['default', 'mute'], Settings::channels()), 'expected_hash' => Registry::hash($raw)];
    }

    public static function updatePreferences(array $input)
    {
        $before = self::preferences($input);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($input['expected_hash'], $before['expected_hash'])) {
            return Registry::error(__('Notification preferences changed. Read them again.', 'publishpress'), 409);
        }
        $scope = array_column($before['preferences'], 'workflow_id');
        $seen = [];
        foreach ($input['preferences'] as $row) {
            $post = get_post($row['workflow_id']);
            if (
                !in_array($row['workflow_id'], $scope, true) || isset($seen[$row['workflow_id']]) || !$post || $post->post_type !== PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW || !current_user_can('read_post', $post->ID)
                || !in_array($row['channel'], $before['channels'], true)
            ) {
                return Registry::error();
            }
            if (isset($row['slack_channel']) && (!Registry::pro() || !Registry::module('slack') || !preg_match('/^(|[#@]?[A-Za-z0-9_.-]{1,199}|[^@\\s]+@[^@\\s]+\\.[^@\\s]+)$/D', $row['slack_channel']))) {
                return Registry::error();
            }
            $seen[$row['workflow_id']] = true;
        }
        if (!empty($input['dry_run'])) {
            return ['preferences' => $input['preferences'], 'dry_run' => true];
        }
        $module = Registry::module('improved_notifications');
        $rows = $input['preferences'];
        foreach ($rows as &$row) {
            if ($row['channel'] === 'default') {
                $row['channel'] = '';
            }
        }
        unset($row);
        $saved = $module->saveNotificationPreferences($input['user_id'], $rows);
        return is_wp_error($saved) ? $saved : self::preferences($input);
    }

    private static function log(int $id)
    {
        $comment = get_comment($id);
        if (!$comment || $comment->comment_type !== Log::COMMENT_TYPE) {
            return null;
        }
        $origin = (int) get_comment_meta($id, Log::META_NOTIF_BLOG_ID, true);
        if ($origin > 0 && $origin !== get_current_blog_id()) {
            return null;
        }
        $log = new Log($comment);
        if ($log->isFromAnotherBlog()) {
            return null;
        }
        if ($log->postId && (!current_user_can('edit_post', $log->postId) || !current_user_can('read_post', $log->postId))) {
            return null;
        }
        if ($log->workflowId && !current_user_can('read_post', $log->workflowId)) {
            return null;
        }
        return $log;
    }

    private static function logHash($log): string
    {
        return Registry::hash(['comment' => (array) get_comment($log->id), 'meta' => get_comment_meta($log->id)]);
    }

    public static function logs(array $input): array
    {
        $size = $input['per_page'] ?? 20;
        $args = ['type' => Log::COMMENT_TYPE, 'status' => $input['status'] ?? Log::COMMENT_ACTIVE,
            'number' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size];
        if (isset($input['post_id'])) {
            $args['post_id'] = $input['post_id'];
        }
        $items = [];
        foreach (pp_get_comments_plus($args) as $comment) {
            $log = self::log((int) $comment->comment_ID);
            if (!$log) {
                continue;
            }
            $items[] = ['id' => $log->id, 'post_id' => $log->postId, 'workflow_id' => $log->workflowId,
                'status' => $log->status, 'success' => (bool) $log->success, 'channel' => $log->channel,
                'date' => $log->date, 'queued' => (bool) $log->getCronTask(), 'expected_hash' => self::logHash($log)];
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($items) > $size];
    }

    public static function manageLogs(array $input)
    {
        $logs = [];
        foreach ($input['items'] as $row) {
            $log = self::log($row['id']);
            if (!$log || isset($logs[$row['id']]) || !hash_equals($row['expected_hash'], self::logHash($log))) {
                return Registry::error();
            }
            $logs[$row['id']] = $log;
        }
        if (!empty($input['dry_run'])) {
            return ['ids' => array_keys($logs), 'dry_run' => true];
        }
        $saved = [];
        foreach ($logs as $id => $log) {
            $input['operation'] === 'archive' ? $log->archive() : $log->delete();
            $comment = get_comment($id);
            if (($input['operation'] === 'delete' && $comment) || ($input['operation'] === 'archive' && (!$comment || $comment->comment_approved !== Log::COMMENT_ARCHIVED))) {
                return ['completed_ids' => $saved, 'failed_id' => $id, 'complete' => false];
            }
            $saved[] = $id;
        }
        return ['completed_ids' => $saved, 'complete' => true, 'dry_run' => false];
    }
}
