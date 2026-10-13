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

/**
 * class PP_Theeventscalendar_Integration
 */

if (! class_exists('PP_Theeventscalendar_Integration')) {
    #[\AllowDynamicProperties]
    class PP_Theeventscalendar_Integration extends PP_Module
    {
        public $module;

        /**
         * Register the module with PublishPress but don't do anything else
         *
         * @since 0.7
         */
        public function __construct()
        {
            $this->module_url = $this->get_module_url(__FILE__);

            // Register the The Events Calendar module with PublishPress
            $args = [
                'title' => __('The Events Calendar Integration', 'publishpress'),
                'short_description' => __('Integrate with the The Events Calendar plugin', 'publishpress'),
                'module_url' => $this->module_url,
                'icon_class' => 'dashicons dashicons-groups',
                'slug' => 'theeventscalendar-integration',
                'settings_slug' => 'theeventscalendar-integration',
                'default_options' => [
                    'enabled' => 'on',
                ],
                'autoload' => true,
            ];
            $this->module = PublishPress()->register_module('theeventscalendar_integration', $args);
        }

        /**
         * Initialize the rest of the stuff in the class if the module is active
         *
         * @since 0.7
         */
        public function init()
        {
            add_action('publishpress_after_moving_calendar_item', [$this, 'afterMovingCalendarItem'], 10, 2);
            add_filter('pp_calendar_posts_query_args', [$this, 'disablePluginQueryFilters']);
        }

        public function afterMovingCalendarItem($postId, $newDate)
        {
            $post = get_post($postId);

            if ('tribe_events' !== $post->post_type) {
                return;
            }

            $eventDuration = (int)get_post_meta($postId, '_EventDuration', true);

            $startDateInSeconds = strtotime($newDate);
            $endDate = gmdate('Y-m-d H:i:s', $startDateInSeconds + $eventDuration);

            update_post_meta($postId, '_EventStartDate', $newDate);
            update_post_meta($postId, '_EventStartDateUTC', get_gmt_from_date($newDate));

            update_post_meta($postId, '_EventEndDate', $endDate);
            update_post_meta($postId, '_EventEndDateUTC', get_gmt_from_date($endDate));
        }

        /**
         * We suppress the query filters for the plugin because it was messing
         * with the calendar data, showing events on different dates depending
         * on the post type filtering. For more details, check the issue #1020.
         *
         * @param $args
         * @return mixed
         */
        public function disablePluginQueryFilters($args)
        {
            // @todo: Instead of suppressing the filters, maybe we should apply the same filters even if the post type is not set in the query?
            $args['tribe_suppress_query_filters'] = true;

            return $args;
        }
    }
}
