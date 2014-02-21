<?php
/**
 * Twig function for outputting a route.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Twig\Method;

use Aura\Router\Router;

class RouteLookup {

    /**
     * @var Router
     */
    protected $router;

    /**
     * Performs a reverse lookup on the route given its name, and creates a URL.
     *
     * @param string $name
     * @param array  $data
     * @return string
     */
    public function execute($name, $data = array()) {
        // Ensure format is set to something, even if empty.
        if (!isset($data['format'])) {
            $data['format'] = '';
        }
        return $this->getRouter()->generate($name, $data);
    }

    /**
     * @param  Router $router
     * @return $this
     */
    public function setRouter($router) {
        $this->router = $router;
        return $this;
    }

    /**
     * @return Router
     */
    public function getRouter() {
        return $this->router;
    }

}