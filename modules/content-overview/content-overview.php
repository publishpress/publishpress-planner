<?php
/**
 * @package PublishPress
 * @author  PublishPress
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

use PublishPress\Core\Ajax;
use PublishPress\Core\Error;
use PublishPress\Legacy\Util;
use PublishPress\Notifications\Traits\Dependency_Injector;

/**
 * class PP_Content_Overview
 * This class displays a budgeting system for an editorial desk's publishing workflow.
 *
 * @author sbressler
 */
#[\AllowDynamicProperties]
class PP_Content_Overview extends PP_Module
{
    use Dependency_Injector;

    /**
     * Settings slug
     */
    const SETTINGS_SLUG = 'pp-content-overview-settings';

    /**
     * Screen id
     */
    const SCREEN_ID = 'dashboard_page_content-overview';

    /**
     * Usermeta key prefix
     */
    const USERMETA_KEY_PREFIX = 'PP_Content_Overview_';

    /**
     * @var string
     */
    const MENU_SLUG = 'pp-content-overview';

    /**
     * [$taxonomy_used description]
     *
     * @var string
     */
    public $taxonomy_used = 'category';

    /**
     * [$module description]
     *
     * @var [type]
     */
    public $module;
    public $module_url;

    /**
     * [$no_matching_posts description]
     *
     * @var boolean
     */
    public $no_matching_posts = true;

    /**
     * @var array
     */
    public $columns;

    /**
     * @var array
     */
    public $filters;

    /**
     * @var array
     */
    public $form_columns = [];

    /**
     * @var array
     */
    public $form_filters = [];

    /**
     * @var array
     */
    public $form_column_lists = [];

    /**
     * @var array
     */
    public $form_filter_list = [];

    /**
     * [$user_filters description]
     *
     * @var [type]
     */
    public $user_filters;

    /**
     * Custom methods
     *
     * @var Array
     */
    private $terms_options = [];

    /**
     * [$content_overview_datas description]
     *
     * @var [type]
     */
    public $content_overview_datas;

    /**
     * Content calendar methods
     *
     * @var [type]
     */
    private $content_overview_methods;

    /**
     * Register the module with PublishPress but don't do anything else
     */
    public function __construct()
    {
        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => esc_html__('Content Overview', 'publishpress'),
            'short_description' => false,
            'extended_description' => false,
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-list-view',
            'slug' => 'content-overview',
            'default_options' => [
                'enabled' => 'on',
                // Leave default as non array to confirm if user save settings or not
                'content_overview_columns' => '',
                'content_overview_custom_columns' => '',
                'content_overview_filters' => '',
                'content_overview_custom_filters' => '',

                'posts_per_page' => 200,

                'post_types' => [
                    'post' => 'on',
                    'page' => 'off',
                ],
            ],
            'configure_page_cb' => 'print_configure_view',
            'options_page' => true,
            'autoload' => false,
            'add_menu' => true,
            'page_link' => admin_url('admin.php?page=content-overview'),
        ];

        $this->module = PublishPress()->register_module('content_overview', $args);

        // Load utilities files.
        $this->load_utilities_files();

