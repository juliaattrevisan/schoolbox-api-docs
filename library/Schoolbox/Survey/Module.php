<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Survey;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'survey';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $routes = [
            'GET'  => [
                '/ajax' => 'getSelectize',
            ],
            'POST' => [
            ]
        ];

        foreach ($routes as $httpMethod => $route) {
            foreach ($route as $path => $controllerMethod) {
                $this->register($httpMethod, $path, $controllerMethod);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory()->get(Mapper::class));
    }
}
