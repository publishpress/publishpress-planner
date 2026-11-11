<?php

/** Native Calendar, Board and Overview column/filter configuration. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;
class Display
{
    public static function register(): void
    {
        $view = ['type' => 'string', 'enum' => ['calendar', 'content_board', 'content_overview']];
        $access = static fn($i) => Content::allowed($i['view']);
        Registry::add(
            'get-view-item',
            __('Read native Planner display values', 'publishpress'),
            __('Read the selected post’s native Calendar information or configured Board/Overview column values as plain text. Uses active dependency renderers and native view/object permissions. Editorial fields require their own viewing capability; protected keys, structured custom metadata, links and form credentials are withheld.', 'publishpress'),
            Registry::object(['view' => $view, 'id' => Registry::id()], ['view', 'id']),
            [self::class, 'item'],
            $access
        );
        Registry::add(
            'get-view-preferences',
            __('Read your Planner view filters', 'publishpress'),
            __('Discover the native filter values and exact input schema for your own Calendar, Board or Overview. Includes active editorial fields, taxonomies and Pro integrations; no other user preferences are read.', 'publishpress'),
            Registry::object(['view' => $view], ['view']),
            [self::class, 'preferences'],
            $access
        );
        Registry::add(
            'save-view-preferences',
            __('Save your Planner view filters', 'publishpress'),
            __('Patch your own native view filter values using expected_hash and discovery. Native date, keyword, taxonomy and integration sanitizers apply. Omitted filters remain. dry_run does not save.', 'publishpress'),
            Registry::object(['view' => $view, 'filters' => ['type' => 'object', 'minProperties' => 1], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['view', 'filters', 'expected_hash']),
            [self::class, 'savePreferences'],
            $access,
            true
        );
        Registry::add(
            'list-view-content',
            __('Find content using native Planner filters', 'publishpress'),
            __('Query a bounded native Planner view using your saved filters or a typed filter patch. Uses the native Calendar/Board/Overview query, including active editorial metadata, taxonomy, Checklists, SEO and Revisions integration filters. Each returned object requires read/edit permission. Does not change saved preferences.', 'publishpress'),
            Registry::object(Registry::page() + ['view' => $view, 'filters' => ['type' => 'object']], ['view']),
            [self::class, 'find'],
            $access
        );
        $selector = ['view' => ['type' => 'string', 'enum' => ['calendar', 'content_board', 'content_overview']],
            'kind' => ['type' => 'string', 'enum' => ['columns', 'filters']]];
        $permission = static fn($i) => current_user_can('manage_options') && Content::allowed($i['view']);
        Registry::add(
            'get-view-configuration',
            __('Read Planner view configuration', 'publishpress'),
            __('Discover native ordered columns/filters, custom labels and active integration choices for Calendar, Board or Overview. Calendar supports filters only. Native Pro, Checklists, SEO and Revisions filters supply their own choices.', 'publishpress'),
            Registry::object($selector, ['view', 'kind']),
            [self::class, 'read'],
            $permission
        );
        Registry::add(
            'save-view-configuration',
            __('Configure Planner columns and filters', 'publishpress'),
            __('Replace one ordered list of native columns or filters using discovered identifiers and expected_hash. Custom metadata keys require explicit labels; credentials and protected security metadata are rejected. Other view settings remain unchanged. dry_run validates without writing.', 'publishpress'),
            Registry::object($selector + ['items' => Registry::listOf(Registry::text(200)),
                'custom_labels' => ['type' => 'object', 'maxProperties' => 100, 'additionalProperties' => Registry::text(200)],
                'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['view', 'kind', 'items', 'expected_hash']),
            [self::class, 'save'],
            $permission,
            true
        );
        Registry::add(
            'search-view-metadata',
            __('Find custom view metadata keys', 'publishpress'),
            __('Search up to 20 existing metadata identifiers through native Planner discovery. No values are exposed; protected/security keys are withheld.', 'publishpress'),
            Registry::object(['search' => Registry::text(100)], ['search']),
            [self::class, 'search'],
            static fn() => current_user_can('manage_options')
        );
    }

    private static function view(string $view)
    {
        if (!Content::allowed($view)) {
            return null;
        }
        $module = Registry::module($view);
        $prefix = $view === 'calendar' ? 'content_calendar' : $view;
        $method = 'get_' . $prefix . '_datas';
        $data = $module->$method();
        $module->filters = apply_filters('publishpress_' . $prefix . '_filters', (array) $data[$prefix . '_filters']);
        if (!current_user_can(apply_filters('publishpress_view_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_VIEW_METADATA))) {
            $module->filters = array_diff_key($module->filters, (array) ($data['editorial_metadata'] ?? []));
        }
        $form = 'get_' . $prefix . '_form_filters';
        $module->form_filters = $module->$form();
        $module->form_filter_list = [];
        foreach ($module->form_filters as $group) {
            $module->form_filter_list += (array) ($group['filters'] ?? []);
        }
        return $module;
    }

    public static function item(array $input)
    {
        $post = get_post($input['id']);
        if (!Content::permitted($post, $input['view'])) {
            return Registry::denied();
        }
        $module = self::view($input['view']);
        $fields = [];
        if ($input['view'] === 'calendar') {
            foreach ($module->get_post_information_fields($post) as $key => $field) {
                if (!self::safeKey((string) $key)) {
                    continue;
                }
                $fields[$key] = ['label' => wp_strip_all_tags($field['label']), 'value' => self::plain($field['value'])];
            }
        } else {
            $get = 'get_' . $input['view'] . '_datas';
            $data = $module->$get();
            foreach ((array) $data[$input['view'] . '_columns'] as $key => $label) {
                if (!self::safeKey((string) $key)) {
                    continue;
                }
                if (isset($data['editorial_metadata'][$key]) && !current_user_can(apply_filters('publishpress_view_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_VIEW_METADATA))) {
                    continue;
                }
                if (isset($data[$input['view'] . '_custom_columns'][$key])) {
                    if (!current_user_can('edit_post_meta', $post->ID, $key) || !is_scalar(get_post_meta($post->ID, $key, true))) {
                        continue;
                    }
                }
                $fields[$key] = ['label' => wp_strip_all_tags($label), 'value' => self::plain($module->column_default($post, $key))];
            }
        }
        return ['id' => $post->ID, 'view' => $input['view'], 'fields' => (object) $fields];
    }

    private static function plain($value): string
    {
        return html_entity_decode(wp_strip_all_tags(is_scalar($value) ? (string) $value : wp_json_encode($value)), ENT_QUOTES, 'UTF-8');
    }

    public static function preferences(array $input)
    {
        $module = self::view($input['view']);
        if (!$module) {
            return Registry::denied();
        }
        $fields = ['start_date' => ['type' => 'string', 'pattern' => '^(|\\d{4}-\\d{2}-\\d{2})$'],
            'me_mode' => ['type' => 'boolean'], 'hide_revision' => ['type' => 'boolean']];
        if ($input['view'] === 'calendar') {
            $fields['weeks'] = ['type' => 'integer', 'minimum' => 1, 'maximum' => 12];
            $fields['s'] = Registry::text(200);
        } else {
            $fields['end_date'] = $fields['start_date'];
        }
        $metadata = [];
        if (Registry::module('editorial_metadata')) {
            foreach (Registry::module('editorial_metadata')->get_editorial_metadata_terms() as $term) {
                $metadata[$term->slug] = $term;
            }
        }
        foreach ($module->filters as $key => $label) {
            if (!self::safeKey((string) $key)) {
                continue;
            }
            if (isset($metadata[$key])) {
                $term = $metadata[$key];
                if ($term->type === 'date') {
                    foreach (['_start', '_end'] as $suffix) {
                        $fields[$key . $suffix] = ['type' => ['string', 'null'], 'pattern' => '^\\d{4}-\\d{2}-\\d{2} [0-2][0-9]:[0-5][0-9]$'];
                    }
                } elseif ($term->type === 'checkbox') {
                    $fields[$key] = ['type' => 'boolean'];
                } elseif (in_array($term->type, ['number', 'user'], true)) {
                    $fields[$key] = ['type' => ['integer', 'null'], 'minimum' => $term->type === 'user' ? 1 : -2147483647, 'maximum' => 2147483647];
                } elseif ($term->type === 'select') {
                    $fields[$key] = ['type' => ['string', 'null'], 'enum' => array_merge([null, ''], (array) ($term->select_options['values'] ?? []))];
                } else {
                    $fields[$key] = ['type' => ['string', 'null'], 'maxLength' => 1000];
                }
            } elseif ($key === 'author') {
                $fields[$key] = ['type' => 'integer', 'minimum' => 0];
            } elseif (in_array($key, ['ptype', 'cpt'], true)) {
                $fields[$key] = ['type' => 'string', 'enum' => array_merge([''], Content::types($input['view']))];
            } elseif ($key === 'post_status') {
                $fields[$key] = ['type' => 'string', 'enum' => array_merge(['', 'unpublish'], array_keys(get_post_stati(['internal' => false])))];
            } elseif (strpos($key, 'ppch_co_checklist_') === 0) {
                $fields[$key] = ['type' => 'string', 'enum' => ['', 'passed', 'failed']];
            } else {
                $fields[$key] = Registry::text(1000);
                foreach (['_start', '_end', '_start_hidden', '_end_hidden'] as $suffix) {
                    $fields[$key . $suffix] = Registry::text(100);
                }
                $fields[$key . '_operator'] = ['type' => 'string', 'enum' => array_keys($module->meta_query_operator_label())];
            }
        }
        $raw = (array) $module->get_user_meta(get_current_user_id(), $module::USERMETA_KEY_PREFIX . 'filters', true);
        $values = [];
        foreach ($fields as $key => $field) {
            $types = (array) $field['type'];
            $value = $raw[$key . '_hidden'] ?? ($raw[$key] ?? ($key === 'weeks' ? \PP_Calendar::DEFAULT_NUM_WEEKS : ''));
            if (in_array('boolean', $types, true)) {
                $value = !empty($value);
            } elseif (in_array('integer', $types, true)) {
                $value = ($value === '' || $value === null) && in_array('null', $types, true) ? null : (int) $value;
            } else {
                $value = ($value === '' || $value === null) && in_array('null', $types, true) ? null : (string) $value;
            }
            $values[$key] = $value;
        }
        return ['view' => $input['view'], 'filters' => (object) $values, 'schema' => Registry::object($fields), 'expected_hash' => Registry::hash($raw)];
    }

    private static function prepare(array $input)
    {
        $before = self::preferences($input);
        if (is_wp_error($before)) {
            return $before;
        }
        $patch = Registry::validate($input['filters'] ?? [], $before['schema']);
        if (is_wp_error($patch)) {
            return $patch;
        }
        foreach (['start_date', 'end_date'] as $key) {
            if (!empty($patch[$key]) && !Content::validDate($patch[$key])) {
                return Registry::error();
            }
        }
        $values = array_merge((array) $before['filters'], $patch);
        if (!empty($values['start_date']) && !empty($values['end_date']) && $values['start_date'] > $values['end_date']) {
            return Registry::error();
        }
        foreach ($patch as $key => $value) {
            if (taxonomy_exists($key) && $value !== '') {
                $taxonomy = get_taxonomy($key);
                if (!current_user_can($taxonomy->cap->assign_terms) || !get_term_by('slug', $value, $key)) {
                    return Registry::error();
                }
            }
            if ($key === 'author' && $value && (!get_userdata($value) || !is_user_member_of_blog($value))) {
                return Registry::error();
            }
            if (substr($key, -6) === '_start' && $value !== null) {
                $endKey = substr($key, 0, -6) . '_end';
                if (!empty($values[$endKey]) && $value > $values[$endKey]) {
                    return Registry::error();
                }
            }
            if (substr($key, -4) === '_end' && $value !== null) {
                $startKey = substr($key, 0, -4) . '_start';
                if (!empty($values[$startKey]) && $values[$startKey] > $value) {
                    return Registry::error();
                }
            }
        }
        foreach ($values as $key => &$value) {
            $field = $before['schema']['properties'][$key];
            if (is_array($field['type']) && in_array('string', $field['type'], true) && isset($field['pattern']) && $value !== null) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
                if (!$date || $date->format('Y-m-d H:i') !== $value) {
                    return Registry::error();
                }
            } elseif ($value === null) {
                $value = '';
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '';
            } elseif (is_int($value)) {
                $value = (string) $value;
            }
        }
        unset($value);
        foreach ($before['schema']['properties'] as $key => $field) {
            if (is_array($field['type']) && isset($field['pattern'])) {
                $values[$key . '_hidden'] = $values[$key] ?? '';
            }
        }
        $module = self::view($input['view']);
        return $module->update_user_filters($values + ['mcp_request' => '1'], false);
    }

    public static function savePreferences(array $input)
    {
        $before = self::preferences($input);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($before['expected_hash'], $input['expected_hash'])) {
            return Registry::error(__('Your view filters changed. Read them again.', 'publishpress'), 409);
        }
        $values = self::prepare($input);
        if (is_wp_error($values)) {
            return $values;
        }
        if (!empty($input['dry_run'])) {
            return ['filters' => (object) $values, 'dry_run' => true];
        }
        $module = self::view($input['view']);
        $stored = (array) $module->get_user_meta(get_current_user_id(), $module::USERMETA_KEY_PREFIX . 'filters', true);
        $values = array_merge($stored, $values);
        $module->update_user_meta(get_current_user_id(), $module::USERMETA_KEY_PREFIX . 'filters', $values);
        return $module->get_user_meta(get_current_user_id(), $module::USERMETA_KEY_PREFIX . 'filters', true) == $values ? self::preferences($input) : Registry::unconfirmed();
    }

    public static function find(array $input)
    {
        $filters = self::prepare($input);
        if (is_wp_error($filters)) {
            return $filters;
        }
        $module = self::view($input['view']);
        $size = $input['per_page'] ?? 20;
        $args = ['required_operation' => 'edit', 'post_status' => $filters['post_status'] ?? '', 'author' => $filters['author'] ?? '', 'offset' => (($input['page'] ?? 1) - 1) * $size];
        if ($input['view'] === 'calendar') {
            $start = $filters['start_date'] ?: current_time('Y-m-d');
            $end = (new \DateTimeImmutable($start))->modify('+' . (($filters['weeks'] ?: \PP_Calendar::DEFAULT_NUM_WEEKS) * 7 - 1) . ' days')->format('Y-m-d');
            $args['date_query'] = ['column' => 'post_date', 'after' => $start . ' 00:00:00', 'before' => $end . ' 23:59:59', 'inclusive' => true];
            $data = $module->get_content_calendar_datas();
            $data['content_calendar_filters'] = $module->filters;
            $posts = $module->getCalendarMethods()->getCalendarDataForMultipleWeeks($args + $filters, 'mcp', [
                'content_calendar_datas' => $data, 'userFilters' => $filters, 'all_filters' => $module->filters,
                'terms_options' => $module->getCalendarMetadataOptions(), 'form_filter_list' => $module->filters, 'post_statuses' => $module->get_post_statuses(), 'operator_labels' => $module->meta_query_operator_label()], $size + 1);
            $posts = $posts ? array_merge(...array_values($posts)) : [];
        } else {
            $module->user_filters = $filters;
            $posts = $module->getPostsForPostType(($filters['ptype'] ?? '') ?: Content::types($input['view']), $args, $size + 1);
        }
        $items = [];
        foreach ($posts as $post) {
            if (Content::permitted($post, $input['view'])) {
                $items[] = ['id' => $post->ID, 'title' => $post->post_title, 'status' => $post->post_status];
            }
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($items) > $size];
    }

    public static function safeKey(string $key): bool
    {
        return $key !== '' && $key[0] !== '_' && !preg_match('/password|passwd|secret|token|nonce|credential|session|license|api.?key|capabilit|user_level/i', $key);
    }

    public static function search(array $input): array
    {
        return ['items' => array_values(array_filter(PublishPress()->findMetaKeys($input['search']), static fn($row) => self::safeKey($row['id'])))];
    }

    public static function read(array $input)
    {
        $view = $input['view'];
        $kind = $input['kind'];
        if (!Content::allowed($view) || !current_user_can('manage_options')) {
            return Registry::denied();
        }
        if ($view === 'calendar' && $kind !== 'filters') {
            return Registry::error();
        }
        $native = Registry::module($view);
        $prefix = $view === 'calendar' ? 'content_calendar' : $view;
// The native getters memoize data. Reset their public caches for this read.
        $native->{$prefix . '_datas'} = null;
        $native->{'form_' . $kind} = [];
        $getData = 'get_' . $prefix . '_datas';
        $data = $native->$getData();
        $getForm = 'get_' . $prefix . '_form_' . $kind;
        $groups = $native->$getForm();
        $choices = [];
        foreach ($groups as $group => $definition) {
            foreach ((array) ($definition[$kind] ?? []) as $key => $label) {
                if ($group !== 'custom' || self::safeKey((string) $key)) {
                    $choices[$key] = wp_strip_all_tags($label);
                }
            }
        }
        $items = (array) ($data[$prefix . '_' . $kind] ?? []);
        $custom = (array) ($data[$prefix . '_custom_' . $kind] ?? []);
        return ['view' => $view, 'kind' => $kind, 'items' => array_keys($items), 'choices' => (object) $choices,
            'custom_labels' => (object) $custom, 'expected_hash' => Registry::hash((array) $native->module->options)];
    }

    public static function save(array $input)
    {
        $before = self::read($input);
        if (is_wp_error($before)) {
            return $before;
        }
        $native = Registry::module($input['view']);
        $fresh = Registry::stale($input, (array) $native->module->options);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        $custom = isset($input['custom_labels']) ? (array) $input['custom_labels'] : (array) $before['custom_labels'];
        foreach ($custom as $key => $label) {
            if (!self::safeKey((string) $key) || $label === '' || sanitize_key($key) !== $key) {
                return Registry::error();
            }
            $custom[$key] = sanitize_text_field($label);
        }
        $choices = $custom + (array) $before['choices'];
        $items = [];
        foreach ($input['items'] as $key) {
            if (!array_key_exists($key, $choices)) {
                return Registry::error();
            }
            $items[$key] = $choices[$key];
        }
        if (!empty($input['dry_run'])) {
            return ['items' => array_keys($items), 'custom_labels' => (object) $custom, 'dry_run' => true];
        }
        $result = $native->save_view_configuration($input['kind'], $items, $custom);
        if (is_wp_error($result)) {
            return $result;
        }
        $after = self::read($input);
        return !is_wp_error($after) && $after['items'] === array_keys($items) && (array) $after['custom_labels'] === $custom ? $after : Registry::unconfirmed();
    }
}
