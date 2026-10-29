<?php
/** Usage: php tests/regression/user-metadata-recipients.php */
namespace PublishPress\Notifications\Workflow\Step\Receiver {
    interface Receiver_Interface {}
    class Simple_Checkbox { protected function is_selected($id) { return $GLOBALS['selected']; } }
}
namespace {
    function get_post_meta($id, $key, $single) { return $id === 1 ? '_pp_editorial_meta_user_manufacturer' : [7, [8, 7, 99, 'bad', 0], ['nested' => [7]]]; }
    function get_user_by($field, $id) { return $id === 99 ? false : (object) ['ID' => $id]; }
    function is_user_member_of_blog($id) { return $id !== 8; }
    require dirname(__DIR__, 2) . '/lib/Notifications/Workflow/Step/Receiver/User_Metadata.php';
    $receiver = (new ReflectionClass(\PublishPress\Notifications\Workflow\Step\Receiver\User_Metadata::class))->newInstanceWithoutConstructor();
    $selected = true;
    $result = $receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => ['post_id' => 12]]);
    if ($result !== [['receiver' => 7, 'group' => 'user_metadata']]) { throw new RuntimeException('Only unique existing site users may be recipients, preserving their channel preference.'); }
    $selected = false;
    if ($receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => ['post_id' => 12]]) !== []) { throw new RuntimeException('Disabled receiver must have no effect.'); }
    echo "PASS: scalar/array IDs, deduplication, deleted users, site membership, invalid values, and disabled receiver\n";
}
