<?php
/** Usage: php tests/regression/workflow-toggle.php */
define('PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW', 'psppnotif_workflow');
class PP_Module {}
$enabled = '';
$allowed = true;
$writes = [];
$events = [];
function get_post_meta($id, $key, $single) { return $GLOBALS['enabled']; }
function update_post_meta($id, $key, $value) { $GLOBALS['writes'][] = [$id, $key, $value]; }
function get_post_type($id) { return $id === 12 ? PUBLISHPRESS_NOTIF_POST_TYPE_WORKFLOW : 'post'; }
function current_user_can(...$args) { return $GLOBALS['allowed']; }
function check_admin_referer($action) { if (($_GET['_wpnonce'] ?? '') !== 'valid') { throw new RuntimeException('nonce'); } }
function wp_die($message) { throw new RuntimeException('denied'); }
function wp_safe_redirect($url) { throw new RuntimeException('redirect'); }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return $value; }
function esc_html__($value, $domain = '') { return $value; }
function do_action($hook, ...$args) { $GLOBALS['events'][] = $hook; }
function apply_filters($hook, $value, ...$args) { return $value; }
$root = dirname(__DIR__, 2);
require $root . '/lib/Notifications/Traits/Dependency_Injector.php';
require $root . '/lib/Notifications/Traits/PublishPress_Module.php';
require $root . '/lib/Notifications/Workflow/Workflow.php';
require $root . '/lib/Notifications/Workflow/Step/Action/Notification.php';
require $root . '/modules/improved-notifications/improved-notifications.php';
$workflow = new \PublishPress\Notifications\Workflow\Workflow((object) ['ID' => 12]);
foreach (['', '1', '0'] as $enabled) {
    $events = [];
    $workflow->run(['params' => ['post_id' => 7]]);
    if (($events !== []) !== ($enabled !== '0')) { throw new RuntimeException('Only disabled workflows should be skipped.'); }
}
$action = (new ReflectionClass(\PublishPress\Notifications\Workflow\Step\Action\Notification::class))->newInstanceWithoutConstructor();
$action->send_notifications($workflow, true); // Disabled queued workflows must stop before recipient/content processing.
$module = (new ReflectionClass(PP_Improved_Notifications::class))->newInstanceWithoutConstructor();
foreach ([[12, true, 'valid', '0', 'redirect'], [12, true, 'valid', '1', 'redirect'], [99, true, 'valid', '0', 'denied'], [12, false, 'valid', '0', 'denied'], [12, true, 'bad', '0', 'nonce'], [12, true, 'valid', 'invalid', 'denied']] as [$id, $allowed, $nonce, $value, $expected]) {
    $_GET = ['workflow_id' => $id, 'enabled' => $value, '_wpnonce' => $nonce];
    $before = count($writes);
    try { $module->toggle_workflow(); } catch (RuntimeException $error) {
        if ($error->getMessage() !== $expected) { throw $error; }
    }
    if ((count($writes) > $before) !== ($expected === 'redirect')) { throw new RuntimeException('Only authorized workflow toggles may persist.'); }
}
echo "PASS: enabled defaults, disabled sync/queued delivery, toggle saves, nonce, type, and permissions\n";
