<?php
/** Usage: php tests/regression/metadata-change-workflows.php */
namespace PublishPress\Notifications\Workflow\Step\Event {
    class Base {
        protected $attr_prefix = 'event';
        protected $name = 'metadata_update';
        protected function should_ignore_event_on_query($args) { return false; }
    }
}
namespace PublishPress\Notifications\Workflow\Step\Event\Filter {
    class Base { public function __construct($step) {} }
}
namespace {
    function get_post_type($id) { return $id === 12 ? 'post' : 'psppnotif_workflow'; }
    function get_current_user_id() { return 7; }
    function do_action($hook, $args) { $GLOBALS['events'][] = $args; }
    $root = dirname(__DIR__, 2);
    require $root . '/lib/Notifications/Workflow/Step/Event/Post_TaxonomyUpdate.php';
    require $root . '/lib/Notifications/Workflow/Step/Event/Filter/Metadata_Key.php';
    require $root . '/lib/Notifications/Workflow/Step/Event/Metadata_Update.php';
    $event = (new ReflectionClass(\PublishPress\Notifications\Workflow\Step\Event\Metadata_Update::class))->newInstanceWithoutConstructor();
    $events = [];
    foreach (['_edit_lock', '_edit_last', '_psppno_metadata_key', '_ppnotif_status'] as $key) { $event->metadata_changed(1, 12, $key, 'value'); }
    $event->metadata_changed(1, 99, 'assignment', 7);
    if ($events !== []) { throw new RuntimeException('Editor locks and workflow bookkeeping must not generate notifications.'); }
    $event->metadata_changed(1, 12, '_pp_editorial_meta_user_assignment', 7);
    if (count($events) !== 1 || $events[0]['params'] !== ['post_id' => 12, 'meta_key' => '_pp_editorial_meta_user_assignment']) { throw new RuntimeException('Metadata changes must retain their exact post and field context.'); }
    $query = $event->filter_running_workflow_query_args(['meta_query' => [], 'needs_event_filter' => true], $events[0]);
    if (isset($query['needs_event_filter']) || $query['meta_query'][1]['value'] !== '_pp_editorial_meta_user_assignment') { throw new RuntimeException('Only enabled metadata workflows for the exact field may match.'); }
    echo "PASS: metadata events, exact key matching, actor/post context, and recursion exclusions\n";
}
