<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

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
    protected $basename = 'upsRuleset';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
        $this->addGetRoute($router, 'intro', $basePath, "");
        $this->addGetRoute($router, 'read', $basePath, "/{rulesetId}");
        $this->addPostRoute($router, 'update', $basePath, "/ruleset/update");
        $this->addGetRoute($router, 'dryRunStart', $basePath, "/{rulesetId}/dry-run-start");
        $this->addGetRoute($router, 'dryRun', $basePath, "/{rulesetId}/dry-run");
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher) {
        $this->addRoutePreload(
            $matcher,
            '(?:read|dryRun|dryRunStart)',
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
        $this->addTemplatePath($templateLoader, __DIR__ . '/../Navigator/templates');
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
            $injector->lazyNew('Schoolbox\\Sync\\Ruleset\\Controller')
        );
    }
}