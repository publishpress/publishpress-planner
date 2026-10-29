<?php
/** Usage: php tests/regression/notification-category-hierarchy.php */
namespace PublishPress\Notifications\Workflow\Step\Event\Filter {
    interface Filter_Interface {}
    class Base { protected function get_metadata($key) { return ['local']; } }
}
namespace {
    function esc_attr($text) { return $text; }
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES); }
    function is_wp_error($value) { return false; }
    function get_taxonomies() { return ['category' => 'category']; }
    function get_categories($args) { return $GLOBALS['terms']; }
    function get_terms($args) { return $GLOBALS['terms']; }
    $root = dirname(__DIR__, 2);
    require $root . '/lib/Notifications/Workflow/Step/Event_Content/Filter/Base.php';
    require $root . '/lib/Notifications/Workflow/Step/Event_Content/Filter/Category.php';
    require $root . '/lib/Notifications/Workflow/Step/Event_Content/Filter/Term.php';
    $terms = [];
    foreach ([[3, 2, 'District'], [1, 0, 'News'], [2, 1, 'Local'], [4, 0, 'Sports'], [5, 99, 'Orphan']] as [$id, $parent, $name]) {
        $terms[] = (object) ['term_id' => $id, 'parent' => $parent, 'name' => $name, 'slug' => strtolower($name), 'taxonomy' => 'category'];
    }
    foreach ([\PublishPress\Notifications\Workflow\Step\Event_Content\Filter\Category::class, \PublishPress\Notifications\Workflow\Step\Event_Content\Filter\Term::class] as $class) {
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($class, 'get_options');
        $options = $method->invoke($object);
        $labels = array_column($options, 'label');
        if (!in_array(($class === \PublishPress\Notifications\Workflow\Step\Event_Content\Filter\Term::class ? 'category/' : '') . 'News › Local › District', $labels, true)) {
            throw new \RuntimeException('Nested categories must display their full ancestor path.');
        }
        if (count($options) !== 5) { throw new \RuntimeException('Orphaned terms must remain available.'); }
    }
    $terms[1]->parent = 3; // Invalid cyclic data must never hang the form.
    $method->invoke($object);
    echo "PASS: three-level paths, both selectors, orphaned terms, and cyclic data\n";
}
