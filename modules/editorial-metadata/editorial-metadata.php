<?php
/**
 * @package PublishPress
 * @author PublishPress
 *
 * Copyright (c) 2022 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

use PublishPress\Notifications\Traits\Dependency_Injector;

if (! class_exists('PP_Editorial_Metadata')) {
    /**
     * class PP_Editorial_Metadata
     * This class gives publishers arbitrary structured content details to go along with every post
     *
     * @author sbressler, danielbachhuber
     *
     * Ways to test and play with this class:
     * 1) Create a new term by selecting Editorial Fields from the PublishPress settings
     * 2) Edit an existing term (slug, description, etc.)
     * 3) Create a post and assign metadata to it
     * 4) Look at the list of terms again - the count should go up!
     * 5) Play with adding more metadata to a post
     * 6) Clear the metadata for a single term in a post and watch the count go down!
     * 6) Delete a term and note the metadata disappears from posts
     * 7) Re-add the term (same slug) and the metadata returns!
     *
     * Improvements to make:
     * @todo Abstract the permissions check for management to class level
     */
    #[\AllowDynamicProperties]
    class PP_Editorial_Metadata extends PP_Module
    {
        use Dependency_Injector;

        /**
         * The name of the taxonomy we're going to register for editorial fields.
         */
        const metadata_taxonomy = 'pp_editorial_meta';

        const metadata_postmeta_key = "_pp_editorial_meta";

        const SETTINGS_SLUG = 'pp-editorial-metadata-settings';

        public $module_name = 'editorial_metadata';

        private $editorial_metadata_terms_cache = [];

        const CAP_VIEW_METADATA = 'pp_view_editorial_metadata';

        const CAP_EDIT_METADATA = 'pp_edit_editorial_metadata';

        /**
         * @var string
         */
        const MENU_SLUG = 'pp-editorial-metadata';

        /**
         * Stores a chain of input-type handlers.
         *
         * @var Editorial_Metadata_Input_Handler $editorial_metadata_input_handler
         */
        private $editorial_metadata_input_handler = null;

        /**
         * Construct the PP_Editorial_Metadata class
         */
        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);
            // Register the module with PublishPress
            $args = [
                'title' => esc_html__('Editorial Fields', 'publishpress'),
                'short_description' => false,
                'extended_description' => false,
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-feedback',
                'slug' => 'editorial-metadata',
                'default_options' => [
                    'enabled' => 'on',
                    'post_types' => [
                        'post' => 'on',
                        'page' => 'off',
                    ],
                ],
                'messages' => [
                    'term-added' => esc_html__("Editorial fields term added.", 'publishpress'),
                    'term-updated' => esc_html__("Editorial fields term updated.", 'publishpress'),
                    'term-missing' => esc_html__("Editorial fields term doesn't exist.", 'publishpress'),
                    'term-deleted' => esc_html__("Editorial fields term deleted.", 'publishpress'),
                    'term-position-updated' => esc_html__("Editorial fields order updated.", 'publishpress'),
                    'term-visibility-changed' => esc_html__("Editorial fields visibility changed.", 'publishpress'),
                ],
                'configure_page_cb' => 'print_configure_view',
                'settings_help_tab' => [
                    'id' => 'pp-editorial-metadata-overview',
                    'title' => esc_html__('Overview', 'publishpress'),
                    'content' => esc_html__(
                        '<p>Keep track of important details about your content with editorial fields. This feature allows you to create as many date, text, number, etc. fields as you like, and then use them to store information like contact details or the location of an interview.</p><p>Once you’ve set your fields up, editorial fields integrates with both the calendar and the content overview. Make an editorial fields item visible to have it appear to the rest of your team. Keep it hidden to restrict the information between the writer and their editor.</p>',
                        'publishpress'
                    ),
                ],
                'settings_help_sidebar' => esc_html__(
                    '<p><strong>For more information:</strong></p><p><a href="https://publishpress.com/features/editorial-metadata/">Editorial Fields Documentation</a></p><p><a href="https://github.com/ostraining/PublishPress">PublishPress on Github</a></p>',
                    'publishpress'
                ),
                'options_page' => true,
                'add_menu' => true,
            ];

            $this->module = PublishPress()->register_module($this->module_name, $args);
        }

        /**
         * Initialize the module. Conditionally loads if the module is enabled
         */
        public function init()
        {
            // Register the taxonomy we use for Editorial Fields with WordPress core
            $this->register_taxonomy();

            // Load the chain of input-type handlers.
            $this->load_input_handlers();
            // Load utilities files.
            $this->load_utilities_files();

            if (is_admin()) {
                // Anything that needs to happen in the admin
                add_action('admin_init', [$this, 'action_admin_init']);

                // Register our settings
                add_action('admin_init', [$this, 'register_settings']);

                if ($this->checkEditCapability()) {
                    // Actions relevant to the configuration view (adding, editing, or sorting existing Editorial Fields)
                    add_action('admin_init', [$this, 'handle_add_editorial_metadata']);
                    add_action('admin_init', [$this, 'handle_edit_editorial_metadata']);
                    add_action('admin_init', [$this, 'handle_change_editorial_metadata_visibility']);
                    add_action('admin_init', [$this, 'handle_delete_editorial_metadata']);
                    add_action('wp_ajax_update_term_positions', [$this, 'handle_ajax_update_term_positions']);

                    add_action('transition_post_status', [$this, 'save_meta_box'], 50, 3);
                }

                if ($this->checkEditCapability() || $this->checkViewCapability()) {
                    add_action('add_meta_boxes', [$this, 'handle_post_metaboxes']);
                    // Add Editorial Fields columns to the Manage Posts view
                    add_filter("manage_posts_columns", [$this, 'filter_manage_posts_columns'], 10, 2);
                    add_filter("manage_pages_columns", [$this, 'filter_manage_posts_columns']);
                    add_action("manage_pages_custom_column", [$this, 'action_manage_posts_custom_column'], 10, 2);
                    add_action("manage_posts_custom_column", [$this, 'action_manage_posts_custom_column'], 10, 2);

                    // Add Editorial Fields to the calendar if the calendar is activated
                    if ($this->module_enabled('calendar')) {
                        add_filter('publishpress_calendar_get_post_data', [$this, 'filterCalendarPostData'], 10, 2);
                    }

                    // Add Editorial Fields columns to the Content Overview if it exists
                    if ($this->module_enabled('story_budget')) {
                        add_filter('pp_story_budget_term_columns', [$this, 'filter_story_budget_term_columns']);
                        // Register an action to handle this data later
                        add_filter(
                            'pp_story_budget_term_column_value',
                            [$this, 'filter_story_budget_term_column_values'],
                            10,
                            3
                        );
                    }
                }
                //admin menu
                add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu'], 20);

                // Load necessary scripts and stylesheets
                add_action('admin_enqueue_scripts', [$this, 'add_admin_scripts']);
            }
        }

        /**
         * Load default editorial fields the first time the module is loaded
         *
         * @since 0.7
         */
        public function install()
        {
            global $publishpress;

            // Our default editorial fieldss
            $default_metadata = [
                [
                    'name' => esc_html__('First Draft Date', 'publishpress'),
                    'slug' => 'first-draft-date',
                    'type' => 'date',
                    'description' => esc_html__('When the first draft needs to be ready.', 'publishpress'),
                    'post_types' => ['post'],
                    'post_types_column' => ['post'],
                ],
                [
                    'name' => esc_html__('Assignment', 'publishpress'),
                    'slug' => 'assignment',
                    'type' => 'paragraph',
                    'description' => esc_html__('What the post needs to cover.', 'publishpress'),
                    'post_types' => ['post'],
                    'post_types_column' => ['post'],
                ],
            ];
            // Load the editorial fieldss if the slugs don't conflict
            foreach ($default_metadata as $args) {
                if (! term_exists($args['slug'], self::metadata_taxonomy)) {
                    $this->insert_editorial_metadata_term($args);
                }
            }

            $this->setDefaultCapabilities();

            $publishpress->update_module_option($this->module->name, 'enabled', 'off');
        }

        /**
         * Upgrade our data in case we need to
         *
         * @since 0.7
         */
        public function upgrade($previousVersion)
        {
            global $publishpress;

            // Upgrade path to v0.7
            if (version_compare($previousVersion, '0.7', '<')) {
                // Technically we've run this code before so we don't want to auto-install new data
                $publishpress->update_module_option($this->module->name, 'loaded_once', true);
            }
            // Upgrade path to v0.7.4
            if (version_compare($previousVersion, '0.7.4', '<')) {
                // Editorial fields descriptions become base64_encoded, instead of maybe json_encoded.
                $this->upgrade_074_term_descriptions(self::metadata_taxonomy);
            }

            if (version_compare($previousVersion, '3.0.1', '<=')) {
                $this->setDefaultCapabilities();
            }

            if (version_compare($previousVersion, '3.10.0', '<=')) {
                $this->migrateMetadataPostTypes();
            }
        }

        /**
         * Anything that needs to happen on the 'admin_init' hook
         *
         * @since 0.7.4
         */
        public function action_admin_init()
        {
            // Parse the query when we're ordering by an editorial fields term
            add_action('parse_query', [$this, 'action_parse_query']);
        }

        /**
         * Prepare an array of supported editorial fields types
         *
         * @return array $supported_metadata_types All of the supported metadata
         */
        private function get_supported_metadata_types()
        {
            $supported_metadata_types = [
                'checkbox' => esc_html__('Checkbox', 'publishpress'),
                'select' => esc_html__('Dropdown Select', 'publishpress'),
                'date' => esc_html__('Date', 'publishpress'),
                'location' => esc_html__('Location', 'publishpress'),
                'number' => esc_html__('Number', 'publishpress'),
                'paragraph' => esc_html__('Paragraph', 'publishpress'),
                'text' => esc_html__('Text', 'publishpress'),
                'user' => esc_html__('User', 'publishpress'),
            ];
            return $supported_metadata_types;
        }

        /**
         * Prepare an array of supported editorial fields user roles
         *
         * @return array $supported_metadata_user_roles All of the supported metadata
         */
        private function get_supported_metadata_user_roles()
        {
            global $wp_roles;
            if (!isset($wp_roles)) {
                $wp_roles = new WP_Roles();
            }

            $supported_metadata_user_roles = [];

            foreach ($wp_roles->roles as $role => $details) {
                $supported_metadata_user_roles[$role] = translate_user_role($details['name']);
            }

            return $supported_metadata_user_roles;
        }

        /**
         * Enqueue relevant admin Javascript
         */
        public function add_admin_scripts()
        {
            global $current_screen, $pagenow;

            // Add the metabox date picker JS and CSS
            $current_post_type = $this->get_current_post_type();
            $supported_post_types = $this->get_all_terms_post_types();
            if (in_array($current_post_type, $supported_post_types) || $this->is_whitelisted_settings_view($this->module->name)) {
                $this->enqueue_datepicker_resources();

                // Now add the rest of the metabox CSS
                wp_enqueue_style(
                    'publishpress-editorial_metadata-styles',
                    $this->module_url . 'lib/editorial-metadata.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'all'
                );

                wp_enqueue_style(
                    'publishpress-select2-css',
                    PUBLISHPRESS_URL . 'common/libs/select2/css/select2-full.min.css',
                    false,
                    PUBLISHPRESS_VERSION,
                    'all'
                );

                wp_enqueue_script('jquery-ui-sortable');

                wp_enqueue_script(
                    'publishpress-editorial-select2',
                    PUBLISHPRESS_URL . 'common/libs/select2/js/select2-full.min.js',
                    ['jquery'],
                    PUBLISHPRESS_VERSION
                );

                wp_enqueue_script(
                    'publishpress-editorial-metadata-configure',
                    PUBLISHPRESS_URL . 'modules/editorial-metadata/lib/editorial-metadata-configure.js',
                    ['jquery', 'jquery-ui-sortable', 'publishpress-editorial-select2'],
                    PUBLISHPRESS_VERSION,
                    true
                );

                wp_localize_script(
                    'publishpress-editorial-metadata-configure',
                    'objectL10nMetadata',
                    [
                        'pp_confirm_delete_term_string' => esc_html__(
                            'Are you sure you want to delete this term? Any metadata for this term will remain but will not be visible unless this term is re-added.',
                            'publishpress'
                        ),
                    ]
                );
            }
            // A bit of custom CSS for the Manage Posts view if we have viewable metadata
            if ($current_screen->base == 'edit' && in_array($current_post_type, $supported_post_types)) {
                $terms = $this->get_editorial_metadata_terms();
                $viewable_terms = [];
                foreach ($terms as $term) {
                    if (!empty($term->post_types_column) && in_array($current_post_type, $term->post_types_column)) {
                        $viewable_terms[] = $term;
                    }
                }
                if (! empty($viewable_terms)) {
                    $css_rules = [
                        '.wp-list-table.fixed .column-author' => [
                            'min-width: 7em;',
                            'width: auto;',
                        ],
                        '.wp-list-table.fixed .column-tags' => [
                            'min-width: 7em;',
                            'width: auto;',
                        ],
                        '.wp-list-table.fixed .column-categories' => [
                            'min-width: 7em;',
                            'width: auto;',
                        ],
                    ];
                    foreach ($viewable_terms as $viewable_term) {
                        switch ($viewable_term->type) {
                            case 'checkbox':
                            case 'number':
                            case 'date':
                                $css_rules['.wp-list-table.fixed .column-' . $this->module->slug . '-' . esc_html($viewable_term->slug)] = [
                                    'min-width: 6em;',
                                ];
                                break;
                            case 'location':
                            case 'text':
                            case 'user':
                                $css_rules['.wp-list-table.fixed .column-' . $this->module->slug . '-' . esc_html($viewable_term->slug)] = [
                                    'min-width: 7em;',
                                ];
                                break;
                            case 'paragraph':
                                $css_rules['.wp-list-table.fixed .column-' . $this->module->slug . '-' . esc_html($viewable_term->slug)] = [
                                    'min-width: 8em;',
                                ];
                                break;
                        }
                    }
                    // Allow users to filter out rules if there's something wonky
                    $css_rules = apply_filters('pp_editorial_metadata_manage_posts_css_rules', $css_rules);
                    echo "<style type=\"text/css\">\n";
                    foreach ((array)$css_rules as $css_property => $rules) {
                        echo $css_property . " {" . implode(' ', $rules) . "}\n";
                    }
                    echo '</style>';
                }
            }
        }

        /**
         * Register the post metadata taxonomy
         */
        private function register_taxonomy()
        {
            // We need to make sure taxonomy is registered for all of the post types that support it
            $supported_post_types = $this->get_post_types_for_module($this->module);

            register_taxonomy(
                self::metadata_taxonomy,
                $supported_post_types,
                [
                    'public' => false,
                    'labels' => [
                        'name' => esc_html_x('Editorial Field', 'taxonomy general name', 'publishpress'),
                        'singular_name' => esc_html_x('Editorial Field', 'taxonomy singular name', 'publishpress'),
                        'search_items' => esc_html__('Search Editorial Fields', 'publishpress'),
                        'popular_items' => esc_html__('Popular Editorial Fields', 'publishpress'),
                        'all_items' => esc_html__('All Editorial Fields', 'publishpress'),
                        'edit_item' => esc_html__('Edit Editorial Fields', 'publishpress'),
                        'update_item' => esc_html__('Update Editorial Fields', 'publishpress'),
                        'add_new_item' => esc_html__('Add New Editorial Fields', 'publishpress'),
                        'new_item_name' => esc_html__('New Editorial Fields', 'publishpress'),
                    ],
                    'rewrite' => false,
                ]
            );
        }

        private function getViewCapability()
        {
            return apply_filters('publishpress_view_editorial_metadata_cap', self::CAP_VIEW_METADATA);
        }

        private function getEditCapability()
        {
            return apply_filters('publishpress_edit_editorial_metadata_cap', self::CAP_EDIT_METADATA);
        }

        private function setDefaultCapabilities()
        {
            $roles = ['administrator', 'editor', 'author'];

            $viewCap = $this->getViewCapability();
            $editCap = $this->getEditCapability();

            foreach ($roles as $role) {
                $role = get_role($role);

                if (is_object($role) && ! is_wp_error($role)) {
                    $role->add_cap($viewCap);
                    $role->add_cap($editCap);
                }
            }
        }

        public function get_all_terms_post_types() {
            $post_type = [];
            $terms = $this->get_editorial_metadata_terms();
            foreach ($terms as $term) {
                if (isset($term->post_types) && is_array($term->post_types)) {
                    $post_type = array_merge($post_type, $term->post_types);
                }
            }
            $post_type = array_unique($post_type);

            return $post_type;
        }

        /**
         * Load the post metaboxes for all of the post types that are supported
         */
        public function handle_post_metaboxes()
        {
            $title = esc_html__('Editorial Fields', 'publishpress');

            $supported_post_types = $this->get_all_terms_post_types();
            foreach ($supported_post_types as $post_type) {
                add_meta_box(self::metadata_taxonomy, $title, [$this, 'display_meta_box'], $post_type, 'side');
            }
        }

        private function checkViewCapability()
        {
            return current_user_can($this->getViewCapability());
        }

        private function checkEditCapability()
        {
            /**
             * The capability "pp_editorial_metadata_user_can_edit" is deprecated in favor of "pp_edit_editorial_metadata".
             */
            return current_user_can($this->getEditCapability()) || current_user_can(
                    'pp_editorial_metadata_user_can_edit'
                );
        }

        /**
         * Displays HTML output for Editorial Fields post meta box
         *
         * @param object $post Current post
         */
        public function display_meta_box($post)
        {
            echo "<div id='" . self::metadata_taxonomy . "_meta_box'>";
            // Add nonce for verification upon save
            echo "<input type='hidden' name='" . self::metadata_taxonomy . "_nonce' value='" . wp_create_nonce(
                    __FILE__
                ) . "' />";

            $terms = $this->get_editorial_metadata_terms();
            if (! count($terms)) {
                $message = esc_html__('No editorial fields available.', 'publishpress');
                if (current_user_can('manage_options')) {
                    $message .= sprintf(__(' <a href="%s">Add fields to get started</a>.', 'publishpress'), esc_url(PP_Editorial_Metadata_Utilities::get_link()));
                } else {
                    $message .= esc_html__(
                        ' Encourage your site administrator to configure your editorial workflow by adding editorial fields.',
                        'publishpress'
                    );
                }
                echo '<p>' . $message . '</p>';
            } else {
                $post_type = $post->post_type;
                foreach ($terms as $term) {
                    if (isset($term->post_types) && is_array($term->post_types) && in_array($post_type, $term->post_types)) {
                        $postmeta_key = esc_attr($this->get_postmeta_key($term));
                        $current_metadata = $this->get_postmeta_value($term, $post->ID);

                        echo "<div class='" . esc_attr(self::metadata_taxonomy) . " " . esc_attr(
                            self::metadata_taxonomy
                        ) . "_$term->type'>";

                        // Check if the user can edit the metadata
                        $can_edit = $this->checkEditCapability();

                        $term_options = $this->get_editorial_metadata_term_by('id', $term->term_id);
                        if ($can_edit) {
                            $this->editorial_metadata_input_handler->handleHtmlRendering(
                                $term->type,
                                [
                                'name' => $postmeta_key,
                                'label' => $term->name,
                                'description' => $term->description,
                                'term_options' => $term_options,
                            ],
                                $current_metadata
                            );
                        } else {
                            $this->editorial_metadata_input_handler->handlePreviewRendering(
                                $term->type,
                                [
                                'name' => $postmeta_key,
                                'label' => $term->name,
                                'description' => $term->description,
                                'term_options' => $term_options,
                            ],
                                $current_metadata
                            );
                        }

                        echo "</div>";
                        echo "<div class='clear'></div>";
                    }
                } // Done iterating through Editorial fields terms
            }

            if ($this->checkEditCapability()) {
                // Make the metabox title include a link to edit the Editorial Fields terms. Logic similar to how Core dashboard widgets work.
                echo '<span class="postbox-title-action"><a href="' . esc_url(PP_Editorial_Metadata_Utilities::get_link()) . '">' . esc_html__(
                        'Configure'
                    ) . '</a></span>';
            }

            echo "</div>";
        }

        /**
         * Save any values in the editorial fields post meta box.
         *
         * We don't use them, but we receive the old_status and new_status param because of the
         * `transition_post_status` action, since this method has to be
         * executed before notifications are triggered. See issue #574.
         *
         * @param $old_status
         * @param $new_status
         * @param object $post Post object
         *
         * @return mixed|void
         */
        public function save_meta_box($old_status, $new_status, $post)
        {
            // Authentication checks: make sure data came from our meta box and that the current user is allowed to edit the post
            // TODO: switch to using check_admin_referrer? See core (e.g. edit.php) for usage
            if (
                (! isset($_POST[self::metadata_taxonomy . "_nonce"])
                || ! wp_verify_nonce(sanitize_key($_POST[self::metadata_taxonomy . "_nonce"]), __FILE__)
                )
                && (! isset($_POST["nonce"])
                || ! wp_verify_nonce(sanitize_key($_POST['nonce']), 'publishpress-calendar-get-data'))
                && (! isset($_POST["_nonce"])
                || ! wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_overview_post_form_nonce'))
                ) {
                return $post->ID;
            }

            if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
                || ! in_array($post->post_type, $this->get_all_terms_post_types())
                || $post->post_type == 'post' && ! current_user_can('edit_post', $post->ID)
                || $post->post_type == 'page' && ! current_user_can('edit_page', $post->ID)) {
                return $post->ID;
            }

            // Authentication passed, let's save the data
            $terms = $this->get_editorial_metadata_terms();
            $term_slugs = [];
            $post_type = $post->post_type;
            foreach ($terms as $term) {
                if (isset($term->post_types) && is_array($term->post_types) && in_array($post_type, $term->post_types)) {
                    // Set up the key for this editorial fields term (same as what's in $_POST)
                    $key = $this->get_postmeta_key($term);
                    if (isset($_POST[$key]) && is_array($_POST[$key])) {
                        $new_metadata = array_map('sanitize_text_field', $_POST[$key]);
                    } else {
                        $new_metadata = isset($_POST[$key]) ? sanitize_text_field($_POST[$key]) : '';
                    }
                    $type = $term->type;
                    if (empty($new_metadata)) {
                        delete_post_meta($post->ID, $key);
                    } else {
                        // TODO: Move this to a function
                        if ($type == 'date') {
                            $new_metadata = isset($_POST[$key . '_hidden']) ? sanitize_text_field($_POST[$key . '_hidden']) : '';
                            $date = DateTime::createFromFormat('Y-m-d H:i', $new_metadata);
                            if (false !== $date) {
                                $new_metadata = $date->getTimestamp();
                            }
                        } elseif ($type == 'number') {
                            $new_metadata = (int)$new_metadata;
                        }
                        if (is_array($new_metadata)) {
                            $new_metadata = array_filter($new_metadata);
                        } else {
                            $new_metadata = strip_tags($new_metadata);
                        }
                        update_post_meta($post->ID, $key, $new_metadata);
                        // Add the slugs of the terms with non-empty new metadata to an array
                        $term_slugs[] = $term->slug;
                    }

                    do_action('pp_editorial_metadata_field_updated', $key, $new_metadata, $post->ID, $type);
                }
            }

            // Relate the post to the terms used and taxonomy type (wp_term_relationships table).
            // This will allow us to update and display the count of metadata in posts in use per term.
            // TODO: Core only correlates posts with terms if the post_status is publish. Do we care what it is?
            if ($post->post_status === 'publish') {
                wp_set_object_terms($post->ID, $term_slugs, self::metadata_taxonomy);
            }
        }

        /**
         * Generate a unique key based on the term
         *
         * @param object $term Term object
         *
         * @return string $postmeta_key Unique key
         */
        public function get_postmeta_key($term)
        {
            $key = self::metadata_postmeta_key;
            $type = $term->type;
            $prefix = "{$key}_{$type}";
            $postmeta_key = "{$prefix}_" . (is_object($term) ? $term->slug : $term);
            return $postmeta_key;
        }

        /**
         * Returns the value for the given metadata
         *
         * @param object|string|int term The term object, slug or ID for the editorial fields term
         * @param int post_id The ID of the post
         */
        private function get_postmeta_value($term, $post_id)
        {
            if (! is_object($term)) {
                if (is_int($term)) {
                    $term = $this->get_editorial_metadata_term_by('id', $term);
                } else {
                    $term = $this->get_editorial_metadata_term_by('slug', $term);
                }
            }
            $postmeta_key = $this->get_postmeta_key($term);
            return get_metadata('post', $post_id, $postmeta_key, true);
        }

        /**
         * Get all of the editorial fields terms as objects and sort by position
         *
         * @param array $filter_args Filter to specific arguments
         *
         * @return array $ordered_terms The terms as they should be ordered
         * @todo Figure out what we should do with the filter...
         *
         */
        public function get_editorial_metadata_terms($filter_args = [])
        {

            if (empty($filter_args['viewable'])) {
                // viewable has been removed, use post type instead
                unset($filter_args['viewable']);
            }

            // Try to fetch from internal object cache
            $arg_hash = md5(wp_json_encode($filter_args));
            if (isset($this->editorial_metadata_terms_cache[$arg_hash])) {
                return $this->editorial_metadata_terms_cache[$arg_hash];
            }

            $args = [
                'orderby' => apply_filters('pp_editorial_metadata_term_order', 'name'),
                'hide_empty' => false,
            ];

            $terms = get_terms(self::metadata_taxonomy, $args);
            $ordered_terms = [];
            $hold_to_end = [];
            // Order the terms
            if (!is_wp_error($terms)) {
                foreach ($terms as $key => $term) {
                    // Unencode and set all of our pseudo term meta because we need the position and viewable if they exists
                    // First do an array_merge() on the term object to make sure the keys exist, then array_merge()
                    // any values that may already exist
                    $unencoded_description = $this->get_unencoded_description($term->description);
                    $defaults = [
                        'description' => '',
                        'position' => false,
                        'show_in_calendar_form' => false,
                    ];
                    $term = array_merge($defaults, (array)$term);
                    if (is_array($unencoded_description)) {
                        $term = array_merge($term, $unencoded_description);
                    }
                    $term = (object)$term;
                    // We used to store the description field in a funny way
                    if (isset($term->desc)) {
                        $term->description = $term->desc;
                        unset($term->desc);
                    }
                    // Only add the term to the ordered array if it has a set position and doesn't conflict with another key
                    // Otherwise, hold it for later
                    if ($term->position && ! array_key_exists($term->position, $ordered_terms)) {
                        $ordered_terms[(int)$term->position] = $term;
                    } else {
                        $hold_to_end[] = $term;
                    }
                }
            }
            // Sort the items numerically by key
            ksort($ordered_terms, SORT_NUMERIC);
            // Append all of the terms that didn't have an existing position
            foreach ($hold_to_end as $unpositioned_term) {
                $ordered_terms[] = $unpositioned_term;
            }

            // If filter arguments were passed, do our filtering
            $ordered_terms = wp_filter_object_list($ordered_terms, $filter_args);

            // Set the internal object cache
            $this->editorial_metadata_terms_cache[$arg_hash] = $ordered_terms;

            return $ordered_terms;
        }

        /**
         * Returns a term for single editorial fields
         *
         * @param int|string $field The slug or ID for the editorial fields term to return
         *
         * @return object $term Term's object representation
         */
        public function get_editorial_metadata_term_by($field, $value)
        {
            if (! in_array($field, ['id', 'slug', 'name'])) {
                return false;
            }

            if ('id' == $field) {
                $field = 'term_id';
            }

            $terms = $this->get_editorial_metadata_terms();
            $term = wp_filter_object_list($terms, [$field => $value]);

            if (! empty($term)) {
                return array_shift($term);
            } else {
                return false;
            }
        }

        /**
         * Register editorial fields fields as columns in the manage posts view
         * Only adds columns for the currently active post types
         *
         * @param array $posts_columns Existing post columns prepared by WP_List_Table
         * @param array $posts_columns Previous post columns with the new values
         *
         * @since 0.7
         * @uses apply_filters('manage_posts_columns') in wp-admin/includes/class-wp-posts-list-table.php
         *
         */
        public function filter_manage_posts_columns($posts_columns = '', $post_type = 'page')
        {
            $screen = get_current_screen();
            if ($screen) {
                add_filter("manage_{$screen->id}_sortable_columns", [$this, 'filter_manage_posts_sortable_columns']);
                $terms = $this->get_editorial_metadata_terms();
                foreach ($terms as $term) {
                    if (isset($term->post_types_column) && is_array($term->post_types_column) && in_array($post_type, $term->post_types_column)) {
                        // Prefixing slug with module slug because it isn't stored prefixed and we want to avoid collisions
                        $key = $this->module->slug . '-' . $term->slug;
                        $posts_columns[$key] = $term->name;
                    }
                }
            }
            return $posts_columns;
        }

        /**
         * Register any viewable date editorial fields as a sortable column
         *
         * @param array $sortable_columns Any existing sortable columns (e.g. Title)
         *
         * @return array $sortable_columms Sortable columns with editorial fields date fields added
         * @since 0.7.4
         *
         */
        public function filter_manage_posts_sortable_columns($sortable_columns)
        {
            $terms = $this->get_editorial_metadata_terms(['type' => 'date']);
            foreach ($terms as $term) {
                // Prefixing slug with module slug because it isn't stored prefixed and we want to avoid collisions
                $key = $this->module->slug . '-' . $term->slug;
                $sortable_columns[$key] = $key;
            }
            return $sortable_columns;
        }

        /**
         * If we're ordering by a sortable column, let's modify the query
         *
         * @since 0.7.4
         */
        public function action_parse_query($query)
        {
            if (is_admin() && is_string(get_query_var('orderby')) && false !== stripos(get_query_var('orderby'), $this->module->slug)) {
                $term_slug = sanitize_key(str_replace($this->module->slug . '-', '', get_query_var('orderby')));
                $term = $this->get_editorial_metadata_term_by('slug', $term_slug);
                $meta_key = $this->get_postmeta_key($term);
                set_query_var('meta_key', $meta_key);
                set_query_var('orderby', 'meta_value_num');
            }
        }

        /**
         * Handle the output of an editorial fields custom column
         *
         * @param string $column_name Unique string for the column
         * @param int $post_id ID for the post of the row
         *
         * @since 0.7
         * @uses do_action('manage_posts_custom_column') in wp-admin/includes/class-wp-posts-list-table.php
         *
         */
        public function action_manage_posts_custom_column($column_name, $post_id)
        {
            $terms = $this->get_editorial_metadata_terms();
            $post_type = get_post_type($post_id);
            // We're looking for the proper term to display its saved value
            foreach ($terms as $term) {
                if (isset($term->post_types_column) && is_array($term->post_types_column) && in_array($post_type, $term->post_types_column)) {
                    $key = $this->module->slug . '-' . $term->slug;
                    if ($column_name != $key) {
                        continue;
                    }

                    $current_metadata = $this->get_postmeta_value($term, $post_id);
                    echo $this->generate_editorial_metadata_term_output($term, $current_metadata);
                }
            }
        }

        /**
         * If the PublishPress Calendar is enabled, add viewable Editorial Fields terms
         *
         * @param array $data Additional data fields to include on the calendar
         * @param WP_Post $post
         *
         * @return array
         */
        public function filterCalendarPostData($data, $post)
        {
            // Make sure we respect which post type we're on
            if (! in_array($post->post_type, $this->get_all_terms_post_types())) {
                return $data;
            }

            $terms = $this->get_editorial_metadata_terms();

            $post_type = $post->post_type;
            foreach ($terms as $term) {
                if (isset($term->post_types) && is_array($term->post_types) && in_array($post_type, $term->post_types)) {
                    $currentMetadata = $this->get_postmeta_value($term, $post->ID);
                    $value = $this->generate_editorial_metadata_term_output($term, $currentMetadata);

                    if (apply_filters(
                        'pp_calendar_hide_empty_item_information_fields',
                        true,
                        $post->ID
                    )
                    && (is_null($value) || '' === $value)
                ) {
                        continue;
                    }

                    $data['fields'][$term->slug] = [
                    'label' => $term->name,
                    'value' => $value,
                    'editable' => true,
                    'type' => $term->type,
                ];
                }
            }

            return $data;
        }

        /**
         * If the PublishPress Content Overview is enabled, register our viewable terms as columns
         *
         * @param array $term_columns The existing columns on the content overview
         *
         * @return array $term_columns Term columns with viewable Editorial Fields terms
         * @since 0.7
         * @uses apply_filters('pp_story_budget_term_columns')
         *
         */
        public function filter_story_budget_term_columns($term_columns)
        {
            $terms = $this->get_editorial_metadata_terms();
            foreach ($terms as $term) {
                // Prefixing slug with module slug because it isn't stored prefixed and we want to avoid collisions
                $key = $this->module->slug . '-' . $term->slug;
                // Switch to underscores
                $key = str_replace('-', '_', $key);
                $term_columns[$key] = $term->name;
            }
            return $term_columns;
        }

        /**
         * If the PublishPress Content Overview is enabled,
         *
         * @param object $post The post we're displaying
         * @param string $column_name Name of the column, as registered with PP_Story_Budget::register_term_columns
         * @param object $parent_term The parent term for the term column
         *
         * @uses apply_filters('pp_story_budget_term_column_value')
         *
         * @since 0.7
         */
        public function filter_story_budget_term_column_values($column_name, $post, $parent_term)
        {
            $local_column_name = str_replace('_', '-', $column_name);
            // Don't accidentally handle values not our own
            if (false === strpos($local_column_name, $this->module->slug)) {
                return $column_name;
            }

            $term_slug = str_replace($this->module->slug . '-', '', $local_column_name);
            $term = $this->get_editorial_metadata_term_by('slug', $term_slug);

            // Don't allow non-viewable term data to be displayed
            if (! $term->viewable) {
                return $column_name;
            }

            $current_metadata = $this->get_postmeta_value($term, $post->ID);
            $output = $this->generate_editorial_metadata_term_output($term, $current_metadata);

            return $output;
        }

        /**
         * Generate the presentational output for an editorial fields term
         *
         * @param object $term The editorial fields term
         *
         * @return string     $html    How the term should be rendered
         * @since 0.8
         *
         */
        private function generate_editorial_metadata_term_output($term, $pm_value)
        {
            return $this->editorial_metadata_input_handler->handleMetaValueHtmling(
                $term->type,
                $pm_value,
                $term
            );
        }

        /**
         * Update an existing editorial fields term if the term_id exists
         *
         * @param int $term_id The term's unique ID
         * @param array $args Any values that need to be updated for the term
         *
         * @return object|WP_Error $updated_term The updated term or a WP_Error object if something disastrous happened
         * @since 0.7
         *
         */
        private function update_editorial_metadata_term($term_id, $args)
        {
            $old_args = [];
            $old_term = $this->get_editorial_metadata_term_by('id', $term_id);
            if ($old_term) {
                $old_args = [
                    'position' => $old_term->position,
                    'name' => $old_term->name,
                    'slug' => $old_term->slug,
                    'description' => $old_term->description,
                    'type' => $old_term->type,
                    'user_role' => isset($old_term->user_role) ? $old_term->user_role : '',
                    'select_type' => isset($old_term->select_type) ? $old_term->select_type : '',
                    'select_default' => isset($old_term->select_default) ? $old_term->select_default : '',
                    'select_options' => isset($old_term->select_options) ? $old_term->select_options : '',
                    'post_types' => isset($old_term->post_types) ? $old_term->post_types : '',
                    'show_in_calendar_form' => isset($old_term->show_in_calendar_form) ? $old_term->show_in_calendar_form : false,
                    'post_types_column' => isset($old_term->post_types_column) ? $old_term->post_types_column : '',
                ];
            }
            $new_args = array_merge($old_args, $args);

            // We're encoding metadata that isn't supported by default in the term's description field
            $args_to_encode = [
                'description' => $new_args['description'],
                'position' => $new_args['position'],
                'type' => $new_args['type'],
                'user_role' => $new_args['user_role'],
                'select_type' => $new_args['select_type'],
                'select_default' => $new_args['select_default'],
                'select_options' => $new_args['select_options'],
                'show_in_calendar_form' => $new_args['show_in_calendar_form'],
                'post_types' => $new_args['post_types'],
                'post_types_column' => $new_args['post_types_column'],
            ];
            $encoded_description = $this->get_encoded_description($args_to_encode);
            $new_args['description'] = $encoded_description;

            $updated_term = wp_update_term($term_id, self::metadata_taxonomy, $new_args);

            // Reset the internal object cache
            $this->editorial_metadata_terms_cache = [];

            $updated_term = $this->get_editorial_metadata_term_by('id', $term_id);
            return $updated_term;
        }

        /**
         * Insert a new editorial fields term
         *
         * @todo Handle conflicts with existing terms at that position (if relevant)
         *
         * @since 0.7
         */
        private function insert_editorial_metadata_term($args)
        {
            // Term is always added to the end of the list
            $default_position = count($this->get_editorial_metadata_terms()) + 2;
            $defaults = [
                'position' => $default_position,
                'name' => '',
                'slug' => '',
                'description' => '',
                'type' => '',
                'user_role' => '',
                'select_type' => '',
                'select_default' => '',
                'select_options' => '',
                'show_in_calendar_form' => false,
                'post_types' => [],
                'post_types_column' => [],
            ];
            $args = array_merge($defaults, $args);
            $term_name = $args['name'];
            unset($args['name']);

            // We're encoding metadata that isn't supported by default in the term's description field
            $args_to_encode = [
                'description' => $args['description'],
                'position' => $args['position'],
                'type' => $args['type'],
                'user_role' => $args['user_role'],
                'select_type' => $args['select_type'],
                'select_default' => $args['select_default'],
                'select_options' => $args['select_options'],
                'show_in_calendar_form' => $args['show_in_calendar_form'],
                'post_types' => $args['post_types'],
                'post_types_column' => $args['post_types_column'],
            ];
            $encoded_description = $this->get_encoded_description($args_to_encode);
            $args['description'] = $encoded_description;

            $inserted_term = wp_insert_term($term_name, self::metadata_taxonomy, $args);

            // Reset the internal object cache
            $this->editorial_metadata_terms_cache = [];

            return $inserted_term;
        }

        /**
         * Settings and other management code
         */

        /**
         * Delete an existing editorial fields term
         *
         * @param int $term_id The term we want deleted
         *
         * @return bool $result Whether or not the term was deleted
         * @since 0.7
         *
         */
        private function delete_editorial_metadata_term($term_id)
        {
            $result = wp_delete_term($term_id, self::metadata_taxonomy);

            // Reset the internal object cache
            $this->editorial_metadata_terms_cache = [];

            return $result;
        }

        /**
         * Handles a request to add a new piece of editorial fields
         */
        public function handle_add_editorial_metadata()
        {
            if (! isset($_POST['submit'], $_POST['form-action'], $_GET['page'])
                || ($_GET['page'] != self::MENU_SLUG) || $_POST['form-action'] != 'add-term') {
                return;
            }

            if (! wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'edit-publishpress-settings')) {
                wp_die($this->module->messages['nonce-failed']);
            }

            if (! current_user_can('manage_options')) {
                wp_die($this->module->messages['invalid-permissions']);
            }

            /**
             * Form validation for adding new editorial fields term
             *
             * Details
             * - "name", "slug", and "type" are required fields
             * - "description" can accept a limited amount of HTML, and is optional
             */
            $_REQUEST['form-errors'] = [];

            // Sanitize all of the user-entered values
            $term_name = trim(sanitize_text_field($_POST['metadata_name']));
            $term_slug = (! empty($_POST['metadata_slug'])) ? sanitize_title($_POST['metadata_slug']) : sanitize_title(
                $term_name
            );

            // Sanitized by the wp_filter_post_kses.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $term_description = trim(stripslashes(wp_filter_post_kses($_POST['metadata_description'])));
            $term_type = sanitize_key($_POST['metadata_type']);
            $term_user_role = (isset($_POST['metadata_user_role']) && is_array($_POST['metadata_user_role']))
                ? array_map('sanitize_key', $_POST['metadata_user_role']) : [];
            $term_select_type    = (isset($_POST['metadata_select_type'])) ? sanitize_text_field($_POST['metadata_select_type']) : '';
            $term_select_default = (isset($_POST['select_dropdown_default'])) ? sanitize_text_field($_POST['select_dropdown_default']) : '';
            $term_post_types = (isset($_POST['post_types']) && is_array($_POST['post_types']))
                ? array_map('sanitize_key', $_POST['post_types']) : [];
            $term_post_types_column = (isset($_POST['post_types_column']) && is_array($_POST['post_types_column']))
                ? array_map('sanitize_key', $_POST['post_types_column']) : [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $metadata_select_options = (isset($_POST['metadata_select_options'])) ? $_POST['metadata_select_options'] : '';
            $term_select_options = '';
            if ($term_type === 'select' && is_array($metadata_select_options) && isset($metadata_select_options['values'])) {
                $sanitized_option_values       = array_map('sanitize_text_field', $metadata_select_options['values']);
                $sanitized_option_labels       = array_map('sanitize_text_field', $metadata_select_options['labels']);
                $term_select_options           = [];
                $term_select_options['values'] = $sanitized_option_values;
                $term_select_options['labels'] = $sanitized_option_labels;

                /**
                 * Validate option entries
                 */
                if (count($sanitized_option_values) > count(array_filter(array_values($sanitized_option_values)))
                    || count($sanitized_option_labels) > count(array_filter(array_values($sanitized_option_labels)))
                ) {
                    $_REQUEST['form-errors']['select_options'] = esc_html__(
                        'All options value and labels are required.',
                        'publishpress'
                    );
                }
            }

            // Field is required
            if (empty($term_name)) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Please enter a name for the editorial fields.',
                    'publishpress'
                );
            }
            // Field is required
            if (empty($term_slug)) {
                $_REQUEST['form-errors']['slug'] = esc_html__(
                    'Please enter a slug for the editorial fields.',
                    'publishpress'
                );
            }
            if (term_exists($term_slug)) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Name conflicts with existing term. Please choose another.',
                    'publishpress'
                );
            }
            // Check to ensure a term with the same name doesn't exist
            if ($this->get_editorial_metadata_term_by('name', $term_name, self::metadata_taxonomy)) {
                $_REQUEST['form-errors']['name'] = esc_html__('Name already in use. Please choose another.', 'publishpress');
            }
            // Check to ensure a term with the same slug doesn't exist
            if ($this->get_editorial_metadata_term_by('slug', $term_slug)) {
                $_REQUEST['form-errors']['slug'] = esc_html__('Slug already in use. Please choose another.', 'publishpress');
            }
            // Check to make sure the status doesn't already exist as another term because otherwise we'd get a weird slug
            // Check that the term name doesn't exceed 200 chars
            if (function_exists('mb_strlen')) {
                if (mb_strlen($term_name) > 200) {
                    $_REQUEST['form-errors']['name'] = esc_html__(
                        'Name cannot exceed 200 characters. Please try a shorter name.',
                        'publishpress'
                    );
                }
            } else {
                if (strlen($term_name) > 200) {
                    $_REQUEST['form-errors']['name'] = esc_html__(
                        'Name cannot exceed 200 characters. Please try a shorter name.',
                        'publishpress'
                    );
                }
            }

            // Metadata type needs to pass our whitelist check
            $metadata_types = $this->get_supported_metadata_types();
            if (empty($_POST['metadata_type']) || ! isset($metadata_types[$_POST['metadata_type']])) {
                $_REQUEST['form-errors']['type'] = esc_html__('Please select a valid metadata type.', 'publishpress');
            }

            // Metadata show_in_calendar_form needs to be a valid Yes or No
            $term_show_in_calendar_form = false;
            if (isset($_POST['show_in_calendar_form']) && $_POST['show_in_calendar_form'] == 'yes') {
                $term_show_in_calendar_form = true;
            }

            // Kick out if there are any errors
            if (! empty($_REQUEST['form-errors'])) {
                $_REQUEST['error'] = 'form-error';
                return;
            }

            // Try to add the status
            $args = [
                'name' => $term_name,
                'description' => $term_description,
                'slug' => $term_slug,
                'type' => $term_type,
                'user_role' => $term_user_role,
                'select_type' => $term_select_type,
                'select_default' => $term_select_default,
                'select_options' => $term_select_options,
                'show_in_calendar_form' => $term_show_in_calendar_form,
                'post_types' => $term_post_types,
                'post_types_column' => $term_post_types_column,
            ];
            $return = $this->insert_editorial_metadata_term($args);

            if (is_wp_error($return)) {
                wp_die(esc_html__('Error adding term.', 'publishpress'));
            }

            $redirect_url = PP_Editorial_Metadata_Utilities::get_link(['message' => 'term-added']);
            wp_redirect($redirect_url);

            exit;
        }

        /**
         * Handles a request to edit an editorial fields
         */
        public function handle_edit_editorial_metadata()
        {
            if (!isset($_POST['submit'], $_GET['action'], $_GET['term-id'])
                || ($_GET['page'] != self::MENU_SLUG)
                || $_GET['action'] != 'edit-term') {
                return;
            }

            if (! wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'editorial-metadata-edit-nonce')) {
                wp_die($this->module->messages['nonce-failed']);
            }

            if (! current_user_can('manage_options')) {
                wp_die($this->module->messages['invalid-permissions']);
            }

            if (! $existing_term = $this->get_editorial_metadata_term_by('id', (int)$_GET['term-id'])) {
                wp_die($this->module->messages['term-missing']);
            }

            $new_name = trim(sanitize_text_field($_POST['name']));
            // Sanitized by wp_filter_post_kses.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $new_description = trim(stripslashes(wp_filter_post_kses(strip_tags($_POST['description']))));

            /**
             * Form validation for editing editorial fields term
             *
             * Details
             * - "name", "slug", and "type" are required fields
             * - "description" can accept a limited amount of HTML, and is optional
             */
            $_REQUEST['form-errors'] = [];


            $term_select_type    = (isset($_POST['metadata_select_type'])) ? sanitize_text_field($_POST['metadata_select_type']) : '';
            $term_select_default = (isset($_POST['select_dropdown_default'])) ? sanitize_text_field($_POST['select_dropdown_default']) : '';
            $term_post_types = (isset($_POST['post_types']) && is_array($_POST['post_types']))
                ? array_map('sanitize_key', $_POST['post_types']) : [];
            $term_post_types_column = (isset($_POST['post_types_column']) && is_array($_POST['post_types_column']))
                ? array_map('sanitize_key', $_POST['post_types_column']) : [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $metadata_select_options = (isset($_POST['metadata_select_options'])) ? $_POST['metadata_select_options'] : '';
            $term_select_options = '';
            if ($existing_term->type === 'select' && is_array($metadata_select_options) && isset($metadata_select_options['values'])) {
                $sanitized_option_values       = array_map('sanitize_text_field', $metadata_select_options['values']);
                $sanitized_option_labels       = array_map('sanitize_text_field', $metadata_select_options['labels']);
                $term_select_options           = [];
                $term_select_options['values'] = $sanitized_option_values;
                $term_select_options['labels'] = $sanitized_option_labels;

                /**
                 * Validate option entries
                 */
                if (count($sanitized_option_values) > count(array_filter(array_values($sanitized_option_values)))
                    || count($sanitized_option_labels) > count(array_filter(array_values($sanitized_option_labels)))
                ) {
                    $_REQUEST['form-errors']['select_options'] = esc_html__(
                        'All options value and labels are required.',
                        'publishpress'
                    );
                }
            }

            // Check if name field was filled in
            if (empty($new_name)) {
                $_REQUEST['form-errors']['name'] = esc_html__('Please enter a name for the editorial fields', 'publishpress');
            }

            // Check that the name isn't numeric
            if (is_numeric($new_name)) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Please enter a valid, non-numeric name for the editorial fields.',
                    'publishpress'
                );
            }

            $term_exists = term_exists(sanitize_title($new_name));

            if ($term_exists && $term_exists != $existing_term->term_id) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Metadata name conflicts with existing term. Please choose another.',
                    'publishpress'
                );
            }

            // Check to ensure a term with the same name doesn't exist,
            $search_term = $this->get_editorial_metadata_term_by('name', $new_name);
            if (is_object($search_term) && $search_term->term_id != $existing_term->term_id) {
                $_REQUEST['form-errors']['name'] = esc_html__('Name already in use. Please choose another.', 'publishpress');
            }
            // or that the term name doesn't map to an existing term's slug
            $search_term = $this->get_editorial_metadata_term_by('slug', sanitize_title($new_name));
            if (is_object($search_term) && $search_term->term_id != $existing_term->term_id) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Name conflicts with slug for another term. Please choose something else.',
                    'publishpress'
                );
            }

            // Check that the term name doesn't exceed 200 chars
            if (strlen($new_name) > 200) {
                $_REQUEST['form-errors']['name'] = esc_html__(
                    'Name cannot exceed 200 characters. Please try a shorter name.',
                    'publishpress'
                );
            }

            // Make sure the show_in_calendar_form state is valid
            $new_show_in_calendar_form = false;
            if (isset($_POST['show_in_calendar_form']) && $_POST['show_in_calendar_form'] == 'yes') {
                $new_show_in_calendar_form = true;
            }

            $term_user_role = (isset($_POST['metadata_user_role']) && is_array($_POST['metadata_user_role']))
                ? array_map('sanitize_key', $_POST['metadata_user_role']) : [];

            // Kick out if there are any errors
            if (! empty($_REQUEST['form-errors'])) {
                $_REQUEST['error'] = 'form-error';
                return;
            }

            // Try to add the Editorial fields term
            $args = [
                'name' => $new_name,
                'description' => $new_description,
                'user_role' => $term_user_role,
                'select_type' => $term_select_type,
                'select_default' => $term_select_default,
                'select_options' => $term_select_options,
                'show_in_calendar_form' => $new_show_in_calendar_form,
                'post_types' => $term_post_types,
                'post_types_column' => $term_post_types_column,
            ];
            $return = $this->update_editorial_metadata_term($existing_term->term_id, $args);

            if (is_wp_error($return)) {
                wp_die(esc_html__('Error updating term.', 'publishpress'));
            }

            $redirect_url = PP_Editorial_Metadata_Utilities::get_link(['message' => 'term-updated']);
            wp_redirect($redirect_url);
            exit;
        }

        /**
         * Handle a $_GET request to change the visibility of an Editorial Fields term
         *
         * @since 0.7
         */
        public function handle_change_editorial_metadata_visibility()
        {
            // Check that the current GET request is our GET request
            if (! isset($_GET['page'], $_GET['action'], $_GET['term-id'], $_GET['nonce'])
                || ($_GET['page'] != self::MENU_SLUG) || ! in_array(
                    $_GET['action'],
                    ['make-viewable', 'make-hidden']
                )) {
                return;
            }

            // Check for proper nonce
            if (! wp_verify_nonce(sanitize_key($_GET['nonce']), 'make-viewable') && ! wp_verify_nonce(
                    sanitize_key($_GET['nonce']),
                    'make-hidden'
                )) {
                wp_die($this->module->messages['nonce-failed']);
            }

            // Only allow users with the proper caps
            if (! current_user_can('manage_options')) {
                wp_die($this->module->messages['invalid-permissions']);
            }

            $term_id = (int)$_GET['term-id'];
            $args = [];

            $return = $this->update_editorial_metadata_term($term_id, $args);

            if (is_wp_error($return)) {
                wp_die(esc_html__('Error updating term.', 'publishpress'));
            }

            $redirect_url = PP_Editorial_Metadata_Utilities::get_link(['message' => 'term-visibility-changed']);

            wp_redirect($redirect_url);
            exit;
        }

        /**
         * Handle the ajax request to update all of the term positions
         *
         * @since 0.7
         */
        public function handle_ajax_update_term_positions()
        {
            if (! wp_verify_nonce(sanitize_key($_POST['editorial_metadata_sortable_nonce']), 'editorial-metadata-sortable')) {
                $this->print_ajax_response('error', $this->module->messages['nonce-failed']);
            }

            if (! current_user_can('manage_options')) {
                $this->print_ajax_response('error', $this->module->messages['invalid-permissions']);
            }

            if (! isset($_POST['term_positions']) || ! is_array($_POST['term_positions'])) {
                $this->print_ajax_response('error', esc_html__('Terms not set.', 'publishpress'));
            }

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $term_positions = array_map('absint', (array)$_POST['term_positions']);

            foreach ($term_positions as $position => $term_id) {
                // Have to add 1 to the position because the index started with zero
                $args = [
                    'position' => (int)$position + 1,
                ];
                $return = $this->update_editorial_metadata_term((int)$term_id, $args);
                // @todo check that this was a valid return
            }
            $this->print_ajax_response('success', $this->module->messages['term-position-updated']);
        }

        /**
         * Handles a request to delete an editorial fields term
         */
        public function handle_delete_editorial_metadata()
        {
            if (! isset($_GET['page'], $_GET['action'], $_GET['term-id'])
                || ($_GET['page'] != self::MENU_SLUG) || $_GET['action'] != 'delete-term') {
                return;
            }

            if (! wp_verify_nonce(sanitize_key($_GET['nonce']), 'delete-term')) {
                wp_die($this->module->messages['nonce-failed']);
            }

            if (! current_user_can('manage_options')) {
                wp_die($this->module->messages['invalid-permissions']);
            }

            if (! $existing_term = $this->get_editorial_metadata_term_by('id', (int)$_GET['term-id'])) {
                wp_die($this->module->messages['term-missing']);
            }

            $result = $this->delete_editorial_metadata_term($existing_term->term_id);
            if (! $result || is_wp_error($result)) {
                wp_die(esc_html__('Error deleting term.', 'publishpress'));
            }

            $redirect_url = PP_Editorial_Metadata_Utilities::get_link(['message' => 'term-deleted']);
            wp_redirect($redirect_url);
            exit;
        }

        /**
         * Migrate metadata post type to individual terms from settings
         *
         * @return void
         */
        private function migrateMetadataPostTypes() {

            $editorial_metadata_terms = $this->get_editorial_metadata_terms();
            $supported_post_types     = $this->get_post_types_for_module($this->module);

            foreach ($editorial_metadata_terms as $editorial_metadata_term) {
                $this->update_editorial_metadata_term(
                    $editorial_metadata_term->term_id,
                    [
                        'post_types'        => $supported_post_types,
                        'post_types_column' => $supported_post_types
                    ]
                );
            }

        }

        /**
         * Register settings for notifications so we can partially use the Settings API
         * (We use the Settings API for form generation, but not saving)
         *
         * @since 0.7
         * @uses add_settings_section(), add_settings_field()
         */
        public function register_settings()
        {
            add_settings_section(
                $this->module->options_group_name . '_general',
                false,
                '__return_false',
                $this->module->options_group_name
            );
            add_settings_field(
                'post_types',
                esc_html__('Register editorial fields for these post types:', 'publishpress'),
                [$this, 'settings_post_types_option'],
                $this->module->options_group_name,
                $this->module->options_group_name . '_general'
            );
        }

        /**
         * Choose the post types for editorial fields
         *
         * @since 0.7
         */
        public function settings_post_types_option()
        {
            global $publishpress;
            $publishpress->settings->helper_option_custom_post_type($this->module);
        }

        /**
         * Validate data entered by the user
         *
         * @param array $new_options New values that have been entered by the user
         *
         * @return array $new_options Form values after they've been sanitized
         * @since 0.7
         *
         */
        public function settings_validate($new_options)
        {
            // Whitelist validation for the post type options
            if (! isset($new_options['post_types'])) {
                $new_options['post_types'] = [];
            }
            $new_options['post_types'] = $this->clean_post_type_options(
                $new_options['post_types'],
                $this->module->post_type_support
            );

            return $new_options;
        }

        /**
         * Settings page for editorial fields
         *
         * @since 0.7
         */
        public function print_configure_view()
        {
            global $publishpress; ?>
            <form class="basic-settings"
                  action="<?php
                  echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
                <?php
                settings_fields($this->module->options_group_name); ?>
                <?php
                do_settings_sections($this->module->options_group_name); ?>
                <?php
                echo '<input id="publishpress_module_name" name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <p class="submit"><?php
                    submit_button(null, 'primary', 'submit', false); ?></p>
                <?php
                echo '<input name="publishpress_module_name[]" type="hidden" value="' . esc_attr(
                        $this->module->name
                    ) . '" />'; ?>
                <?php
                wp_nonce_field('edit-publishpress-settings'); ?>
            </form>
            <?php
        }

        /**
         * Add necessary things to the admin menu
         */
        public function action_admin_submenu()
        {
            $publishpress = $this->get_service('publishpress');

            // Main Menu
            add_submenu_page(
                $publishpress->get_menu_slug(),
                esc_html__('Editorial Fields', 'publishpress'),
                esc_html__('Editorial Fields', 'publishpress'),
                $this->getViewCapability(),
                self::MENU_SLUG,
                [$this, 'render_admin_page'],
                20
            );
        }

        /**
         * Prepare and display the configuration view for editorial fields.
         * There are four primary components:
         * - Form to add a new Editorial Fields term
         * - Form generated by the settings API for managing Editorial Fields options
         * - Table of existing Editorial Fields terms with ability to take actions on each
         * - Full page width view for editing a single Editorial Fields term
         *
         * @since 0.7
         */
        public function render_admin_page()
        {
            $publishpress = $this->get_service('publishpress');

            $args = [];
            $args['publishpress']           = $publishpress;
            $args['edited_term']            = false;
            $args['module']                 = $this->module;
            $args['metadata_types']         = $this->get_supported_metadata_types();
            $args['valid_post_types']       = $this->get_all_post_types();
            $args['metadata_user_roles']    = $this->get_supported_metadata_user_roles();

            if (isset($_GET['action'], $_GET['term-id']) && $_GET['action'] == 'edit-term') {
                $term_id = (int)$_GET['term-id'];
                $args['edited_term'] = $this->get_editorial_metadata_term_by('id', $term_id);
            }
            PP_Editorial_Metadata_Utilities::render_admin_page($args);
        }

        /**
         * Load utilities files
         *
         * @return void
         */
        private function load_utilities_files() {
            require_once dirname(__FILE__) . "/library/editorial-metadata-list-table.php";
            require_once dirname(__FILE__) . "/library/editorial-metadata-utilities.php";
        }

        /**
         * Load the chain of input-type handlers.
         *
         * @since   1.20.0
         */
        private function load_input_handlers()
        {
            $handlers_base_path = dirname(__FILE__) . '/input-handlers';

            require_once "{$handlers_base_path}/editorial-metadata-input-text-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-paragraph-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-number-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-date-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-checkbox-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-user-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-location-handler.php";
            require_once "{$handlers_base_path}/editorial-metadata-input-select-handler.php";

            $handlers = [
                new Editorial_Metadata_Input_Paragraph_Handler(),
                new Editorial_Metadata_Input_Text_Handler(),
                new Editorial_Metadata_Input_Number_Handler(),
                new Editorial_Metadata_Input_Date_Handler(),
                new Editorial_Metadata_Input_Checkbox_Handler(),
                new Editorial_Metadata_Input_User_Handler(),
                new Editorial_Metadata_Input_Location_Handler(),
                new Editorial_Metadata_Input_Select_Handler(),
            ];

            foreach ($handlers as $handler) {
                if (is_null($this->editorial_metadata_input_handler)) {
                    $this->editorial_metadata_input_handler = $handler;
                    continue;
                }

                $this->editorial_metadata_input_handler->registerHandler($handler);

                $handler_type = $handler->getType();

                add_filter(
                    "pp_editorial_metadata_{$handler_type}_render_value_html",
                    [get_class($handler), 'getMetaValueHtml'],
                    10, 2
                );

                add_filter(
                    "pp_editorial_metadata_{$handler_type}_get_input_html",
                    [get_class($handler), 'getInputHtml'],
                    10, 2
                );
            }
        }
    }
}
