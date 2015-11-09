<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Role;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    protected $basename = 'submission';

    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'viewOverview', $basePath, "/overview/{box_id}");
        $this->addGetRoute($router, 'downloadOverview', $basePath, "/overview/{box_id}/csv");
    }


    /**
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermissionForBasename($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermissionForBasename(
                $authorizer,
                '.+(?:Overview)',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the item to params, if box_id is in the route.
        // TODO DI these
        $boxMapper = SubmissionBoxMapper::getInstance();
        $catMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
        $this->addRoutePreloadForBasename(
            $matcher,
            '.*',
            function ($params) use ($boxMapper, $catMapper) {
                if (array_key_exists('box_id', $params)) {

                    $box = $boxMapper->findById($params['box_id']);

                    // yeah... sure... doing this manually isn't outrageous at all...
                    $box->setCategory($catMapper->findById($box->getCategory()->getId()));

                    return array(
                        'box' => $box,
                    );
                }
                return array();
            }
        );
    }


    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        // TODO inherit from an Admin baseclass to setup subnav
        $loader = new Twig_Loader_Filesystem([]);
        $loader->addPath(__DIR__ . '/templates', 'submission');
        $templateLoader->addLoader($loader);
    }


    /**
     * @param ContainerInterface $injector
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:submission',
            $injector->lazyNew(Controller::class)
        );
    }
} 
