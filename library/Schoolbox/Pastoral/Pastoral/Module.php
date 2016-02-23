<?php
/**
 * Module definition for pastoral actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use \Schoolbox\User;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    protected $basename = 'pastoral';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getReportBuilder', $basePath, "/report-builder");
        $this->addGetRoute($router, 'getReport', $basePath, "/report");
        $this->addGetRoute($router, 'getIndex', $basePath, "/{id}");
        $this->addGetRoute($router, 'getInsert', $basePath, "/insert/{id}");
        $this->addGetRoute($router, 'getRecord', $basePath, "/{studentId}/{id}");
        $this->addGetRoute($router, 'getUpdate', $basePath, "/update/{studentId}/{id}");

        $this->addPostRoute($router, 'postSave', $basePath, "/save");
        $this->addPostRoute($router, 'postComment', $basePath, "/comment");
        $this->addPostRoute($router, 'postCommentDelete', $basePath, "/commentDelete");
        $this->addPostRoute($router, 'postDelete', $basePath, "/delete");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew('Schoolbox\\Pastoral\\Pastoral\\Controller'));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates');
    }
}
