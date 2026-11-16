<?php

/** Native editorial field definitions and per-post values with scoped, prevalidated writes. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;

class Editorial
{
    public static function register(): void
    {
        Registry::add(
            'reorder-editorial-fields',
            __('Reorder all editorial fields', 'publishpress'),
            __('Replace the order of all native Editorial Fields, supplying every field ID and its current expected_hash from discovery. All definitions are validated before native position updates. dry_run does not write. A failed multi-field update reports confirmed IDs.', 'publishpress'),
            Registry::object(['items' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 200, 'items' => Registry::object(['id' => Registry::id(), 'expected_hash' => Registry::revision()], ['id', 'expected_hash'])], 'dry_run' => ['type' => 'boolean']], ['items']),
            [self::class, 'reorder'],
            static fn() => Registry::module('editorial_metadata') && current_user_can('manage_options') && current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA)),
            true
        );
        $read = static fn() => Registry::module('editorial_metadata')
            && current_user_can(apply_filters('publishpress_view_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_VIEW_METADATA));
        $manage = static fn() => Registry::module('editorial_metadata') && current_user_can('manage_options') && current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA));
        Registry::add(
            'list-editorial-fields',
            __('Discover editorial fields', 'publishpress'),
            __('Read native Editorial Fields, their exact definition schema, types, dropdown choices, post-type applicability and revisions. Includes checkbox, text, paragraph, number, date, location and user fields.', 'publishpress'),
            Registry::object(Registry::page()),
            [self::class, 'definitions'],
            $read
        );
        $definition = Registry::object(['id' => Registry::id(), 'fields' => ['type' => 'object', 'minProperties' => 1],
            'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['fields']);
        Registry::add(
            'save-editorial-field',
            __('Create or edit an editorial field', 'publishpress'),
            __('Create or patch a native Editorial Field definition using discovered fields and choices. Existing definitions require expected_hash; existing slug/type cannot be changed because native post values depend on them. Preserves unrelated fields. dry_run validates without creating terms.', 'publishpress'),
            $definition,
            [self::class, 'saveDefinition'],
            $manage,
            true
        );
        Registry::add(
            'delete-editorial-field',
            __('Delete an editorial field', 'publishpress'),
            __('Delete one native Editorial Field definition with its current revision. Uses native taxonomy deletion and cache refresh. Existing post metadata follows the native deletion behavior. dry_run checks access without deleting.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['id', 'expected_hash']),
            [self::class, 'deleteDefinition'],
            $manage,
            true,
            true
        );
        $post = ['post_id' => Registry::id()];
        Registry::add(
            'get-editorial-values',
            __('Read a post’s editorial fields', 'publishpress'),
            __('Read fields applicable to one editable post, their JSON schemas, native stored values and revision. Requires the filtered editorial-field view capability plus edit_post/read_post. Dates use the native YYYY-MM-DD HH:MM control format; empty values clear metadata.', 'publishpress'),
            Registry::object($post, ['post_id']),
            [self::class, 'values'],
            $read
        );
        Registry::add(
            'update-editorial-values',
            __('Update a post’s editorial fields', 'publishpress'),
            __('Patch only supplied native Editorial Field slugs using get-editorial-values discovery and expected_hash. All types, dates, user/role restrictions and dropdown choices are validated before writing. Omitted fields and term counts are preserved. Native zero/empty clearing semantics apply. dry_run previews without saving or firing field-update hooks.', 'publishpress'),
            Registry::object($post + ['values' => ['type' => 'object', 'minProperties' => 1], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['post_id', 'values', 'expected_hash']),
            [self::class, 'updateValues'],
            static fn() => Registry::module('editorial_metadata')
            && current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA)),
            true
        );
    }

    public static function reorder(array $input)
    {
        $module = Registry::module('editorial_metadata');
        $all = array_map(static fn($term) => (int) $term->term_id, $module->get_editorial_metadata_terms());
        $ids = [];
        foreach ($input['items'] as $row) {
            $term = $module->get_editorial_metadata_term_by('id', $row['id']);
            if (!$term || in_array($row['id'], $ids, true)) {
                return Registry::error();
            }
            $fresh = Registry::stale($row, (array) $term);
            if (is_wp_error($fresh)) {
                return $fresh;
            }
            $ids[] = $row['id'];
        }
        if (array_diff($all, $ids) || array_diff($ids, $all)) {
            return Registry::error(__('Supply every editorial field in the desired order.', 'publishpress'));
        }
        if (!empty($input['dry_run'])) {
            return ['ids' => $ids, 'dry_run' => true];
        }
        $result = $module->setTermPositions($ids);
        return is_wp_error($result) ? $result : ['ids' => $result, 'dry_run' => false];
    }

    public static function fields(): array
    {
        $module = Registry::module('editorial_metadata');
        $types = array_keys($module->get_supported_metadata_types());
        $roles = array_keys(wp_roles()->roles);
        $postTypes = array_keys($module->get_all_post_types());
        $text = Registry::text();
        return ['name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200], 'slug' => ['type' => 'string', 'pattern' => '^[a-z0-9_-]{1,100}$'],
            'description' => Registry::text(5000), 'type' => ['type' => 'string', 'enum' => $types],
            'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
            'user_role' => Registry::listOf(['type' => 'string', 'enum' => $roles]), 'select_type' => ['type' => 'string', 'enum' => ['', 'multiple']],
            'select_default' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 99], 'select_options' => Registry::object(['values' => Registry::listOf($text, 100),
                'labels' => ['type' => 'array', 'items' => $text, 'maxItems' => 100]], ['values', 'labels']),
            'show_in_calendar_form' => ['type' => 'boolean'],
            'post_types' => Registry::listOf(['type' => 'string', 'enum' => $postTypes]),
            'post_types_column' => Registry::listOf(['type' => 'string', 'enum' => $postTypes])];
    }

    public static function format($term): array
    {
        $values = [];
        foreach (self::fields() as $name => $schema) {
            $value = $name === 'description' ? ($term->description ?? '') : ($term->$name ?? ($schema['type'] === 'array' ? [] : ''));
            if ($schema['type'] === 'boolean') {
                $value = !empty($value);
            } elseif ($schema['type'] === 'integer') {
                $value = (int) $value;
            } elseif ($schema['type'] === 'array') {
                $value = is_array($value) ? array_values($value) : [];
            } elseif ($schema['type'] === 'object') {
                $value = (object) ['values' => array_values((array) ($value['values'] ?? [])), 'labels' => array_values((array) ($value['labels'] ?? []))];
            }
            if ($name === 'select_default') {
                $value = $value === '' || $value === null ? null : (int) $value;
            }
            if ($name === 'select_type' && $value !== 'multiple') {
                $value = '';
            }
            $values[$name] = $value;
        }
        return ['id' => (int) $term->term_id, 'fields' => (object) $values, 'expected_hash' => Registry::hash((array) $term)];
    }

    public static function definitions(array $input): array
    {
        $terms = Registry::module('editorial_metadata')->get_editorial_metadata_terms();
        $size = $input['per_page'] ?? 20;
        $offset = (($input['page'] ?? 1) - 1) * $size;
        return ['items' => array_map([self::class, 'format'], array_slice(array_values($terms), $offset, $size)),
            'has_more' => count($terms) > $offset + $size, 'schema' => Registry::object(self::fields())];
    }

    public static function saveDefinition(array $input)
    {
        $module = Registry::module('editorial_metadata');
        if (!$module || !current_user_can('manage_options') || !current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA))) {
            return Registry::denied();
        }
        $patch = Registry::validate($input['fields'], Registry::object(self::fields()));
        if (is_wp_error($patch)) {
            return $patch;
        }
        $term = isset($input['id']) ? $module->get_editorial_metadata_term_by('id', $input['id']) : null;
        if (isset($input['id']) && !$term) {
            return Registry::error(__('Editorial field not found.', 'publishpress'), 404);
        }
        if ($term) {
            $fresh = Registry::stale($input, (array) $term);
            if (is_wp_error($fresh)) {
                return $fresh;
            }
            foreach (['slug', 'type'] as $fixed) {
                if (isset($patch[$fixed]) && $patch[$fixed] !== $term->$fixed) {
                    return Registry::error(__('Existing field slug and type are fixed by the native data model.', 'publishpress'));
                }
            }
        }
        $fields = array_merge($term ? (array) self::format($term)['fields'] : [], $patch);
        if (empty(trim($fields['name'] ?? '')) || empty($fields['type'])) {
            return Registry::error();
        }
        if (!$term && !isset($fields['slug'])) {
            $fields['slug'] = sanitize_title($fields['name']);
        }
        foreach (['name', 'description'] as $key) {
            if (isset($fields[$key])) {
                $fields[$key] = sanitize_text_field($fields[$key]);
            }
        }
        foreach (['name', 'slug'] as $unique) {
            if (!isset($fields[$unique])) {
                continue;
            }
            $existing = $module->get_editorial_metadata_term_by($unique, $fields[$unique]);
            if ($existing && (!$term || (int) $existing->term_id !== (int) $term->term_id)) {
                return Registry::error(__('This editorial field name or slug is already in use.', 'publishpress'));
            }
        }
        if (isset($fields['select_options'])) {
            $choices = (array) $fields['select_options'];
            if (count($choices['values']) !== count($choices['labels']) || count(array_filter($choices['values'])) !== count($choices['values']) || count(array_filter($choices['labels'])) !== count($choices['labels'])) {
                return Registry::error();
            }
            $fields['select_options'] = ['values' => array_map('sanitize_text_field', $choices['values']), 'labels' => array_map('sanitize_text_field', $choices['labels'])];
            if (isset($fields['select_default']) && $fields['select_default'] >= count($choices['values'])) {
                return Registry::error();
            }
        }
        if (!empty($input['dry_run'])) {
            return ['id' => $term ? (int) $term->term_id : 0, 'fields' => (object) $fields, 'dry_run' => true];
        }
        if (array_key_exists('select_default', $fields)) {
            $fields['select_default'] = $fields['select_default'] === null ? '' : (string) $fields['select_default'];
        }
        $result = $term ? $module->update_editorial_metadata_term($term->term_id, $fields) : $module->insert_editorial_metadata_term($fields);
        if (is_wp_error($result) || !$result) {
            return is_wp_error($result) ? $result : Registry::unconfirmed();
        }
        if (is_array($result)) {
            $result = $module->get_editorial_metadata_term_by('id', $result['term_id']);
        }
        return $result ? self::format($result) + ['dry_run' => false] : Registry::unconfirmed();
    }

    public static function deleteDefinition(array $input)
    {
        $module = Registry::module('editorial_metadata');
        if (!$module || !current_user_can('manage_options') || !current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA))) {
            return Registry::denied();
        }
        $term = $module->get_editorial_metadata_term_by('id', $input['id']);
        if (!$term) {
            return Registry::error();
        }
        $fresh = Registry::stale($input, (array) $term);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        if (empty($input['dry_run'])) {
            $result = $module->delete_editorial_metadata_term($term->term_id);
            if (is_wp_error($result) || $module->get_editorial_metadata_term_by('id', $term->term_id)) {
                return Registry::unconfirmed();
            }
        }
        return ['id' => $input['id'], 'deleted' => empty($input['dry_run']), 'dry_run' => !empty($input['dry_run'])];
    }

    public static function valueSchema($term): array
    {
        if ($term->type === 'checkbox') {
            return ['type' => 'boolean'];
        }
        if (in_array($term->type, ['number', 'user'], true)) {
            return ['type' => ['integer', 'null'], 'minimum' => $term->type === 'user' ? 1 : -2147483647, 'maximum' => 2147483647];
        }
        if ($term->type === 'date') {
            return ['type' => ['string', 'null'], 'pattern' => '^\\d{4}-\\d{2}-\\d{2} [0-2][0-9]:[0-5][0-9]$'];
        }
        if ($term->type === 'select') {
            $choices = array_map('strval', (array) ($term->select_options['values'] ?? []));
            return ($term->select_type ?? 'single') === 'multiple'
            ? Registry::listOf(['type' => 'string', 'enum' => $choices])
            : ['type' => ['string', 'null'], 'enum' => array_merge([null, ''], $choices)];
        }
        return ['type' => ['string', 'null'], 'maxLength' => 50000];
    }

    public static function valueTerms($post): array
    {
        $module = Registry::module('editorial_metadata');
        if (!$module) {
            return [];
        }
        return array_values(array_filter($module->get_editorial_metadata_terms(), static fn($term) => in_array($post->post_type, (array) ($term->post_types ?? []), true)));
    }

    public static function values(array $input)
    {
        $module = Registry::module('editorial_metadata');
        $post = get_post($input['post_id']);
        if (
            !$module || !$post || !current_user_can('edit_post', $post->ID) || !current_user_can('read_post', $post->ID)
            || !current_user_can(apply_filters('publishpress_view_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_VIEW_METADATA))
        ) {
            return Registry::denied();
        }
        $values = $raw = $fields = [];
        foreach (self::valueTerms($post) as $term) {
            $key = $module->get_postmeta_key($term);
            $value = get_post_meta($post->ID, $key, true);
            $exists = metadata_exists('post', $post->ID, $key);
            $raw[$term->slug] = ['exists' => $exists, 'value' => $value, 'definition' => (array) $term];
            $fields[$term->slug] = self::valueSchema($term) + ['title' => $term->name];
            if ($term->type === 'checkbox') {
                $value = !empty($value);
            } elseif (!$exists) {
                $value = $term->type === 'select' && ($term->select_type ?? '') === 'multiple' ? [] : null;
            } elseif (in_array($term->type, ['number', 'user'], true)) {
                $value = (int) $value;
            } elseif ($term->type === 'date') {
                $value = gmdate('Y-m-d H:i', (int) $value);
            } elseif ($term->type === 'select' && ($term->select_type ?? '') === 'multiple') {
                $value = array_values((array) $value);
            }
            $values[$term->slug] = $value;
        }
        return ['post_id' => $post->ID, 'values' => (object) $values, 'schema' => Registry::object($fields),
            'stored' => (object) array_map(static fn($row) => ['exists' => $row['exists'], 'value' => $row['value']], $raw),
            'expected_hash' => Registry::hash($raw), 'dry_run' => false];
    }

    public static function updateValues(array $input)
    {
        if (!current_user_can(apply_filters('publishpress_edit_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_EDIT_METADATA))) {
            return Registry::denied();
        }
        $before = self::values($input);
        if (is_wp_error($before)) {
            return $before;
        }
        if (!hash_equals($before['expected_hash'], $input['expected_hash'])) {
            return Registry::error(__('Editorial fields changed. Read them again.', 'publishpress'), 409);
        }
        $patch = Registry::validate($input['values'], $before['schema']);
        if (is_wp_error($patch)) {
            return $patch;
        }
        $post = get_post($input['post_id']);
        $module = Registry::module('editorial_metadata');
        $data = [];
        $expected = [];
        foreach (self::valueTerms($post) as $term) {
            if (!array_key_exists($term->slug, $patch)) {
                continue;
            }
            $value = $patch[$term->slug];
            $key = $module->get_postmeta_key($term);
            if ($term->type === 'user' && $value !== null) {
                $user = get_userdata($value);
                if (!$user || !is_user_member_of_blog($value) || (is_array($term->user_role ?? null) && $term->user_role && !array_intersect($term->user_role, $user->roles))) {
                    return Registry::error();
                }
            }
            if ($term->type === 'date' && $value !== null) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
                if (!$date || $date->format('Y-m-d H:i') !== $value) {
                    return Registry::error();
                }
                $data[$key . '_hidden'] = $value;
            }
            $data[$key] = $value === null ? '' : ($term->type === 'checkbox' ? ($value ? 'on' : '') : $value);
            $expected[$key] = $module->normalize_postmeta_value($term, $data);
            $normalized = $expected[$key];
            $patch[$term->slug] = $term->type === 'checkbox' ? $normalized !== null : ($normalized === null ? (($term->type === 'select' && ($term->select_type ?? '') === 'multiple') ? [] : null) : (in_array($term->type, ['number', 'user'], true) ? (int) $normalized : ($term->type === 'date' ? gmdate('Y-m-d H:i', (int) $normalized) : $normalized)));
        }
        if (!empty($input['dry_run'])) {
            return ['post_id' => $post->ID, 'values' => (object) array_merge((array) $before['values'], $patch), 'dry_run' => true, 'expected_hash' => $before['expected_hash']];
        }
        $result = $module->save_postmeta_values($post, $data, true);
        if (is_wp_error($result)) {
            return $result;
        }
        foreach ($expected as $key => $value) {
            if ($value === null ? metadata_exists('post', $post->ID, $key) : get_post_meta($post->ID, $key, true) !== $value) {
                return Registry::unconfirmed();
            }
        }
        $saved = self::values($input);
        return is_wp_error($saved) ? Registry::unconfirmed() : $saved;
    }
}
