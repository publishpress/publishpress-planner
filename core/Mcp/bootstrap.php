<?php

/** Optional private Abilities API integration; Planner owns all domain behavior. */

defined('ABSPATH') || exit;
if (!function_exists('wp_register_ability') || !function_exists('wp_register_ability_category')) {
    return;
}
if (defined('PUBLISHPRESS_PLANNER_MCP_LOADED')) {
    return;
}
define('PUBLISHPRESS_PLANNER_MCP_LOADED', true);
foreach (['Registry', 'Settings', 'Content', 'Editorial', 'WorkflowFields', 'Workflows', 'Notifications', 'Display', 'Maintenance'] as $file) {
    $abilityFilePath = __DIR__ . '/' . $file . '.php';
    if (is_readable($abilityFilePath)) {
        require_once $abilityFilePath;
    }
}
add_action('wp_abilities_api_categories_init', static function () {

    if (!wp_has_ability_category('publishpress')) {
        wp_register_ability_category('publishpress', ['label' => __('PublishPress', 'publishpress'),
            'description' => __('Actions provided by PublishPress plugins.', 'publishpress')]);
    }
});
add_action('wp_abilities_api_init', static function () {

    foreach (['Settings', 'Content', 'Editorial', 'WorkflowFields', 'Workflows', 'Notifications', 'Display', 'Maintenance'] as $name) {
        $class = '\\PublishPress\\Core\\Mcp\\' . $name;
        if (is_callable([$class, 'register'])) {
            $class::register();
        }
    }
    do_action('publishpress_planner_mcp_register');
});
