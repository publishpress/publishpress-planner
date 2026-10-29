<?php
/** Usage: php tests/regression/post-list-planner-views.php */
class PP_Module { public function get_post_types_for_module($module) { return $module->types; } }
function __($text, $domain = '') { return $text; }
function get_current_screen() { return (object) ['post_type' => $GLOBALS['post_type']]; }
function current_user_can($cap) { return $cap !== $GLOBALS['denied']; }
function apply_filters($hook, $value) { return $value; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function esc_url($url) { return $url; }
function esc_html($text) { return $text; }
require dirname(__DIR__, 2) . '/modules/modules-settings/modules-settings.php';
$publishpress = (object) ['modules' => (object) []];
foreach (['calendar', 'content_overview', 'content_board'] as $name) {
    $publishpress->modules->$name = (object) ['options' => (object) ['enabled' => 'on'], 'types' => ['post', 'book']];
}
$module = (new ReflectionClass(PP_Modules_Settings::class))->newInstanceWithoutConstructor();
$post_type = 'book'; $denied = '';
$links = $module->post_view_links(['all' => 'All']);
if (count($links) !== 4 || strpos($links['pp-calendar'], 'post_type=book') === false) { throw new RuntimeException('All existing views and the current post type must be preserved.'); }
$denied = 'pp_view_calendar';
$publishpress->modules->content_board->options->enabled = 'off';
$links = $module->post_view_links(['all' => 'All']);
if (array_keys($links) !== ['all', 'pp-content-overview']) { throw new RuntimeException('Disabled or unauthorized views must be omitted.'); }
$post_type = 'unsupported';
if ($module->post_view_links(['all' => 'All']) !== ['all' => 'All']) { throw new RuntimeException('Unsupported post types must not have Planner links.'); }
echo "PASS: current post type, existing links, enabled modules, permissions, and unsupported types\n";
