<?php

/**
 * Private Planner abilities and strict, shared input contracts.
 *
 * @package PublishPress
 */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;
class Registry
{
    public static function object(array $properties = [], array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    public static function id(): array
    {
        return ['type' => 'integer', 'minimum' => 1];
    }
    public static function text(int $max = 1000): array
    {
        return ['type' => 'string', 'maxLength' => $max];
    }
    public static function revision(): array
    {
        return ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'];
    }
    public static function page(): array
    {
        return ['page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10000],
            'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]];
    }
    public static function listOf(array $items, int $max = 100): array
    {
        return ['type' => 'array', 'items' => $items, 'maxItems' => $max, 'uniqueItems' => true];
    }

    public static function error(string $message = '', int $status = 400): \WP_Error
    {
        return new \WP_Error('publishpress_planner_mcp', $message ?: __('Use the exact fields, types and choices returned by discovery.', 'publishpress'), ['status' => $status]);
    }

    public static function denied(): \WP_Error
    {
        return self::error(__('You do not have permission to use this Planner operation.', 'publishpress'), 403);
    }

    public static function hash($value): string
    {
        return hash_hmac('sha256', wp_json_encode($value), wp_salt('nonce'));
    }

    public static function validate($value, array $schema, string $path = 'input')
    {
        $type = $schema['type'] ?? null;
        $types = (array) $type;
        $matches = false;
        foreach ($types as $candidate) {
            $matches = $matches || ($candidate === 'object' && (is_object($value) || (is_array($value) && (!$value || !array_is_list($value)))))
            || ($candidate === 'array' && is_array($value) && array_is_list($value))
            || ($candidate === 'string' && is_string($value))
            || ($candidate === 'integer' && is_int($value))
            || ($candidate === 'boolean' && is_bool($value))
            || ($candidate === 'null' && $value === null)
            || ($candidate === 'number' && (is_int($value) || is_float($value)));
        }
        if (!$matches) {
            return self::error(sprintf(__('Invalid JSON type at %s.', 'publishpress'), $path));
        }
        if (in_array('object', $types, true) && (is_array($value) || is_object($value))) {
            $value = (array) $value;
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) {
                    return self::error();
                }
            }
            if (($schema['additionalProperties'] ?? true) === false && array_diff(array_keys($value), array_keys($schema['properties'] ?? []))) {
                return self::error();
            }
            foreach ($value as $key => &$child) {
                if (isset($schema['properties'][$key]) || is_array($schema['additionalProperties'] ?? null)) {
                    $child = self::validate($child, $schema['properties'][$key] ?? $schema['additionalProperties'], $path . '.' . $key);
                    if (is_wp_error($child)) {
                        return $child;
                    }
                }
            }
            unset($child);
        } elseif (in_array('array', $types, true) && is_array($value)) {
            foreach ($value as $key => &$child) {
                $child = self::validate($child, $schema['items'], $path . '[' . $key . ']');
                if (is_wp_error($child)) {
                    return $child;
                }
            }
            unset($child);
        }
        $check = rest_validate_value_from_schema($value, $schema, $path);
        return is_wp_error($check) ? $check : $value;
    }

