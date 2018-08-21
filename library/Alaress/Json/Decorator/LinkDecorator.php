<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Json\Decorator;

use Alaress\Authorizer\Authorizer;
use Alaress\Json\Linkable;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Router;


class LinkDecorator implements IDecorator
{
    /** @var Router */
    private $router;

    /** @var Authorizer */
    private $auth;


    /**
     * @param Router     $router
     * @param Authorizer $auth
     */
    public function __construct(Router $router, Authorizer $auth)
    {
        $this->router = $router;
        $this->auth   = $auth;
    }


    /**
     * Decorate thew given input according
     *
     * @param mixed  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if ($model instanceof Linkable) {
            foreach ($model->getJsonApiLinks() as $linkName => [ $routeName, $routeArgs ]) {
                try {
                    $json['_links'][$linkName] = [
                        'href' => $this->router->generate($routeName, $routeArgs),
                        'auth' => $this->auth->isPathAuthorizedForUser($routeName, $routeArgs, $_SESSION['user'])
                    ];
                } catch (RouteNotFound $e) {
                    // noop; the model incorrectly specifies a route name
                }
            }
        }

        return $json;
    }

}