        $this->content_overview_methods = new PP_Overview_Methods([
            'module' => $this->module,
            'module_url' => $this->module_url
        ]);
    }

    /**
     * Initialize the rest of the stuff in the class if the module is active
     */
    public function init()
    {
        if (false === is_admin()) {
            return;
        }

        $this->setDefaultCapabilities();

        if (! $this->currentUserCanViewContentOverview()) {
            return;
        }

        // Filter to allow users to pick a taxonomy other than 'category' for sorting their posts
        $this->taxonomy_used = apply_filters('PP_Content_Overview_taxonomy_used', $this->taxonomy_used);

        add_action('admin_init', [$this, 'handle_form_date_range_change']);

        // Register our settings
        add_action('admin_init', [$this->content_overview_methods, 'register_settings']);

        add_action('wp_ajax_publishpress_content_overview_search_authors', [$this, 'sendJsonSearchAuthors']);
        add_action('wp_ajax_publishpress_content_overview_search_categories', [$this, 'sendJsonSearchCategories']);
        add_action('wp_ajax_publishpress_content_overview_get_form_fields', [$this, 'getFormFieldAjaxHandler']);

        // Menu
        add_filter('publishpress_admin_menu_slug', [$this->content_overview_methods, 'filter_admin_menu_slug'], 20);
        add_action('publishpress_admin_menu_page', [$this, 'action_admin_menu_page'], 20);
        add_action('publishpress_admin_submenu', [$this, 'action_admin_submenu'], 20);

        // Load necessary scripts and stylesheets
        add_action('admin_enqueue_scripts', [$this->content_overview_methods, 'enqueue_admin_scripts']);
        add_action('admin_enqueue_scripts', [$this->content_overview_methods, 'action_enqueue_admin_styles']);
        // Content Board body class
        add_filter('admin_body_class', [$this->content_overview_methods, 'add_admin_body_class']);
    }

    private function getViewCapability()
    {
        return apply_filters('pp_view_content_overview_cap', 'pp_view_content_overview');
    }

    private function currentUserCanViewContentOverview()
    {
        return current_user_can($this->getViewCapability());
    }

    public function setDefaultCapabilities()
    {
        $role = get_role('administrator');

        $view_content_overview_cap = $this->getViewCapability();

        if (! $role->has_cap($view_content_overview_cap)) {
            $role->add_cap($view_content_overview_cap);
        }
    }

    /**
     * Get the post types for editorial fields
     *
     * @return array $post_types All existing post types
     *
     * @since 0.7
     */
    public function get_settings_post_types()
    {
        global $publishpress;
        return $publishpress->settings->get_supported_post_types_for_module($this->module);
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
     * Settings page for notifications
     *
     * @since 0.7
     */
    public function print_configure_view()
    { ?>
        <form class="basic-settings"
              action="<?php
              echo esc_url(menu_page_url($this->module->settings_slug, false)); ?>" method="post">
            <?php
            settings_fields($this->module->options_group_name);
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
     * Give users the appropriate permissions to view the content overview the first time the module is loaded
     *
     * @since 0.7
     */
    public function install()
    {
        $viewCapability = $this->getViewCapability();
        $eligible_roles = ['administrator', 'editor', 'author'];

        foreach ($eligible_roles as $eligible_role) {
            $role = get_role($eligible_role);
            if (is_object($role) && !$role->has_cap($viewCapability)) {
                $role->add_cap($viewCapability);
            }
        }
    }

    /**
     * Upgrade our data in case we need to
     *
     * @since 0.7
     */
    public function upgrade($previous_version)
    {
        global $publishpress;

        // Upgrade path to v0.7
        if (version_compare($previous_version, '0.7', '<')) {
            // Migrate whether the content overview was enabled or not and clean up old option
            if ($enabled = get_option('publishpress_content_overview_enabled')) {
                $enabled = 'on';
            } else {
                $enabled = 'off';
            }
            $publishpress->update_module_option($this->module->name, 'enabled', $enabled);
            delete_option('publishpress_content_overview_enabled');

            // Technically we've run this code before so we don't want to auto-install new data
            $publishpress->update_module_option($this->module->name, 'loaded_once', true);
        }
    }

    /**
     * Creates the admin menu if there is no menu set.
     */
    public function action_admin_menu_page()
    {
        $publishpress = $this->get_service('publishpress');

        if ($publishpress->get_menu_slug() !== self::MENU_SLUG) {
            return;
        }

        $publishpress->add_menu_page(
            esc_html__('Content Overview', 'publishpress'),
            apply_filters('pp_view_content_overview_cap', 'pp_view_content_overview'),
            self::MENU_SLUG,
            [$this, 'render_admin_page']
        );
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
            esc_html__('Content Overview', 'publishpress'),
            esc_html__('Content Overview', 'publishpress'),
            apply_filters('pp_view_content_overview_cap', 'pp_view_content_overview'),
            self::MENU_SLUG,
            [$this, 'render_admin_page'],
            20
        );
    }

    /**
     * Create the content overview view. This calls lots of other methods to do its work. This will
     * output any messages, create the table navigation, then print the columns..
     */
    public function render_admin_page()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        global $publishpress;

        // update content overview form action
        $this->update_content_overview_form_action();

        // Get content overview data
        $this->content_overview_datas = $this->get_content_overview_datas();

        $columns = [
            'post_title' => esc_html__('Title', 'publishpress')
        ];
        $columns = array_merge($columns, $this->content_overview_datas['content_overview_columns']);
        /**
         * @param array $columns
         *
         * @return array
         */
        $this->columns = apply_filters('publishpress_content_overview_columns', $columns);


        $filters = $this->content_overview_datas['content_overview_filters'];
        /**
         * @param array $columns
         *
         * @return array
         */
        $this->filters = apply_filters('publishpress_content_overview_filters', $filters);

        $this->form_columns = $this->get_content_overview_form_columns();
        $this->form_column_list = array_merge(...array_values(array_column($this->form_columns, 'columns')));

        $this->form_filters = $this->get_content_overview_form_filters();
        $this->form_filter_list = array_merge(...array_values(array_column($this->form_filters, 'filters')));

        // Update the current user's filters with the variables set in $_GET
        $this->user_filters = $this->update_user_filters();

        $description = sprintf(
            '<div>%s <span class="time-range">%s</span></div> %s',
            '',
            '',
            ''
        );
        $publishpress->settings->print_default_header($publishpress->modules->content_overview, $description); ?>

        <div class="wrap" id="pp-content-overview-wrap">
            <?php
            $this->content_overview_methods->print_messages(); ?>
            <?php
            $this->table_navigation(); ?>

            <div class="metabox-holder">
                <?php
                if (isset($_GET['ptype']) && ! empty($_GET['ptype'])) {
                    $selectedPostTypes = [sanitize_text_field($_GET['ptype'])];
                } else {
                    $selectedPostTypes = $this->get_selected_post_types();
                }

                echo '<div class="postbox-container">';
                $this->printPostForPostType($selectedPostTypes);
                echo '</div>';
                ?>
            </div>
        </div>
        <br clear="all">
        <?php

        $publishpress->settings->print_default_footer($publishpress->modules->content_overview);
        // phpcs:enable
    }

    public function get_selected_post_types()
    {
        $postTypesOption = $this->module->options->post_types;

        $enabledPostTypes = [];
        foreach ($postTypesOption as $postType => $status) {
            if ('on' === $status
                && ! in_array($postType, $enabledPostTypes)) {
                $enabledPostTypes[] = $postType;
            }
        }

        return $enabledPostTypes;
    }

    /**
     * Update the current user's filters for content overview display with the filters in $_GET. The filters
     * in $_GET take precedence over the current users filters if they exist.
     */
    public function update_user_filters()
    {
        global $pp_content_overview_user_filters;

        if (is_array($pp_content_overview_user_filters)) {
            return $pp_content_overview_user_filters;
        }

        $current_user = wp_get_current_user();

        $user_filters = [
            'start_date' => $this->filter_get_param('start_date'),
            'end_date' => $this->filter_get_param('end_date'),
            'me_mode' => $this->filter_get_param('me_mode'),
            'revision_status' => $this->filter_get_param('revision_status'),
            'hide_revision' => $this->filter_get_param('hide_revision'),
        ];

        $editorial_metadata = $this->terms_options;

        foreach ($this->filters as $filter_key => $filter_label) {
            if (array_key_exists($filter_key, $editorial_metadata)) {
                //add metadata to filter
                $meta_term = $editorial_metadata[$filter_key];
                $meta_term_type = $meta_term['type'];
                if ($meta_term_type === 'checkbox') {
                    if (! isset($_GET[$filter_key])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        $check_value = null;
                    } else {
                        $check_value = absint($this->filter_get_param($filter_key));
                    }
                    $user_filters[$filter_key] = $check_value;
                } elseif ($meta_term_type === 'date') {
                    $user_filters[$filter_key]                   = $this->filter_get_param_text($filter_key);
                    $user_filters[$filter_key . '_start']        = $this->filter_get_param_text($filter_key . '_start');
                    $user_filters[$filter_key . '_end']          = $this->filter_get_param_text($filter_key . '_end');
                    $user_filters[$filter_key . '_start_hidden'] = $this->filter_get_param_text($filter_key . '_start_hidden');
                    $user_filters[$filter_key . '_end_hidden']   = $this->filter_get_param_text($filter_key . '_end_hidden');
                }  elseif ($meta_term_type === 'user') {
                    if (empty($user_filters['me_mode'])) {
                        $user_filters[$filter_key] = $this->filter_get_param_text($filter_key);
                    }
                } else {
                    $user_filters[$filter_key] = $this->filter_get_param_text($filter_key);
                }
            } else {
                // other filters
                $user_filters[$filter_key] = $this->filter_get_param_text($filter_key);
                if (in_array($filter_key, $this->content_overview_datas['meta_keys']) || in_array($filter_key, ['ppch_co_yoast_seo__yoast_wpseo_linkdex', 'ppch_co_yoast_seo__yoast_wpseo_content_score'])) {
                    $user_filters[$filter_key . '_operator'] = $this->filter_get_param_text($filter_key . '_operator');
                }
            }
        }

        $current_user_filters = [];
        $current_user_filters = $this->get_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', true);

        // If any of the $_GET vars are missing, then use the current user filter
        foreach ($user_filters as $key => $value) {
            if (is_null($value) && $value !== '0' && ! empty($current_user_filters[$key])) {
                $user_filters[$key] = $current_user_filters[$key];
            }
        }

        if (! $user_filters['start_date']) {
            $user_filters['start_date'] = date('Y-m-d', strtotime('-5 weeks')); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
        }

        if (! $user_filters['end_date']) {
            $user_filters['end_date'] = date('Y-m-d', strtotime($user_filters['start_date'] . ' +10 weeks'));
        }

        if (!empty($user_filters['me_mode'])) {
            $user_filters['author'] = $current_user->ID;
        }

        $user_filters = apply_filters('PP_Content_Overview_filter_values', $user_filters, $current_user_filters);

        $this->update_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', $user_filters);

        $pp_content_overview_user_filters = $user_filters;

        return $user_filters;
    }

    /**
    * Handle a form submission to change the user's date range on the budget
    *
    * @since 0.7
    */
    public function handle_form_date_range_change()
    {
        if (
            ! isset(
                $_REQUEST['pp-content-overview-start-date_hidden'],
                $_REQUEST['pp-content-overview-range-use-today'],
                $_REQUEST['nonce']
            )
            || (
                ! isset($_REQUEST['pp-content-overview-range-submit'])
                && $_REQUEST['pp-content-overview-range-use-today'] == '0'
            )
        ) {
            return;
        }

        if (! wp_verify_nonce(sanitize_key($_REQUEST['nonce']), 'change-date')) {
            return;
        }

        $current_user = wp_get_current_user();
        $user_filters = $this->get_user_meta(
            $current_user->ID,
            self::USERMETA_KEY_PREFIX . 'filters',
            true
        );

        $use_today_as_start_date = (bool)$_REQUEST['pp-content-overview-range-use-today'];

        $date_format = 'Y-m-d';
        $user_filters['start_date'] = $use_today_as_start_date
            ? date($date_format, strtotime('-5 weeks'))
            : date($date_format, strtotime(sanitize_text_field($_REQUEST['pp-content-overview-start-date_hidden']))); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

        $user_filters['end_date'] = $_REQUEST['pp-content-overview-end-date_hidden'];

        if ($use_today_as_start_date || (empty(trim($user_filters['end_date']))) || (strtotime($user_filters['start_date']) > strtotime($user_filters['end_date']))) {
            $user_filters['end_date'] = date($date_format, strtotime($user_filters['start_date'] . ' +10 weeks'));
        }

        $this->update_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', $user_filters);
    }

    /**
     * Update content overview form action
     *
     * @return void
     */
    public function update_content_overview_form_action() {
        global $publishpress;

        if (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'column_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_overview_column_form_nonce')) {
            // Content overview column form
            $content_overview_columns = !empty($_POST['content_overview_columns']) ? array_map('sanitize_text_field', $_POST['content_overview_columns']) : [];
            $content_overview_columns_order = !empty($_POST['content_overview_columns_order']) ? array_map('sanitize_text_field', $_POST['content_overview_columns_order']) : [];
            $content_overview_custom_columns = !empty($_POST['content_overview_custom_columns']) ? map_deep($_POST['content_overview_custom_columns'], 'sanitize_text_field') : [];

            // make sure enabled columns are saved in organized order
            $content_overview_columns = array_intersect($content_overview_columns_order, $content_overview_columns);

            $publishpress->update_module_option($this->module->name, 'content_overview_columns', $content_overview_columns);
            $publishpress->update_module_option($this->module->name, 'content_overview_custom_columns', $content_overview_custom_columns);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo pp_planner_admin_notice(esc_html__('Column updated successfully.', 'publishpress'));
        } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'filter_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_overview_filter_form_nonce')) {
            // Content overview filter form
            $content_overview_filters = !empty($_POST['content_overview_filters']) ? array_map('sanitize_text_field', $_POST['content_overview_filters']) : [];
            $content_overview_filters_order = !empty($_POST['content_overview_filters_order']) ? array_map('sanitize_text_field', $_POST['content_overview_filters_order']) : [];
            $content_overview_custom_filters = !empty($_POST['content_overview_custom_filters']) ? map_deep($_POST['content_overview_custom_filters'], 'sanitize_text_field') : [];

            // make sure enabled filters are saved in organized order
            $content_overview_filters = array_intersect($content_overview_filters_order, $content_overview_filters);

            $publishpress->update_module_option($this->module->name, 'content_overview_filters', $content_overview_filters);
            $publishpress->update_module_option($this->module->name, 'content_overview_custom_filters', $content_overview_custom_filters);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo pp_planner_admin_notice(esc_html__('Filter updated successfully.', 'publishpress'));
        } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && $_POST['co_form_action'] == 'settings_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_overview_settings_form_nonce')) {
            // Content Board filter form
            $posts_per_page = !empty($_POST['posts_per_page']) ? (int) $_POST['posts_per_page'] : 200;

            $publishpress->update_module_option($this->module->name, 'posts_per_page', $posts_per_page);

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo pp_planner_admin_notice(esc_html__('Settings updated successfully.', 'publishpress'));
        } elseif (!empty($_POST['co_form_action']) && !empty($_POST['_nonce']) && !empty($_POST['ptype']) && $_POST['co_form_action'] == 'post_form' && wp_verify_nonce(sanitize_key($_POST['_nonce']), 'content_overview_post_form_nonce')) {
            $postType = sanitize_text_field($_POST['ptype']);
            $postTypeObject = get_post_type_object($postType);
            if (current_user_can($postTypeObject->cap->edit_posts)) {
                $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
                $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
                // Sanitized by the wp_filter_post_kses function.
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $content = isset($_POST['content']) ? wp_filter_post_kses($_POST['content']) : '';
                $authors = isset($_POST['authors']) ? (int) $_POST['authors'] : get_current_user_id();
                $categories = isset($_POST['category']) ? array_map('sanitize_text_field', $_POST['category']) : [];
                $tags = isset($_POST['post_tag']) ? array_map('sanitize_text_field', $_POST['post_tag']) : [];

                $postArgs = [
                    'post_author' => $authors,
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_type' => $postType,
                    'post_status' => $status
                ];

                // set post date
                if ( ! empty( $_POST['mm'] ) ) {
                    $aa = sanitize_text_field($_POST['aa']);
                    $mm = sanitize_text_field($_POST['mm']);
                    $jj = sanitize_text_field($_POST['jj']);
                    $hh = sanitize_text_field($_POST['hh']);
                    $mn = sanitize_text_field($_POST['mn']);
                    $ss = sanitize_text_field($_POST['ss']);
                    $aa = ( $aa <= 0 ) ? gmdate( 'Y' ) : $aa;
                    $mm = ( $mm <= 0 ) ? gmdate( 'n' ) : $mm;
                    $jj = ( $jj > 31 ) ? 31 : $jj;
                    $jj = ( $jj <= 0 ) ? gmdate( 'j' ) : $jj;
                    $hh = ( $hh > 23 ) ? $hh - 24 : $hh;
                    $mn = ( $mn > 59 ) ? $mn - 60 : $mn;
                    $ss = ( $ss > 59 ) ? $ss - 60 : $ss;


                    $post_date = sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $aa, $mm, $jj, $hh, $mn, $ss );
                    $valid_date = wp_checkdate( $mm, $jj, $aa, $post_date );
                    if ($valid_date ) {
                        $postArgs['post_date'] = $post_date;
                        $postArgs['post_date_gmt'] = get_gmt_from_date( $post_date );
                    }
                }

                $postId = wp_insert_post($postArgs);

                if ($postId) {
                    if (! empty($categories)) {
                        $categoriesIdList = [];
                        foreach ($categories as $categorySlug) {
                            $category = get_term_by('slug', $categorySlug, 'category');

                            if (! $category || is_wp_error($category)) {
                                $category = wp_create_category($categorySlug);
                                $category = get_term($category);
                            }

                            if (! is_wp_error($category)) {
                                $categoriesIdList[] = $category->term_id;
                            }
                        }
                        wp_set_post_terms($postId, $categoriesIdList, 'category');
                    }

                    if (! empty($tags)) {
                        foreach ($tags as $tagSlug) {
                            $tag = get_term_by('slug', $tagSlug, 'post_tag');

                            if (! $tag || is_wp_error($tag)) {
                                wp_create_tag($tagSlug);
                            }
                        }
                        wp_set_post_terms($postId, $tags);
                    }
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo pp_planner_admin_notice(sprintf(__('%s created successfully. <a href="%s" target="_blank">Edit %s</a>', 'publishpress'), esc_html($postTypeObject->labels->singular_name), esc_url(get_edit_post_link($postId)), esc_html($postTypeObject->labels->singular_name)));
                } else {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    echo pp_planner_admin_notice(sprintf(esc_html__('%s could not be created', 'publishpress'), esc_html($postTypeObject->labels->singular_name)), false);
                }

            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo pp_planner_admin_notice(sprintf(esc_html__('You do not have permission to add new %s', 'publishpress'), esc_html($postTypeObject->labels->singular_name)), false);
            }
        }

    }

    /**
     * Get content overview data that's required on the
     * content overview page
     *
     * @return array
     */
    public function get_content_overview_datas() {
        global $wpdb;

        if (is_array($this->content_overview_datas)) {
            return $this->content_overview_datas;
        }

        $datas = [];

        // add all meta keys
        $datas['meta_keys'] = [];

        // add editorial fields
        if (class_exists('PP_Editorial_Metadata')) {
            $additional_terms = get_terms(
                [
                    'taxonomy' => PP_Editorial_Metadata::metadata_taxonomy,
                    'orderby' => 'name',
                    'order' => 'asc',
                    'hide_empty' => 0,
                    'parent' => 0,
                    'fields' => 'all',
                ]
            );

            $metadatas = [];
            foreach ($additional_terms as $term) {
                if (! is_object($term) || $term->taxonomy !== PP_Editorial_Metadata::metadata_taxonomy) {
                    continue;
                }
                $metadatas[$term->slug] = $term->name;

                $term_options = $this->get_unencoded_description($term->description);
                $term_options['name'] = $term->name;
                $term_options['slug'] = $term->slug;
                $this->terms_options[$term->slug] = $term_options;
            }

            $datas['editorial_metadata'] = $metadatas;
        }

        // add taxononomies
        $taxonomies = $this->get_post_types_taxonomies($this->get_selected_post_types());
        $all_taxonomies = [];
        foreach ($taxonomies as $taxonomy) {
            if (in_array($taxonomy->name, ['post_status', 'post_status_core_wp_pp', 'post_visibility_pp'])) {
                continue;
            }
            $all_taxonomies[$taxonomy->name] = $taxonomy->label;// . ' (' . $taxonomy->name . ')';
        }
        $datas['taxonomies'] = $all_taxonomies;

        // Add content overview columns content
        $content_overview_columns = $this->module->options->content_overview_columns;
        $content_overview_custom_columns = $this->module->options->content_overview_custom_columns;

        $datas['content_overview_columns'] = is_array($content_overview_columns) ? $content_overview_columns :
        [
            'post_status' => esc_html__('Status', 'publishpress'),
            'revision_status' => esc_html__('Revision Status', 'publishpress'),
            'post_type' => esc_html__('Post Type', 'publishpress'),
            'post_author' => esc_html__('Author', 'publishpress'),
            'post_date' => esc_html__('Post Date', 'publishpress'),
            'post_modified' => esc_html__('Last Modified', 'publishpress'),
        ];

        $datas['content_overview_custom_columns'] = is_array($content_overview_custom_columns) ? $content_overview_custom_columns : [];

        // Add content overview filters content
        $content_overview_filters = $this->module->options->content_overview_filters;
        $content_overview_custom_filters = $this->module->options->content_overview_custom_filters;

        $datas['content_overview_filters'] = is_array($content_overview_filters) ? $content_overview_filters : [
            'post_status' => esc_html__('Status', 'publishpress'),
            'revision_status' => esc_html__('Revision Status', 'publishpress'),
            'author' => esc_html__('Author', 'publishpress'),
            'ptype' => esc_html__('Post Type', 'publishpress')
        ];

        $datas['content_overview_custom_filters'] = is_array($content_overview_custom_filters) ? $content_overview_custom_filters : [];

        /**
         * @param array $datas
         *
         * @return $datas
         */
        $datas = apply_filters('publishpress_content_overview_datas', $datas);

        $this->content_overview_datas = $datas;

        return $datas;
    }

    /**
     * Get content overview form columns
     *
     * @return array
     */
    public function get_content_overview_form_columns() {

        if (!empty($this->form_columns)) {
            return $this->form_columns;
        }

        $content_overview_datas   = $this->content_overview_datas;

        $columns = [];
        // custom columns
        $columns['custom'] = [
            'title'     => esc_html__('Custom Columns', 'publishpress'),
            'message'   => esc_html__('Click the "Add New" button to create new columns.', 'publishpress'),
            'columns'   => is_array($content_overview_datas['content_overview_custom_columns']) ? $content_overview_datas['content_overview_custom_columns'] : []
        ];

        // default columns
        $columns['default'] = [
            'title'     => esc_html__('Inbuilt Columns', 'publishpress'),
            'columns'   => [
                'post_status' => esc_html__('Status', 'publishpress'),
                'post_type' => esc_html__('Post Type', 'publishpress'),
                'post_author' => esc_html__('Author', 'publishpress'),
                'post_date' => esc_html__('Post Date', 'publishpress'),
                'post_modified' => esc_html__('Last Modified', 'publishpress')
            ]
        ];

        // editorial fields columns
        if (isset($content_overview_datas['editorial_metadata'])) {
            $columns['editorial_metadata'] = [
                'title'     => esc_html__('Editorial Fields', 'publishpress'),
                'message'   => esc_html__('You do not have any editorial fields enabled', 'publishpress'),
                'columns'   => is_array($content_overview_datas['editorial_metadata']) ? $content_overview_datas['editorial_metadata'] : []
            ];
        }

        $columns['taxonomies'] = [
            'title'     => esc_html__('Taxonomies', 'publishpress'),
            'message'   => esc_html__('You do not have any public taxonomies', 'publishpress'),
            'columns'   => is_array($content_overview_datas['taxonomies']) ? $content_overview_datas['taxonomies'] : []
        ];

        /**
        * @param array $columns
        * @param array $content_overview_datas
        *
        * @return array $columns
        */
        $columns = apply_filters('publishpress_content_overview_form_columns', $columns, $content_overview_datas);

        $this->form_columns = $columns;

        return $columns;
    }


    /**
     * Get content overview form filters
     *
     * @return array
     */
    public function get_content_overview_form_filters() {

        if (!empty($this->form_filters)) {
            return $this->form_filters;
        }

        $content_overview_datas   = $this->content_overview_datas;

        $filters = [];
        // custom filters
        $filters['custom'] = [
            'title'     => esc_html__('Custom filters', 'publishpress'),
            'message'   => esc_html__('Click the "Add New" button to create new filters.', 'publishpress'),
            'filters'   => $content_overview_datas['content_overview_custom_filters']
        ];

        // default filters
        $filters['default'] = [
            'title'     => esc_html__('Inbuilt filters', 'publishpress'),
            'filters'   => [
                'post_status' => esc_html__('Post Status', 'publishpress'),
                'revision_status' => esc_html__('Revision Status', 'publishpress'),
                'author' => esc_html__('Author', 'publishpress'),
                'ptype' => esc_html__('Post Type', 'publishpress')
            ]
        ];

        // editorial fields filters
        if (isset($content_overview_datas['editorial_metadata'])) {
            $filters['editorial_metadata'] = [
                'title'     => esc_html__('Editorial Fields', 'publishpress'),
                'message'   => esc_html__('You do not have any editorial fields enabled', 'publishpress'),
                'filters'   => $content_overview_datas['editorial_metadata']
            ];
        }

        $filters['taxonomies'] = [
            'title'     => esc_html__('Taxonomies', 'publishpress'),
            'message'   => esc_html__('You do not have any public taxonomies', 'publishpress'),
            'filters'   => $content_overview_datas['taxonomies']
        ];

        /**
        * @param array $filters
        * @param array $content_overview_datas
        *
        * @return $filters
        */
        $filters = apply_filters('publishpress_content_overview_form_filters', $filters, $content_overview_datas);

        $this->form_filters = $filters;

        return $filters;
    }

    public function getFormFieldAjaxHandler() {
        $response['status']  = 'error';
        $response['content'] = esc_html__('An error occured', 'publishpress');


        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key($_POST['nonce']), 'content_overview_filter_nonce')) {
            $response['content'] = esc_html__('Error validating nonce. Please reload this page and try again.', 'publishpress');
        } elseif (empty($_POST['post_type'])) {
            $response['content'] = esc_html__('Invalid form request.', 'publishpress');
        } else {
            $post_type = sanitize_text_field($_POST['post_type']);
            $form_args = [];
            $form_args['editable_post_types']    = $this->get_editable_post_types();
            $form_args['default_post_type']      = $post_type;
            $form_args['post_status_options']    = $this->getUserAuthorizedPostStatusOptions($post_type);
            $form_args['publish_date_markup']    = $this->get_publish_date_markup();
            $response['status']  = 'success';
            $response['content'] = PP_Content_Overview_Utilities::content_overview_get_post_form($form_args);
        }

        wp_send_json($response);
    }

    /**
     * Get post types user has capability to edit posts in
     */
    public function get_editable_post_types() {
        $editable_post_types = [];
        $postTypes = $this->get_selected_post_types();
        foreach ($postTypes as $postType) {
            $postTypeObject = get_post_type_object($postType);
            if (!empty($postTypeObject->cap->edit_posts) && current_user_can($postTypeObject->cap->edit_posts)) {
                $editable_post_types[$postTypeObject->name] = $postTypeObject->labels->singular_name;
            }
        }

        return $editable_post_types;
    }

    /**
     * Load utilities files
     *
     * @return void
     */
    private function load_utilities_files() {
        require_once dirname(__FILE__) . "/library/content-overview-utilities.php";
        require_once dirname(__FILE__) . "/library/content-overview-methods.php";
    }

    /**
     * Print the table navigation and filter controls, using the current user's filters if any are set.
     */
    public function table_navigation()
    {
        $args = [];
        $args['editable_post_types']        = $this->get_editable_post_types();
        $args['post_types']                 = $this->get_selected_post_types();
        $args['user_filters']               = $this->user_filters;
        $args['post_form']                  = [];
        $args['default_post_type']          = [];
        $args['overview_filters']           = $this->content_overview_filters();
        $args['content_overview_datas']     = $this->content_overview_datas;
        $args['form_columns']               = $this->form_columns;
        $args['form_filters']               = $this->form_filters;
        $args['form_filter_list']           = $this->form_filter_list;
        $args['all_filters']                = $this->filters;
        $args['terms_options']              = $this->terms_options;
        $args['posts_per_page']             = $this->module->options->posts_per_page;
        $args['operator_labels']            = $this->meta_query_operator_label();
        $args['post_statuses']              = $this->get_post_statuses();
        $args['post_status_options']        = '';
        $args['publish_date_markup']        = '';
        if (!empty($args['editable_post_types'])) {
            $default_post_type  = array_keys($args['editable_post_types'])[0];
            $args['default_post_type']      = $default_post_type;
            $args['post_status_options']    = $this->getUserAuthorizedPostStatusOptions($default_post_type);
            $args['publish_date_markup']    = $this->get_publish_date_markup();
            $args['post_form']  = PP_Content_Overview_Utilities::content_overview_get_post_form($args);
        }

        $this->content_overview_methods::table_navigation($args);
    }

    public function content_overview_filters()
    {
        $select_filter_names = [];

        $editorial_metadata = $this->terms_options;

        foreach ($this->filters as $filter_key => $filter_label) {
            if (array_key_exists($filter_key, $editorial_metadata) && $editorial_metadata[$filter_key]['type'] === 'date') {
                $select_filter_names[$filter_key . '_start']        = $filter_key . '_start';
                $select_filter_names[$filter_key . '_end']          = $filter_key . '_end';
                $select_filter_names[$filter_key . '_start_hidden'] = $filter_key . '_start_hidden';
                $select_filter_names[$filter_key . '_end_hidden']   = $filter_key . '_end_hidden';
            }
            $select_filter_names[$filter_key] = $filter_key;
        }

        return apply_filters('PP_Content_Overview_filter_names', $select_filter_names);
    }

    /**
     * Prints the stories in a single term in the content overview.
     *
     * @param string $postType
     */
    public function printPostForPostType($postType)
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $order = (isset($_GET['order']) && ! empty($_GET['order'])) ? strtoupper(sanitize_key($_GET['order'])) : 'ASC';
        $orderBy = (isset($_GET['orderby']) && ! empty($_GET['orderby'])) ? sanitize_key($_GET['orderby']) : 'post_date';
        $search = (isset($_GET['s']) && ! empty($_GET['s'])) ? sanitize_text_field($_GET['s']) : '';

        $this->user_filters['orderby'] = $orderBy;
        $this->user_filters['order'] = $order;
        $this->user_filters['s']     = $search;

        $localized_post_data = [];
        $posts = $this->getPostsForPostType($postType, $this->user_filters);
        $sortableColumns = $this->getSortableColumns();

        if (! empty($posts)) {
            // Don't display the message for $no_matching_posts
            $this->no_matching_posts = false;
        } ?>

        <div class="postbox-1<?php
        echo (! empty($posts)) ? ' postbox-has-posts' : ''; ?>">
        <div class="content-overview-table-wrap">
            <div class="content-overview-inside inside" data-fl-scrolls>
                <div>
                <?php
                if (! empty($posts)) : ?>
                    <table class="widefat post content-overview striped" cellspacing="0">
                        <thead>
                        <tr>
                            <?php
                            foreach ((array)$this->columns as $key => $name):
                                if (!array_key_exists($key, $this->form_column_list) && $key !== 'post_title') {
                                    continue;
                                }
                            ?>
                                <?php
                                $key = sanitize_key($key);

                                $newOrder = 'ASC';
                                if ($key === $orderBy) :
                                    $newOrder = ($order === 'ASC') ? 'DESC' : 'ASC';
                                endif;
                                ?>
                                <th scope="col" id="<?php echo esc_attr($key); ?>"
                                    class="manage-column column-<?php echo esc_attr($key); ?>">
                                    <div class="column-content">
                                        <?php
                                        if (in_array($key, $sortableColumns)) : ?>
                                            <a href="<?php
                                            echo esc_url(add_query_arg(
                                                ['orderby' => $key, 'order' => $newOrder]
                                            )); ?>">
                                                <?php
                                                echo esc_html($name); ?>
                                                <?php
                                                if ($orderBy === $key) : ?>
                                                    <?php
                                                    $orderIconClass = $order === 'DESC' ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2'; ?>
                                                    <i class="dashicons <?php echo esc_attr($orderIconClass); ?>"></i>
                                                <?php
                                                endif; ?>
                                            </a>
                                        <?php
                                        else: ?>
                                            <?php
                                            echo esc_html($name); ?>
                                        <?php
                                        endif; ?>
                                    </div>
                                </th>
                            <?php
                            endforeach; ?>
                        </tr>
                        </thead>
                        <tfoot></tfoot>
                        <tbody>
                        <?php
                        foreach ($posts as $post) {
                            $filtered_title = apply_filters('pp_calendar_post_title_html', $post->post_title, $post);
                            $post->filtered_title = $filtered_title;
                            if (Util::isPlannersProActive()) {
                                $post_type_object = get_post_type_object($post->post_type);
                                $can_edit_post = current_user_can($post_type_object->cap->edit_post, $post->ID);
                                $localized_post_data = $this->localize_post_data($localized_post_data, $post, $can_edit_post);
                            }
                            $this->print_post($post);
                        } ?>
                        </tbody>
                    </table>
                <?php
                else: ?>
                    <div class="message info">
                        <svg width="170" height="170" viewBox="0 0 170 170" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="85" cy="85" r="85" fill="#F3F3F3"></circle>
                        <path d="M97.6667 78.6665H72.3333C70.5917 78.6665 69.1667 80.0915 69.1667 81.8332C69.1667 83.5748 70.5917 84.9998 72.3333 84.9998H97.6667C99.4083 84.9998 100.833 83.5748 100.833 81.8332C100.833 80.0915 99.4083 78.6665 97.6667 78.6665ZM107.167 56.4998H104V53.3332C104 51.5915 102.575 50.1665 100.833 50.1665C99.0917 50.1665 97.6667 51.5915 97.6667 53.3332V56.4998H72.3333V53.3332C72.3333 51.5915 70.9083 50.1665 69.1667 50.1665C67.425 50.1665 66 51.5915 66 53.3332V56.4998H62.8333C61.1536 56.4998 59.5427 57.1671 58.355 58.3548C57.1673 59.5426 56.5 61.1535 56.5 62.8332V107.167C56.5 108.846 57.1673 110.457 58.355 111.645C59.5427 112.833 61.1536 113.5 62.8333 113.5H107.167C110.65 113.5 113.5 110.65 113.5 107.167V62.8332C113.5 59.3498 110.65 56.4998 107.167 56.4998ZM104 107.167H66C64.2583 107.167 62.8333 105.742 62.8333 104V72.3332H107.167V104C107.167 105.742 105.742 107.167 104 107.167ZM88.1667 91.3332H72.3333C70.5917 91.3332 69.1667 92.7582 69.1667 94.4998C69.1667 96.2415 70.5917 97.6665 72.3333 97.6665H88.1667C89.9083 97.6665 91.3333 96.2415 91.3333 94.4998C91.3333 92.7582 89.9083 91.3332 88.1667 91.3332Z" fill="#8E8E8E"></path>
                        </svg>
                        <h4><?php
                            esc_html_e(
                                'No results found',
                                'publishpress'
                            ); ?></h4>
                        <p><?php
                            esc_html_e(
                                'There are no posts in the range or filter specified.',
                                'publishpress'
                            ); ?>
                        </p>
                    </div>
                <?php
                endif; ?>
            </div>
            </div>
            </div>
        </div>
        <div id="pp-content-overview-general-modal" style="display: none;">
            <div id="pp-content-overview-general-modal-container" class="pp-content-overview-general-modal-container publishpress-quick-modal"></div>
        </div>
        <?php

        if (Util::isPlannersProActive()) {

            $localized_post_data['post_author_label']   = esc_html__('Author', 'publishpress');
            $localized_post_data['post_date_label']     = esc_html__('Post Date', 'publishpress');
            $localized_post_data['edit_label']          = esc_html__('Edit', 'publishpress');
            $localized_post_data['delete_label']        = esc_html__('Trash', 'publishpress');
            $localized_post_data['preview_label']       = esc_html__('Preview', 'publishpress');
            $localized_post_data['view_label']          = esc_html__('View', 'publishpress');
            $localized_post_data['prev_label']          = esc_html__('Previous Post', 'publishpress');
            $localized_post_data['next_label']          = esc_html__('Next Post', 'publishpress');
            $localized_post_data['post_status_label']   = esc_html__('Post Status', 'publishpress');
            $localized_post_data['update_label']        = esc_html__('Save Changes', 'publishpress');
            $localized_post_data['empty_term']          = esc_html__('Taxonomy not set.', 'publishpress');
            $localized_post_data['date_format']         = pp_convert_date_format_to_jqueryui_datepicker(get_option('date_format'));
            $localized_post_data['week_first_day']      = esc_js(get_option('start_of_week'));
            $localized_post_data['nonce']               = wp_create_nonce('content_overview_action_nonce');

            wp_localize_script(
                'publishpress-content_overview',
                'PPContentOverviewPosts',
                $localized_post_data
            );
        }

        // phpcs:enable
    }

    private function getSortableColumns()
    {
        $sortableColumns = [
            'post_title',
            'post_date',
            'post_modified',
            'post_type',
            'post_status',
            'post_author',
        ];

        return apply_filters('publishpress_content_overview_sortable_columns', $sortableColumns);
    }

    /**
     * Get all of the posts for a given term based on filters
     *
     * @param string $postType
     * @param array $args
     *
     * @return array $term_posts An array of post objects for the term
     */
    public function getPostsForPostType($postType, $args = null)
    {
        $defaults = [
            'post_status' => null,
            'author' => null,
            'posts_per_page' => $this->module->options->posts_per_page,
        ];

        $args = array_merge($defaults, $args);

        $enabled_filters = array_keys($this->filters);
        $editorial_metadata = $this->terms_options;

        //remove inactive builtin filter
        if (!in_array('ptype', $enabled_filters)) {
            // show all post type
            $postType = $this->get_selected_post_types();
        }

        if (!in_array('author', $enabled_filters)) {
            unset($args['author']);
        }

        $meta_query = $tax_query = ['relation' => 'AND'];
        $metadata_filter = $taxonomy_filter = false;
        $checklists_filters = [];
        // apply enabled filter
        foreach ($enabled_filters as $enabled_filter) {
            if (array_key_exists($enabled_filter, $editorial_metadata)) {
                //metadata field filter
                $meta_key = $enabled_filter;
                $metadata_term = $editorial_metadata[$meta_key];
                unset($args[$enabled_filter]);
                if ($metadata_term['type'] === 'date') {
                    $date_type_metaquery = [];
                    if (! empty($this->user_filters[$meta_key . '_start'])) {
                        $date_type_metaquery[] = strtotime($this->user_filters[$meta_key . '_start_hidden']);
                    }
                    if (! empty($this->user_filters[$meta_key . '_end'])) {
                        $date_type_metaquery[] = strtotime($this->user_filters[$meta_key . '_end_hidden']);
                    }
                    if (count($date_type_metaquery) === 2) {
                        $metadata_filter = true;
                        $compare        = 'BETWEEN';
                        $meta_value     = $date_type_metaquery;
                    } elseif (count($date_type_metaquery) === 1) {
                        $metadata_filter = true;
                        $compare        = '=';
                        $meta_value     = $date_type_metaquery[0];
                    }
                    if (!empty($date_type_metaquery)) {
                        $metadata_filter = true;
                        $meta_query[] = array(
                            'key' => '_pp_editorial_meta_' . $metadata_term['type'] . '_' . $metadata_term['slug'],
                            'value' => $meta_value,
                            'compare' => $compare
                        );
                    }

                } elseif (! empty($this->user_filters[$meta_key])) {
                    if ($metadata_term['type'] === 'date') {
                        continue;
                    } else {
                        $meta_value = sanitize_text_field($this->user_filters[$meta_key]);
                    }

                     $compare = '=';
                    if ($metadata_term['type'] === 'paragraph'
                        || ($metadata_term['type'] === 'select' && isset($metadata_term->select_type) && $metadata_term['select_type'] === 'multiple')
                    ) {
                        $compare = 'LIKE';
                    }
                    $metadata_filter = true;
                    $meta_query[] = array(
                        'key' => '_pp_editorial_meta_' . $metadata_term['type'] . '_' . $metadata_term['slug'],
                        'value' => $meta_value,
                        'compare' => $compare
                    );
                }

            } elseif(
                in_array($enabled_filter, $this->content_overview_datas['meta_keys'])
                && (
                    isset($this->user_filters[$enabled_filter])
                    &&
                        (
                            !empty($this->user_filters[$enabled_filter])
                            || $this->user_filters[$enabled_filter] == '0'
                            || (
                                !empty($this->user_filters[$enabled_filter . '_operator'])
                                && $this->user_filters[$enabled_filter . '_operator'] === 'not_exists'
                                )
                        )
                    )
                ) {
                // metakey filter
                unset($args[$enabled_filter]);
                $meta_value = sanitize_text_field($this->user_filters[$enabled_filter]);
                $meta_operator = !empty($this->user_filters[$enabled_filter . '_operator']) ? $this->user_filters[$enabled_filter . '_operator'] : 'equals';
                $compare = $this->meta_query_operator_symbol($meta_operator);

                $metadata_filter = true;

                if ($meta_operator == 'not_exists') {
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => $enabled_filter,
                            'compare' => 'NOT EXISTS'
                        ),
                        array(
                            'key' => $enabled_filter,
                            'value' => '',
                            'compare' => '='
                        )
                    );
                } else {
                    $meta_query[] = array(
                        'key' => $enabled_filter,
                        'value' => $meta_value,
                        'compare' => $compare
                    );
                }
            } elseif (in_array($enabled_filter, ['ppch_co_yoast_seo__yoast_wpseo_linkdex', 'ppch_co_yoast_seo__yoast_wpseo_content_score']) && !empty($this->user_filters[$enabled_filter]) && array_key_exists($enabled_filter, $this->form_filter_list) && class_exists('WPSEO_Meta')) {
                // yoast seo filter
                unset($args[$enabled_filter]);
                $meta_value = sanitize_text_field($this->user_filters[$enabled_filter]);
                $meta_key = str_replace('ppch_co_yoast_seo_', '', $enabled_filter);
                $meta_operator = !empty($this->user_filters[$enabled_filter . '_operator']) ? $this->user_filters[$enabled_filter . '_operator'] : 'equals';
                $compare = $this->meta_query_operator_symbol($meta_operator);
                $metadata_filter = true;
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => $compare
                );

            } elseif(array_key_exists($enabled_filter, $this->content_overview_datas['taxonomies']) && !empty($this->user_filters[$enabled_filter])) {
                //taxonomy filter
                unset($args[$enabled_filter]);
                $tax_value = sanitize_text_field($this->user_filters[$enabled_filter]);
                $taxonomy_filter = true;
                $tax_query[] = array(
                      'taxonomy' => $enabled_filter,
                      'field'     => 'slug',
                      'terms'    => [$tax_value],
                      'include_children' => true,
                      'operator' => 'IN',
                );
            } elseif(!empty($this->user_filters[$enabled_filter]) && strpos($enabled_filter, "ppch_co_checklist_") === 0 && array_key_exists($enabled_filter, $this->form_filter_list)) {
                // checklists filter
                /**
                 * TODO: Implement metaquery filter when checklists started storing checklists status in meta_key
                 */
                unset($args[$enabled_filter]);
                $meta_value = sanitize_text_field($this->user_filters[$enabled_filter]);
                $meta_key = str_replace('ppch_co_checklist_', '', $enabled_filter);
                $checklists_filters[$meta_key] = $meta_value;
            }

        }

        if ($metadata_filter) {
            $args['meta_query'] = $meta_query;
        }

        if ($taxonomy_filter) {
            $args['tax_query'] = $tax_query;
        }

        $args['post_type'] = $postType;

        // Unpublished as a status is just an array of everything but 'publish'
        if ($args['post_status'] == 'unpublish') {
            $args['post_status'] = '';
            $post_statuses = $this->get_post_statuses();

            foreach ($post_statuses as $post_status) {
                $args['post_status'] .= sanitize_key($post_status->slug) . ', ';
            }

            $args['post_status'] = rtrim($args['post_status'], ', ');

            // Optional filter to include scheduled content as unpublished
            if (apply_filters('pp_show_scheduled_as_unpublished', false)) {
                $args['post_status'] .= ', future';
            }
        }

        // Filter by post_author if it's set
        if (isset($args['author']) && $args['author'] === '0') {
            unset($args['author']);
        }

        // Order the post list by publishing date.
        if (! isset($args['orderby'])) {
            $args['orderby'] = 'post_date';
            $args['order'] = 'ASC';
        }

        // Filter for an end user to implement any of their own query args
        $context = 'default';
        $args = apply_filters('PP_Content_Overview_posts_query_args', $args, $context, $enabled_filters, $this->user_filters);

        add_filter('posts_where', [$this, 'posts_where_range']);
        $term_posts_query_results = new WP_Query($args);
        remove_filter('posts_where', [$this, 'posts_where_range']);

        $term_posts = [];
        while ($term_posts_query_results->have_posts()) {
            $term_posts_query_results->the_post();

            global $post;

            $add_post = true;

            if (!empty($checklists_filters)) {
                $post_checklists = apply_filters('publishpress_checklists_requirement_list', [], $post);
                foreach ($checklists_filters as $checklists_filter_name => $checklists_filter_check) {
                    if (!array_key_exists($checklists_filter_name, $post_checklists)) {
                        // post that doesn't have this requirement shouldn't show?
                        $add_post = false;
                    } elseif ($checklists_filter_check == 'passed' && empty($post_checklists[$checklists_filter_name]['status'])) {
                        // filter posts that failed when condition is passed
                        $add_post = false;
                    } elseif ($checklists_filter_check == 'failed' && !empty($post_checklists[$checklists_filter_name]['status'])) {
                        // filter out post that passed when condition is failed
                        $add_post = false;
                    }
                }
            }

            if ($add_post) {
                /**
                 * TODO: Should we require posts like x2 if results is empty due to $add_post been false for all?
                 */
                $term_posts[] = $post;
            }
        }


        return $term_posts;
    }

    /**
     * Prints a single post within a term in the content overview.
     *
     * @param object $post The post to print.
     */
    public function print_post($post)
    {
        ?>
        <tr id="post-<?php echo esc_attr($post->ID); ?>" data-post_id="<?php echo esc_attr($post->ID); ?>" valign="top">
            <?php
            foreach ((array)$this->columns as $key => $name) {
                if (!array_key_exists($key, $this->form_column_list) && $key !== 'post_title') {
                    continue;
                }
                echo '<td><div class="column-content column-content-'. esc_attr($key) .'">';
                if (method_exists($this, 'column_' . $key)) {
                    $method = 'column_' . $key;
                    echo $this->$method($post); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                } else {
                    echo $this->column_default($post, $key); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                echo '</div></td>';
            } ?>
        </tr>
        <?php
    }

    /**
     * Default callback for producing the HTML for a term column's single post value
     * Includes a filter other modules can hook into
     *
     * @param object $post The post we're displaying
     * @param string $column_name Name of the column
     *
     * @return string $output Output value for the term column
     * @since 0.7
     *
     */
    public function column_default($post, $column_name)
    {

        // Hook for other modules to get data into columns
        $column_value = null;

        /**
         * @deprecated
         */
        $column_value = apply_filters('PP_Content_Overview_term_column_value', $column_name, $post, null);

        /**
         * @param string $column_name
         * @param WP_Post $post
         *
         * @return string
         */

        $column_value = apply_filters('publishpress_content_overview_column_value', $column_name, $post, $this->module_url);

        $content_overview_datas = $this->get_content_overview_datas();
        $taxonomies     = !empty($content_overview_datas['taxonomies']) ? array_keys($content_overview_datas['taxonomies']) : [];

        if (! is_null($column_value) && $column_value != $column_name) {
            return $column_value;
        }

        if (strpos($column_name, '_pp_editorial_meta_') === 0) {
            $column_value = get_post_meta($post->ID, $column_name, true);

            if (empty($column_value)) {
                return '<span>' . esc_html__('None', 'publishpress') . '</span>';
            }

            return $column_value;
        }

        if (in_array($column_name, $taxonomies)) {
            // taxonomies
            $terms = get_the_terms($post->ID, $column_name);
            $out = '';
            if (!empty($terms) && !is_wp_error($terms)) {
                $out .='<div class="taxonomy-terms">';
                $term_labels = [];
                foreach ($terms as $term) {
                    $term_labels[] = esc_html(sanitize_term_field('name', $term->name, $term->term_id, $column_name, 'display'));
                }
                $out .= implode(', ', $term_labels);
                $out .='</div>';
            }
            return $out;
        }

        switch ($column_name) {
            case 'post_status':
                $status_name = $this->get_post_status_friendly_name($post->post_status, $post);

                return $status_name;
                break;
            case 'post_type':
                $post_type_object = get_post_type_object($post->post_type);

                return $post_type_object->label;
                break;
            case 'post_author':
                $post_author = get_userdata($post->post_author);
                $author_name = is_object($post_author) ? $post_author->display_name : '';
                $author_name = apply_filters('the_author', $author_name);

                $author_name = apply_filters('publishpress_content_overview_author_column', $author_name, $post);

                return $author_name;
                break;
            case 'post_date':
                $output = get_the_time(get_option('date_format'), $post->ID) . '<br />';
                $output .= get_the_time(get_option('time_format'), $post->ID);

                return $output;
                break;
            case 'post_modified':
                $modified_time_gmt = strtotime($post->post_modified_gmt . " GMT");

                return $this->timesince($modified_time_gmt);
                break;
            default:
                break;
        }

        $meta_options = isset($this->terms_options[$column_name])
            ? $this->terms_options[$column_name]
            : null;

        if (is_null($meta_options)) {
            // let assume it's a post meta
            $post_meta_value = get_post_meta($post->ID, $column_name, true);
            return is_array($post_meta_value) ? print_r($post_meta_value, true) : $post_meta_value;
        }

        $column_type = $meta_options['type'];
        $column_value = get_post_meta($post->ID, "_pp_editorial_meta_{$column_type}_{$column_name}", true);

        return apply_filters("pp_editorial_metadata_{$column_type}_render_value_html", $column_value, $meta_options);
    }

    /**
     * Filter the WP_Query so we can get a range of posts
     *
     * @param string $where The original WHERE SQL query string
     *
     * @return string $where Our modified WHERE query string
     */
    public function posts_where_range($where = '')
    {
        global $wpdb;

        $beginning_date = date('Y-m-d', strtotime($this->user_filters['start_date'])) . ' 00:00:00'; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
        $ending_date = date('Y-m-d', strtotime($this->user_filters['end_date'])) . ' 23:59:59'; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

        $where = $where . $wpdb->prepare(
                " AND ($wpdb->posts.post_date >= %s AND $wpdb->posts.post_date < %s)",
                $beginning_date,
                $ending_date
            );

        return $where;
    }

    /**
     * Prepare the data for the title term column
     *
     * @since 0.7
     */
    public function column_post_title($post)
    {
        $post_title = _draft_or_post_title($post->ID);
        $filtered_title = apply_filters('pp_content_overview_post_title_html', $post_title, $post);

        $post_type_object = get_post_type_object($post->post_type);
        $can_edit_post = current_user_can($post_type_object->cap->edit_post, $post->ID);
        if ($can_edit_post) {
            $output = '<strong class="title-column post-title"><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . $filtered_title . '</a></strong>';
        } else {
            $output = '<strong class="post-title">' . $filtered_title . '</strong>';
        }

        // Edit or Trash or View
        $output .= '<div class="row-actions">';
        $item_actions = [];

        if ($can_edit_post) {
            $item_actions['edit'] = '<a target="_blank" title="' . esc_attr(
                    esc_html__(
                        'Edit this post',
                        'publishpress'
                    )
                ) . '" href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__(
                    'Edit',
                    'publishpress'
                ) . '</a>';
        }

        if (EMPTY_TRASH_DAYS > 0 && current_user_can($post_type_object->cap->delete_post, $post->ID)) {
            $item_actions['trash'] = '<a class="submitdelete" title="' . esc_attr(
                    esc_html__(
                        'Move this item to the Trash',
                        'publishpress'
                    )
                ) . '" href="' . esc_url(get_delete_post_link($post->ID)) . '">' . esc_html__(
                    'Trash',
                    'publishpress'
                ) . '</a>';
        }

        // Display a View or a Preview link depending on whether the post has been published or not
        if (in_array($post->post_status, ['publish'])) {
            $item_actions['view'] = '<a target="_blank" href="' . esc_url(get_permalink($post->ID)) . '" title="' . esc_attr(
                    sprintf(
                        __(
                            'View &#8220;%s&#8221;',
                            'publishpress'
                        ),
                        $post_title
                    )
                ) . '" rel="permalink">' . esc_html__('View', 'publishpress') . '</a>';
        } elseif ($can_edit_post) {
            $item_actions['previewpost'] = '<a target="_blank" href="' . esc_url(
                    apply_filters(
                        'preview_post_link',
                        add_query_arg('preview', 'true', get_permalink($post->ID)),
                        $post
                    )
                ) . '" title="' . esc_attr(
                    sprintf(
                        __('Preview &#8220;%s&#8221;', 'publishpress'),
                        $post_title
                    )
                ) . '" rel="permalink">' . esc_html__('Preview', 'publishpress') . '</a>';
        }

        $item_actions = apply_filters('PP_Content_Overview_item_actions', $item_actions, $post->ID);
        if (count($item_actions)) {
            $output .= '<div class="row-actions">';
            $html = '';
            foreach ($item_actions as $class => $item_action) {
                $html .= '<span class="' . esc_attr($class) . '">' . $item_action . '</span> | ';
            }
            $output .= rtrim($html, '| ');
            $output .= '</div>';
        }

        return $output;
    }

    /**
     * Get the filters for the current user for the content overview display, or insert the default
     * filters if not already set.
     *
     * @return array The filters for the current user, or the default filters if the current user has none.
     */
    public function get_user_filters()
    {
        global $pp_content_overview_user_filters;

        if (is_array($pp_content_overview_user_filters)) {
            return $pp_content_overview_user_filters;
        }

        $current_user = wp_get_current_user();
        $user_filters = [];
        $user_filters = $this->get_user_meta($current_user->ID, self::USERMETA_KEY_PREFIX . 'filters', true);

        // If usermeta didn't have filters already, insert defaults into DB
        if (empty($user_filters)) {
            $user_filters = $this->update_user_filters();
        }

        return $user_filters;
    }

    public function sendJsonSearchAuthors()
    {
        $ajax = Ajax::getInstance();

        if (
            (! isset($_GET['nonce']))
            || (! wp_verify_nonce(sanitize_key($_GET['nonce']), 'content_overview_filter_nonce'))
        ) {
            $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
        }

        if (! $this->currentUserCanViewContentOverview()) {
            $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
        }

        $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

        /**
         * @param array $results
         * @param string $searchText
         */
        $results = apply_filters('publishpress_search_authors_results_pre_search', [], $queryText);

        if (! empty($results)) {
            $ajax->sendJson($results);
        }

        global $wpdb;

        $cacheKey = 'search_authors_result_' . md5($queryText);
        $cacheGroup = 'content_overview';

        $queryResult = wp_cache_get($cacheKey, $cacheGroup);

        if (false === $queryResult) {
            $user_args = [
                'number'     => apply_filters('pp_planner_author_result_limit', 20),
                'capability' => 'edit_posts',
            ];
            if (!empty($queryText)) {
                $user_args['search'] = sanitize_text_field('*' . $queryText . '*');
            }
            $users   = get_users($user_args);
            $queryResult = [];
            foreach ($users as $user) {
                $queryResult[] = [
                    'id'   => (isset($_GET['field']) && sanitize_key($_GET['field']) === 'slug') ? $user->user_nicename : $user->ID,
                    'text' => $user->display_name,
                ];
            }

            wp_cache_set($cacheKey, $queryResult, $cacheGroup);
        }

        $ajax->sendJson($queryResult);
    }

    public function sendJsonSearchCategories()
    {
        $ajax = Ajax::getInstance();

        if (
            (! isset($_GET['nonce']))
            || (! wp_verify_nonce(sanitize_key($_GET['nonce']), 'content_overview_filter_nonce'))
        ) {
            $ajax->sendJsonError(Error::ERROR_CODE_INVALID_NONCE);
        }

        if (! $this->currentUserCanViewContentOverview()) {
            $ajax->sendJsonError(Error::ERROR_CODE_ACCESS_DENIED);
        }

        $queryText = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
        $taxonomy  = (isset($_GET['taxonomy']) && !empty(trim($_GET['taxonomy']))) ? sanitize_text_field($_GET['taxonomy']) : 'category';

        global $wpdb;

        $cacheKey = 'search_categories_result_' . md5($queryText);
        $cacheGroup = 'content_overview';

        $queryResult = wp_cache_get($cacheKey, $cacheGroup);

        if (false === $queryResult) {
            global $wpdb;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $queryResult = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT t.slug AS id, t.name AS text
                FROM {$wpdb->term_taxonomy} as tt
                INNER JOIN {$wpdb->terms} as t ON (tt.term_id = t.term_id)
                WHERE taxonomy = %s AND t.name LIKE %s
                ORDER BY 2
                LIMIT 20",
                $taxonomy,
                '%' . $wpdb->esc_like($queryText) . '%'
            )
        );
            wp_cache_set($cacheKey, $queryResult, $cacheGroup);
        }

        $ajax->sendJson($queryResult);
    }
}
