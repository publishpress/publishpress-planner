<?php

/** Typed workflow configuration derived from the active native workflow step objects. */

namespace PublishPress\Core\Mcp;

use PublishPress\Notifications\Workflow\Step\Event\Base as Event;
use PublishPress\Notifications\Workflow\Step\Receiver\Simple_Checkbox as Receiver;
use PublishPress\Notifications\Workflow\Step\Content\Main as ContentStep;
use PublishPress\Notifications\Workflow\Option\OptionCheckboxAbstract as Option;

defined('ABSPATH') || exit;
class WorkflowFields
{
    public static function definitions(): array
    {
        $module = Registry::module('improved_notifications');
        if (!$module) {
            return [];
        }
        $controller = $module->get_service('workflows_controller');
        $definitions = [];
        foreach ($controller->getConfigurationSteps() as $step) {
            if (strpos(get_class($step), 'PublishPress\\Addon\\Reminders\\') === 0 && (!Registry::pro() || !Registry::module('reminders'))) {
                continue;
            }
            if (strpos(get_class($step), 'PublishPress\\Addon\\') === 0 && !Registry::pro()) {
                continue;
            }

            if ($step instanceof Option && is_callable([$step, 'save_values'])) {
                $native = $step->getConfigurationDefinition();
                $name = str_replace('_psppno_option_', '', $native['name']);
                $definitions[$name] = ['schema' => ['type' => 'boolean', 'title' => $native['label']],
                    'fields' => ['value' => ['meta' => $native['name'], 'schema' => ['type' => 'boolean'], 'path' => [$native['field']]]],
                    'step' => $step, 'option' => true];
                continue;
            }
            if (!is_callable([$step, 'getConfigurationDefinition']) || !is_callable([$step, 'save_values'])) {
                continue;
            }
            $native = $step->getConfigurationDefinition();
            $name = $native['prefix'] . '_' . $native['name'];
            $fields = [];
            if ($step instanceof Event) {
                $fields['enabled'] = ['meta' => $step::META_KEY_SELECTED, 'schema' => ['type' => 'boolean'],
                'path' => ['publishpress_notif', $native['prefix']], 'selection' => $step::META_VALUE_SELECTED];
                foreach ($native['filters'] as $filter) {
                    $fields = array_merge($fields, self::filterFields($filter, $name));
                }
            } elseif ($step instanceof Receiver) {
                $fields['enabled'] = ['meta' => $step::META_KEY, 'schema' => ['type' => 'boolean'],
                'path' => ['publishpress_notif', $native['option_name']], 'selection' => $step::META_VALUE];
                if (defined(get_class($step) . '::META_LIST_KEY')) {
                    $kind = $native['name'];
                    $schema = $kind === 'role'
                    ? Registry::listOf(['type' => 'string', 'enum' => array_keys(apply_filters('editable_roles', wp_roles()->roles))])
                    : Registry::listOf(Registry::id());
                    $fields[$kind === 'role' ? 'roles' : ($kind === 'group' ? 'groups' : 'users')] = [
                        'meta' => $step::META_LIST_KEY, 'multiple' => true, 'schema' => $schema,
                        'path' => ['publishpress_notif', 'receiver_' . $kind], 'reference' => $kind];
                }
            } elseif ($step instanceof ContentStep) {
                $fields['subject'] = ['meta' => $step::META_KEY_SUBJECT, 'schema' => Registry::text(1000), 'path' => ['publishpress_notif', 'content_main', 'subject']];
                $fields['body'] = ['meta' => $step::META_KEY_BODY, 'schema' => Registry::text(200000), 'path' => ['publishpress_notif', 'content_main', 'body']];
            }
            if (!$fields) {
                continue;
            }
            $definitions[$name] = ['schema' => Registry::object(array_map(static fn($field) => $field['schema'], $fields)),
                'label' => $native['label'], 'fields' => $fields, 'step' => $step, 'option' => false];
        }
        return $definitions;
    }

