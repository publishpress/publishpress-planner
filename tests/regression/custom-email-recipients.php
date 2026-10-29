<?php
/** Usage: php tests/regression/custom-email-recipients.php */
namespace PublishPress\Notifications\Workflow\Step\Receiver {
    interface Receiver_Interface {}
    class Simple_Checkbox { protected function is_selected($id) { return true; } }
}
namespace {
    function get_post_meta($id, $key, $single) {
        if ($id === 1) { return "editor@example.test, [psppno_post meta:customer_email]\ninvalid\n[unrelated_shortcode]"; }
        return 'customer@example.test';
    }
    function is_email($value) { return filter_var($value, FILTER_VALIDATE_EMAIL); }
    require dirname(__DIR__, 2) . '/lib/Notifications/Workflow/Step/Receiver/Custom_Email.php';
    $receiver = (new ReflectionClass(\PublishPress\Notifications\Workflow\Step\Receiver\Custom_Email::class))->newInstanceWithoutConstructor();
    $result = $receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => ['post_id' => 12]]);
    if (array_column($result, 'receiver') !== ['editor@example.test', 'customer@example.test']) { throw new RuntimeException('Literal and metadata addresses must resolve without executing other shortcodes.'); }
    $result = $receiver->filter_workflow_receivers([], (object) ['ID' => 1], ['params' => []]);
    if (array_column($result, 'receiver') !== ['editor@example.test']) { throw new RuntimeException('Missing post context must not expand metadata.'); }
    echo "PASS: literal emails, metadata placeholders, unrelated shortcodes, and missing context\n";
}
