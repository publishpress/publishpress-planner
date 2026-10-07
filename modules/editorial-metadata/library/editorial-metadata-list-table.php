<?php 
/**
 * Management interface for Editorial Fields. Extends WP_List_Table class
 */
class PP_Editorial_Metadata_List_Table extends WP_List_Table
{
    public $callback_args;
    public $taxonomy;
    public $tax;
    public $metadata_types;

    /**
     * Construct the class
     */
    public function __construct($metadata_types = [])
    {
        global $publishpress;
        $this->metadata_types = $metadata_types;

        $this->taxonomy = PP_Editorial_Metadata::metadata_taxonomy;

        $this->tax = get_taxonomy($this->taxonomy);

        $columns = $this->get_columns();
        $hidden = [
            'position',
        ];
        $sortable = [];

        $this->_column_headers = [$columns, $hidden, $sortable];

        parent::__construct([
            'plural' => 'metadata',
            'singular' => 'metadata',
        ]);
    }

    /**
     * Register the columns to appear in the table
     *
     * @since 0.7
     */
    public function get_columns()
    {
        $columns = [
            'position' => esc_html__('Position', 'publishpress'),
            'name' => esc_html__('Name', 'publishpress'),
            'post_types' => esc_html__('Post Types', 'publishpress'),
            'type' => esc_html__('Field Type', 'publishpress'),
            'description' => esc_html__('Description', 'publishpress')
        ];

        return $columns;
    }

    /**
     * Prepare a single row of Editorial Fields
     *
     * @param object $term The current term we're displaying
     * @param int $level Level is always zero because it isn't a parent-child tax
     *
     * @since 0.7
     *
     */
    public function single_row($term, $level = 0)
    {
        static $alternate_class = '';
        $alternate_class = ($alternate_class == '' ? ' alternate' : '');
        $row_class = ' class="term-static' . $alternate_class . '"';

        echo '<tr id="term-' . esc_attr($term->term_id) . '"' . $row_class . '>';
        echo $this->single_row_columns($term);
        echo '</tr>';
    }

    /**
     * Handle the column output when there's no method for it
     *
     * @param object $item Editorial Fields term as an object
     * @param string $column_name How the column was registered at birth
     *
     * @since 0.7
     *
     */
    public function column_default($item, $column_name)
    {
        global $publishpress;

        switch ($column_name) {
            case 'position':
            case 'description':
                return esc_html($item->$column_name);
            case 'type':
                $type = $item->$column_name;

                return isset($this->metadata_types[$type])
                    ? esc_html($this->metadata_types[$type])
                    : esc_html($type);
            case 'post_types':
                $post_types = (isset($item->post_types) && !empty(($item->post_types))) ? (array) $item->post_types : [];
                if (!empty($post_types)) {
                    $valid_post_types = $publishpress->editorial_metadata->get_all_post_types($publishpress->editorial_metadata->module);
                    $post_type_names = [];
                    foreach ($valid_post_types as $post_type => $post_types_name) {
                        if (in_array($post_type, $post_types)) {
                            $post_type_names[] = $post_types_name;
                        }
                    }
                    return join(', ', $post_type_names);
                } else {
                    return '-';
                }
                break;
            default:
                break;
        }
    }

    /**
     * Prepare the items to be displayed on the list table
     *
     * @since 0.7
     */
    public function prepare_items()
    {
        global $publishpress;
        $this->items = $publishpress->editorial_metadata->get_editorial_metadata_terms();

        $this->set_pagination_args([
            'total_items' => count($this->items),
            'per_page' => count($this->items),
        ]);
    }

    /**
     * Message to be displayed when there is no editorial fields
     *
     * @since 0.7
     */
    public function no_items()
    {
        esc_html_e('No editorial fields found.', 'publishpress');
    }

    /**
     * Column for displaying the term's name and associated actions
     *
     * @param object $item Editorial Fields term as an object
     *
     * @since 0.7
     *
     */
    public function column_name($item)
    {
        global $publishpress;
        $item_edit_link = esc_url(
            PP_Editorial_Metadata_Utilities::get_link(['action' => 'edit-term', 'term-id' => $item->term_id])
        );
        $item_delete_link = esc_url(
            PP_Editorial_Metadata_Utilities::get_link(['action' => 'delete-term', 'term-id' => $item->term_id])
        );

        $out = '<strong><a class="row-title" href="' . $item_edit_link . '">' . esc_html($item->name) . '</a></strong>';

        $actions = [];
        $actions['edit'] = "<a href='$item_edit_link'>" . esc_html__('Edit', 'publishpress') . "</a>";

        $actions['delete delete-status'] = "<a href='$item_delete_link'>" . esc_html__('Delete', 'publishpress') . "</a>";

        $out .= $this->row_actions($actions, false);

        return $out;
    }
}
