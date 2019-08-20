<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for admin importer introduction.
 *
 * @package Schoolbox\Import
 */
class IntroModule extends BaseModule
{
    protected $basename = 'adminImport';

    /**
     * Sets up the submodules for each import type.
     *
     * @param ContainerInterface $injector
     * @param string             $basePath
     */
    public function initSubmodules(ContainerInterface $injector, $basePath)
    {
        $userModule      = new ImportModule($injector, $this->basename.'User');
        $profileModule   = new ImportModule($injector, $this->basename.'Profile');
        $structureModule = new ImportModule($injector, $this->basename.'Structure');
        $timetableModule = new ImportModule($injector, $this->basename.'Timetable');
        $enrolmentModule = new ImportModule($injector, $this->basename.'Enrolment');

        $userModule->init($basePath.'/user');
        $profileModule->init($basePath.'/profile');
        $structureModule->init($basePath.'/timetable-structure');
        $timetableModule->init($basePath.'/timetable');
        $enrolmentModule->init($basePath.'/enrolment');
    }

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'intro', $basePath, "");
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
    public function initTemplatePaths(IChainLoader $templateLoader)
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
        $injector->set('controller:'.$this->basename, $injector->lazyNew(IntroController::class));
    }
}
