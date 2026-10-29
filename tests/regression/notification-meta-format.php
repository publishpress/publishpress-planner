<?php
/** Usage: php tests/regression/notification-meta-format.php */
function get_post_meta($id, $name, $single) { return $GLOBALS['values'][$name] ?? ''; }
function get_user_by($field, $id) { return $id === 7 ? (object) ['display_name' => 'Editor', 'user_email' => 'editor@example.test'] : false; }
function get_option($name) { return 'Y-m-d'; }
function date_i18n($format, $timestamp) { return gmdate($format, $timestamp); }
function is_wp_error($value) { return false; }
function sanitize_key($value) { return $value; }
function apply_filters($hook, $value, ...$args) { return $hook === 'publishpress_notif_meta_format_priority' ? 'Priority ' . $value : $value; }
$root = dirname(__DIR__, 2);
require $root . '/lib/Notifications/Traits/Dependency_Injector.php';
require $root . '/lib/Notifications/Traits/PublishPress_Module.php';
require $root . '/lib/Notifications/Shortcodes.php';
class FormattedMetaProbe extends \PublishPress\Notifications\Shortcodes {
    public function __construct() {}
    public function get_service($name) { return (object) []; }
    public function format($attrs) { return $this->get_post_data((object) ['ID' => 12], $attrs); }
}
$values = ['editor' => 7, 'deleted' => 99, 'deadline' => 0, 'bad-date' => 'not a date', 'priority' => '0'];
$probe = new FormattedMetaProbe();
foreach ([
    [['meta:editor', 'type' => 'user', 'format' => 'display_name'], 'Editor'],
    [['meta:editor', 'type' => 'user', 'format' => 'user.user_email'], 'editor@example.test'],
    [['meta:deleted', 'type' => 'user'], ''],
    [['meta:deadline', 'type' => 'date', 'format' => 'Y-m-d'], '1970-01-01'],
    [['meta:bad-date', 'type' => 'date'], ''],
    [['meta:priority'], '0'],
    [['meta:priority', 'type' => 'filter', 'format' => 'priority'], 'Priority 0'],
] as [$attrs, $expected]) {
    if ($probe->format($attrs) !== $expected) { throw new RuntimeException('Incorrect formatted metadata: ' . json_encode($attrs)); }
}
echo "PASS: user names/emails, missing users, dates, invalid dates, zero values, and custom filters\n";
