<?php
/**
 * Twig function for outputting a route.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;

class RouteLookup {

    /**
     * @var Router
     */
    protected $router;

    /**
     * Performs a reverse lookup on the route given its name, and creates a URL.
     *
     * @param  string $name The name of the route to look up.
     * @param  array  $data Any params to pass as part of the route.
     * @return string
     */
    public function execute($name, $data = array()) {
        // Ensure format is set to something, even if empty.
        if (!isset($data['format'])) {
            $data['format'] = '';
        }

        try {
            return $this->getRouter()->generate($name, $data);
        } catch (RouteNotFound $e) {
            return false;
        }
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
