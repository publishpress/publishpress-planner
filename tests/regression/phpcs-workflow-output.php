<?php
/**
 * Check workflow output and nonce handling without bootstrapping WordPress.
 * Usage: php tests/regression/phpcs-workflow-output.php
 */

$root = dirname(__DIR__, 2);

// Minimal WordPress substitutes let these checks run without a database.
function esc_attr($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function esc_html($value)
{
    return esc_attr($value);
}

function checked($actual, $expected)
{
    if ($actual == $expected) {
        echo 'checked="checked"';
    }
}

function wp_unslash($value)
{
    return stripslashes($value);
}

function sanitize_text_field($value)
{
    return trim(strip_tags($value));
}

function sanitize_key($value)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function wp_verify_nonce($value, $action)
{
    return $value === 'valid-token' && $action === 'publishpress_notif_save_metabox';
}

function update_post_meta($id, $key, $value)
{
    $GLOBALS['saved_metadata'][] = [$id, $key, $value];
}

function expect_workflow($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

require $root . '/core/View.php';
require $root . '/lib/Notifications/Workflow/Option/OptionCheckboxAbstract.php';
require $root . '/lib/Notifications/Workflow/Option/SkipUser.php';

$view = new PublishPress\Core\View();
$context = [
    'name' => 'events["unsafe"]',
    'id' => 'event" onclick="alert(1)',
    'value' => '1',
    'label' => '<script>alert(1)</script>',
    'meta' => ['selected' => 1],
    'event_filters' => [],
];
ob_start();
$view->display('workflow_event_field', $context, $root . '/views');
$html = ob_get_clean();
expect_workflow(str_contains($html, '<input'), 'Workflow controls must remain intact.');
expect_workflow(str_contains($html, 'checked="checked"'), 'Selected workflow events must stay checked.');
expect_workflow(!str_contains($html, '<script>'), 'Workflow labels must be escaped.');
expect_workflow(str_contains($html, 'event&quot; onclick=&quot;alert(1)'), 'Attribute values must be escaped.');
expect_workflow($html === $view->render('workflow_event_field', $context, $root . '/views'), 'Display must preserve rendered form markup.');

// Avoid hook registration; exercise the real save handler directly.
$option = (new ReflectionClass(PublishPress\Notifications\Workflow\Option\SkipUser::class))->newInstanceWithoutConstructor();
$GLOBALS['saved_metadata'] = [];
foreach ([null, 'invalid-token'] as $nonce) {
    $_POST = ['publishpress_notif_option_skip_user' => '1'];
    if ($nonce !== null) {
        $_POST['publishpress_notif_metabox_events_nonce'] = $nonce;
    }
    $option->setValue(42, (object)['ID' => 42]);
}
expect_workflow($GLOBALS['saved_metadata'] === [], 'Missing or invalid nonces must not change workflow settings.');
$_POST['publishpress_notif_metabox_events_nonce'] = 'valid-token';
$option->setValue(42, (object)['ID' => 42]);
expect_workflow($GLOBALS['saved_metadata'] === [[42, '_psppno_option_skip_user', '1']], 'Valid workflow saves must still work.');
unset($_POST['publishpress_notif_option_skip_user']);
$option->setValue(42, (object)['ID' => 42]);
expect_workflow(end($GLOBALS['saved_metadata']) === [42, '_psppno_option_skip_user', 0], 'A valid save must allow unchecking an option.');

echo "PASS: workflow form escaping, control preservation, and nonce-protected saves\n";
