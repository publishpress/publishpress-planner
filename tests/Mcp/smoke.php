<?php

/** Run only on an isolated planner_mcp_* database with WordPress and released Adapter installed. */

require $argv[1];
if (strpos(DB_NAME, 'planner_mcp_') !== 0) {
    throw new RuntimeException('Isolated Planner database required.');
}
require $argv[2] . '/vendor/autoload.php';
$adapterInfo = get_file_data($argv[2] . '/mcp-adapter.php', ['Version' => 'Version']);
$adapterVersion = $adapterInfo['Version'];
wp_set_current_user(1);
foreach (PublishPress()->modules as $id => $module) {
    if (get_option(PublishPress()->options_group . $id . '_options', null) === null) {
        PublishPress()->update_all_module_options($id, (array) $module->options);
    }
}
publishPressRegisterImprovedNotificationsPostTypes();
$assertions = 0;
function check($value, $message)
{
    global $assertions;
    ++$assertions;
    if (!$value) {
        throw new RuntimeException($message);
    }
}
function callPlanner($name, $input = [])
{
    $ability = wp_get_ability('publishpress-planner/' . $name);
    check($ability !== null, 'Missing ability ' . $name);
    $tool = \WP\MCP\Domain\Tools\McpTool::fromAbility($ability);
    check(!is_wp_error($tool), 'Adapter rejected ' . $name);
    return $tool->execute($input);
}
function success($result, $message)
{
    check(!is_wp_error($result), $message . (is_wp_error($result) ? ': ' . $result->get_error_message() : ''));
    return $result;
}
$features = success(callPlanner('list-features'), 'Features');
foreach ($features['features'] as $feature) {
    $name = $feature['module'];
    $state = success(callPlanner('get-plugin-settings', ['module' => $name]), 'Read ' . $name);
    $fields = $state['schema']['properties'];
    foreach ($fields as $key => $field) {
        $value = $state['settings']->$key;
        $valid = \PublishPress\Core\Mcp\Registry::validate($value, $field);
        success($valid, 'Native current/default incompatible with discovery ' . $name . '.' . $key);
        $before = get_option(PublishPress()->options_group . $name . '_options');
        $input = ['module' => $name, 'settings' => (object) [$key => $value], 'expected_hash' => $state['expected_hash'], 'dry_run' => true];
        success(callPlanner('update-plugin-settings', $input), 'Preview ' . $name . '.' . $key);
        check($before == get_option(PublishPress()->options_group . $name . '_options'), 'Preview wrote ' . $name);
        $input['dry_run'] = false;
        $state = success(callPlanner('update-plugin-settings', $input), 'Save ' . $name . '.' . $key);
        $read = success(callPlanner('get-plugin-settings', ['module' => $name]), 'Read-back ' . $name);
        check($read['settings']->$key === $state['settings']->$key || $read['settings']->$key == $state['settings']->$key, 'Read-back differs ' . $key);
        $bad = $input;
        $bad['expected_hash'] = $state['expected_hash'];
        $bad['settings'] = (object) [$key => $field['type'] === 'boolean' ? 'false' : ($field['type'] === 'integer' ? '12' : true)];
        check(is_wp_error(callPlanner('update-plugin-settings', $bad)), 'Malformed field accepted ' . $name . '.' . $key);
    }
}
$before = get_option('publishpress_notifications_options');
$state = callPlanner('get-plugin-settings', ['module' => 'notifications']);
$bad = callPlanner('update-plugin-settings', ['module' => 'notifications', 'expected_hash' => $state['expected_hash'], 'settings' => (object) ['notify_author_by_default' => false, 'unknown' => true]]);
check(is_wp_error($bad) && $before == get_option('publishpress_notifications_options'), 'Mixed invalid patch wrote');
$deny = static function ($caps, $cap) { return $cap === 'manage_options' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $deny, PHP_INT_MAX, 2);
check(is_wp_error(callPlanner('get-plugin-settings', ['module' => 'calendar'])), 'Administrator bypassed denied capability');
remove_filter('map_meta_cap', $deny, PHP_INT_MAX);
$fields = success(callPlanner('get-workflow-fields'), 'Workflow discovery');
$workflow = success(callPlanner('save-workflow', ['title' => 'MCP fixture', 'configuration' => (object) ['content_main' => (object) ['subject' => 'Quoted "subject"', 'body' => '<p>Body</p>']]]), 'Workflow create');
check($workflow['configuration']->content_main['subject'] === 'Quoted "subject"', 'Workflow subject changed');
$stale = callPlanner('save-workflow', ['id' => $workflow['id'], 'title' => 'bad', 'expected_hash' => str_repeat('0', 64)]);
check(is_wp_error($stale), 'Stale workflow accepted');
$fixturePost = success(callPlanner('save-content', ['post_type' => 'post', 'changes' => (object) ['title' => 'Planner MCP fixture', 'status' => 'draft']]), 'Content create');
check($fixturePost['id'] > 0, 'No content ID');
$followers = success(callPlanner('get-post-followers', ['post_id' => $fixturePost['id']]), 'Followers');
$followers = success(callPlanner('update-post-followers', ['post_id' => $fixturePost['id'], 'users' => [1], 'expected_hash' => $followers['expected_hash']]), 'Follower save');
check(in_array(1, $followers['users'], true), 'Native user follower did not round trip');
foreach (['calendar', 'content_board', 'content_overview'] as $view) {
    $kinds = $view === 'calendar' ? ['filters'] : ['columns', 'filters'];
    foreach ($kinds as $kind) {
        $config = success(callPlanner('get-view-configuration', ['view' => $view, 'kind' => $kind]), 'View discovery ' . $view);
        success(callPlanner('save-view-configuration', ['view' => $view, 'kind' => $kind, 'items' => $config['items'], 'expected_hash' => $config['expected_hash']]), 'View save ' . $view);
    }
}
// Explicit native filter reads/queries must preserve browser/global request state.
foreach (['calendar', 'content_board', 'content_overview'] as $view) {
    $preferences = success(callPlanner('get-view-preferences', ['view' => $view]), 'View preferences ' . $view);
    success(callPlanner('save-view-preferences', ['view' => $view, 'filters' => (object) ['me_mode' => true], 'expected_hash' => $preferences['expected_hash']]), 'View preference save ' . $view);
    $globalPost = $GLOBALS['post'] ?? null;
    success(callPlanner('list-view-content', ['view' => $view]), 'Native content query ' . $view);
    check(($GLOBALS['post'] ?? null) === $globalPost, 'Native view query leaked global post');
}
foreach (['editorial_metadata', 'editorial_comments', 'dashboard', 'debug'] as $feature) {
    $state = callPlanner('get-plugin-settings', ['module' => $feature]);
    if (!$state['enabled']) {
        success(callPlanner('set-feature-enabled', ['module' => $feature, 'enabled' => true, 'expected_hash' => $state['expected_hash']]), 'Enable ' . $feature);
    }
    if (!$state['enabled']) { PublishPress()->$feature->init(); }
}
// Exercise all native editorial field value types through the released Adapter.
foreach (['text' => 'A <b>value</b>', 'paragraph' => "Line one\nLine two", 'number' => 7, 'date' => '2026-11-10 15:30', 'checkbox' => true, 'location' => 'Paris', 'user' => 1, 'select' => 'one'] as $type => $value) {
    $definition = ['name' => 'MCP ' . $type, 'slug' => 'mcp-' . $type, 'type' => $type, 'post_types' => ['post']];
    if ($type === 'user') { $definition['user_role'] = ['administrator', 'editor']; }
    if ($type === 'select') {
        $definition += ['select_type' => '', 'select_default' => 1, 'select_options' => (object) ['values' => ['one', 'two'], 'labels' => ['One', 'Two']]];
    }
    $term = success(callPlanner('save-editorial-field', ['fields' => (object) $definition]), 'Editorial definition ' . $type);
    $state = success(callPlanner('get-editorial-values', ['post_id' => $fixturePost['id']]), 'Editorial values ' . $type);
    $patch = ['post_id' => $fixturePost['id'], 'expected_hash' => $state['expected_hash'], 'values' => (object) ['mcp-' . $type => $value], 'dry_run' => true];
    $preview = success(callPlanner('update-editorial-values', $patch), 'Editorial preview ' . $type);
    $patch['dry_run'] = false;
    $saved = success(callPlanner('update-editorial-values', $patch), 'Editorial value save ' . $type);
    check($saved['values']->{'mcp-' . $type} === $preview['values']->{'mcp-' . $type}, 'Editorial preview/readback mismatch ' . $type);
}
$state = success(callPlanner('get-content', ['id' => $fixturePost['id']]), 'Content revision');
$beforeDate = get_post_field('post_date', $fixturePost['id']);
$invalidBatch = callPlanner('move-calendar-items', ['items' => [
    (object) ['id' => $fixturePost['id'], 'date' => '2026-12-01', 'expected_hash' => $state['expected_hash']],
    (object) ['id' => $fixturePost['id'] + 50000, 'date' => '2026-12-02', 'expected_hash' => str_repeat('0', 64)]]]);
