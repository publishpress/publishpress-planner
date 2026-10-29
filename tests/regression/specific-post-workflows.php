<?php
/** Usage: php tests/regression/specific-post-workflows.php */
namespace PublishPress\Notifications\Workflow\Step\Event_Content {
    class Base {}
}
namespace PublishPress\Notifications\Workflow\Step\Event_Content\Filter {
    class Base {
        protected $step_name = 'event_content_posts';
        public function update_metadata_array($id, $key, $values) { $GLOBALS['saved'] = array_values($values); }
    }
}
namespace {
    function sanitize_text_field($text) { return $text; }
    function wp_unslash($value) { return $value; }
    function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
    function current_user_can($cap, $id) { return $id === 12; }
    function wp_parse_id_list($value) { return array_unique(array_map('intval', explode(',', $value))); }
    $root = dirname(__DIR__, 2);
    require $root . '/lib/Notifications/Workflow/Step/Event_Content/Posts.php';
    require $root . '/lib/Notifications/Workflow/Step/Event_Content/Filter/Posts.php';
    $filter = new \PublishPress\Notifications\Workflow\Step\Event_Content\Filter\Posts();
    foreach ([12, 20] as $id) {
        $query = $filter->get_run_workflow_query_args(['meta_query' => []], ['params' => ['post_id' => $id]]);
        $rule = $query['meta_query'][0];
        if ($rule[2]['value'] !== $id || $rule[2]['type'] !== 'NUMERIC' || $rule[0]['compare'] !== 'NOT EXISTS') {
            throw new RuntimeException('Specific-post filters must match exact IDs and preserve workflows without this restriction.');
        }
    }
    $_POST = ['publishpress_notif_metabox_events_nonce' => 'valid', 'publishpress_notif' => ['event_content_posts_filters' => ['post_ids' => '12,20,12']]];
    $filter->save_metabox_data(1, (object) ['ID' => 1]);
    if ($saved !== [12]) { throw new RuntimeException('Only editable unique post IDs may be selected.'); }
    $_POST['publishpress_notif_metabox_events_nonce'] = 'invalid';
    $_POST['publishpress_notif']['event_content_posts_filters']['post_ids'] = '';
    $filter->save_metabox_data(1, (object) ['ID' => 1]);
    if ($saved !== [12]) { throw new RuntimeException('Invalid nonces must not erase selected posts.'); }
    echo "PASS: exact post-ID matching, unrestricted workflows, editable ID saves, and nonce checks\n";
}
