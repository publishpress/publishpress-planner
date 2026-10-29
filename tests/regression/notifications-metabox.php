<?php
/** Usage: php tests/regression/notifications-metabox.php */
function current_user_can($capability) { return true; }
function add_meta_box(...$args) { $GLOBALS['boxes'][] = $args; }
function __($text, $domain = '') { return $text; }
require dirname(__DIR__, 2) . '/common/php/class-module.php';
require dirname(__DIR__, 2) . '/lib/Notifications/Traits/Dependency_Injector.php';
require dirname(__DIR__, 2) . '/modules/notifications/notifications.php';

class MetaboxNotifications extends PP_Notifications
{
    public function __construct() {
        $this->module = (object) ['options' => (object) [], 'post_type_support' => 'pp_notification'];
    }
    public function get_post_types_for_module($module) { return ['post']; }
    public function clean_post_type_options($types = [], $support = null) { return $types; }
    protected function should_show_notifications_meta_box($post = null) { return true; }
}

$notifications = new MetaboxNotifications();
$GLOBALS['boxes'] = [];
$notifications->add_post_meta_box('post');
$notifications->module->options->enable_metabox = '0';
$notifications->add_post_meta_box('post');
if ($GLOBALS['boxes'] !== []) {
    throw new RuntimeException('Missing and disabled options must hide the metabox.');
}
$notifications->module->options->enable_metabox = '1';
$notifications->add_post_meta_box('post');
$notifications->add_post_meta_box('page');
if (count($GLOBALS['boxes']) !== 1 || $GLOBALS['boxes'][0][0] !== 'publishpress-notifications') {
    throw new RuntimeException('The enabled metabox must respect supported post types.');
}
foreach ([['enable_metabox' => '1'], ['enable_metabox' => '0'], []] as $options) {
    $saved = $notifications->settings_validate($options);
    $expected = empty($options['enable_metabox']) ? '0' : '1';
    if ($saved['enable_metabox'] !== $expected) {
        throw new RuntimeException('The metabox setting did not round-trip.');
    }
}
echo "PASS: disabled default, enabled registration, post types, and settings saves\n";
