<?php
/** Usage: php tests/regression/permission-aware-recipients.php */
function get_post_meta($id, $key, $single) { return $GLOBALS['options'][$key] ?? ''; }
function get_post($id) { return $id === 12 ? (object) ['ID' => 12] : false; }
function apply_filters($hook, $value, ...$args) { return $hook === 'publishpress_notif_run_workflow_receivers' ? $GLOBALS['receivers'] : $value; }
function email_exists($email) { return $email === 'editor@example.test' ? 7 : false; }
function user_can($id, $cap, $post) { return $post === 12 && ($id === 7 || ($id === 9 && $cap === 'read_post')); }
$root = dirname(__DIR__, 2);
require $root . '/lib/Notifications/Traits/Dependency_Injector.php';
require $root . '/lib/Notifications/Workflow/Workflow.php';
$workflow = new \PublishPress\Notifications\Workflow\Workflow((object) ['ID' => 1]);
$workflow->event_args = ['params' => ['post_id' => 12]];
$receivers = array_map(static function ($receiver) { return ['receiver' => $receiver]; }, [7, 8, 9, 'editor@example.test', 'external@example.test']);
$options = [];
$method = new ReflectionMethod($workflow, 'get_receivers');
if ($method->invoke($workflow) !== $receivers) { throw new RuntimeException('Existing workflows must remain unrestricted.'); }
$options['_psppno_option_require_read_access'] = '1';
if (count($method->invoke($workflow)) !== 3) { throw new RuntimeException('View permissions must be checked per recipient and post.'); }
$options['_psppno_option_require_edit_access'] = '1';
if (count($method->invoke($workflow)) !== 2) { throw new RuntimeException('Edit access must further restrict recipients.'); }
$workflow->event_args = [];
if ($method->invoke($workflow) !== []) { throw new RuntimeException('Access-limited workflows must fail closed without a post.'); }
echo "PASS: unrestricted defaults, view/edit permissions, registered emails, external addresses, and missing posts\n";