check(is_wp_error($invalidBatch) && get_post_field('post_date', $fixturePost['id']) === $beforeDate, 'Mixed invalid calendar batch wrote');
success(callPlanner('move-calendar-items', ['items' => [(object) ['id' => $fixturePost['id'], 'date' => '2026-12-01', 'expected_hash' => $state['expected_hash']]]]), 'Calendar native move');
$board = callPlanner('get-content', ['id' => $fixturePost['id'], 'view' => 'content_board']);
$preview = success(callPlanner('move-board-item', ['id' => $fixturePost['id'], 'status' => 'future', 'number' => 2, 'period' => 'weeks', 'expected_hash' => $board['expected_hash'], 'dry_run' => true]), 'Board schedule preview');
check(get_post_status($fixturePost['id']) === 'draft', 'Board preview wrote');
$board = success(callPlanner('move-board-item', ['id' => $fixturePost['id'], 'status' => 'future', 'number' => 2, 'period' => 'weeks', 'expected_hash' => $board['expected_hash']]), 'Board native schedule');
check($board['status'] === 'future', 'Board schedule status failed');
$comment = success(callPlanner('save-editorial-comment', ['post_id' => $fixturePost['id'], 'content' => "A quoted 'note' and backslash \\path"]), 'Editorial comment');
check(strpos($comment['content'], '\\path') !== false, 'Comment backslash lost');
$reply = success(callPlanner('save-editorial-comment', ['post_id' => $fixturePost['id'], 'parent_id' => $comment['id'], 'content' => 'Reply']), 'Editorial reply');
$parentDeletion = callPlanner('delete-editorial-comment', ['id' => $comment['id'], 'expected_hash' => $comment['expected_hash']]);
check(is_wp_error($parentDeletion), 'Parent comment with replies deleted');
success(callPlanner('delete-editorial-comment', ['id' => $reply['id'], 'expected_hash' => $reply['expected_hash']]), 'Delete reply');
$followers = callPlanner('get-post-followers', ['post_id' => $fixturePost['id']]);
$followers = success(callPlanner('update-post-followers', ['post_id' => $fixturePost['id'], 'emails' => ['synthetic@example.test'], 'expected_hash' => $followers['expected_hash']]), 'Authorized email recipients');
check($followers['emails'] === ['synthetic@example.test'], 'Native email recipient read-back');
$denyPost = static function ($caps, $cap, $actor, $args) use ($fixturePost) {
    return in_array($cap, ['edit_post', 'read_post'], true) && (int) ($args[0] ?? 0) === $fixturePost['id'] ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $denyPost, PHP_INT_MAX, 4);
