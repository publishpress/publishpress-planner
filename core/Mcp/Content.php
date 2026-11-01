<?php

/** Planner content discovery and writes through native WordPress controllers and Calendar methods. */

namespace PublishPress\Core\Mcp;

defined('ABSPATH') || exit;

class ContentController extends \WP_REST_Posts_Controller
{
    public function prepareForPlanner($request)
    {
        return $this->prepare_item_for_database($request);
    }
}

class Content
{
    public static function register(): void
    {
        Registry::add(
            'move-board-item',
            __('Move content to a Board status', 'publishpress'),
            __('Move an editable Board item to an authorized native status using expected_hash. Scheduling accepts a number of days, weeks, months or years; native publication/date behavior applies. Global schedule defaults remain unchanged. dry_run does not move content.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'status' => Registry::text(100), 'number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 31],
                'period' => ['type' => 'string', 'enum' => ['days', 'weeks', 'months', 'years']], 'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['id', 'status', 'expected_hash']),
            [self::class, 'board'],
            static fn() => self::allowed('content_board'),
            true
        );
        Registry::add(
            'lookup-choices',
            __('Find Planner users, roles and taxonomy choices', 'publishpress'),
            __('Find a bounded page of native users, roles, public taxonomies, terms or Permissions groups for Planner filters and notification recipients. Returns identifiers and labels without email/account internals. User lookup requires list_users; groups require the active Permissions integration and its settings capability. Category filters use slugs; general workflow term filters use numeric term IDs.', 'publishpress'),
            Registry::object(Registry::page() + ['kind' => ['type' => 'string', 'enum' => ['users', 'roles', 'taxonomies', 'terms', 'groups']],
                'taxonomy' => Registry::text(100), 'search' => Registry::text(200)], ['kind']),
            [self::class, 'lookup'],
            static fn() => self::allowed('calendar') || Workflows::allowed()
        );
        Registry::add(
            'export-calendar',
            __('Export selected Calendar content', 'publishpress'),
            __('Render up to 100 explicitly selected readable/editable Calendar items as native iCalendar text. Does not create a public URL or return feed credentials. Editorial details require the native editorial-field viewing capability.', 'publishpress'),
            Registry::object(['ids' => Registry::listOf(Registry::id(), 100)], ['ids']),
            [self::class, 'exportCalendar'],
            static fn() => self::allowed('calendar')
        );
        $view = ['type' => 'string', 'enum' => ['calendar', 'content_board', 'content_overview']];
        $read = static fn($i) => self::allowed($i['view'] ?? 'calendar');
        Registry::add(
            'list-content',
            __('Find Planner content', 'publishpress'),
            __('Find editable content for Calendar, Content Board or Content Overview. Bounded filters include post type, title search, status, author, taxonomy terms and date range. Every result requires native view and object permissions. Board and overview display configuration is available separately.', 'publishpress'),
            Registry::object(Registry::page() + ['view' => $view, 'post_type' => Registry::text(100), 'search' => Registry::text(200),
                'status' => Registry::text(100), 'author' => Registry::id(), 'date_from' => self::date(), 'date_to' => self::date(),
                'taxonomy' => Registry::text(100), 'term_id' => Registry::id()]),
            [self::class, 'find'],
            $read
        );
        Registry::add(
            'get-content',
            __('Read a Planner content item', 'publishpress'),
            __('Read an editable post with its current revision, native post type/status choices, terms and scheduling dates. Requires the selected Planner view and edit_post/read_post on this object. Passwords and arbitrary metadata are excluded.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'view' => $view], ['id']),
            [self::class, 'get'],
            $read
        );
        $changes = Registry::object(['title' => Registry::text(1000), 'content' => Registry::text(200000), 'excerpt' => Registry::text(20000),
            'status' => Registry::text(100), 'date' => ['type' => 'string', 'format' => 'date-time'], 'author' => Registry::id(),
            'terms' => ['type' => 'object', 'maxProperties' => 20]]);
        Registry::add(
            'save-content',
            __('Create or update Planner content', 'publishpress'),
            __('Create a draft or update discovered content using native post controllers, author/term permissions and Planner hooks. Existing content requires expected_hash. Changes are a patch; omitted content, authors and terms remain. Dates use ISO 8601 with a time-zone offset. dry_run performs all permission and validation checks without saving or triggering notifications.', 'publishpress'),
            Registry::object(['id' => Registry::id(), 'view' => $view, 'post_type' => Registry::text(100), 'changes' => $changes,
                'expected_hash' => Registry::revision(), 'dry_run' => ['type' => 'boolean']], ['changes']),
            [self::class, 'save'],
            $read,
            true
        );
        $move = Registry::object(['id' => Registry::id(), 'date' => self::date(), 'expected_hash' => Registry::revision()], ['id', 'date', 'expected_hash']);
        Registry::add(
            'move-calendar-items',
            __('Reschedule Planner calendar items', 'publishpress'),
            __('Move up to 50 Calendar items to YYYY-MM-DD dates, retaining each native time of day. Uses Calendar publishing/author permissions and move hooks. All objects and revisions are checked before any writes. dry_run previews the complete batch. A later failure reports already moved IDs rather than pretending the batch was unchanged.', 'publishpress'),
            Registry::object(['items' => ['type' => 'array', 'items' => $move, 'minItems' => 1, 'maxItems' => 50], 'dry_run' => ['type' => 'boolean']], ['items']),
            [self::class, 'move'],
            static fn() => self::allowed('calendar'),
            true
        );
        Registry::add(
            'get-content-choices',
            __('Discover Planner content choices', 'publishpress'),
            __('Read native post types, post statuses and taxonomies for an enabled Planner view. Includes object capability names and active Pro editing/integration state. Use these exact identifiers before content writes.', 'publishpress'),
            Registry::object(['view' => $view]),
            [self::class, 'choices'],
            $read
        );
    }

    public static function exportCalendar(array $input)
    {
        $module = Registry::module('calendar');
        if (!$module || ($module->module->options->ics_subscription ?? 'off') !== 'on') {
            return Registry::denied();
        }
        $posts = [];
        foreach ($input['ids'] as $id) {
            $post = get_post($id);
            if (!self::permitted($post, 'calendar')) {
                return Registry::denied();
            }
            $posts[] = $post;
        }
        $timestamps = array_map(static fn($post) => strtotime($post->post_date_gmt), $posts);
        [$calendar, $zone] = $module->createCalendarExport($timestamps ? min($timestamps) : time(), ($timestamps ? max($timestamps) : time()) + DAY_IN_SECONDS);
        foreach ($posts as $post) {
            $module->addPostToCalendarExport($calendar, $post, $zone, current_user_can(apply_filters('publishpress_view_editorial_metadata_cap', \PP_Editorial_Metadata::CAP_VIEW_METADATA)));
        }
        return ['filename' => 'calendar.ics', 'mime_type' => 'text/calendar', 'content' => $calendar->serialize(), 'count' => count($posts)];
    }

    public static function lookup(array $input)
    {
        $size = $input['per_page'] ?? 20;
        $offset = (($input['page'] ?? 1) - 1) * $size;
        $search = $input['search'] ?? '';
        $items = [];
        if ($input['kind'] === 'users') {
            if (!current_user_can('list_users')) {
                return Registry::denied();
            }
            foreach (get_users(['number' => $size + 1, 'offset' => $offset, 'search' => $search === '' ? '' : '*' . $search . '*', 'fields' => ['ID', 'display_name']]) as $user) {
                $items[] = ['id' => (int) $user->ID, 'label' => $user->display_name];
            }
        } elseif ($input['kind'] === 'terms') {
            $taxonomy = get_taxonomy($input['taxonomy'] ?? 'category');
            if (!$taxonomy || !$taxonomy->show_ui || !current_user_can($taxonomy->cap->assign_terms)) {
                return Registry::denied();
            }
            $terms = get_terms(['taxonomy' => $taxonomy->name, 'hide_empty' => false, 'number' => $size + 1, 'offset' => $offset, 'search' => $search]);
            if (is_wp_error($terms)) {
                return $terms;
            }
            foreach ($terms as $term) {
                $items[] = ['id' => (int) $term->term_id, 'slug' => $term->slug, 'label' => $term->name, 'taxonomy' => $term->taxonomy];
            }
        } elseif ($input['kind'] === 'groups') {
            if (!class_exists('PublishPress\\Permissions\\API') || !current_user_can('pp_manage_settings')) {
                return Registry::denied();
            }
            foreach (\PublishPress\Permissions\API::getGroups('pp_group', ['include_metagroups' => false, 'limit' => $size + 1, 'offset' => $offset]) as $group) {
                $items[] = ['id' => (int) $group->ID, 'label' => $group->name];
            }
        } else {
            $choices = $input['kind'] === 'roles' ? apply_filters('editable_roles', wp_roles()->roles) : get_taxonomies(['show_ui' => true], 'objects');
            foreach ($choices as $id => $choice) {
                $label = is_array($choice) ? $choice['name'] : $choice->label;
                if ($search === '' || stripos($label, $search) !== false) {
                    $items[] = ['id' => $id, 'label' => $label];
                }
            }
            $items = array_slice($items, $offset, $size + 1);
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($items) > $size];
    }

    public static function date(): array
    {
        return ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
    }

    public static function allowed(string $view): bool
    {
        if (!Registry::module($view)) {
            return false;
        }
        $cap = $view === 'calendar'
        ? apply_filters('publishpress_calendar_cap_view_calendar', apply_filters('pp_view_calendar_cap', 'pp_view_calendar'))
        : apply_filters('pp_view_' . $view . '_cap', 'pp_view_' . $view);
        return current_user_can($cap);
    }

    public static function types(string $view): array
    {
        $module = Registry::module($view);
        if (!$module) {
            return [];
        }
        return array_values($module->get_post_types_for_module($module->module));
    }

    public static function contentTaxonomy($taxonomy): bool
    {
        $object = is_object($taxonomy) ? $taxonomy : get_taxonomy($taxonomy);
        return $object && ($object->public || $object->show_ui)
        && $object->name !== \PP_Editorial_Metadata::metadata_taxonomy
        && strpos($object->name, 'pp_notify_') !== 0;
    }

    public static function choices(array $input): array
    {
        $view = $input['view'] ?? 'calendar';
        $types = [];
        foreach (self::types($view) as $type) {
            $object = get_post_type_object($type);
            $types[] = ['name' => $type, 'label' => $object->label, 'capabilities' => (object) $object->cap,
                'taxonomies' => array_values(array_filter(get_object_taxonomies($type), [self::class, 'contentTaxonomy']))];
        }
        $statuses = [];
        foreach (get_post_stati(['internal' => false], 'objects') as $status) {
            $statuses[] = ['name' => $status->name, 'label' => $status->label];
        }
        return ['post_types' => $types, 'statuses' => $statuses, 'pro_active' => Registry::pro(), 'timezone' => wp_timezone_string()];
    }

    public static function permitted($post, string $view): bool
    {
        return $post && self::allowed($view) && in_array($post->post_type, self::types($view), true)
        && current_user_can('edit_post', $post->ID) && current_user_can('read_post', $post->ID);
    }

    public static function snapshot($post): array
    {
        $terms = [];
        foreach (get_object_taxonomies($post->post_type) as $taxonomy) {
            if (!self::contentTaxonomy($taxonomy)) {
                continue;
            }
            $ids = wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
            $terms[$taxonomy] = is_wp_error($ids) ? [] : array_map('intval', $ids);
        }
        return ['post' => array_intersect_key((array) $post, array_flip(['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_date_gmt', 'post_author', 'post_modified_gmt'])), 'terms' => $terms];
    }

    public static function get(array $input)
    {
        $post = get_post($input['id']);
        $view = $input['view'] ?? 'calendar';
        if (!self::permitted($post, $view)) {
            return Registry::denied();
        }
        $state = self::snapshot($post);
        return ['id' => $post->ID, 'post_type' => $post->post_type, 'title' => $post->post_title,
            'content' => $post->post_content, 'excerpt' => $post->post_excerpt, 'status' => $post->post_status,
            'date' => $post->post_date, 'date_gmt' => $post->post_date_gmt, 'author' => (int) $post->post_author,
            'terms' => (object) $state['terms'], 'expected_hash' => Registry::hash($state)];
    }

    public static function find(array $input)
    {
        $view = $input['view'] ?? 'calendar';
        $types = self::types($view);
        if (isset($input['post_type'])) {
            $types = array_intersect($types, [$input['post_type']]);
        }
        if (!$types) {
            return ['items' => [], 'has_more' => false];
        }
        $size = $input['per_page'] ?? 20;
        $args = ['required_operation' => 'edit', 'post_type' => array_values($types), 'post_status' => array_values(array_diff(array_keys(get_post_stati()), ['inherit', 'trash', 'auto-draft'])),
            'posts_per_page' => $size + 1, 'offset' => (($input['page'] ?? 1) - 1) * $size, 'orderby' => 'date', 'order' => 'ASC', 'no_found_rows' => true];
        if (isset($input['status'])) {
            if (!get_post_status_object($input['status']) || in_array($input['status'], ['inherit', 'trash', 'auto-draft'], true)) {
                return Registry::error();
            }
            $args['post_status'] = $input['status'];
        }
        foreach (['search' => 's', 'author' => 'author'] as $from => $to) {
            if (isset($input[$from])) {
                $args[$to] = $input[$from];
            }
        }
        if (isset($input['date_from']) || isset($input['date_to'])) {
            $range = ['inclusive' => true];
            foreach (['date_from' => 'after', 'date_to' => 'before'] as $from => $to) {
                if (isset($input[$from])) {
                    if (!self::validDate($input[$from])) {
                        return Registry::error();
                    }
                    $range[$to] = $input[$from];
                }
            }
            if (isset($input['date_from'], $input['date_to']) && $input['date_from'] > $input['date_to']) {
                return Registry::error();
            }
            $args['date_query'] = [$range];
        }
        if (isset($input['taxonomy']) || isset($input['term_id'])) {
            $tax = get_taxonomy($input['taxonomy'] ?? '');
            $term = get_term($input['term_id'] ?? 0, $input['taxonomy'] ?? '');
            if (!self::contentTaxonomy($tax) || !$term || is_wp_error($term) || !array_intersect($types, $tax->object_type) || !current_user_can($tax->cap->assign_terms)) {
                return Registry::denied();
            }
            $args['tax_query'] = [['taxonomy' => $tax->name, 'terms' => [$term->term_id]]];
        }
        $args = apply_filters('publishpress_planner_mcp_content_query', $args, $view);
        $args['posts_per_page'] = $size + 1;
        $items = [];
        foreach ((new \WP_Query($args))->posts as $post) {
            if (self::permitted($post, $view)) {
                $items[] = ['id' => $post->ID, 'title' => $post->post_title, 'post_type' => $post->post_type,
                    'status' => $post->post_status, 'date' => $post->post_date, 'author' => (int) $post->post_author,
                    'expected_hash' => Registry::hash(self::snapshot($post))];
            }
        }
        return ['items' => array_slice($items, 0, $size), 'has_more' => count($items) > $size];
    }

    public static function validDate(string $date): bool
    {
        $parts = explode('-', $date);
        return count($parts) === 3 && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    public static function save(array $input)
    {
        $view = $input['view'] ?? 'calendar';
        if (!self::allowed($view) || !$input['changes']) {
            return Registry::denied();
        }
        $post = isset($input['id']) ? get_post($input['id']) : null;
        if (isset($input['id']) && !self::permitted($post, $view)) {
            return Registry::denied();
        }
        $type = $post ? $post->post_type : ($input['post_type'] ?? 'post');
        if (!in_array($type, self::types($view), true)) {
            return Registry::denied();
        }
        if ($post) {
            $fresh = Registry::stale($input, self::snapshot($post));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
        }
        $object = get_post_type_object($type);
        $controller = new ContentController($type);
        $request = new \WP_REST_Request($post ? 'PUT' : 'POST');
        if ($post) {
            $request->set_param('id', $post->ID);
        }
        $changes = $input['changes'];
        if (isset($changes['date'])) {
            try {
                new \DateTimeImmutable($changes['date']);
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                    return Registry::error(__('Use a valid ISO 8601 publication date.', 'publishpress'));
                }
            } catch (\Exception $error) {
                return Registry::error(__('Use a valid ISO 8601 publication date.', 'publishpress'));
            }
        }
        $terms = $changes['terms'] ?? [];
        unset($changes['terms']);
        if (!$post && !isset($changes['status'])) {
            $changes['status'] = 'draft';
        }
        foreach ($changes as $key => $value) {
            $request->set_param($key, in_array($key, ['title', 'content', 'excerpt'], true) ? ['raw' => $value] : $value);
        }
        $nativeTerms = [];
        foreach ($terms as $taxonomy => $ids) {
            $tax = get_taxonomy($taxonomy);
            $valid = Registry::validate($ids, Registry::listOf(Registry::id()));
            if (!self::contentTaxonomy($tax) || !in_array($type, $tax->object_type, true) || !current_user_can($tax->cap->assign_terms)) {
                return Registry::denied();
            }
            if (is_wp_error($valid)) {
                return $valid;
            }
            foreach ($ids as $id) {
                if (!get_term($id, $taxonomy) || is_wp_error(get_term($id, $taxonomy))) {
                    return Registry::error();
                }
                if (!current_user_can('assign_term', $id)) {
                    return Registry::denied();
                }
            }
            if ($tax->show_in_rest) {
                $request->set_param($tax->rest_base ?: $taxonomy, $ids);
            } else {
                $nativeTerms[$taxonomy] = $ids;
            }
        }
        $permitted = $post ? $controller->update_item_permissions_check($request) : $controller->create_item_permissions_check($request);
        if (is_wp_error($permitted) || !$permitted) {
            return is_wp_error($permitted) ? $permitted : Registry::denied();
        }
        if (isset($changes['status'])) {
            $status = get_post_status_object($changes['status']);
            if (!$status || $status->internal || in_array($status->name, ['inherit', 'auto-draft', 'trash'], true)) {
                return Registry::error();
            }
            if (($status->public || $status->private || $status->name === 'future') && !current_user_can($object->cap->publish_posts)) {
                return Registry::denied();
            }
        }
        $schema = $controller->get_endpoint_args_for_item_schema($post ? \WP_REST_Server::EDITABLE : \WP_REST_Server::CREATABLE);
        foreach ($request->get_params() as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            if (!isset($schema[$key])) {
                return Registry::error(__('This post type does not support the supplied content field.', 'publishpress'));
            }
            if (isset($schema[$key])) {
                $valid = rest_validate_value_from_schema($value, $schema[$key], $key);
                if (is_wp_error($valid)) {
                    return $valid;
                }
            }
        }
        // The REST controller performs status, author, sticky and taxonomy checks during preparation.
        $prepared = $controller->prepareForPlanner($request);
        if (is_wp_error($prepared)) {
            return $prepared;
        }
        if (!empty($input['dry_run'])) {
            return ['id' => $post ? $post->ID : 0, 'post_type' => $type, 'changes' => (object) $input['changes'], 'dry_run' => true];
        }
        if ($post) {
            clean_post_cache($post->ID);
            $freshPost = get_post($post->ID);
            if (!self::permitted($freshPost, $view)) {
                return Registry::denied();
            }
            $fresh = Registry::stale($input, self::snapshot($freshPost));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
        }
        $result = $post ? $controller->update_item($request) : $controller->create_item($request);
        if (is_wp_error($result)) {
            return $result;
        }
        $id = (int) $result->get_data()['id'];
        foreach ($nativeTerms as $taxonomy => $ids) {
            $written = wp_set_object_terms($id, $ids, $taxonomy, false);
            if (is_wp_error($written)) {
                return new \WP_Error('planner_content_unconfirmed', __('Content was saved, but some taxonomy assignments could not be confirmed. Read this object before retrying.', 'publishpress'), ['status' => 500, 'id' => $id]);
            }
        }
        foreach ($terms as $taxonomy => $ids) {
            $actual = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
            if (is_wp_error($actual) || array_diff($ids, $actual) || array_diff($actual, $ids)) {
                return new \WP_Error('planner_content_unconfirmed', __('Content term assignments could not be confirmed. Read this object before retrying.', 'publishpress'), ['status' => 500, 'id' => $id]);
            }
        }
        if (!$post) {
            do_action('publishpress_calendar_after_create_post', $id, [(int) get_post_field('post_author', $id)]);
        }
        $saved = self::get(['id' => $id, 'view' => $view]);
        return is_wp_error($saved) ? Registry::unconfirmed() : $saved + ['dry_run' => false];
    }

    public static function board(array $input)
    {
        $post = get_post($input['id']);
        if (!self::permitted($post, 'content_board')) {
            return Registry::denied();
        }
        $fresh = Registry::stale($input, self::snapshot($post));
        if (is_wp_error($fresh)) {
            return $fresh;
        }
        $native = Registry::module('content_board')->getBoardMethods();
        $result = $native->changePostStatus($post->ID, $input['status'], $input['number'] ?? 1, $input['period'] ?? 'days', !empty($input['dry_run']));
        if (is_wp_error($result)) {
            return $result;
        }
        return !empty($input['dry_run']) ? ['changes' => $result, 'dry_run' => true] : self::get(['id' => $post->ID, 'view' => 'content_board']);
    }

    public static function move(array $input)
    {
        $calendar = Registry::module('calendar');
        if (!$calendar || !self::allowed('calendar')) {
            return Registry::denied();
        }
        $seen = [];
        $previews = [];
        foreach ($input['items'] as $row) {
            $post = get_post($row['id']);
            if (isset($seen[$row['id']]) || !self::permitted($post, 'calendar') || !self::validDate($row['date'])) {
                return Registry::error();
            }
            $seen[$row['id']] = true;
            $fresh = Registry::stale($row, self::snapshot($post));
            if (is_wp_error($fresh)) {
                return $fresh;
            }
            [$year, $month, $day] = array_map('intval', explode('-', $row['date']));
            $preview = $calendar->getCalendarMethods()->moveItem($row['id'], $year, $month, $day, true);
            if (is_wp_error($preview)) {
                return $preview;
            }
            $previews[] = $preview;
        }
        if (!empty($input['dry_run'])) {
            return ['items' => $previews, 'dry_run' => true];
        }
        $applied = [];
        foreach ($input['items'] as $row) {
            $current = get_post($row['id']);
            if (!self::permitted($current, 'calendar')) {
                return ['applied_ids' => $applied, 'failed_id' => $row['id'], 'complete' => false];
            }
            $fresh = Registry::stale($row, self::snapshot($current));
            if (is_wp_error($fresh)) {
                return ['applied_ids' => $applied, 'failed_id' => $row['id'], 'complete' => false];
            }
            [$year, $month, $day] = array_map('intval', explode('-', $row['date']));
            $result = $calendar->getCalendarMethods()->moveItem($row['id'], $year, $month, $day);
            if (is_wp_error($result) || get_post_field('post_date', $row['id']) !== $result['date']) {
                return ['applied_ids' => $applied, 'failed_id' => $row['id'], 'complete' => false, 'outcome' => 'unconfirmed'];
            }
            $applied[] = $row['id'];
        }
        return ['applied_ids' => $applied, 'complete' => true, 'dry_run' => false];
    }
}
