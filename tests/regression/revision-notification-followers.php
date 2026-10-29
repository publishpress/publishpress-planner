<?php
/** Usage: php tests/regression/revision-notification-followers.php */
namespace PublishPress\Legacy {
    class Util { public static function getRequestMethod() { return 'GET'; } }
}
namespace PublishPress\Notifications\Workflow\Step\Receiver {
    interface Receiver_Interface {}
    class Simple_Checkbox { public function is_selected($id) { return true; } }
}
namespace {
    function get_post($id) { return $id === 24 ? (object) ['ID' => 24] : false; }
    function apply_filters($hook, $value, ...$args) { return $value; }
    function __($text, $domain = '') { return $text; }
    require dirname(__DIR__, 2) . '/lib/Notifications/Workflow/Step/Receiver/Follower.php';
    $publishpress = (object) ['notifications' => new class {
        public function get_roles_to_notify($id, $format) { return []; }
        public function get_emails_to_notify($id) { return []; }
        public function get_users_to_notify($id, $format) { return $id === 24 ? [21, 22, 23] : [11, 12, 13]; }
    }];
    $receiver = (new \ReflectionClass(\PublishPress\Notifications\Workflow\Step\Receiver\Follower::class))->newInstanceWithoutConstructor();
    $workflow = (object) ['ID' => 1];
    foreach ([[['post_id' => 12], [11, 12, 13]], [['post_id' => 12, 'revision_id' => 24], [21, 22, 23]], [['post_id' => 12, 'revision_id' => 99], []]] as [$params, $expected]) {
        $result = $receiver->filter_workflow_receivers([], $workflow, ['params' => $params]);
        if (array_column($result, 'receiver') !== $expected) {
            throw new \RuntimeException('Revision events must use revision followers; ordinary posts must retain their followers.');
        }
    }
    echo "PASS: ordinary post followers, independent revision followers, and missing revisions\n";
}
