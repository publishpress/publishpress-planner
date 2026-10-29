<?php
/**
 * Check revision status presentation without a WordPress database.
 *
 * Usage: php tests/regression/scheduled-revisions.php [--without-revisions]
 */

$revisions_active = !in_array('--without-revisions', $argv, true);
$statuses = [
    'publish' => 'Published',
    'draft' => 'Draft',
    'pending' => 'Pending Review',
    'future' => 'Scheduled',
    'draft-revision' => 'Working Copy',
    'pending-revision' => 'Change Request',
    'future-revision' => 'Scheduled Change',
    'custom-revision' => 'Custom Revision',
];

// Revisions stores its workflow status in post_mime_type, independently of post_status.
if ($revisions_active) {
    function rvy_in_revision_workflow($post)
    {
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        return in_array($post->post_mime_type, [
            'draft-revision', 'pending-revision', 'future-revision', 'custom-revision',
        ], true) ? $post->post_mime_type : false;
    }
}

function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function _n($single, $plural, $count, $domain = '') { return $count === 1 ? $single : $plural; }
function apply_filters($hook, $value, ...$args) { return $value; }
function is_wp_error($value) { return false; }
function get_post($id) { return $GLOBALS['test_post']; }
function get_option($name, $default = '') { return $default; }
function get_post_type_object($type) {
    return (object) ['label' => 'Posts', 'cap' => (object) [
        'edit_post' => 'edit_posts', 'delete_post' => 'delete_posts', 'read_post' => 'read',
    ]];
}
function get_the_date($format, $post) { return $post->post_date; }
function get_the_time($format, $id) { return ''; }
function wp_get_post_terms($id, $taxonomy) { return []; }
function get_the_author_meta($field, $id) { return 'Author'; }
function current_user_can($cap, $id) { return false; }
function wp_verify_nonce($nonce, $action) { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function get_object_taxonomies($type, $output) { return []; }
function _draft_or_post_title($id) { return 'Revision'; }
function wp_send_json($data, $status) {
    $GLOBALS['calendar_response'] = $data;
}

$publishpress = new class {
    public function getPostStatuses() {
        $result = [];
        foreach ($GLOBALS['statuses'] as $slug => $label) {
            $result[] = (object) ['slug' => $slug, 'label' => $label];
        }
        return $result;
    }
    public function getPostStatusBy($field, $value) {
        return isset($GLOBALS['statuses'][$value])
            ? (object) ['label' => $GLOBALS['statuses'][$value]] : false;
    }
};

$root = dirname(__DIR__, 2);
require_once $root . '/common/php/class-module.php';
require_once $root . '/lib/Notifications/Traits/Dependency_Injector.php';
require_once $root . '/modules/calendar/calendar.php';
require_once $root . '/modules/calendar/library/calendar-utilities.php';
require_once $root . '/modules/content-overview/content-overview.php';

class RevisionStatusModule extends PP_Module
{
    public function __construct() {}
    public function getUserAuthorizedPostStatusOptions($postType, $post = false) { return []; }
    public function get_post_action_links($post, $can_edit_post) { return []; }
    public function get_author_markup($post, $can_edit_post) { return ''; }
}

function expect_status($expected, $actual, $context)
{
    if ($expected !== $actual) {
        throw new RuntimeException($context . ': expected ' . var_export($expected, true)
            . ', received ' . var_export($actual, true));
    }
}

$module = new RevisionStatusModule();
$calendar = (new ReflectionClass(PP_Calendar::class))->newInstanceWithoutConstructor();
$overview = (new ReflectionClass(PP_Content_Overview::class))->newInstanceWithoutConstructor();
$overview->content_overview_datas = ['taxonomies' => []];
$_GET = ['id' => 1, 'nonce' => 'test'];

foreach ([
    ['pending', 'future-revision'],
    ['pending', 'pending-revision'],
    ['draft', 'draft-revision'],
    ['pending', 'custom-revision'],
    ['publish', ''],
    ['future', ''],
    ['draft', ''],
    ['pending', ''],
    ['pending', 'image/jpeg'],
] as [$post_status, $mime_type]) {
    $test_post = (object) [
        'ID' => 1, 'post_type' => 'post', 'post_status' => $post_status,
        'post_mime_type' => $mime_type, 'post_author' => 1,
        'post_title' => 'Revision', 'post_content' => '', 'post_date' => '2026-10-29 12:00:00',
    ];
    $is_revision = $revisions_active && rvy_in_revision_workflow($test_post);
    $effective_status = $is_revision ? $mime_type : $post_status;
    $expected_label = $statuses[$effective_status];
    $context = $post_status . '/' . $mime_type;

    $calendar->getPostData();
    expect_status($expected_label, $calendar_response['fields']['status']['value'], 'Calendar ' . $context);
    $localized = $module->localize_post_data([], $test_post, false)['posts'][0];
    expect_status($expected_label, $localized['status_label'], 'Modal label ' . $context);
    expect_status($effective_status, $localized['post_status'], 'Modal status ' . $context);
    expect_status($is_revision ? 'Revision Status' : 'Post Status', $localized['status_field_label'], 'Modal field ' . $context);
    expect_status($is_revision ? $expected_label : '&mdash;', $overview->column_default($test_post, 'revision_status'), 'Overview ' . $context);
    expect_status($expected_label, $overview->column_default($test_post, 'post_status'), 'Existing status column ' . $context);
    echo 'PASS: ' . $context . "\n";
}

// Column output must escape labels supplied by status integrations.
if ($revisions_active) {
    $statuses['custom-revision'] = '<b>Custom & Revision</b>';
    $test_post->post_mime_type = 'custom-revision';
    expect_status('&lt;b&gt;Custom &amp; Revision&lt;/b&gt;', $overview->column_default($test_post, 'revision_status'), 'Escaped revision label');
}
