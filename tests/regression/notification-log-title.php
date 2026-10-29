<?php
/** Usage: php tests/regression/notification-log-title.php */
namespace PublishPress\NotificationsLog {
    class NotificationsLogModel {
        public $workflowId = 12;
        public $workflowTitle = '<Custom notification>';
        public $event = 'transition_post_status';
        public $userId = 7;
        public $status = 'sent';
        public function __construct($item) {}
        public function switchToTheBlog() {}
        public function restoreCurrentBlog() {}
    }
}
namespace {
    class WP_List_Table {}
    function __($text, $domain = '') { return $text; }
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
    function esc_html($text) { return esc_attr($text); }
    function esc_url($url) { return $url; }
    function get_user_by($field, $id) { return (object) ['user_nicename' => 'editor']; }
    function is_wp_error($value) { return false; }
    function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
    function apply_filters($hook, $value, ...$args) { return $hook === 'publishpress_notifications_event_label' ? 'When the content is moved to a new status' : $value; }
    $root = dirname(__DIR__, 2);
    require $root . '/lib/Notifications/Traits/Dependency_Injector.php';
    require $root . '/modules/notifications-log/library/NotificationsLogTable.php';
    $table = (new ReflectionClass(\PublishPress\NotificationsLog\NotificationsLogTable::class))->newInstanceWithoutConstructor();
    $html = $table->column_default((object) [], 'event');
    if (strpos($html, '<strong><a') !== 0 || strpos($html, '&lt;Custom notification&gt;') === false || strpos($html, '<Custom notification>') !== false) {
        throw new RuntimeException('The escaped workflow title must be the primary link.');
    }
    if (strpos($html, 'When the content') < strpos($html, 'class="muted"') || $table->get_columns()['event'] !== 'Notification') {
        throw new RuntimeException('Event details must remain available below the notification title.');
    }
    echo "PASS: prominent escaped workflow title and secondary event details\n";
}
