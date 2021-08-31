<?php

/**
 * Module definition for e-portfolio blog actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Eportfolio;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Eportfolio\Page as EportfolioPageModel;
use Eportfolio\PageMapper as EportfolioPageMapper;
use Eportfolio_Authenticator;
use Schoolbox\Blog\Eportfolio\Comment\Mapper as EportfolioBlogCommentMapper;
use Schoolbox\Blog\Eportfolio\Comment\Model as EportfolioBlogCommentModel;
use Schoolbox\Blog\Eportfolio\Post\Controller as BlogController;
use Schoolbox\Blog\Eportfolio\Post\Mapper as EportfolioBlogMapper;
use Schoolbox\Blog\Eportfolio\Post\Model as EportfolioBlogModel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;

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
                '/modify/new/{pageId}'                       => 'getInsert',
                '/modify/{pageId}/{id}'                      => 'getModify',
                '/archive/{pageId}'                          => 'getArchiveCurrent',
                '/archive/{pageId}/{year}/{month}'           => 'getArchive',
                '/{pageId}/{id}'                             => 'getItem',
            ],
            'POST' => [
                '/delete/{id}'                               => 'postDelete',
                '/insert/{pageId}'                           => 'postInsert',
                '/modify/{id}'                               => 'postModify',
                '/comment/insert/{id}'                       => 'postCommentInsert',
                '/comment/delete/{commentId}'                => 'postCommentDelete',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // All routes: must be logged in
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());

        // getInsert: Eportfolio_Authenticator->canCreate on page
        $this->addRoutePermission(
            $authorizer,
            'getInsert',
            $this->buildAuthorizationRule('canCreate')
        );

        // getArchive: Eportfolio_Authenticator->canView on page
        // getItem: Eportfolio_Authenticator->canView on blog's page
        // postCommentInsert: Eportfolio_Authenticator->canView on blog's page
        $this->addRoutePermission(
            $authorizer,
            '(getArchive|getItem|postCommentInsert)',
            $this->buildAuthorizationRule('canView')
        );

        // getModify: Eportfolio_Authenticator->canModify on blog's page || isOwner of post
        // postDelete: Eportfolio_Authenticator->canModify on blog's page || isOwner of post
        // postInsert: Eportfolio_Authenticator->canModify on page
        // postModify: Eportfolio_Authenticator->canModify on blog's page || isOwner of post
        // postCommentDelete: Eportfolio_Authenticator->canModify on comment's blog's page || isOwner of comment
        $this->addRoutePermission(
            $authorizer,
            '(getModify|postDelete|postInsert|postModify|postCommentDelete)',
            $this->buildAuthorizationRule('canModify')
        );
    }

    /**
     * @param string $method `Eportfolio_Authenticator` method to call:
     *                       should be 'canCreate', 'canView', 'canModify'
     *
     * @return Rule\IAuthorizationRule
     */
    protected function buildAuthorizationRule($method)
    {
        $callback = function ($params, User $user) use ($method) {
            // Allow owner of either blog or comment to do stuff
            if ($params['comment'] instanceof EportfolioBlogCommentModel) {
                if ($params['comment']->getOwner()->getId() == $user->getId()) {
                    return;
                }
            }
            if ($params['blog'] instanceof EportfolioBlogModel) {
                if ($params['blog']->getOwner()->getId() == $user->getId()) {
                    return;
                }
            }
            if ($params['page'] instanceof EportfolioPageModel) {
                $auth = new Eportfolio_Authenticator(
                    $user,
                    $params['page']->getOwner(),
                    $params['page']->getPageId()
                );
                if (!method_exists($auth, $method)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Method "Eportfolio_Authenticator::%s" does not exist',
                            $method
                        )
                    );
                }
                if ($auth->$method()) {
                    return;
                }
            }
            throw new Unauthorized();
        };
        return new Rule\Any(new Rule\IsSuperuser(), new Rule\Callback($callback));
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
                    if (array_key_exists('commentId', $params)) {
                        /** @var EportfolioBlogCommentModel $comment */
                        $comment              = $this->getEportfolioBlogCommentMapper()->findById($params['commentId']);
                        $newParams['comment'] = $comment;
                        // Add blog in next step
                        $params['id']         = $comment->getBlog()->getId();
                    }
                    if (array_key_exists('id', $params)) {
                        /** @var EportfolioBlogModel $blog */
                        $blog              = $this->getEportfolioBlogMapper()->findById($params['id']);
                        $newParams['blog'] = $blog;
                        // Add page in next step
                        $params['pageId']  = $blog->getPage()->getPageId();
                    }
                    if (array_key_exists('pageId', $params)) {
                        $newParams['page'] = $this->getEportfolioPageMapper()->findById($params['pageId']);
                    }
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
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
        $injector->set('controller:' . $this->basename, $injector->lazyNew(BlogController::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return EportfolioBlogCommentMapper
     */
    protected function getEportfolioBlogCommentMapper()
    {
        return $this->getMapperFactory()->get(EportfolioBlogCommentMapper::class);
    }

    /**
     * @return EportfolioBlogMapper
     */
    protected function getEportfolioBlogMapper()
    {
        return $this->getMapperFactory()->get(EportfolioBlogMapper::class);
    }

    /**
     * @return EportfolioPageMapper
     */
    protected function getEportfolioPageMapper()
    {
        return $this->getMapperFactory()->get(EportfolioPageMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
