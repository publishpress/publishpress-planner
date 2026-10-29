<?php
/** Usage: php tests/regression/email-metadata-recipients.php */
namespace PublishPress\Notifications\Workflow\Step\Receiver {
    interface Receiver_Interface {}
    class Simple_Checkbox { protected function is_selected($id) { return $GLOBALS['selected']; } }
}
namespace {
    function get_post_meta($id, $key, $single) { return $id === 1 ? 'customer_email' : $GLOBALS['values']; }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    require dirname(__DIR__, 2) . '/lib/Notifications/Workflow/Step/Receiver/Email_Metadata.php';
    $receiver = (new ReflectionClass(\PublishPress\Notifications\Workflow\Step\Receiver\Email_Metadata::class))->newInstanceWithoutConstructor();
    $selected = true;
    $values = ['first@example.test,second@example.test', ['FIRST@example.test', 'invalid', 'third@example.test'], ['nested' => ['invalid']]];
    $result = $receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => ['post_id' => 12]]);
    if (count($result) !== 3 || array_column($result, 'channel') !== ['email', 'email', 'email']) { throw new RuntimeException('Only unique valid email addresses may be recipients.'); }
    $selected = false;
    if ($receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => ['post_id' => 12]]) !== []) { throw new RuntimeException('Disabled metadata recipients must have no effect.'); }
    echo "PASS: scalar, array, delimited, duplicate, invalid, nested, and disabled recipients\n";
}
