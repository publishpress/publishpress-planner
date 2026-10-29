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

use WP_Error;

/**
 * @package PublishPress\Core
 */
class Error implements ErrorHandlerInterface
{
    const ERROR_CODE_UNDEFINED = 'PPE_0';

    const ERROR_CODE_INVALID_NONCE = 'PPE_1';

    const ERROR_CODE_ACCESS_DENIED = 'PPE_2';

    /**
     * @var Error
     */
    private static $instance;

    /**
     * @var array
     */
    private $errorMessages;

    public function __construct()
    {
        $this->registerErrors($this->getGlobalErrors());
    }

    /**
     * @return Error
     */
    public static function getInstance()
    {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array
     */
    private function getGlobalErrors()
    {
        return [
            self::ERROR_CODE_UNDEFINED => __('Undefined error found', 'publishpress'),
            self::ERROR_CODE_INVALID_NONCE => __('Invalid nonce', 'publishpress'),
            self::ERROR_CODE_ACCESS_DENIED => __('Access denied', 'publishpress'),
        ];
    }

    /**
     * @param array $errors
     *
     * @return void
     */
    private function registerErrors($errors)
    {
        foreach ($errors as $errorCode => $errorMessage)
        {
            $this->errorMessages[$errorCode] = $errorMessage;
        }
    }

    /**
     * @param string $code
     * @return WP_Error
     */
    public function getWpError($code)
    {
        return new WP_Error($code, esc_html($this->getErrorMessage($code)));
    }

    /**
     * @param $code
     * @return string
     */
    private function getErrorMessage($code)
    {
        if (! isset($this->errorMessages[$code])) {
            $code = self::ERROR_CODE_UNDEFINED;
        }

        return $this->errorMessages[$code];
    }

    /**
     * @param ModuleErrorsInterface $instance
     * @return void
     */
    public function registerModuleErrors($instance)
    {
        $this->registerErrors($instance->getErrors());
    }

    /**
     * @param $code
     * @return string
     */
    public function getErrorHtml($code)
    {
        return sprintf(
            '<div class="notice notice-error"><span class="publishpress-error-code">%s</span>%s</div>',
            $code,
            $this->getErrorMessage($code)
        );
    }

    /**
     * @param $code
     * @return void
     */
    public function wpDie($code)
    {
        wp_die($this->getWpError($code), esc_html($code)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The WP_Error object is handled by WordPress's wp_die error handler.
    }
}