foreach (['get-content' => ['id' => $fixturePost['id']], 'get-editorial-values' => ['post_id' => $fixturePost['id']], 'get-post-followers' => ['post_id' => $fixturePost['id']], 'list-editorial-comments' => ['post_id' => $fixturePost['id']]] as $action => $args) {
    check(is_wp_error(callPlanner($action, $args)), 'Denied administrator object bypass ' . $action);
}
remove_filter('map_meta_cap', $denyPost, PHP_INT_MAX);
// All registered fields must round-trip through the actual native step objects.
$discovery = callPlanner('get-workflow-fields');
$configuration = (array) $discovery['defaults'];
$category = wp_insert_term('MCP category', 'category', ['slug' => 'mcp-category']);
$configuration['event_content_category']['categories'] = ['mcp-category'];
$configuration['event_content_category']['enabled'] = true;
$configuration['event_content_taxonomy']['terms'] = [(int) $category['term_id']];
$configuration['receiver_user'] = ['enabled' => true, 'users' => [1]];
$configuration['receiver_role'] = ['enabled' => true, 'roles' => ['editor']];
$configuration['event_post_save'] = ['enabled' => true, 'from' => ['new'], 'to' => ['draft']];
$configuration['content_main'] = ['subject' => 'Native subject', 'body' => 'Native body \\path [psppno_post title]'];
foreach ($configuration as $name => &$fields) {
    if (is_array($fields) && isset($fields['amount'])) {
        $fields['amount'] = 2;
        $fields['unit'] = 'day';
    }
}
unset($fields);
$full = success(callPlanner('save-workflow', ['title' => 'Full configuration fixture', 'configuration' => (object) $configuration, 'status' => 'publish']), 'Full native workflow');
check($full['configuration']->content_main['body'] === $configuration['content_main']['body'], 'Workflow backslash lost');
$settings = callPlanner('get-plugin-settings', ['module' => 'improved_notifications']);
$defaults = success(callPlanner('update-plugin-settings', ['module' => 'improved_notifications', 'expected_hash' => $settings['expected_hash'], 'settings' => (object) ['default_channels' => (object) [$full['id'] => 'email']]]), 'Per-workflow default channel object');
check(get_option('publishpress_improved_notifications_options')->default_channels[$full['id']] === 'email', 'Native default channel map differs');
$prefs = success(callPlanner('get-notification-preferences', ['user_id' => 1]), 'User channels');
success(callPlanner('update-notification-preferences', ['user_id' => 1, 'expected_hash' => $prefs['expected_hash'], 'preferences' => [(object) ['workflow_id' => $full['id'], 'channel' => 'mute']]]), 'Mute user workflow');
$prefs = callPlanner('get-notification-preferences', ['user_id' => 1]);
success(callPlanner('update-notification-preferences', ['user_id' => 1, 'expected_hash' => $prefs['expected_hash'], 'preferences' => [(object) ['workflow_id' => $full['id'], 'channel' => 'default']]]), 'Clear user channel override');
check(get_user_meta(1, 'psppno_workflow_channel_' . $full['id'], true) === '', 'Default channel not cleared');
check(is_wp_error(callPlanner('save-workflow', ['title' => 'Unsafe metadata', 'configuration' => (object) ['content_main' => (object) ['body' => '[psppno_post "meta:_secret_token"]']]])), 'Secret metadata template accepted');
check(is_wp_error(callPlanner('save-workflow', ['title' => 'Foreign shortcode', 'configuration' => (object) ['content_main' => (object) ['body' => '[unknown_shortcode]']]])), 'Unrelated shortcode accepted');
$export = success(callPlanner('export-workflows', ['ids' => [$full['id']]]), 'Workflow export');
success(callPlanner('import-workflows', ['items' => $export['items'], 'dry_run' => true]), 'Workflow import preview');
// Native UI nonce guards continue protecting form-bound setters.
$steps = \PublishPress\Core\Mcp\WorkflowFields::definitions();
$step = $steps['content_main']['step'];
$prior = get_post_meta($full['id'], $step::META_KEY_SUBJECT, true);
$oldPost = $_POST;
$_POST = ['publishpress_notif' => ['content_main' => ['subject' => 'must not save']]];
$step->save_metabox_data($full['id'], get_post($full['id']));
check(get_post_meta($full['id'], $step::META_KEY_SUBJECT, true) === $prior, 'Missing native nonce bypass');
$_POST['publishpress_notif_metabox_events_nonce'] = wp_create_nonce('publishpress_notif_save_metabox');
$step->save_metabox_data($full['id'], get_post($full['id']));
check(get_post_meta($full['id'], $step::META_KEY_SUBJECT, true) === 'must not save', 'Valid native nonce did not save');
$_POST = $oldPost;
$note = success(callPlanner('get-dashboard-note'), 'Dashboard note');
success(callPlanner('save-dashboard-note', ['content' => '<b>Plain note</b>', 'expected_hash' => $note['expected_hash']]), 'Save dashboard note');
$debug = success(callPlanner('get-diagnostics'), 'Safe diagnostics');
success(callPlanner('clear-debug-log', ['expected_hash' => $debug['expected_hash'], 'dry_run' => true]), 'Debug clear preview');
$calendar = callPlanner('get-plugin-settings', ['module' => 'calendar']);
$secret = PublishPress()->calendar->module->options->ics_secret_key;
success(callPlanner('rotate-calendar-feed-key', ['expected_hash' => $calendar['expected_hash'], 'dry_run' => true]), 'Feed rotation preview');
check(PublishPress()->calendar->module->options->ics_secret_key === $secret, 'Feed preview rotated key');
foreach (wp_get_abilities() as $name => $ability) {
    if (strpos($name, 'publishpress-planner/') !== 0) {
        continue;
    }
    check($ability->get_meta()['public'] === false && $ability->get_meta()['show_in_rest'] === false, 'Public ability exposure');
}
if (getenv('PLANNER_MCP_MODE') === 'pro') {
    check($discovery['pro_active'], 'Pro edition not active');
    $state = callPlanner('get-plugin-settings', ['module' => 'reminders']);
    success(callPlanner('update-plugin-settings', ['module' => 'reminders', 'expected_hash' => $state['expected_hash'], 'settings' => (object) ['before_publishing_check_interval' => 'never']]), 'Disable native reminder schedule');
    check(wp_next_scheduled('publishpress_before_publishing_notifications') === false, 'Reminder schedule not cancelled');
    $prefs = callPlanner('get-notification-preferences', ['user_id' => 1]);
    success(callPlanner('update-notification-preferences', ['user_id' => 1, 'expected_hash' => $prefs['expected_hash'], 'preferences' => [(object) ['workflow_id' => $full['id'], 'channel' => 'slack', 'slack_channel' => 'synthetic@example.test']]]), 'Native Slack preference');
    check(get_user_meta(1, 'psppno_workflow_' . $full['id'] . '_slack_channel', true) === 'synthetic@example.test', 'Slack native read-back');
    check(is_wp_error(callPlanner('test-slack-connection', ['dry_run' => true])), 'Unconfigured Slack should fail without network request');
}
require __DIR__ . '/edge-cases.php';

echo 'Planner ', getenv('PLANNER_MCP_MODE'), ': ', $assertions, " assertions passed through Adapter ", $adapterVersion, ", WordPress ", get_bloginfo('version'), ".\n";