    private static function filterFields($filter, string $name): array
    {
        $class = get_class($filter);
        $fields = [];
        $path = ['publishpress_notif', $name . '_filters'];
        $statuses = [];
        foreach (['from', 'to'] as $group) {
            $native = apply_filters('publishpress_notification_statuses', PublishPress()->getPostStatuses(), $group);
            $statuses[$group] = array_values(array_unique(array_merge(array_map(static fn($status) => $status->slug, $native), $group === 'from' ? ['new', 'auto-draft', 'trash'] : ['trash'])));
        }
        $add = static function ($property, $constant, $schema, $form, $reference = null, $single = false) use (&$fields, $class, $path) {

            if (!defined($class . '::' . $constant)) {
                return;
            }
            $fields[$property] = ['meta' => constant($class . '::' . $constant), 'schema' => $schema,
                'multiple' => true, 'single_list' => $single, 'path' => array_merge($path, (array) $form), 'reference' => $reference];
        };
        $list = Registry::listOf(['type' => 'string', 'enum' => $statuses['to']]);
        $add('from', 'META_KEY_POST_STATUS_FROM', Registry::listOf(['type' => 'string', 'enum' => $statuses['from']]), ['post_status', 'from']);
        $add('to', 'META_KEY_POST_STATUS_TO', $list, ['post_status', 'to']);
        $add('taxonomies', 'META_KEY_TAXONOMIES_FROM', Registry::listOf(['type' => 'string', 'enum' => array_keys(\PP_Notifications::getWorkflowTaxonomies())]), 'taxonomies', 'taxonomy');
        $add('post_types', 'META_KEY_POST_TYPE', Registry::listOf(['type' => 'string', 'enum' => array_keys(Registry::module('improved_notifications')->get_all_post_types(Registry::module('notifications')->module))]), 'post_type', 'post_type');
        $add('categories', 'META_KEY_CATEGORY', Registry::listOf(Registry::text(200)), 'category', 'category');
        $add('terms', 'META_KEY_TERM', Registry::listOf(Registry::id()), 'term', 'term');
        if (Registry::pro() && Registry::module('reminders')) {
            $add('amount', 'META_KEY_POST_STATUS_AMOUNT', ['type' => 'integer', 'minimum' => 1, 'maximum' => 60], ['timer', 'amount'], null, true);
            $add('unit', 'META_KEY_POST_STATUS_UNIT', ['type' => 'string', 'enum' => ['hour', 'day', 'week', 'month']], ['timer', 'unit'], null, true);
            if ($name === 'event_beforepublishing') {
                $choices = apply_filters('publishpress_reminder_statuses', ['draft' => '', 'pending' => '', 'future' => '']);
                $add('status', 'META_KEY_POST_STATUS', ['type' => 'string', 'enum' => array_keys($choices)], ['timer', 'post_status'], null, true);
            }
        }
        return $fields;
    }

    public static function read(int $id, array $definitions): array
    {
        $values = [];
        foreach ($definitions as $name => $definition) {
            $fields = [];
            foreach ($definition['fields'] as $key => $field) {
                $value = $id ? get_post_meta($id, $field['meta'], empty($field['multiple'])) : (empty($field['multiple']) ? '' : []);
                $type = $field['schema']['type'];
                if (!empty($field['single_list'])) {
                    $value = is_array($value) ? ($value[0] ?? null) : null;
                    $value = $value ?? ($key === 'amount' ? 1 : ($key === 'unit' ? 'hour' : 'future'));
                }
                if ($type === 'boolean') {
                    $value = in_array($value, [1, '1', true], true);
                } elseif ($type === 'integer') {
                    $value = (int) $value;
                } elseif ($type === 'array') {
                    $value = array_values((array) $value);
                    $value = array_map(($field['schema']['items']['type'] ?? '') === 'integer' ? 'intval' : 'strval', $value);
                } else {
                    $value = (string) $value;
                }
                $fields[$key] = $value;
            }
            $values[$name] = $definition['option'] ? $fields['value'] : $fields;
        }
        return $values;
    }

    public static function raw(int $id, array $definitions): array
    {
        $raw = [];
        foreach ($definitions as $definition) {
            foreach ($definition['fields'] as $field) {
                $raw[$field['meta']] = get_post_meta($id, $field['meta']);
            }
        }
        return $raw;
    }

    public static function validateTemplate(array $content)
    {
        $allowed = ['psppno_actor', 'psppno_post', 'psppno_workflow', 'psppno_edcomment', 'psppno_receiver'];
        foreach ($content as $text) {
            preg_match_all('/\\[\\/*([^\\x00-\\x20<>&\\/\\[\\]=]+)([^\\]]*)\\]/', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                if (!in_array($match[1], $allowed, true)) {
                    return Registry::error(__('Use Planner notification placeholders only.', 'publishpress'));
                }
                $attributes = shortcode_parse_atts($match[2]);
                foreach ((array) $attributes as $attribute) {
                    if (preg_match('/password|passwd|secret|token|nonce|credential|session|license|api.?key|meta-(?:relationship|post):/i', (string) $attribute)) {
                        return Registry::error(__('This placeholder can expose protected data.', 'publishpress'));
                    }
                    if (preg_match('/^meta[^:]*:([^.:]+)/', (string) $attribute, $meta) && !Display::safeKey($meta[1])) {
                        return Registry::error(__('Protected metadata placeholders are unavailable through MCP.', 'publishpress'));
                    }
                }
            }
        }
        return true;
    }

