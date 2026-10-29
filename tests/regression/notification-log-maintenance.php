<?php
/** Usage: php tests/regression/notification-log-maintenance.php */
namespace PublishPress\NotificationsLog {
    class NotificationsLogModel {
        const COMMENT_TYPE = 'notification'; const COMMENT_ACTIVE = 'active'; const META_NOTIF_STATUS = '_ppnotif_status';
    }
}
namespace {
    class PP_Module {}
    function check_ajax_referer($action, $key) { if (($_POST[$key] ?? '') !== 'valid') { throw new RuntimeException('nonce'); } }
    function current_user_can($cap) { return $GLOBALS['allowed']; }
    function get_comments($args) { $GLOBALS['query'] = $args; return $GLOBALS['ids']; }
    function wp_delete_comment($id, $force) { $GLOBALS['deleted'][] = $id; return true; }
    function wp_send_json_success($data) { $GLOBALS['response'] = $data; }
    function wp_send_json_error($data, $status) { $GLOBALS['response'] = $status; }
    require dirname(__DIR__, 2) . '/modules/maintenance/maintenance.php';
    $module = (new ReflectionClass(PP_Maintenance::class))->newInstanceWithoutConstructor();
    $_POST = ['nonce' => 'valid']; $allowed = true; $ids = range(1, 50); $deleted = [];
    $module->clear_log_batch();
    if ($response !== ['deleted' => 50, 'more' => true] || $query['number'] !== 50 || $query['type'] !== 'notification' || $query['meta_query'][0]['value'] !== 'scheduled') { throw new RuntimeException('Cleanup must be bounded, scoped to logs, and exclude scheduled deliveries.'); }
    $ids = [51]; $module->clear_log_batch();
    if ($response['more'] !== false) { throw new RuntimeException('The last batch must finish cleanup.'); }
    $allowed = false; $before = count($deleted); $module->clear_log_batch();
    if ($response !== 403 || count($deleted) !== $before) { throw new RuntimeException('Non-administrators must not delete logs.'); }
    $_POST['nonce'] = 'invalid';
    try { $module->clear_log_batch(); } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'nonce') { throw $error; }
    }
    if (count($deleted) !== $before) { throw new RuntimeException('Invalid nonces must not delete logs.'); }
    echo "PASS: bounded batches, log-only query, scheduled preservation, completion, permissions, and nonce\n";
}
