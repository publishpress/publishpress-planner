<?php
/**
 * @package     PublishPress\Notifications
 * @author      PublishPress <help@publishpress.com>
 * @copyright   Copyright (c) 2022 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Notifications\Traits;

use Exception;
use PublishPress\Notifications\Pimple_Container;

trait Dependency_Injector
{
    /**
     * Instance of the Pimple container
     */
    protected $container;

    /**
     * Returns the required service or dependency from the container.
     * Throws an exception if the service is not defined.
     *
     * @param string $service_name
     *
     * @return mix
     *
     * @throws Exception
     */
    public function get_service($service_name)
    {
        if (empty($this->container)) {
            $this->init_pimple();
        }

        if (!isset($this->container[$service_name])) {
            throw new Exception('Service "' . esc_html($service_name) . '" not found in the container');
        }

        return $this->container[$service_name];
    }

    protected function init_pimple()
    {
        $this->container = Pimple_Container::get_instance();
    }
}