    private static function output(string $name): array
    {
        $text = ['type' => 'string'];
        $integer = ['type' => 'integer'];
        $bool = ['type' => 'boolean'];
        $object = ['type' => 'object'];
        $rows = ['type' => 'array', 'items' => ['type' => 'object']];
        $strings = ['type' => 'array', 'items' => $text];
        $ids = ['type' => 'array', 'items' => $integer];
        $properties = array_fill_keys(['module', 'view', 'kind', 'step', 'title', 'post_type', 'status', 'date', 'date_gmt', 'content', 'excerpt', 'operation', 'filename', 'mime_type', 'timezone', 'error', 'outcome'], $text)
        + array_fill_keys(['id', 'post_id', 'user_id', 'author', 'count', 'failed_id', 'failed_index', 'size', 'modified', 'version'], $integer)
        + array_fill_keys(['dry_run', 'complete', 'has_more', 'enabled', 'pro_active', 'exists', 'cleared', 'rotated', 'deleted', 'sent', 'configured'], $bool)
        + array_fill_keys(['settings', 'schema', 'fields', 'values', 'stored', 'configuration', 'defaults', 'terms', 'choices', 'custom_labels', 'filters', 'changes', 'counts', 'object'], $object)
        + array_fill_keys(['features', 'post_types', 'statuses', 'preferences'], $rows)
        + array_fill_keys(['channels', 'roles', 'emails'], $strings)
        + array_fill_keys(['users', 'groups', 'attachment_ids', 'ids', 'applied_ids', 'saved_ids', 'completed_ids'], $ids);
        $properties['expected_hash'] = self::revision();
        $properties['completed'] = ['type' => 'array', 'items' => ['type' => ['boolean', 'string', 'integer']]];
        $properties['items'] = ['type' => 'array', 'items' => ['type' => ['object', 'string', 'integer']]];
        $properties['modified'] = ['type' => ['integer', 'string']];
        $groups = [
            'list-features' => 'features pro_active',
            'get-plugin-settings' => 'module settings schema enabled expected_hash dry_run',
            'update-plugin-settings' => 'module settings schema enabled expected_hash dry_run',
            'set-feature-enabled' => 'module enabled expected_hash dry_run',
            'get-workflow-fields' => 'schema defaults pro_active channels',
            'get-workflow' => 'id title status configuration expected_hash dry_run',
            'save-workflow' => 'id title status configuration expected_hash dry_run',
            'delete-workflow' => 'id operation dry_run',
            'export-workflows' => 'version items',
            'import-workflows' => 'dry_run count saved_ids failed_index complete error object',
            'get-content' => 'id post_type title content excerpt status date date_gmt author terms expected_hash',
            'save-content' => 'id post_type title content excerpt status date date_gmt author terms expected_hash dry_run changes',
            'move-board-item' => 'id post_type title content excerpt status date date_gmt author terms expected_hash dry_run changes',
            'move-calendar-items' => 'items dry_run applied_ids failed_id complete outcome',
            'get-content-choices' => 'post_types statuses pro_active timezone',
            'export-calendar' => 'filename mime_type content count',
            'save-editorial-field' => 'id fields expected_hash dry_run',
            'delete-editorial-field' => 'id deleted dry_run',
            'reorder-editorial-fields' => 'ids dry_run',
            'get-editorial-values' => 'post_id values schema stored expected_hash dry_run',
            'update-editorial-values' => 'post_id values schema stored expected_hash dry_run',
            'save-editorial-comment' => 'id post_id content attachment_ids expected_hash dry_run',
            'delete-editorial-comment' => 'id deleted dry_run',
            'get-post-followers' => 'post_id users roles groups emails expected_hash',
            'update-post-followers' => 'post_id users roles groups emails expected_hash dry_run',
            'get-notification-preferences' => 'user_id preferences has_more channels expected_hash',
            'update-notification-preferences' => 'user_id preferences has_more channels expected_hash dry_run',
            'manage-notification-logs' => 'ids dry_run completed_ids failed_id complete',
            'get-view-configuration' => 'view kind items choices custom_labels expected_hash',
            'save-view-configuration' => 'view kind items choices custom_labels expected_hash dry_run',
            'get-view-preferences' => 'view filters schema expected_hash',
            'get-view-item' => 'id view fields',
            'save-view-preferences' => 'view filters schema expected_hash dry_run',
            'get-dashboard-note' => 'id content modified expected_hash',
            'save-dashboard-note' => 'id content modified expected_hash dry_run',
            'get-diagnostics' => 'exists size modified expected_hash',
            'clear-debug-log' => 'cleared dry_run',
            'rotate-calendar-feed-key' => 'rotated dry_run',
            'get-migration-status' => 'step counts expected_hash completed',
            'run-migration-step' => 'step counts expected_hash completed dry_run',
            'test-slack-connection' => 'sent configured dry_run',
        ];
        $keys = explode(' ', $groups[$name] ?? ($name === 'list-editorial-fields' ? 'items has_more schema' : 'items has_more'));
        return self::object(array_intersect_key($properties, array_flip($keys)));
    }

    public static function add(string $name, string $label, string $description, array $schema, callable $callback, callable $permission, bool $write = false, bool $destructive = false): void
    {
        wp_register_ability('publishpress-planner/' . $name, [
            'label' => $label, 'description' => $description, 'category' => 'publishpress',
            'input_schema' => $schema, 'output_schema' => self::output($name),
            'permission_callback' => static function ($input) use ($permission, $schema) {

                $input = self::validate($input, $schema);
                return is_wp_error($input) ? $input : ($permission($input) ? true : self::denied());
            },
            'execute_callback' => static function ($input) use ($callback, $permission, $schema) {

                $input = self::validate($input, $schema);
                if (is_wp_error($input)) {
                    return $input;
                }
                if (!$permission($input)) {
                    return self::denied();
                }
                return self::executeNative($callback, $input);
            },
            'meta' => ['public' => false, 'show_in_rest' => false, 'mcp' => ['public' => false],
                'annotations' => ['readonly' => !$write, 'destructive' => $destructive, 'idempotent' => !$write]],
        ]);
    }

    private static function executeNative(callable $callback, array $input)
    {
        $saved = [];
        foreach (['post', 'id', 'authordata', 'currentday', 'currentmonth', 'pages', 'page', 'multipage', 'more', 'numpages'] as $key) {
            $saved[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        try {
            return $callback($input);
        } catch (\Throwable $error) {
            return self::error(__('Planner could not complete this operation. Inspect the current state before retrying; some changes may have been saved.', 'publishpress'), 500);
        } finally {
            foreach ($saved as $key => $value) {
                if ($value[0]) {
                    $GLOBALS[$key] = $value[1];
                } else {
                    unset($GLOBALS[$key]);
                }
            }
        }
    }

    public static function module(string $name, bool $enabled = true)
    {
        $planner = PublishPress();
        if (!isset($planner->modules->$name, $planner->$name)) {
            return null;
        }
        $module = $planner->modules->$name;
        return (!$enabled || ($module->options->enabled ?? 'off') === 'on') ? $planner->$name : null;
    }

    public static function pro(): bool
    {
        return \PublishPress\Legacy\Util::isPlannersProActive();
    }

    public static function stale(array $input, $state)
    {
        return isset($input['expected_hash']) && hash_equals(self::hash($state), $input['expected_hash'])
        ? true : self::error(__('This object changed. Read it again before saving.', 'publishpress'), 409);
    }

    public static function unconfirmed(): \WP_Error
    {
        return self::error(__('The operation could not be fully confirmed. Read the object before retrying; some changes may have been saved.', 'publishpress'), 500);
    }

    public static function saveOptions(string $module, array $values)
    {
        $planner = PublishPress();
        $key = $planner->options_group . $module . '_options';
        $planner->update_all_module_options($module, $values);
        $stored = (array) get_option($key);
        if (self::hash($stored) === self::hash($values)) {
            return true;
        }
        // Never overwrite a concurrent native save when read-back is different.
        $planner->modules->$module->options = (object) $stored;
        $planner->$module->module = $planner->modules->$module;
        return self::unconfirmed();
    }
}
