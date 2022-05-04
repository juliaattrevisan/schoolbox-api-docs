<?php

/**
 * Extends \Aura\Router\RouteCollection with a method for adding POST aliases
 * for REST HTTP methods that aren't supported by most browsers.
 *
 * The REST routes should have already been added with the attachResource()
 * method.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Router;

class RouteCollection extends \Aura\Router\RouteCollection
{
    /**
     * The prefix for the key within which the route collection cache is stored.
     *
     * Will be suffixed with the current instance ID.
     */
    public const MODULE_CACHE_KEY_PREFIX = 'routeCache:';

    /**
     * Adds POST aliases to a route already added with $this->attachResource()
     *
     * The following aliases are added:
     * * update:post
     * * replace:post
     * * delete:post
     *
     * @param string $name
     * @param string $path
     */
    public function attachResourcePostAlias($name, $path)
    {
        $this->attach(
            $name,
            $path,
            function ($subroute) {
                /** @var \Aura\Router\Router $subroute */
                $subroute->setTokens(array(
                        'id'     => '\d+',
                        'format' => '(\.[^/]+)?'
                    ));
                $subroute
                    ->addPost('update:post', '/{id}/update')
                    ->addValues(array('action' => 'update'));
                $subroute
                    ->addPost('replace:post', '/{id}/replace')
                    ->addValues(array('action' => 'replace'));
                $subroute
                    ->addPost('delete:post', '/{id}/delete')
                    ->addValues(array('action' => 'delete'));
            }
        );
    }
}
