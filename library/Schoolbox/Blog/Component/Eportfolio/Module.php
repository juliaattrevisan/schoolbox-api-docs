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
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Schoolbox\Blog\EportfolioBlog\Controller as BlogController;
use Schoolbox\Blog\EportfolioBlog\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\EportfolioComment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
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
                '/insert/{ownerId}/{pageId}'                 => 'postInsert',
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
        // All routes: must be logged in
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * Loads owner, page, blog and comment objects into requests which use them.
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                $newParams = [];
                try {
                    if (array_key_exists('ownerId', $params)) {
                        $newParams['owner'] = UserMapper::getInstance()->findById($params['ownerId']);
                    }
                    if (array_key_exists('pageId', $params)) {
                        $newParams['page'] = EportfolioPageMapper::getInstance()->findById($params['pageId']);
                    }
                    if (array_key_exists('id', $params)) {
                        $newParams['blog'] = EportfolioBlogMapper::getInstance()->findById($params['id']);
                    }
                    if (array_key_exists('commentId', $params)) {
                        $newParams['comment'] = EportfolioBlogCommentMapper::getInstance()->findById($params['commentId']);
                    }
                } catch (MapperException $e) {
                    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
                }
                return $newParams;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(BlogController::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