    public static function validateReferences(array $values, array $definitions)
    {
        foreach ($values as $name => $value) {
            $definition = $definitions[$name];
            if ($name === 'receiver_network_admin' && !current_user_can('manage_network_options')) {
                return Registry::denied();
            }
            if ($name === 'content_main') {
                if (isset($value['subject'])) {
                    $values[$name]['subject'] = sanitize_text_field($value['subject']);
                }
                if (isset($value['body'])) {
                    $values[$name]['body'] = wp_kses_post($value['body']);
                }
            }
            if ($definition['option']) {
                continue;
            }
            foreach ($definition['fields'] as $property => $field) {
                if (!array_key_exists($property, $value) || empty($field['reference'])) {
                    continue;
                }
                foreach ($value[$property] as $reference) {
                    if ($field['reference'] === 'user' && (!get_userdata($reference) || !is_user_member_of_blog($reference))) {
                        return Registry::error();
                    }
                    if ($field['reference'] === 'group') {
                        if (!class_exists('PublishPress\\Permissions\\API') || !current_user_can('pp_manage_settings')) {
                            return Registry::denied();
                        }
                        $groups = \PublishPress\Permissions\API::getGroups('pp_group', ['include_metagroups' => false]);
                        if (!isset($groups[$reference])) {
                            return Registry::error();
                        }
                    }
                    if (in_array($field['reference'], ['term', 'category'], true)) {
                        $term = $field['reference'] === 'category' ? get_term_by('slug', $reference, 'category') : get_term($reference);
                        if (!$term || is_wp_error($term)) {
                            return Registry::error();
                        }
                        $taxonomy = get_taxonomy($term->taxonomy);
                        if (!$taxonomy || in_array($term->taxonomy, array_filter(array_map('trim', explode(',', (string) (Registry::module('notifications')->module->options->blacklisted_taxonomies ?? '')))), true) || !current_user_can($taxonomy->cap->assign_terms) || !current_user_can('assign_term', $term->term_id)) {
                            return Registry::denied();
                        }
                    }
                }
            }
        }
        return $values;
    }

    public static function merge(array $values, array $patch, array $definitions)
    {
        $byMeta = [];
        foreach ($patch as $name => $value) {
            foreach ($definitions[$name]['fields'] as $property => $field) {
                if (!$definitions[$name]['option'] && !array_key_exists($property, $value)) {
                    continue;
                }
                $next = $definitions[$name]['option'] ? $value : $value[$property];
                if (array_key_exists($field['meta'], $byMeta) && $byMeta[$field['meta']] !== $next) {
                    return Registry::error(__('Conflicting values for the same native workflow field.', 'publishpress'));
                }
                $byMeta[$field['meta']] = $next;
            }
        }
        foreach ($definitions as $name => $definition) {
            foreach ($definition['fields'] as $property => $field) {
                if (!array_key_exists($field['meta'], $byMeta)) {
                    continue;
                }
                if ($definition['option']) {
                    $values[$name] = $byMeta[$field['meta']];
                } else {
                    $values[$name][$property] = $byMeta[$field['meta']];
                }
            }
        }
        return $values;
    }

    private static function path(array &$data, array $path, $value, bool $append = false): void
    {
        $cursor =& $data;
        $last = array_pop($path);
        foreach ($path as $key) {
            if (!isset($cursor[$key])) {
                $cursor[$key] = [];
            }
            $cursor =& $cursor[$key];
        }
        if ($append) {
            $cursor[$last][] = $value;
        } else {
            $cursor[$last] = $value;
        }
    }

    public static function save(int $id, array $values, array $definitions, array $changed): void
    {
        $data = ['publishpress_notif' => []];
        foreach ($definitions as $name => $definition) {
            foreach ($definition['fields'] as $property => $field) {
                $value = $definition['option'] ? $values[$name] : $values[$name][$property];
                if (($field['schema']['type'] ?? '') === 'boolean' && isset($field['selection'])) {
                    if (!$value) {
                        continue;
                    }
                    self::path($data, $field['path'], $field['selection'], $field['path'][1] === 'event' || $field['path'][1] === 'event_content');
                } else {
                    self::path($data, $field['path'], $definition['option'] ? ($value ? '1' : '0') : $value);
                }
            }
        }
        $post = get_post($id);
        foreach ($changed as $name) {
            $definitions[$name]['step']->save_values($id, $post, $data);
        }
    }
}
