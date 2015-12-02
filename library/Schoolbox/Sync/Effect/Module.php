<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception;

class Module extends BaseModule
{
    protected $basename = 'syncEffect';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        $this->addPostRoute($router, 'add', $basePath, "/add/{ruleId}");

        /* Special case
         * Has no inputs or anything so we can lighten the backend load
         */
        $this->addPostRoute($router, 'addSkip', $basePath, "/add/{ruleId}/skip");

        $this->addPostRoute($router, 'delete', $basePath, "/delete");
        $this->addGetRoute($router, 'change', $basePath, "/change");

        /* Spawn a new Mapped Key/Value fragment
         */
        $this->addGetRoute($router, 'newMappedRow', $basePath, "/newMappedRow");
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        $this->addRoutePreload(
            $matcher,
            '(?:add|addSkip|delete|change)',
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
            $injector->lazyNew('Schoolbox\\Sync\\Effect\\Controller')
        );
    }
}