<?php
/**
 * Module definition for De La Selle's custom assessment csv export.
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\DLSC\AssessmentCSV;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Mapper\Exception as MapperException;

class Module extends BaseModule
{
    protected $basename = 'customDLSCAssessmentCSV';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        //exit($basePath);
        $this->addGetRoute($router, 'getIndex', $basePath, '/index');
    }

    /**
     * These pages may be accessed by:
     *
     * * superusers
     * * staff
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $permissionIndex = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF)
        );

        $this->addRoutePermission($authorizer, 'getIndex', $permissionIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {

    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

}
