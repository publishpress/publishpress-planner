<?php

/** Typed native Planner module settings, feature gates and persistence. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;
class Settings
{
    public static function register(): void
    {
        $manage = static fn() => current_user_can('manage_options');
        Registry::add(
            'list-features',
            __('Discover Planner features', 'publishpress'),
            __('Discover Calendar, Content Board, Content Overview, Editorial Fields, Editorial Comments, notifications, dashboard, dependencies and Pro features. Each entry gives its settings schema and native module state. Secrets and license settings are not exposed.', 'publishpress'),
            Registry::object(),
            [self::class, 'features'],
            $manage
        );
        $selector = ['module' => Registry::text(100)];
        Registry::add(
            'get-plugin-settings',
            __('Read Planner settings', 'publishpress'),
            __('Read one discovered Planner module, exact JSON types, native choices, defaults and its revision. For example calendar, content_board, notifications, editorial_metadata, or Pro reminders/slack.', 'publishpress'),
            Registry::object($selector, ['module']),
            [self::class, 'read'],
            $manage
        );
        Registry::add(
            'update-plugin-settings',
            __('Update Planner settings', 'publishpress'),
            __('Patch supplied settings only, using the schema and expected_hash from get-plugin-settings. Native validators and configuration locks apply. dry_run validates without writing. Boolean JSON false is distinct from the string false. Empty lists clear list selections.', 'publishpress'),
            Registry::object($selector + ['settings' => ['type' => 'object', 'minProperties' => 1, 'maxProperties' => 50], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['module', 'settings', 'expected_hash']),
            [self::class, 'update'],
            $manage,
            true
        );
        Registry::add(
            'set-feature-enabled',
            __('Enable or disable a Planner module', 'publishpress'),
            __('Change one native optional module switch using the revision returned by settings discovery. Mandatory/autoloaded modules cannot be disabled. Feature availability changes after the next request; refresh the AI connection after changes.', 'publishpress'),
            Registry::object($selector + ['enabled' => ['type' => 'boolean'], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['module', 'enabled', 'expected_hash']),
            [self::class, 'toggle'],
            $manage,
            true
        );
    }

    public static function fields(string $module): array
    {
        $text = Registry::text();
        $bool = ['type' => 'boolean'];
        $native = Registry::module($module, false);
        $types = $native ? array_keys($native->get_all_post_types($native->module)) : ['post', 'page'];
        $postTypes = [];
        foreach ($types as $type) {
            $postTypes[$type] = $bool;
        }
        $postMap = Registry::object($postTypes);
        $statuses = array_keys(\PP_Calendar::getPublishTimeStatusChoices());
        $statusMap = Registry::object(array_fill_keys($statuses, $bool));
        $list = Registry::listOf($text);
        $all = [
            'calendar' => ['post_types' => $postMap, 'ics_subscription' => $bool, 'ics_subscription_public_visibility' => $bool + ['description' => __('Native Calendar feed public visibility disables per-user visibility filters for subscription queries. Enable only when that wider visibility is intended.', 'publishpress')],
                'show_posts_publish_time' => $statusMap, 'posts_publish_time_format' => ['type' => 'string', 'enum' => ['ga', 'ha', 'H']],
                'default_publish_time' => ['type' => 'string', 'pattern' => '^(|(?:[01][0-9]|2[0-3]):[0-5][0-9])$'],
                'sort_by' => ['type' => 'string', 'enum' => ['time', 'status']],
                'max_visible_posts_per_date' => ['type' => 'integer', 'enum' => array_merge([-1], range(4, 30))],
                'show_calendar_posts_full_title' => $bool, 'calendar_today_in_first_row' => $bool],
            'content_board' => ['post_types' => $postMap, 'posts_per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999],
                'content_board_scheduled_date' => Registry::object(['number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 31],
                    'period' => ['type' => 'string', 'enum' => ['days', 'weeks', 'months', 'years']]], ['number', 'period'])],
            'content_overview' => ['post_types' => $postMap, 'posts_per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999]],
            'editorial_metadata' => ['post_types' => $postMap],
            'editorial_comments' => ['post_types' => $postMap,
                'editorial_comment_name_field' => ['type' => 'string', 'enum' => ['display_name', 'user_login', 'user_nicename', 'first_name', 'last_name', 'user_email', 'user_url']]],
            'notifications' => ['post_types' => $postMap, 'notify_author_by_default' => $bool, 'notify_current_user_by_default' => $bool,
                'blacklisted_taxonomies' => Registry::listOf(['type' => 'string', 'enum' => array_keys(get_taxonomies())]),
                'email_from' => ['type' => 'string', 'pattern' => '^(|[^@\s]+@[^@\s]+\.[^@\s]+)$'], 'email_from_name' => $text],
            'improved_notifications' => ['post_types' => $postMap,
                'default_channels' => self::defaultChannelSchema(),
                'duplicated_notification_threshold' => ['type' => 'integer', 'minimum' => 1]],
            'dashboard' => ['post_status_widget' => $bool, 'my_posts_widget' => $bool, 'notepad_widget' => $bool],
        ];
        return apply_filters('publishpress_planner_mcp_setting_fields', $all[$module] ?? [], $module);
    }

    private static function defaultChannelSchema(): array
    {
        $fields = [];
        if (post_type_exists(PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW)) {
            $posts = get_posts(['post_type' => PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW, 'post_status' => 'publish', 'numberposts' => 1000]);
            foreach ($posts as $post) {
                if (current_user_can('read_post', $post->ID) && current_user_can('edit_post', $post->ID)) {
                    $fields[(string) $post->ID] = ['type' => 'string', 'enum' => self::channels()];
                }
            }
        }
        return Registry::object($fields);
    }

    public static function channels(): array
    {
        $channels = ['email'];
        if (Registry::pro() && Registry::module('slack')) {
            $channels[] = 'slack';
        }
        return $channels;
    }

    public static function features(): array
    {
        $items = [];
        foreach (PublishPress()->modules as $id => $module) {
            $items[] = ['module' => $id, 'label' => wp_strip_all_tags($module->title ?? $id),
                'enabled' => ($module->options->enabled ?? 'off') === 'on', 'switchable' => empty($module->autoload),
                'required_capability' => 'manage_options', 'schema' => Registry::object(self::fields($id)),
                'expected_hash' => Registry::hash((array) $module->options)];
        }
        return ['features' => $items, 'pro_active' => Registry::pro()];
    }

    public static function read(array $input)
    {
        $name = $input['module'];
        $module = Registry::module($name, false);
        if (!$module) {
            return Registry::error(__('Planner module is unavailable in this edition.', 'publishpress'), 404);
        }
        $fields = self::fields($name);
        $raw = (array) $module->module->options;
        $values = [];
        $defaults = (array) $module->module->default_options;
        foreach ($fields as $key => &$field) {
            $default = $defaults[$key] ?? ($field['default'] ?? self::fallback($key, $field));
            $field['default'] = self::publicValue($key, $default, $field);
            $values[$key] = self::publicValue($key, $raw[$key] ?? $default, $field);
        }
        unset($field);
        return ['module' => $name, 'settings' => (object) $values, 'schema' => Registry::object($fields),
            'enabled' => ($raw['enabled'] ?? 'off') === 'on', 'expected_hash' => Registry::hash($raw), 'dry_run' => false];
    }

    private static function fallback(string $key, array $field)
    {
        return ['posts_publish_time_format' => 'ga', 'sort_by' => 'time', 'max_visible_posts_per_date' => 4][$key]
        ?? ($field['type'] === 'object' || $field['type'] === 'array' ? [] : ($field['type'] === 'integer' ? 0 : ''));
    }

    private static function publicValue(string $key, $value, array $field)
    {
        if ($field['type'] === 'boolean') {
            return in_array($value, ['on', 'yes', '1', 1, true], true);
        }
        if ($field['type'] === 'integer') {
            return (int) $value;
        }
        if ($field['type'] === 'array') {
            $value = $key === 'blacklisted_taxonomies' && is_string($value) ? array_filter(array_map('trim', explode(',', $value))) : ($value === '' ? [] : (array) $value);
            return array_values(array_map('strval', $value));
        }
        if ($field['type'] === 'object') {
            $result = [];
            foreach ((array) $value as $name => $child) {
                if (isset($field['properties'][$name])) {
                    $result[$name] = self::publicValue($name, $child, $field['properties'][$name]);
                }
            }
            return (object) $result;
        }
        return is_scalar($value) ? (string) $value : '';
    }

    public static function update(array $input)
    {
        if (!current_user_can('manage_options')) {
            return Registry::denied();
        }
        $state = self::read($input);
        if (is_wp_error($state)) {
            return $state;
        }
        $module = Registry::module($input['module'], false);
        $before = (array) $module->module->options;
        $storedBefore = get_option(PublishPress()->options_group . $input['module'] . '_options');
        $fresh = Registry::stale($input, $before);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        $patch = Registry::validate($input['settings'], $state['schema']);
        if (is_wp_error($patch)) {
            return $patch;
        }
        $values = $before;
        foreach ($patch as $key => $value) {
            $type = $state['schema']['properties'][$key]['type'];
            if ($type === 'boolean') {
                $value = $input['module'] === 'notifications' ? ($value ? '1' : '0') : ($value ? 'on' : 'off');
            } elseif ($type === 'object') {
                $map = is_array($before[$key] ?? null) || is_object($before[$key] ?? null) ? (array) $before[$key] : [];
                foreach ($value as $name => $child) {
                    $childType = $state['schema']['properties'][$key]['properties'][$name]['type'];
                    $map[$name] = $childType === 'boolean' ? ($child ? 'on' : 'off') : $child;
                }
                $value = $map;
                if ($key === 'post_types' && $input['module'] === 'calendar' && !in_array('on', $map, true)) {
                    return Registry::error(__('Select at least one Calendar post type.', 'publishpress'));
                }
                if ($key === 'post_types') {
                    foreach ($map as $postType => $on) {
                        if (post_type_supports($postType, $module->module->post_type_support ?? 'pp_' . $input['module']) && $on !== 'on') {
                            return Registry::error(__('This post type is enabled by site code.', 'publishpress'));
                        }
                    }
                }
            } elseif ($type === 'string') {
                $value = sanitize_text_field($value);
            }
            if ($key === 'blacklisted_taxonomies') {
                $value = implode(',', $value);
            }
            $values[$key] = $value;
        }
        // Form validators expect unchecked checkboxes to be absent. Convert only at their boundary.
        $form = $values;
        if ($input['module'] === 'calendar') {
            foreach (['show_calendar_posts_full_title', 'calendar_today_in_first_row', 'ics_subscription_public_visibility'] as $key) {
                if (($form[$key] ?? 'off') !== 'on') {
                    unset($form[$key]);
                }
            }
        }
        if (is_callable([$module, 'settings_validate'])) {
            $validated = $input['module'] === 'calendar' ? $module->settings_validate($form, false) : $module->settings_validate($form);
            if (is_wp_error($validated) || !is_array($validated)) {
                return is_wp_error($validated) ? $validated : Registry::error();
            }
            foreach ($patch as $key => $unused) {
                $values[$key] = $validated[$key] ?? $values[$key];
            }
        }
        $validation = apply_filters('publishpress_planner_mcp_validate_settings', true, $input['module'], $values, $patch);
        if (is_wp_error($validation)) {
            return $validation;
        }
        if (empty($input['dry_run'])) {
            if (Registry::hash(get_option(PublishPress()->options_group . $input['module'] . '_options')) !== Registry::hash($storedBefore)) {
                return Registry::error(__('Settings changed during validation. Read them again.', 'publishpress'), 409);
            }
            $saved = Registry::saveOptions($input['module'], $values);
            if (is_wp_error($saved)) {
                return $saved;
            }
            do_action('publishpress_planner_mcp_settings_saved', $input['module'], $values, $before);
        }
        $result = $state;
        foreach ($state['schema']['properties'] as $key => $field) {
            $result['settings']->$key = self::publicValue($key, $values[$key] ?? self::fallback($key, $field), $field);
        }
        $result['dry_run'] = !empty($input['dry_run']);
        $result['expected_hash'] = empty($input['dry_run']) ? Registry::hash($values) : $state['expected_hash'];
        return $result;
    }

    public static function toggle(array $input)
    {
        if (!current_user_can('manage_options') || ($input['module'] === 'debug' && !current_user_can(\PP_Debug::REQUIRED_CAPABILITY))) {
            return Registry::denied();
        }
        $module = Registry::module($input['module'], false);
        if (!$module || !empty($module->module->autoload)) {
            return Registry::error(__('Choose an optional native module.', 'publishpress'));
        }
        $before = (array) $module->module->options;
        $storedBefore = get_option(PublishPress()->options_group . $input['module'] . '_options');
        $fresh = Registry::stale($input, $before);
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        $after = $before;
        $after['enabled'] = $input['enabled'] ? 'on' : 'off';
        if (empty($input['dry_run'])) {
            if (Registry::hash(get_option(PublishPress()->options_group . $input['module'] . '_options')) !== Registry::hash($storedBefore)) {
                return Registry::error(__('Settings changed during validation. Read them again.', 'publishpress'), 409);
            }
            $saved = Registry::saveOptions($input['module'], $after);
            if (is_wp_error($saved)) {
                return $saved;
            }
        }
        return ['module' => $input['module'], 'enabled' => $input['enabled'], 'dry_run' => !empty($input['dry_run']), 'expected_hash' => Registry::hash(empty($input['dry_run']) ? $after : $before)];
    }
}
