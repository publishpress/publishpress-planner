<?php
/**
 * @package PublishPress
 * @author  PublishPress
 *
 * Copyright (c) 2022 PublishPress
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
 *
 */

use PublishPress\WordPressReviews\ReviewsController;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Class PP_Reviews
 *
 * This class adds a review request system for your plugin or theme to the WP dashboard.
 */
#[\AllowDynamicProperties]
class PP_Reviews extends PP_Module
{
    /**
     * Tracking API Endpoint.
     *
     * @var string
     */
    public static $api_url = '';

    public $module_name = 'reviews';

    public $module_url = '';

    /**
     * @var ReviewsController
     */
    private $reviewController;

    /**
     * The constructor
     */
    public function __construct()
    {
        global $publishpress;

        $this->module_url = $this->get_module_url(__FILE__);

        // Register the module with PublishPress
        $args = [
            'title' => __('Reviews', 'publishpress'),
            'short_description' => false,
            'extended_description' => false,
            'module_url' => $this->module_url,
            'icon_class' => 'dashicons dashicons-feedback',
            'slug' => 'reviews',
            'default_options' => [
                'enabled' => 'on',
            ],
            'general_options' => false,
            'autoload' => true,
        ];

        // Apply a filter to the default options
        $args['default_options'] = apply_filters(
            'pp_reviews_default_options',
            $args['default_options']
        );

        $this->module = $publishpress->register_module(
            PublishPress\Legacy\Util::sanitize_module_name($this->module_name),
            $args
        );

        parent::__construct();

        $this->reviewController = new ReviewsController(
            'publishpress',
            'PublishPress Planner',
            PUBLISHPRESS_URL . '/common/img/publishpress-wp-logo.png'
        );
    }

    /**
     *
     */
    public function init()
    {
        $this->reviewController->init();
    }
}
