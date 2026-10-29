<?php
/** Usage: php tests/regression/new-post-fields.php */
function __($text, $domain = '') { return $text; }
function apply_filters($hook, $value, ...$args) { return $value; }
function get_post_type_object($type) {
    return (object) ['cap' => (object) ['edit_others_posts' => 'edit_others_posts']];
}
function get_object_taxonomies($type) { return $type === 'post' ? ['category', 'post_tag'] : []; }
function current_user_can($cap) { return true; }
function get_current_user_id() { return 1; }
function sanitize_text_field($text) { return is_scalar($text) ? strip_tags($text) : ''; }
function sanitize_key($text) { return $text; }
function wp_unslash($text) { return $text; }
function wp_verify_nonce($nonce, $action) { return true; }
function is_wp_error($value) { return false; }
require dirname(__DIR__, 2) . '/common/php/class-module.php';
require dirname(__DIR__, 2) . '/modules/modules-settings/modules-settings.php';
require dirname(__DIR__, 2) . '/modules/content-board/library/content-board-utilities.php';
require dirname(__DIR__, 2) . '/modules/content-overview/library/content-overview-utilities.php';
require dirname(__DIR__, 2) . '/modules/calendar/library/calendar-methods.php';
$publishpress = (object) ['modules' => (object) ['modules_settings' => (object) ['options' => (object) ['new_post_fields' => []]]]];
$options = $publishpress->modules->modules_settings->options;
function expect_fields($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$args = ['default_post_type' => 'post', 'editable_post_types' => ['post' => 'Posts'], 'post_status_options' => [], 'publish_date_markup' => 'date'];
$defaults = PP_Content_Overview_Utilities::getPostTypeFields($args);
expect_fields(isset($defaults['content'], $defaults['categories']) && $defaults['title']['required'], 'Defaults must remain unchanged.');
$options->new_post_fields = ['title' => 'optional', 'content' => 'hidden', 'categories' => 'required'];
foreach ([PP_Content_Overview_Utilities::class, PP_Content_Board_Utilities::class] as $utilities) {
    $fields = $utilities::getPostTypeFields($args);
    expect_fields(!isset($fields['content']) && !$fields['title']['required'] && $fields['categories']['required'], 'Both forms must apply field settings.');
    expect_fields(isset($fields['ptype']), 'The post type selector must remain available.');
}
$_GET = ['nonce' => 'test', 'postType' => 'post'];
$calendar = (new ReflectionClass(PP_Calendar_Methods::class))->newInstanceWithoutConstructor();
$fields = $calendar->getPostTypeFields([])['fields'];
expect_fields(!isset($fields['content']) && !$fields['title']['required'] && $fields['categories']['required'], 'Calendar must share the field policy.');
expect_fields(PP_Module::validate_new_post_fields(['categories' => []], 'post') === 'Categories is required.', 'Missing required data must be rejected on save.');
expect_fields(PP_Module::validate_new_post_fields(['categories' => ['news']], 'post') === '', 'Valid required values must pass.');
expect_fields(PP_Module::validate_new_post_fields([], 'page') === '', 'Unsupported taxonomies must not block creation.');
$options->new_post_fields = ['content' => 'required'];
expect_fields(PP_Module::validate_new_post_fields(['content' => '<img src="image.jpg">'], 'post') === '', 'Media-only content is valid content.');
$settings = (new ReflectionClass(PP_Modules_Settings::class))->newInstanceWithoutConstructor();
$saved = $settings->settings_validate(['new_post_fields' => ['title' => 'optional', 'content' => 'arbitrary', 'unknown' => 'required']]);
expect_fields($saved['new_post_fields'] === ['title' => 'optional'], 'Settings must accept only known fields and states.');
echo "PASS: default and configured schemas, all three forms, required validation, and sanitized settings\n";
