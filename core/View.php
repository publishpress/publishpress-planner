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

namespace PublishPress\Core;

use Exception;

/**
 * @package PublishPress\Core
 */
class View
{
    const FILE_EXTENSION = '.html.php';

    /**
     * @throws Exception
     */
    public function render($view, $context = [], $views_path = null)
    {
        $view = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $view);

        if (is_null($views_path)) {
            $views_path = PUBLISHPRESS_VIEWS_PATH;
        }

        $view_path = $this->get_view_path($view, $views_path);

        if (! is_readable($view_path)) {
            error_log('PublishPress: View is not readable: ' . $view); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Record a template loading failure for diagnostics.

            return '';
        }

        ob_start();
        include $view_path;

        return ob_get_clean();
    }

    /**
     * Display a template whose dynamic values are escaped in the template itself.
     */
    public function display($view, $context = [], $views_path = null)
    {
        echo $this->render($view, $context, $views_path); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaping belongs to each template's output context; filtering here would strip form controls.
    }

    protected function get_view_path($view, $views_path)
    {
        return $views_path . '/' . $view . self::FILE_EXTENSION;
    }
}
