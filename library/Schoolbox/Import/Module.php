<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Redis;
use Alaress\Web\Router\PathMatcher;
use Aura\Di;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage;
use Twig_Loader_Chain;

/**
 * Module for admin importer.
 *
 * @package Schoolbox\Import
 */
class Module extends BaseModule
{
    protected $basename = 'adminImport';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'intro', $basePath, "");

        // User paths
        $this->addGetRoute($router, 'user', $basePath, "/user");
        $this->addPostRoute($router, 'userPreview', $basePath, "/user/preview");
        $this->addPostRoute($router, 'userImport', $basePath, "/user/import");
        // For now, download templates directly
//        $this->addGetRoute($router, 'userTemplate', $basePath, "/userTemplate.csv");

        // Structure paths
        $this->addGetRoute($router, 'timetableStructure', $basePath, "/timetable-structure");
        $this->addPostRoute($router, 'timetableStructureImport', $basePath, "/timetable-structure/import");

        // Timetable paths
        $this->addGetRoute($router, 'timetable', $basePath, "/timetable");
        $this->addPostRoute($router, 'timetableImport', $basePath, "/timetable/import");

        // Enrolment paths
        $this->addGetRoute($router, 'enrolment', $basePath, "/enrolment");
        $this->addPostRoute($router, 'enrolmentImport', $basePath, "/enrolment/import");

        // Generic log path
        $this->addGetRoute($router, 'log', $basePath, "/user/log/{hash}");
    }

    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            'log',
            function ($params) {
                // TODO inject the storage mapper
                $params['log'] = Storage\Mapper::getInstance()->findByHash($params['hash']);
                return $params;
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
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }
}
