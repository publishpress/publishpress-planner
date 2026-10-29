<?php
/** Usage: php tests/regression/calendar-modal-fields.php */
$fields = [
    'note' => ['label' => '<Note>', 'meta_key' => 'note'],
    'description' => ['type' => 'textarea', 'meta_key' => 'description'],
    'priority' => ['type' => 'select', 'meta_key' => 'priority', 'options' => ['low' => 'Low', 'high' => 'High']],
    'amount' => ['type' => 'number', 'meta_key' => 'amount'],
    'approved' => ['type' => 'checkbox', 'meta_key' => 'approved'],
    'locked' => ['meta_key' => 'locked', 'readonly' => true],
    'display' => ['value' => '<script>alert(1)</script>'],
];
$meta = ['note' => '"><script>alert(1)</script>', 'description' => 'Text', 'priority' => 'high', 'amount' => '0', 'approved' => '1', 'locked' => 'Private'];
$writes = [];
$allowed = true;
function apply_filters($hook, $value, ...$args) { return $GLOBALS['fields']; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return esc_attr($value); }
function esc_textarea($value) { return esc_attr($value); }
function selected($a, $b, $echo = true) { return $a === $b ? 'selected' : ''; }
function checked($a, $b, $echo = true) { return $a === $b ? 'checked' : ''; }
function get_post_meta($id, $key, $single) { return $GLOBALS['meta'][$key] ?? ''; }
function get_post($id) { return (object) ['ID' => $id]; }
function update_post_meta($id, $key, $value) { $GLOBALS['writes'][$key] = $value; }
function current_user_can(...$args) { return $GLOBALS['allowed']; }
function sanitize_key($text) { return $text; }
function sanitize_text_field($text) { return strip_tags($text); }
function sanitize_textarea_field($text) { return strip_tags($text); }
function wp_doing_ajax() { return true; }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
require dirname(__DIR__, 2) . '/common/php/class-module.php';
$module = (new ReflectionClass(PP_Module::class))->newInstanceWithoutConstructor();
function expect_modal($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$html = $module->get_calendar_modal_fields_html((object) ['ID' => 12], true);
expect_modal(strpos($html, '<script>') === false && strpos($html, '&lt;Note&gt;') !== false, 'Labels and values must be escaped.');
expect_modal(strpos($html, 'value="high" selected') !== false, 'Selects must display saved values.');
expect_modal(strpos($html, 'data-pp-modal-field="locked"') === false, 'Readonly meta must not be editable.');
expect_modal(strpos($module->get_calendar_modal_fields_html((object) ['ID' => 12], false), 'data-pp-modal-field') === false, 'Viewers must only receive display values.');
$_POST = ['action' => 'publishpress_content_calendar_update_post', 'nonce' => 'valid', 'post_id' => 12,
    'custom_fields' => ['note' => '<b>Updated</b>', 'description' => "Line one\nLine two", 'priority' => 'low', 'amount' => '2.5', 'approved' => '0', 'locked' => 'attack', 'unknown' => 'attack']];
$module->save_calendar_modal_fields(12);
expect_modal($writes === ['note' => 'Updated', 'description' => "Line one\nLine two", 'priority' => 'low', 'amount' => '2.5', 'approved' => '0'], 'Only configured, editable meta may be saved.');
$writes = [];
$_POST['custom_fields'] = ['priority' => 'invalid', 'amount' => 'invalid', 'note' => ['unexpected']];
$module->save_calendar_modal_fields(12);
expect_modal($writes === [], 'Invalid option, number, and array values must be ignored.');
$_POST['custom_fields'] = ['note' => 'attack'];
$_POST['nonce'] = 'invalid';
$module->save_calendar_modal_fields(12);
expect_modal($writes === [], 'Invalid nonces must prevent writes.');
$_POST['nonce'] = 'valid';
$module->save_calendar_modal_fields(99);
expect_modal($writes === [], 'Other post updates must not save these fields.');
$allowed = false;
$module->save_calendar_modal_fields(12);
expect_modal($writes === [], 'Missing edit permission must prevent writes.');
echo "PASS: escaped fields, readonly display, typed saves, allowlist, nonce, and edit permissions\n";
