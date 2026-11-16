<?php

/** Additional native failure, lifecycle and privacy fixtures; loaded by smoke.php. */

use PublishPress\NotificationsLog\NotificationsLogModel as PlannerLog;
use PublishPress\AsyncNotifications\WPCronAdapter as PlannerQueue;

$state = callPlanner('get-plugin-settings', ['module' => 'calendar']);
$beforeOption = get_option('publishpress_calendar_options');
$blockOption = static function ($new, $old) {
    return $old;
};
add_filter('pre_update_option_publishpress_calendar_options', $blockOption, PHP_INT_MAX, 2);
$failed = callPlanner('update-plugin-settings', ['module' => 'calendar', 'expected_hash' => $state['expected_hash'], 'settings' => (object) ['calendar_today_in_first_row' => !$state['settings']->calendar_today_in_first_row]]);
check(is_wp_error($failed), 'Failed native option write claimed success');
check(get_option('publishpress_calendar_options') == $beforeOption, 'Failed option write changed storage');
remove_filter('pre_update_option_publishpress_calendar_options', $blockOption, PHP_INT_MAX);
register_taxonomy('mcp_private_restless', ['post'], ['public' => true, 'show_ui' => true, 'show_in_rest' => false]);
$term = wp_insert_term('Non REST fixture', 'mcp_private_restless');
$content = callPlanner('get-content', ['id' => $fixturePost['id']]);
$updated = success(callPlanner('save-content', ['id' => $fixturePost['id'], 'expected_hash' => $content['expected_hash'], 'changes' => (object) ['terms' => (object) ['mcp_private_restless' => [(int) $term['term_id']]]]]), 'Native non-REST taxonomy');
check(wp_get_object_terms($fixturePost['id'], 'mcp_private_restless', ['fields' => 'ids']) === [(int) $term['term_id']], 'Non-REST assignment not saved');
$exported = success(callPlanner('export-calendar', ['ids' => [$fixturePost['id']]]), 'Native calendar export');
check(strpos($exported['content'], 'BEGIN:VCALENDAR') !== false && strpos($exported['content'], 'BEGIN:VEVENT') !== false, 'Native iCalendar missing event');
$fields = callPlanner('list-editorial-fields', ['per_page' => 100]);
$ordered = array_reverse(array_map(static fn($item) => (object) ['id' => $item['id'], 'expected_hash' => $item['expected_hash']], $fields['items']));
success(callPlanner('reorder-editorial-fields', ['items' => $ordered]), 'Native editorial reorder');
$afterFields = callPlanner('list-editorial-fields', ['per_page' => 100]);
check(array_column($afterFields['items'], 'id') === array_map(static fn($item) => $item->id, $ordered), 'Native field order differs');
$commentState = success(callPlanner('save-editorial-comment', ['post_id' => $fixturePost['id'], 'content' => 'Permanent lifecycle fixture']), 'Comment lifecycle fixture');
check(is_wp_error(callPlanner('delete-editorial-comment', ['id' => $commentState['id'], 'operation' => 'delete', 'expected_hash' => $commentState['expected_hash']])), 'Untrashed comment deleted permanently');
success(callPlanner('delete-editorial-comment', ['id' => $commentState['id'], 'expected_hash' => $commentState['expected_hash']]), 'Native comment trash');
$commentHash = PublishPress\Core\Mcp\Registry::hash(['comment' => array_intersect_key((array) get_comment($commentState['id']), array_flip(['comment_ID', 'comment_post_ID', 'comment_parent', 'user_id', 'comment_content', 'comment_approved'])), 'attachments' => get_comment_meta($commentState['id'], '_pp_editorial_comment_files', true)]);
success(callPlanner('delete-editorial-comment', ['id' => $commentState['id'], 'operation' => 'delete', 'expected_hash' => $commentHash]), 'Native comment permanent delete');
check(get_comment($commentState['id']) === null, 'Comment remained after permanent delete');
$logId = wp_insert_comment(['comment_post_ID' => $fixturePost['id'], 'comment_type' => PlannerLog::COMMENT_TYPE, 'comment_approved' => PlannerLog::COMMENT_ACTIVE, 'comment_content' => maybe_serialize(['subject' => 'Synthetic fixture', 'body' => 'private token must not be returned'])]);
update_comment_meta($logId, PlannerLog::META_NOTIF_WORKFLOW_ID, $full['id']);
update_comment_meta($logId, PlannerLog::META_NOTIF_STATUS, 'queued');
update_comment_meta($logId, PlannerLog::META_NOTIF_CHANNEL, 'email');
$args = ['fixture' => $logId];
$cronId = md5(serialize($args));
update_comment_meta($logId, PlannerLog::META_NOTIF_CRON_ID, $cronId);
wp_schedule_single_event(time() + 3600, PlannerQueue::SEND_NOTIFICATION_HOOK, $args);
$logs = success(callPlanner('list-notification-logs', ['post_id' => $fixturePost['id']]), 'Native log status');
$selected = array_values(array_filter($logs['items'], static fn($item) => $item['id'] === $logId));
check(count($selected) === 1 && $selected[0]['queued'], 'Native queued status missing');
check(strpos(wp_json_encode($logs), 'private token') === false, 'Log payload leaked');
$badLogs = callPlanner('manage-notification-logs', ['items' => [(object) ['id' => $logId, 'expected_hash' => $selected[0]['expected_hash']], (object) ['id' => 999999, 'expected_hash' => str_repeat('0', 64)]], 'operation' => 'delete']);
check(is_wp_error($badLogs) && get_comment($logId) !== null && wp_next_scheduled(PlannerQueue::SEND_NOTIFICATION_HOOK, $args), 'Invalid log batch deleted or cancelled valid target');
success(callPlanner('manage-notification-logs', ['items' => [(object) ['id' => $logId, 'expected_hash' => $selected[0]['expected_hash']]], 'operation' => 'delete']), 'Native log deletion');
check(!get_comment($logId) && wp_next_scheduled(PlannerQueue::SEND_NOTIFICATION_HOOK, $args) === false, 'Native queued delivery not cancelled');
$subscriber = wp_insert_user(['user_login' => 'mcp_subscriber_fixture', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
wp_set_current_user($subscriber);
foreach (['list-features', 'list-workflows', 'get-workflow-fields'] as $action) {
    check(is_wp_error(callPlanner($action)), 'Subscriber accessed ' . $action);
}
wp_set_current_user(1);
$state = callPlanner('get-plugin-settings', ['module' => 'calendar']);
$secret = PublishPress()->calendar->module->options->ics_secret_key;
$rotated = success(callPlanner('rotate-calendar-feed-key', ['expected_hash' => $state['expected_hash']]), 'Native feed key rotation');
check(PublishPress()->calendar->module->options->ics_secret_key !== $secret && strpos(wp_json_encode($rotated), 'key') === false, 'Feed secret returned or rotation failed');
// Generic content writes cannot bypass dedicated follower or editorial capabilities.
$content = callPlanner('get-content', ['id' => $fixturePost['id']]);
$followersBefore = callPlanner('get-post-followers', ['post_id' => $fixturePost['id']]);
$internalTerms = wp_get_object_terms($fixturePost['id'], PublishPress()->notifications->notify_user_taxonomy, ['fields' => 'ids']);
$bypass = callPlanner('save-content', ['id' => $fixturePost['id'], 'expected_hash' => $content['expected_hash'], 'changes' => (object) ['terms' => (object) [PublishPress()->notifications->notify_user_taxonomy => []]]]);
check(is_wp_error($bypass) && wp_get_object_terms($fixturePost['id'], PublishPress()->notifications->notify_user_taxonomy, ['fields' => 'ids']) === $internalTerms, 'Generic terms bypassed follower policy');
$denyEditorial = static function ($caps, $cap) { return $cap === 'pp_edit_editorial_metadata' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyEditorial, PHP_INT_MAX, 2);
check(is_wp_error(callPlanner('save-editorial-field', ['fields' => (object) ['name' => 'Must fail', 'type' => 'text']])), 'Definition edit bypassed native editorial capability');
remove_filter('map_meta_cap', $denyEditorial, PHP_INT_MAX);

// Native metadata filter schemas follow the corresponding field controls.
$config = callPlanner('get-view-configuration', ['view' => 'content_board', 'kind' => 'filters']);
success(callPlanner('save-view-configuration', ['view' => 'content_board', 'kind' => 'filters', 'items' => ['mcp-number', 'mcp-checkbox', 'mcp-select', 'mcp-date'], 'expected_hash' => $config['expected_hash']]), 'Enable native metadata filters');
$prefs = callPlanner('get-view-preferences', ['view' => 'content_board']);
check($prefs['schema']['properties']['mcp-checkbox']['type'] === 'boolean', 'Checkbox filter advertised as text');
$bad = callPlanner('save-view-preferences', ['view' => 'content_board', 'expected_hash' => $prefs['expected_hash'], 'filters' => (object) ['mcp-checkbox' => 'false', 'mcp-number' => 7]]);
check(is_wp_error($bad), 'String false accepted for checkbox filter');
success(callPlanner('save-view-preferences', ['view' => 'content_board', 'expected_hash' => $prefs['expected_hash'], 'filters' => (object) ['mcp-checkbox' => true, 'mcp-number' => 7, 'mcp-select' => 'one', 'mcp-date_start' => '2026-11-01 00:00']]), 'Typed native metadata filter save');
success(callPlanner('list-view-content', ['view' => 'content_board']), 'Native metadata filter query');
$prefs = callPlanner('get-view-preferences', ['view' => 'content_board']);
success(callPlanner('save-view-preferences', ['view' => 'content_board', 'expected_hash' => $prefs['expected_hash'], 'filters' => (object) ['mcp-number' => null, 'mcp-date_start' => null]]), 'Clear typed native filters');
$prefs = callPlanner('get-view-preferences', ['view' => 'content_board']);
check($prefs['filters']->{'mcp-number'} === null && $prefs['filters']->{'mcp-date_start'} === null, 'Native nullable filters did not clear');

foreach (['calendar', 'content_board', 'content_overview'] as $view) {
    $shown = success(callPlanner('get-view-item', ['id' => $fixturePost['id'], 'view' => $view]), 'Native displayed values ' . $view);
    check(is_object($shown['fields']) && strpos(wp_json_encode($shown), '_wpnonce') === false, 'Native display returned form credentials');
}
if (getenv('PLANNER_MCP_MODE') === 'pro') {
    // Fake credentials and in-process HTTP responses only; no Slack request is sent.
    update_option('publishpress_pro_slack_token', 'synthetic-planner-fixture-token');
    $requests = [];
    $mockSlack = static function ($pre, $args, $url) use (&$requests) {
        if (strpos($url, 'https://slack.com/api/') !== 0) { return $pre; }
        $requests[] = $url;
        return ['headers' => [], 'body' => wp_json_encode(['ok' => true, 'channels' => [['id' => 'C_MCP_FIXTURE', 'name' => 'mcp-fixture']], 'members' => [], 'response_metadata' => ['next_cursor' => '']]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
    };
    add_filter('pre_http_request', $mockSlack, PHP_INT_MAX, 3);
    $state = callPlanner('get-plugin-settings', ['module' => 'slack']);
    success(callPlanner('update-plugin-settings', ['module' => 'slack', 'expected_hash' => $state['expected_hash'], 'settings' => (object) ['default_slack_channel' => '#mcp-fixture']]), 'Configured Slack destination');
    success(callPlanner('test-slack-connection', ['dry_run' => true]), 'Configured Slack preview');
    check($requests === [], 'Slack preview contacted delivery API');
    $sent = success(callPlanner('test-slack-connection'), 'Mocked native Slack delivery');
    check($sent['sent'] && count($requests) > 0, 'Native mocked Slack client not exercised');
    remove_filter('pre_http_request', $mockSlack, PHP_INT_MAX);
    delete_option('publishpress_pro_slack_token');
}
if (is_multisite() && getenv('PLANNER_MCP_MODE') === 'pro') {
    $nativeFields = callPlanner('get-workflow-fields');
    check(isset($nativeFields['schema']['properties']['receiver_network_admin']), 'Native network administrator receiver missing');
    $denyNetwork = static function ($caps, $cap) { return $cap === 'manage_network_options' ? ['do_not_allow'] : $caps; };
    add_filter('map_meta_cap', $denyNetwork, PHP_INT_MAX, 2);
    check(is_wp_error(callPlanner('save-workflow', ['title' => 'Denied network receiver', 'configuration' => (object) ['receiver_network_admin' => (object) ['enabled' => true]], 'dry_run' => true])), 'Network receiver bypassed denied network capability');
    check(is_wp_error(callPlanner('get-migration-status', ['step' => 'options'])), 'Migration bypassed denied network capability');
    remove_filter('map_meta_cap', $denyNetwork, PHP_INT_MAX);
}
if (defined('PPCH_VERSION') && getenv('PLANNER_MCP_MODE') === 'pro') {
    $fixtureRequirement = static function ($requirements) { $requirements['planner_mcp_fixture'] = ['label' => 'Planner integration fixture', 'status' => true, 'rule' => 'warning']; return $requirements; };
    add_filter('publishpress_checklists_requirement_list', $fixtureRequirement);
    foreach (['calendar', 'content_board', 'content_overview'] as $view) {
        $config = success(callPlanner('get-view-configuration', ['view' => $view, 'kind' => 'filters']), 'Active native Checklists choices');
        check(isset($config['choices']->{'ppch_co_checklist_planner_mcp_fixture'}), 'Native Checklists dependency field missing ' . $view);
    }
    remove_filter('publishpress_checklists_requirement_list', $fixtureRequirement);
}

// Native migration fixtures remain isolated and use only synthetic values.
update_user_meta($subscriber, 'ef_fixture_preferences', ['checked' => false, 'ids' => [1, 2]]);
$state = success(callPlanner('get-migration-status', ['step' => 'usermeta']), 'Migration status');
$deniedUser = static function ($caps, $cap, $actor, $args) use ($subscriber) {
    return $cap === 'edit_user' && (int) ($args[0] ?? 0) === (int) $subscriber ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $deniedUser, PHP_INT_MAX, 4);
check(is_wp_error(callPlanner('run-migration-step', ['step' => 'usermeta', 'expected_hash' => $state['expected_hash']])), 'Migration bypassed denied user');
remove_filter('map_meta_cap', $deniedUser, PHP_INT_MAX);
success(callPlanner('run-migration-step', ['step' => 'usermeta', 'expected_hash' => $state['expected_hash'], 'dry_run' => true]), 'Migration preview');
check(get_user_meta($subscriber, 'pp_fixture_preferences', true) === '', 'Migration preview wrote');
success(callPlanner('run-migration-step', ['step' => 'usermeta', 'expected_hash' => $state['expected_hash']]), 'Native user preference migration');
check(get_user_meta($subscriber, 'pp_fixture_preferences', true) === ['checked' => false, 'ids' => [1, 2]], 'Migration double-serialized user values');
$nativeOption = get_option('publishpress_calendar_options');
$state = callPlanner('get-migration-status', ['step' => 'options']);
success(callPlanner('run-migration-step', ['step' => 'options', 'expected_hash' => $state['expected_hash']]), 'Empty native migration options');
check(get_option('publishpress_calendar_options') == $nativeOption, 'Missing legacy source destroyed options');
register_taxonomy('ef_editorial_meta', ['post'], ['public' => false]);
$module = PublishPress()->editorial_metadata;
$description = $module->get_encoded_description(['description' => 'Fixture', 'position' => 1, 'type' => 'text', 'viewable' => true]);
$legacyTerm = wp_insert_term('Legacy fixture', 'ef_editorial_meta', ['slug' => 'legacy-fixture', 'description' => $description]);
update_post_meta($fixturePost['id'], '_ef_editorial_meta_text_legacy-fixture', 'Migrated fixture');
wp_set_object_terms($fixturePost['id'], [(int) $legacyTerm['term_id']], 'ef_editorial_meta');
$state = success(callPlanner('get-migration-status', ['step' => 'metadata']), 'Metadata migration status');
success(callPlanner('run-migration-step', ['step' => 'metadata', 'expected_hash' => $state['expected_hash']]), 'Native editorial migration');
check(get_post_meta($fixturePost['id'], PP_Editorial_Metadata::metadata_postmeta_key . '_text_legacy-fixture', true) === 'Migrated fixture', 'Native metadata prefix did not migrate');

$datedFixture = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Calendar range fixture', 'post_author' => 1, 'post_date' => '2040-06-15 12:00:00']);
$within = success(callPlanner('list-view-content', ['view' => 'calendar', 'filters' => (object) ['start_date' => '2040-06-10', 'weeks' => 2, 'me_mode' => false, 's' => '', 'post_status' => 'draft'], 'per_page' => 100]), 'Calendar explicit range');
$fixtureVisible = current_user_can('edit_post', $datedFixture) && current_user_can('read_post', $datedFixture);
check(in_array($datedFixture, array_column($within['items'], 'id'), true) === $fixtureVisible, 'Calendar range ignored object permission or omitted permitted post');
$outside = success(callPlanner('list-view-content', ['view' => 'calendar', 'filters' => (object) ['start_date' => '2041-06-10', 'weeks' => 2, 'me_mode' => false, 's' => '', 'post_status' => 'draft'], 'per_page' => 100]), 'Calendar excluding range');
check(!in_array($datedFixture, array_column($outside['items'], 'id'), true), 'Calendar ignored explicit date range');

success(callPlanner('list-editorial-comments', ['search' => 'fixture']), 'Native cross-post editorial discussion browsing');


// Audit regressions exercise the released Adapter and native failure hooks.
$calendarPrefs = success(callPlanner('get-view-preferences', ['view' => 'calendar']), 'Calendar native filter schema');
check($calendarPrefs['schema']['properties']['weeks']['maximum'] === 12, 'Calendar weeks differ from native select');
$beforePrefs = PublishPress()->calendar->get_user_meta(1, PP_Calendar::USERMETA_KEY_PREFIX . 'filters', true);
foreach ([(object) ['weeks' => 13], (object) ['weeks' => '2'], (object) ['cpt' => 'unknown_mcp_type']] as $invalidFilters) {
    check(is_wp_error(callPlanner('save-view-preferences', ['view' => 'calendar', 'expected_hash' => $calendarPrefs['expected_hash'], 'filters' => $invalidFilters])), 'Invalid Calendar choice accepted');
    check(PublishPress()->calendar->get_user_meta(1, PP_Calendar::USERMETA_KEY_PREFIX . 'filters', true) === $beforePrefs, 'Invalid filter patch wrote');
}
$boardPrefs = success(callPlanner('get-view-preferences', ['view' => 'content_board']), 'Metadata date range schema');
check(is_wp_error(callPlanner('save-view-preferences', ['view' => 'content_board', 'expected_hash' => $boardPrefs['expected_hash'], 'filters' => (object) ['mcp-date_start' => '2027-01-02 00:00', 'mcp-date_end' => '2027-01-01 00:00']])), 'Reversed metadata date range accepted');

// Real Permissions distribution coverage runs when that dependency is active.
if (class_exists('PublishPress\\Permissions\\API')) {
    $followersBefore = success(callPlanner('get-post-followers', ['post_id' => $fixturePost['id']]), 'Group follower scope');
    $denyGroups = static function ($caps, $cap) { return $cap === 'pp_manage_settings' ? ['do_not_allow'] : $caps; };
    add_filter('map_meta_cap', $denyGroups, PHP_INT_MAX, 2);
    check(is_wp_error(callPlanner('update-post-followers', ['post_id' => $fixturePost['id'], 'groups' => [], 'expected_hash' => $followersBefore['expected_hash']])), 'Empty group list bypassed denied capability');
    remove_filter('map_meta_cap', $denyGroups, PHP_INT_MAX);
    check(callPlanner('get-post-followers', ['post_id' => $fixturePost['id']])['expected_hash'] === $followersBefore['expected_hash'], 'Denied group clear changed followers');
}

$mediaFixture = wp_insert_attachment(['post_title' => 'Synthetic attachment', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_author' => 1]);
$blockAttachment = static function ($check, $id, $key) { return $key === '_pp_editorial_comment_files' ? false : $check; };
add_filter('add_comment_metadata', $blockAttachment, PHP_INT_MAX, 3);
$failedComment = callPlanner('save-editorial-comment', ['post_id' => $fixturePost['id'], 'content' => 'Synthetic failed attachment fixture', 'attachment_ids' => [$mediaFixture]]);
remove_filter('add_comment_metadata', $blockAttachment, PHP_INT_MAX);
check(is_wp_error($failedComment) && !empty($failedComment->get_error_data()['id']), 'Failed attachment save claimed success or omitted partial comment ID');

$contentState = success(callPlanner('get-content', ['id' => $fixturePost['id']]), 'Concurrent content fixture');
$changeDuringPrepare = static function ($prepared) use ($fixturePost) {
    wp_update_post(['ID' => $fixturePost['id'], 'post_title' => 'Concurrent native title']);
    return $prepared;
};
add_filter('rest_pre_insert_post', $changeDuringPrepare);
$concurrent = callPlanner('save-content', ['id' => $fixturePost['id'], 'expected_hash' => $contentState['expected_hash'], 'changes' => (object) ['title' => 'Must not overwrite concurrent title']]);
remove_filter('rest_pre_insert_post', $changeDuringPrepare);
check(is_wp_error($concurrent) && get_post_field('post_title', $fixturePost['id']) === 'Concurrent native title', 'Content write ignored revision changed during native preparation');

$batchFirst = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Bulk first fixture', 'post_author' => 1]);
$batchSecond = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Bulk removed fixture', 'post_author' => 1]);
$batchOne = success(callPlanner('get-content', ['id' => $batchFirst]), 'Batch first revision');
$batchTwo = success(callPlanner('get-content', ['id' => $batchSecond]), 'Batch second revision');
$removeSecond = static function ($id) use ($batchFirst, $batchSecond) { if ($id === $batchFirst) { wp_delete_post($batchSecond, true); } };
add_action('wp_after_insert_post', $removeSecond);
$partial = success(callPlanner('move-calendar-items', ['items' => [(object) ['id' => $batchFirst, 'date' => '2028-02-01', 'expected_hash' => $batchOne['expected_hash']], (object) ['id' => $batchSecond, 'date' => '2028-02-02', 'expected_hash' => $batchTwo['expected_hash']]]]), 'Partial Calendar batch');
remove_action('wp_after_insert_post', $removeSecond);
check(!$partial['complete'] && $partial['applied_ids'] === [$batchFirst] && $partial['failed_id'] === $batchSecond, 'Partial batch omitted confirmed IDs after target disappeared');


// Non-REST taxonomy assignments must honor individual term denials, too.
$termState = success(callPlanner('get-content', ['id' => $fixturePost['id']]), 'Term permission scope');
$denyTerm = static function ($caps, $cap, $user, $args) use ($term) { return $cap === 'assign_term' && (int) ($args[0] ?? 0) === (int) $term['term_id'] ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyTerm, PHP_INT_MAX, 4);
$termResult = callPlanner('save-content', ['id' => $fixturePost['id'], 'expected_hash' => $termState['expected_hash'], 'changes' => (object) ['title' => 'Denied term must not write', 'terms' => (object) ['mcp_private_restless' => [(int) $term['term_id']]]]]);
remove_filter('map_meta_cap', $denyTerm, PHP_INT_MAX);
check(is_wp_error($termResult) && get_post_field('post_title', $fixturePost['id']) === $termState['title'], 'Individual term denial bypassed or mixed patch wrote');

// Exercise non-default checkbox values rather than only saving the current defaults.
foreach (success(callPlanner('list-features'), 'Native checkbox settings audit')['features'] as $feature) {
    $moduleName = $feature['module'];
    $settingsState = success(callPlanner('get-plugin-settings', ['module' => $moduleName]), 'Checkbox discovery ' . $moduleName);
    foreach ($settingsState['schema']['properties'] as $key => $field) {
        if ($field['type'] !== 'boolean') { continue; }
        $previous = $settingsState['settings']->$key;
        $rawBefore = get_option(PublishPress()->options_group . $moduleName . '_options');
        $preview = success(callPlanner('update-plugin-settings', ['module' => $moduleName, 'expected_hash' => $settingsState['expected_hash'], 'settings' => (object) [$key => !$previous], 'dry_run' => true]), 'Opposite checkbox preview ' . $moduleName . '.' . $key);
        check($preview['settings']->$key === !$previous && get_option(PublishPress()->options_group . $moduleName . '_options') == $rawBefore, 'Checkbox preview disagrees or wrote');
        $savedState = success(callPlanner('update-plugin-settings', ['module' => $moduleName, 'expected_hash' => $settingsState['expected_hash'], 'settings' => (object) [$key => !$previous]]), 'Opposite checkbox save ' . $moduleName . '.' . $key);
        $nativeAfter = get_option(PublishPress()->options_group . $moduleName . '_options');
        $nativeAfter = (array) $nativeAfter;
        $rawBefore = (array) $rawBefore;
        check($savedState['settings']->$key === !$previous && in_array($nativeAfter[$key], ['on', 'off', '1', '0'], true), 'Checkbox native storage differs');
        unset($nativeAfter[$key], $rawBefore[$key]);
        check($nativeAfter == $rawBefore, 'Checkbox patch modified unrelated settings');
        $settingsState = success(callPlanner('update-plugin-settings', ['module' => $moduleName, 'expected_hash' => $savedState['expected_hash'], 'settings' => (object) [$key => $previous]]), 'Restore checkbox fixture');
    }
}


$dashboardCounts = success(callPlanner('get-dashboard-content', ['kind' => 'statuses']), 'Native dashboard counts');
check($dashboardCounts['items'] === PublishPress()->dashboard->getPostStatusCounts(), 'Dashboard count data diverges from native widget');
$dashboardFollowed = success(callPlanner('get-dashboard-content', ['kind' => 'followed']), 'Native followed content');
check(in_array($fixturePost['id'], array_column($dashboardFollowed['items'], 'id'), true), 'Dashboard omitted followed fixture');
$denyCount = static function ($caps, $cap) { return $cap === 'read_private_posts' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $denyCount, PHP_INT_MAX, 2);
check(is_wp_error(callPlanner('get-dashboard-content', ['kind' => 'statuses'])), 'Dashboard count bypassed denied capability');
remove_filter('map_meta_cap', $denyCount, PHP_INT_MAX);
if (getenv('PLANNER_MCP_MODE') === 'pro') {
    $proFields = success(callPlanner('get-workflow-fields'), 'Native timer choice schema');
    check($proFields['schema']['properties']['event_beforepublishing']['properties']['amount']['maximum'] === 60, 'Pro timer differs from native selector');
    check(is_wp_error(callPlanner('save-workflow', ['title' => 'Invalid timer fixture', 'configuration' => (object) ['event_beforepublishing' => (object) ['amount' => 61]], 'dry_run' => true])), 'Pro timer outside native choices accepted');
}


// Authenticate a real synthetic WordPress application password, then enforce a scope denial.
add_filter('wp_is_application_passwords_available', '__return_true');
add_filter('application_password_is_api_request', '__return_true');
$application = WP_Application_Passwords::create_new_application_password(1, ['name' => 'Planner isolated audit fixture']);
check(!is_wp_error($application), 'Synthetic application password creation failed');
$priorRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$appUser = wp_authenticate_application_password(null, get_userdata(1)->user_login, $application[0]);
if ($priorRemoteAddress === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $priorRemoteAddress; }
check($appUser instanceof WP_User && $appUser->ID === 1, 'Application password authentication failed');
wp_set_current_user($appUser->ID);
$applicationRestriction = static function ($caps, $cap) { return $cap === 'manage_options' ? ['do_not_allow'] : $caps; };
add_filter('map_meta_cap', $applicationRestriction, PHP_INT_MAX, 2);
check(is_wp_error(callPlanner('get-plugin-settings', ['module' => 'calendar'])), 'Application password restriction bypassed');
success(callPlanner('get-content', ['id' => $fixturePost['id']]), 'Application password retains permitted object access');
remove_filter('map_meta_cap', $applicationRestriction, PHP_INT_MAX);
WP_Application_Passwords::delete_application_password(1, $application[1]['uuid']);
remove_filter('application_password_is_api_request', '__return_true');
remove_filter('wp_is_application_passwords_available', '__return_true');


// Numeric and punctuated tags are legal WordPress shortcodes, but not Planner placeholders.
foreach (['123', 'psppno_post.custom'] as $foreignTag) {
    add_shortcode($foreignTag, static function () { return 'Synthetic foreign shortcode'; });
    check(shortcode_exists($foreignTag), 'Native shortcode fixture did not register');
    check(is_wp_error(callPlanner('save-workflow', ['title' => 'Foreign shortcode fixture', 'configuration' => (object) ['content_main' => (object) ['body' => '[' . $foreignTag . ']']], 'dry_run' => true])), 'Foreign shortcode tag bypassed Planner template policy');
    remove_shortcode($foreignTag);
}
