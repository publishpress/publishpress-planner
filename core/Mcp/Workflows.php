<?php

/** Notification workflow CRUD and transfers using native workflow steps. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;
class Workflows
{
    public static function register(): void
    {
        $manage = static fn() => self::allowed();
        Registry::add(
            'get-workflow-fields',
            __('Discover notification workflow fields', 'publishpress'),
            __('Read exact native workflow event, content, receiver, status/taxonomy filter, template and option schemas. Active Pro modules add before/after publishing timers and the network administrator receiver. Use these nested field names before saving; channels are selected in user notification preferences.', 'publishpress'),
            Registry::object(),
            [self::class, 'fields'],
            $manage
        );
        Registry::add(
            'list-workflows',
            __('Find Planner notification workflows', 'publishpress'),
            __('Find a bounded page of native notification workflows, including title, status and current revision. Requires native notification management and per-workflow access.', 'publishpress'),
            Registry::object(Registry::page() + ['search' => Registry::text(200)]),
            [self::class, 'find'],
            $manage
        );
        Registry::add(
            'get-workflow',
            __('Read a notification workflow', 'publishpress'),
            __('Read native workflow configuration and expected_hash, including every supported active step and filter. Internal metadata and authentication credentials are excluded.', 'publishpress'),
            Registry::object(['id' => Registry::id()], ['id']),
            [self::class, 'get'],
            $manage
        );
        Registry::add(
            'save-workflow',
            __('Create or update a notification workflow', 'publishpress'),
            __('Create a draft or patch supplied native workflow fields. Existing workflows require expected_hash. Publish only after all configuration writes are verified. Native event/receiver classes persist values. dry_run validates choices, edition, object and content-scope permissions without writes or sending messages. Unconfirmed multi-step writes report the workflow ID and its active state; inspect it before retrying.', 'publishpress'),
            self::saveSchema(),
            [self::class, 'save'],
            $manage,
            true
        );
        Registry::add(
            'delete-workflow',
            __('Trash, restore or delete a notification workflow', 'publishpress'),
            __('Use native workflow trash, restore and deletion with expected_hash and object permissions. Permanent deletion requires an already trashed workflow. dry_run checks without writing.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'operation' => ['type' => 'string', 'enum' => ['trash', 'restore', 'delete']],
                'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['id', 'operation', 'expected_hash']),
            [self::class, 'delete'],
            $manage,
            true,
            true
        );
        Registry::add(
            'export-workflows',
            __('Export Planner notification workflows', 'publishpress'),
            __('Export up to 50 authorized native notification configurations in the Planner MCP transfer format. Secrets, license keys and unrelated post metadata are excluded. Use import-workflows for validated copies.', 'publishpress'),
            Registry::object(['ids' => Registry::listOf(Registry::id(), 50)], ['ids']),
            [self::class, 'export'],
            $manage
        );
        Registry::add(
            'import-workflows',
            __('Import Planner notification workflows', 'publishpress'),
            __('Import up to 50 typed workflow requests. All are validated before writing; dry_run previews the entire import. Native classes and edition/dependency rules apply. A later failure reports completed IDs and the failed index; it never claims an unchanged import.', 'publishpress'),
            Registry::object(
                ['items' => ['type' => 'array', 'items' => self::saveSchema(), 'minItems' => 1, 'maxItems' => 50],
                'dry_run' => ['type' => 'boolean']],
                ['items']
            ),
            [self::class, 'import'],
            $manage,
            true
        );
    }

    public static function saveSchema(): array
    {
        return Registry::object(['id' => Registry::id(), 'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
            'status' => ['type' => 'string', 'enum' => ['draft', 'publish']],
            'configuration' => ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 100],
            'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']]);
    }

    public static function allowed(): bool
    {
        $type = get_post_type_object(PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW);
        return Registry::module('improved_notifications') && $type && current_user_can($type->cap->edit_posts);
    }

    public static function fields(): array
    {
        $definitions = WorkflowFields::definitions();
        return ['schema' => Registry::object(array_map(static fn($d) => $d['schema'], $definitions)),
            'defaults' => (object) WorkflowFields::read(0, $definitions), 'pro_active' => Registry::pro(), 'channels' => Settings::channels()];
    }

    private static function post(int $id)
    {
        $post = get_post($id);
        return $post && $post->post_type === PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW && current_user_can('edit_post', $id)
        && current_user_can('read_post', $id) ? $post : null;
    }

    public static function state($post, array $definitions): array
    {
        return ['post' => array_intersect_key((array) $post, array_flip(['ID', 'post_title', 'post_status', 'post_modified_gmt'])),
            'metadata' => WorkflowFields::raw($post->ID, $definitions)];
    }

    public static function find(array $input): array
    {
        $size = $input['per_page'] ?? 20;
        $query = new \WP_Query(['post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW, 'post_status' => ['draft', 'publish', 'trash'],
            'posts_per_page' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size, 's' => $input['search'] ?? '', 'no_found_rows' => true]);
        $items = [];
        $definitions = WorkflowFields::definitions();
        foreach ($query->posts as $post) {
            if (self::post($post->ID)) {
                $items[] = ['id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status,
                'expected_hash' => Registry::hash(self::state($post, $definitions))];
            }
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($items) > $size];
    }

    public static function get(array $input)
    {
        $post = self::post($input['id']);
        if (!$post) {
            return Registry::denied();
        }
        $definitions = WorkflowFields::definitions();
        return ['id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status,
            'configuration' => (object) WorkflowFields::read($post->ID, $definitions),
            'expected_hash' => Registry::hash(self::state($post, $definitions)), 'dry_run' => false];
    }

    public static function save(array $input)
    {
        if (!self::allowed()) {
            return Registry::denied();
        }
        $type = get_post_type_object(PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW);
        $definitions = WorkflowFields::definitions();
        $post = isset($input['id']) ? self::post($input['id']) : null;
        if (isset($input['id']) && !$post) {
            return Registry::denied();
        }
        if ($post) {
            $fresh = Registry::stale($input, self::state($post, $definitions));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
        } elseif (!current_user_can($type->cap->create_posts)) {
            return Registry::denied();
        }
        $title = sanitize_text_field($input['title'] ?? ($post ? $post->post_title : ''));
        if ($title === '') {
            return Registry::error(__('A notification title is required.', 'publishpress'));
        }
        $status = $input['status'] ?? ($post ? $post->post_status : 'draft');
        if (!in_array($status, ['draft', 'publish'], true)) {
            return Registry::error();
        }
        if ($status === 'publish' && !current_user_can($type->cap->publish_posts)) {
            return Registry::denied();
        }
        $patch = Registry::validate($input['configuration'] ?? [], Registry::object(array_map(static fn($d) => $d['schema'], $definitions)));
        if (is_wp_error($patch)) {
            return $patch;
        }
        $patch = WorkflowFields::validateReferences($patch, $definitions);
        if (is_wp_error($patch)) {
            return $patch;
        }
        $values = WorkflowFields::read($post ? $post->ID : 0, $definitions);
        $values = WorkflowFields::merge($values, $patch, $definitions);
        if (is_wp_error($values)) {
            return $values;
        }
        if (isset($values['content_main'])) {
            $safeTemplate = WorkflowFields::validateTemplate($values['content_main']);
            if (is_wp_error($safeTemplate)) {
                return $safeTemplate;
            }
        }
        // A workflow may disclose matching content through a configured native channel.
        $scope = Registry::module('notifications');
        if (!$scope) {
            return Registry::denied();
        }
        $types = $scope->get_post_types_for_module($scope->module);
        foreach ($types as $contentType) {
            $object = get_post_type_object($contentType);
            if (!$object || !current_user_can($object->cap->edit_posts) || !current_user_can($object->cap->read_private_posts)) {
                return Registry::denied();
            }
        }
        if (!empty($input['dry_run'])) {
            return ['id' => $post ? $post->ID : 0, 'title' => $title, 'status' => $status, 'configuration' => (object) $values, 'dry_run' => true];
        }
        if ($post && !$patch && $title === $post->post_title && $status === $post->post_status) {
            return self::get(['id' => $post->ID]);
        }
        if ($post) {
            clean_post_cache($post->ID);
            $fresh = Registry::stale($input, self::state(get_post($post->ID), $definitions));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
        }
        $id = wp_insert_post(wp_slash(['ID' => $post ? $post->ID : 0, 'post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW,
            'post_title' => $title, 'post_status' => 'draft', 'post_author' => $post ? $post->post_author : get_current_user_id()]), true);
        if (is_wp_error($id) || !$id) {
            return is_wp_error($id) ? $id : Registry::unconfirmed();
        }
        try {
            WorkflowFields::save($id, $values, $definitions, $post ? array_keys($patch) : array_keys($definitions));
            $after = WorkflowFields::read($id, $definitions);
            if (Registry::hash($after) !== Registry::hash($values)) {
                return self::incomplete($id);
            }
            if ($status === 'publish') {
                $result = wp_update_post(['ID' => $id, 'post_status' => 'publish'], true);
                if (is_wp_error($result) || get_post_status($id) !== 'publish' || Registry::hash(WorkflowFields::read($id, $definitions)) !== Registry::hash($values)) {
                    return self::incomplete($id);
                }
            }
            return self::get(['id' => $id]);
        } catch (\Throwable $error) {
            return self::incomplete($id);
        }
    }

    private static function incomplete(int $id): \WP_Error
    {
        if (get_post_status($id) === 'publish' && current_user_can('edit_post', $id)) {
            wp_update_post(['ID' => $id, 'post_status' => 'draft'], true);
        }
        return new \WP_Error('planner_workflow_unconfirmed', __('Workflow saving could not be confirmed. Inspect this workflow before retrying; it may remain inactive with partial changes.', 'publishpress'), ['status' => 500, 'id' => $id, 'active' => get_post_status($id) === 'publish']);
    }

    public static function delete(array $input)
    {
        $post = self::post($input['id']);
        if (!$post || !current_user_can('delete_post', $post->ID)) {
            return Registry::denied();
        }
        $fresh = Registry::stale($input, self::state($post, WorkflowFields::definitions()));
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        if ($input['operation'] === 'trash' && defined('EMPTY_TRASH_DAYS') && !EMPTY_TRASH_DAYS) {
            return Registry::error(__('Trash is disabled. Use native administration for permanent deletion.', 'publishpress'));
        }
        if (($input['operation'] === 'delete' || $input['operation'] === 'restore') && $post->post_status !== 'trash') {
            return Registry::error();
        }
        if ($input['operation'] === 'restore' && !current_user_can(get_post_type_object($post->post_type)->cap->publish_posts)) {
            return Registry::denied();
        }
        if (empty($input['dry_run'])) {
            $result = $input['operation'] === 'delete' ? wp_delete_post($post->ID, true)
            : ($input['operation'] === 'restore' ? wp_untrash_post($post->ID) : wp_trash_post($post->ID));
            if (!$result || is_wp_error($result)) {
                return Registry::unconfirmed();
            }
        }
        return ['id' => $post->ID, 'operation' => $input['operation'], 'dry_run' => !empty($input['dry_run'])];
    }

    public static function export(array $input)
    {
        $items = [];
        foreach ($input['ids'] as $id) {
            $item = self::get(['id' => $id]);
            if (is_wp_error($item)) {
                return $item;
            }
            $items[] = ['title' => $item['title'], 'status' => $item['status'] === 'publish' ? 'publish' : 'draft', 'configuration' => $item['configuration']];
        }
        return ['version' => 1, 'items' => $items];
    }

    public static function import(array $input)
    {
        $seen = [];
        foreach ($input['items'] as $item) {
            if (isset($item['id'])) {
                if (isset($seen[$item['id']])) {
                    return Registry::error();
                }
                $seen[$item['id']] = true;
            }
            $item['dry_run'] = true;
            $result = self::save($item);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        if (!empty($input['dry_run'])) {
            return ['dry_run' => true, 'count' => count($input['items'])];
        }
        $saved = [];
        foreach ($input['items'] as $index => $item) {
            $item['dry_run'] = false;
            $result = self::save($item);
            if (is_wp_error($result)) {
                return ['saved_ids' => $saved, 'failed_index' => $index, 'complete' => false, 'error' => $result->get_error_code(), 'object' => $result->get_error_data()];
            }
            $saved[] = $result['id'];
        }
        return ['saved_ids' => $saved, 'complete' => true, 'dry_run' => false];
    }
}
