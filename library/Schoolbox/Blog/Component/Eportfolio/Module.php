<?php
/**
 * Module definition for e-portfolio blog actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Component\Eportfolio;

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
    protected $basename = 'eportfolioBlog';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/modify/new/{ownerId}/{pageId}'             => 'getInsert',
                '/modify/{ownerId}/{pageId}/{id}'            => 'getModify',
                '/archive/{ownerId}/{pageId}'                => 'getArchiveCurrent',
                '/archive/{ownerId}/{pageId}/{year}/{month}' => 'getArchive',
                '/{ownerId}/{pageId}/{id}'                   => 'getItem',
            ],
            'POST' => [
                '/delete/{id}'                               => 'postDelete',
                '/modify'                                    => 'postInsert',
                '/modify/{id}'                               => 'postModify',
                '/comment/insert/{id}'                       => 'postCommentInsert',
                '/comment/delete/{commentId}'                => 'postCommentDelete',
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
        $injector->set('controller:'.$this->basename, $injector->lazyNew('Schoolbox\\Blog\\EportfolioBlog\\Controller'));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
