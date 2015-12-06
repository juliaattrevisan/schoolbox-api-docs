<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;

use Schoolbox\Mapper\Exception;
use Schoolbox\Collection;

class Module extends BaseModule
{
    protected $basename = 'upsRule';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        $this->addGetRoute($router, 'add', $basePath, "/add/{rulesetId}");
        $this->addPostRoute($router, 'create', $basePath, "/create");

        $this->addGetRoute($router, 'edit', $basePath, "/edit/{ruleId}");
        $this->addPostRoute($router, 'update', $basePath, "/update");

        $this->addPostRoute($router, 'delete', $basePath, "/delete");
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        $this->addRoutePreload(
            $matcher,
            '(?:add|create|edit|update|delete)',
            function($params) {
                return array(
                    'params' => $params,
                );
            }
        );
    }

    /**
     * Only admins can use this
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Schoolbox\\Sync\\Rule\\Controller')
        );
    }
}