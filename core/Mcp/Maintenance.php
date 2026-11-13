<?php

/** Native dashboard notes and safe diagnostics. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;

class Maintenance
{
    public static function register(): void
    {
        Registry::add(
            'get-dashboard-content',
            __('Read Planner dashboard content', 'publishpress'),
            __('Read native unpublished status counts or your own followed content. Requires the enabled native widget and edit_posts. Counts additionally require edit_others_posts and read_private_posts; followed items require per-post read/edit access. No other user subscriptions are queried.', 'publishpress'),
            Registry::object(Registry::page() + ['kind' => ['type' => 'string', 'enum' => ['statuses', 'followed']]], ['kind']),
            [self::class, 'dashboard'],
            static fn() => Registry::module('dashboard') && current_user_can('edit_posts')
        );
        Registry::add(
            'rotate-calendar-feed-key',
            __('Rotate the Calendar feed key', 'publishpress'),
            __('Invalidate existing authenticated Calendar subscription links using the revision from Calendar settings discovery. The new credential is never returned. Requires manage_options and an enabled Calendar. dry_run does not rotate the key.', 'publishpress'),
            Registry::object(['expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['expected_hash']),
            [self::class, 'rotateFeed'],
            static fn() => Registry::module('calendar') && current_user_can('manage_options'),
            true,
            true
        );
        $migration = static fn() => Registry::module('efmigration') && current_user_can('manage_options') && (!is_multisite() || current_user_can('manage_network_options'));
        $step = ['type' => 'string', 'enum' => ['options', 'usermeta', 'metadata', 'finish']];
        Registry::add(
            'get-migration-status',
            __('Inspect Edit Flow migration status', 'publishpress'),
            __('Read a revision and counts for a native Edit Flow migration stage. Raw options, user data and metadata are never returned. User and content permissions are checked for affected objects.', 'publishpress'),
            Registry::object(['step' => $step], ['step']),
            [self::class, 'migrationStatus'],
            $migration
        );
        Registry::add(
            'run-migration-step',
            __('Run an Edit Flow migration stage', 'publishpress'),
            __('Run one native migration stage using expected_hash from status discovery. Affected users and posts must be editable; configuration locks and network permissions apply. dry_run validates without writing. These native stages are not transactional; inspect status after any unconfirmed failure. Stages are bounded to 1000 source records/objects.', 'publishpress'),
            Registry::object(['step' => $step, 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['step', 'expected_hash']),
            [self::class, 'migrate'],
            $migration,
            true,
            true
        );
        $notes = static fn() => Registry::module('dashboard') && current_user_can(apply_filters('pp_dashboard_notepad_edit_cap', 'edit_others_posts'));
        Registry::add(
            'get-dashboard-note',
            __('Read the Planner dashboard note', 'publishpress'),
            __('Read the native dashboard notepad and its revision. Requires the enabled notepad and native note editing capability.', 'publishpress'),
            Registry::object(),
            [self::class, 'note'],
            $notes
        );
        Registry::add(
            'save-dashboard-note',
            __('Update the Planner dashboard note', 'publishpress'),
            __('Update the current native notepad using expected_hash. Native plain-text sanitization and object permission checks apply. dry_run does not save.', 'publishpress'),
            Registry::object(['content' => Registry::text(50000), 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['content', 'expected_hash']),
            [self::class, 'saveNote'],
            $notes,
            true
        );
        $debug = static fn() => Registry::module('debug') && current_user_can(\PP_Debug::REQUIRED_CAPABILITY);
        Registry::add(
            'get-diagnostics',
            __('Read Planner diagnostics status', 'publishpress'),
            __('Read only the native debug log existence, size and revision. Raw log data, paths, message payloads and credentials are excluded.', 'publishpress'),
            Registry::object(),
            [self::class, 'diagnostics'],
            $debug
        );
        Registry::add(
            'clear-debug-log',
            __('Clear the Planner debug log', 'publishpress'),
            __('Delete the native Planner log using its current revision and native capability. No arbitrary path is accepted. dry_run does not delete.', 'publishpress'),
            Registry::object(['expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['expected_hash']),
            [self::class, 'clear'],
            $debug,
            true,
            true
        );
    }

    public static function dashboard(array $input)
    {
        $module = Registry::module('dashboard');
        if ($input['kind'] === 'statuses') {
            if (($module->module->options->post_status_widget ?? 'off') !== 'on' || !current_user_can('edit_others_posts') || !current_user_can('read_private_posts')) {
                return Registry::denied();
            }
            return ['items' => $module->getPostStatusCounts(), 'has_more' => false];
        }
        $notifications = Registry::module('notifications');
        if (!$notifications || ($module->module->options->my_posts_widget ?? 'off') !== 'on') {
            return Registry::denied();
        }
        $size = $input['per_page'] ?? 20;
        $posts = $notifications->get_user_to_notify_posts(get_current_user_id(), ['posts_per_page' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size]);
        $items = [];
        foreach ($posts as $post) {
            if (current_user_can('read_post', $post->ID) && current_user_can('edit_post', $post->ID)) {
                $items[] = ['id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status];
            }
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($posts) > $size];
    }

    private static function migrationState(array $input)
    {
        $module = Registry::module('efmigration');
        if (!$module || !current_user_can('manage_options') || (is_multisite() && !current_user_can('manage_network_options'))) {
            return Registry::denied();
        }
        if ($input['step'] === 'metadata' && defined('PUBLISHPRESS_PLANNER_DISABLE_EF_METADATA_MIGRATION') && PUBLISHPRESS_PLANNER_DISABLE_EF_METADATA_MIGRATION) {
            return Registry::error(__('Metadata migration is locked by site configuration.', 'publishpress'));
        }
        $state = $module->migrationSnapshot($input['step']);
        foreach (['metadata', 'terms', 'objects', 'users'] as $kind) {
            if (count($state[$kind] ?? []) > 1000) {
                return Registry::error(__('Use native migration administration for datasets larger than 1000 records.', 'publishpress'));
            }
        }
        foreach ($state['users'] ?? [] as $row) {
            if (!current_user_can('edit_user', $row->user_id)) {
                return Registry::denied();
            }
        }
        foreach ($state['metadata'] ?? [] as $row) {
            $existing = $state['destination'][$row->post_id][$row->meta_key] ?? [];
            if ($existing && !in_array(maybe_unserialize($row->meta_value), $existing, true)) {
                return Registry::error(__('Resolve conflicting legacy and Planner metadata in native administration first.', 'publishpress'), 409);
            }
        }
        $posts = array_map(static fn($row) => (int) $row->post_id, $state['metadata'] ?? []);
        foreach (array_unique(array_merge($posts, array_map('intval', $state['objects'] ?? []))) as $id) {
            if (!current_user_can('edit_post', $id) || !current_user_can('read_post', $id)) {
                return Registry::denied();
            }
        }
        return $state;
    }

    public static function migrationStatus(array $input)
    {
        $state = self::migrationState($input);
        if (is_wp_error($state)) {
            return $state;
        }
        return ['step' => $input['step'], 'counts' => ['options' => count($state['options'] ?? []), 'users' => count($state['users'] ?? []), 'metadata' => count($state['metadata'] ?? []), 'terms' => count($state['terms'] ?? [])],
            'expected_hash' => Registry::hash($state), 'completed' => $state['flags']];
    }

    public static function migrate(array $input)
    {
        $state = self::migrationState($input);
        if (is_wp_error($state)) {
            return $state;
        }
        $fresh = Registry::stale($input, $state);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        if (!empty($input['dry_run'])) {
            return ['step' => $input['step'], 'dry_run' => true];
        }
        try {
            $result = Registry::module('efmigration')->runMigrationStep($input['step']);
        } catch (\Throwable $exception) {
            return Registry::unconfirmed();
        }
        if (is_wp_error($result)) {
            return $result;
        }
        foreach ($state['metadata'] ?? [] as $row) {
            clean_post_cache((int) $row->post_id);
        }
        if ($input['step'] === 'metadata') {
            $after = Registry::module('efmigration')->migrationSnapshot('metadata');
            if (!empty($after['metadata'])) {
                return Registry::unconfirmed();
            }
        }
        return self::migrationStatus($input);
    }

    public static function rotateFeed(array $input)
    {
        $module = Registry::module('calendar');
        $fresh = Registry::stale($input, (array) $module->module->options);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        if (empty($input['dry_run']) && !$module->getCalendarMethods()->rotateFeedKey()) {
            return Registry::unconfirmed();
        }
        return ['rotated' => empty($input['dry_run']), 'dry_run' => !empty($input['dry_run'])];
    }

    private static function notepad()
    {
        $module = Registry::module('dashboard');
        if (!$module || ($module->module->options->notepad_widget ?? 'off') !== 'on') {
            return null;
        }
        require_once PUBLISHPRESS_BASE_PATH . '/modules/dashboard/widgets/dashboard-notepad.php';
        $widget = new \PP_Dashboard_Notepad_Widget();
        if (!post_type_exists($widget::notepad_post_type)) {
            $widget->init();
        }
        return $widget;
    }

    public static function note()
    {
        $widget = self::notepad();
        if (!$widget || !current_user_can(apply_filters('pp_dashboard_notepad_edit_cap', 'edit_others_posts'))) {
            return Registry::denied();
        }
        $posts = get_posts(['post_type' => $widget::notepad_post_type, 'post_status' => 'draft', 'numberposts' => 1]);
        $post = $posts[0] ?? null;
        if ($post && (!current_user_can('read_post', $post->ID) || !current_user_can('edit_post', $post->ID))) {
            return Registry::denied();
        }
        $state = ['id' => $post ? $post->ID : 0, 'content' => $post ? $post->post_content : '', 'modified' => $post ? $post->post_modified_gmt : ''];
        return $state + ['expected_hash' => Registry::hash($state)];
    }

    public static function saveNote(array $input)
    {
        $before = self::note();
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($before['expected_hash'], $input['expected_hash'])) {
            return Registry::error(__('The dashboard note changed. Read it again.', 'publishpress'), 409);
        }
        if (!empty($input['dry_run'])) {
            return ['content' => wp_filter_nohtml_kses($input['content']), 'dry_run' => true];
        }
        $id = self::notepad()->saveNote($input['content'], $before['id']);
        return is_wp_error($id) ? $id : ($id ? self::note() : Registry::unconfirmed());
    }

    public static function diagnostics()
    {
        $module = Registry::module('debug');
        if (!$module || !current_user_can(\PP_Debug::REQUIRED_CAPABILITY)) {
            return Registry::denied();
        }
        $state = $module->logState();
        return $state + ['expected_hash' => Registry::hash($state)];
    }

    public static function clear(array $input)
    {
        $before = self::diagnostics();
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($before['expected_hash'], $input['expected_hash'])) {
            return Registry::error(__('The debug log changed. Read it again.', 'publishpress'), 409);
        }
        if (empty($input['dry_run']) && !Registry::module('debug')->clearLog()) {
            return Registry::unconfirmed();
        }
        return ['cleared' => empty($input['dry_run']), 'dry_run' => !empty($input['dry_run'])];
    }
}
