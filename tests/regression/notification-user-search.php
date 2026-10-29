<?php
/** Usage: php tests/regression/notification-user-search.php */
$queries = [];
$allowed = true;
function apply_filters($hook, $value, ...$args) { return $value; }
function absint($value) { return abs((int) $value); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function wp_create_nonce($action) { return 'nonce'; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_attr__($value, $domain = '') { return $value; }
function esc_html_e($value, $domain = '') { echo esc_html($value); }
function selected($selected, $current) { if ($selected == $current) { echo 'selected'; } }
function get_editable_roles() { return ['editor' => ['name' => 'Editor']]; }
function current_user_can(...$args) { return $GLOBALS['allowed']; }
function check_ajax_referer($action, $key) {
    if (($_GET[$key] ?? '') !== 'nonce') { throw new RuntimeException('Invalid nonce'); }
}
function sanitize_text_field($value) { return strip_tags($value); }
function wp_unslash($value) { return $value; }
function wp_send_json($value) { $GLOBALS['response'] = $value; }
function wp_send_json_error($value, $status) { $GLOBALS['response'] = $status; }
function get_users($args) {
    $GLOBALS['queries'][] = $args;
    $ids = $args['include'] ?? range(1, 21);
    return array_map(function ($id) {
        return (object) ['ID' => $id, 'display_name' => 'User ' . $id];
    }, $ids);
}
require dirname(__DIR__, 2) . '/common/php/class-module.php';
require dirname(__DIR__, 2) . '/lib/Notifications/Traits/Dependency_Injector.php';
require dirname(__DIR__, 2) . '/modules/notifications/notifications.php';
$module = (new ReflectionClass(PP_Module::class))->newInstanceWithoutConstructor();
$notifications = (new ReflectionClass(PP_Notifications::class))->newInstanceWithoutConstructor();
function expect_search($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
ob_start();
$module->users_select_form([], ['post_id' => 12]);
$empty_html = ob_get_clean();
expect_search($queries === [], 'An empty selection must never load all users.');
expect_search(strpos($empty_html, 'data-post-id="12"') !== false, 'Post context must reach the AJAX selector.');
ob_start();
$module->users_select_form([100001, 'editor', 'test@example.com'], ['post_id' => 12]);
$html = ob_get_clean();
expect_search($queries[0]['include'] === [100001], 'Initial queries must include only saved user IDs.');
foreach (['100001', 'editor', 'test@example.com'] as $selected) {
    expect_search(strpos($html, 'value="' . $selected . '" selected') !== false, 'Saved recipient must remain selected: ' . $selected);
}
$_GET = ['nonce' => 'nonce', 'post_id' => 12, 'q' => 'alice', 'page' => 2];
$notifications->ajax_search_notification_users();
$query = end($queries);
expect_search($query['number'] === 21 && $query['offset'] === 20, 'Search must be paginated and bounded.');
expect_search($query['search'] === '*alice*' && $query['count_total'] === false, 'Search must filter without a total-count query.');
expect_search(count($response['results']) === 20 && $response['pagination']['more'], 'The extra record must only signal another page.');
$before = count($queries);
$allowed = false;
$notifications->ajax_search_notification_users();
expect_search($response === 403 && count($queries) === $before, 'Unauthorized requests must not query users.');
$allowed = true;
$_GET['nonce'] = 'invalid';
try {
    $notifications->ajax_search_notification_users();
    throw new RuntimeException('Invalid nonce was accepted.');
} catch (RuntimeException $error) {
    expect_search($error->getMessage() === 'Invalid nonce', 'Nonce verification must run before a query.');
}
echo "PASS: saved selections, no full user load, pagination, permissions, and nonce checks\n";
