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
    /**
     * @var string
     */
    protected $basename = 'pastoral';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/report-builder'       => 'getReportBuilder',
                '/report'               => 'getReport',
                '/report-print'         => 'getReportPrint',
                '/categories/{typeId}'  => 'getCategories',
                '/insert/{id}'          => 'getInsert',
                '/update/{id}'          => 'getUpdate',
                '/{studentId}/{id}'     => 'getRecord',
                '/{id}'                 => 'getIndex',
            ],
            'POST' => [
                '/save'                 => 'postSave',
                '/comment'              => 'postComment',
                '/comment-delete'       => 'postCommentDelete',
                '/delete'               => 'postDelete',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias)
            $this->addGetRoute($router, $alias, $basePath, $path);

        foreach ($routes['POST'] as $path => $alias)
            $this->addPostRoute($router, $alias, $basePath, $path);
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
