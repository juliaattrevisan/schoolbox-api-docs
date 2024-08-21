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
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
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
    public function initRoutes(string $basePath)
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
            $this->addGetRoute($alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($alias, $basePath, $path);
        }
    }

    /**
     * Historical context
     * ------------------
     * The way the Eportfolio_Authenticator and Eportfolio/Module files work together to determine permissions is a
     * little odd.  This permissions system has evolved as it has is because different parts of eportfolio have been
     * developed using different paradigms (most of it is Sitepage, whilst the blogs are Aura).  The Eportfolio_Authenticator
     * already existed when the blogs were added using the Aura paradigm, so to avoid duplicating permissions code, as
     * much as possible we want to defer permissions handling logic to the Eportfolio_Authenticator class.
     *    In an ideal world the eportfolio module would be written completely in Aura, and thus all permissions would be
     * handled within the module.  There might still be a class similar to Eportfolio_Authenticator for determining
     * base-level permissions to eportfolio content, but this would only be used within the module permission functions.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer): void
    {
        // All routes: must be logged in
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());

        // Affected routes are listed here with the expected parameters (from Blog\Eportfolio\Post\Controller).
        //  * Route: getInsert(EportfolioPageModel $page):
        //    Rule : Eportfolio_Authenticator->canCreate on page
        $this->addRoutePermission(
            $authorizer,
            'getInsert',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\Callback(
                    function ($params, User $loggedInUser): void {
                        // Use Eportfolio_Authenticator->canCreate() to determine permissions.
                        $auth = new Eportfolio_Authenticator(
                            $loggedInUser,
                            $params['page']->getOwner(),
                            $params['page']->getPageId()
                        );

                        if (!$auth->canCreate()) {
                            throw new Unauthorized();
                        }
                    }
                )
            )
        );

        // Affected routes are listed here with the expected parameters (from Blog\Eportfolio\Post\Controller).
        //  * Route: getArchive(EportfolioPageModel $page, $year, $month)
        //    Rule : Eportfolio_Authenticator->canView on page
        //  * Route: getItem(EportfolioPageModel $page, EportfolioBlogModel $blog)
        //    Rule : isOwner of post OR Eportfolio_Authenticator->canView on blog's page
        //  * Route: postCommentInsert(EportfolioBlogModel $blog)
        //    Rule : isOwner of post OR Eportfolio_Authenticator->canView on blog's page
        $this->addRoutePermission(
            $authorizer,
            '(getArchive|getItem|postCommentInsert)',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\Callback(
                    function ($params, User $loggedInUser): void {
                        // If a blog parameter exists, permissions depend on the blog's owner.
                        if (($params['blog'] ?? null) instanceof EportfolioBlogModel) {
                            if ($params['blog']->getOwner()->getId() === $loggedInUser->getId()) {
                                return;
                            }
                            // Ensure $params['page'] exists, so we can fall back to the 'page' case below.
                            if (!array_key_exists('page', $params)) {
                                $params['page'] = $params['blog']->getPage();
                            }
                        }
                        // If a page parameter exists, use Eportfolio_Authenticator->canView() to determine permissions.
                        if (($params['page'] ?? null) instanceof EportfolioPageModel) {
                            $auth = new Eportfolio_Authenticator(
                                $loggedInUser,
                                $params['page']->getOwner(),
                                $params['page']->getPageId()
                            );
                            if ($auth->canView()) {
                                return;
                            }
                        }
                        // Otherwise, not authorised.
                        throw new Unauthorized();
                    }
                )
            )
        );

        // Affected routes are listed here with the expected parameters (from Blog\Eportfolio\Post\Controller).
        //  * Route: getModify(EportfolioPageModel $page, EportfolioBlogModel $blog)
        //    Rule : isOwner of post OR Eportfolio_Authenticator->canModify on blog's page
        //  * Route: postDelete(EportfolioBlogModel $blog)
        //    Rule : isOwner of post OR Eportfolio_Authenticator->canModify on blog's page
        //  * Route: postInsert(EportfolioPageModel $page)
        //    Rule : Eportfolio_Authenticator->canModify on page
        //  * Route: postModify(EportfolioPageModel $page, EportfolioBlogModel $blog)
        //    Rule : isOwner of post OR Eportfolio_Authenticator->canModify on blog's page
        $this->addRoutePermission(
            $authorizer,
            '(getModify|postDelete|postInsert|postModify)',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\Callback(
                    function ($params, User $loggedInUser): void {
                        // If a blog parameter exists, permissions depend on the blog's owner.
                        if (($params['blog'] ?? null) instanceof EportfolioBlogModel) {
                            if ($params['blog']->getOwner()->getId() === $loggedInUser->getId()) {
                                return;
                            }
                            // Ensure $params['page'] exists, so we can fall back to the 'page' case below.
                            if (!array_key_exists('page', $params)) {
                                $params['page'] = $params['blog']->getPage();
                            }
                        }
                        // If a page parameter exists, use Eportfolio_Authenticator->canModify() to determine permissions.
                        if (($params['page'] ?? null) instanceof EportfolioPageModel) {
                            $auth = new Eportfolio_Authenticator(
                                $loggedInUser,
                                $params['page']->getOwner(),
                                $params['page']->getPageId()
                            );
                            if ($auth->canModify()) {
                                return;
                            }
                        }
                        // Otherwise, not authorised.
                        throw new Unauthorized();
                    }
                )
            )
        );

        // Affected routes are listed here with the expected parameters (from Blog\Eportfolio\Post\Controller).
        //  * Route: postCommentDelete(EportfolioBlogCommentModel $comment)
        //    Rule : if comment owner is staff
        //            * isStaff AND Eportfolio_Authenticator->canModify on comment's blog's page
        //           else
        //            * isOwner of comment OR Eportfolio_Authenticator->canModify on comment's blog's page
        $this->addRoutePermission(
            $authorizer,
            'postCommentDelete',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\Callback(
                    function ($params, User $loggedInUser): void {
                        if (($params['comment'] ?? null) instanceof EportfolioBlogCommentModel) {
                            $commentOwner = $params['comment']->getOwner();

                            if ($commentOwner->getRole()->isStaff()) {
                                if (!$loggedInUser->getRole()->isStaff()) {
                                    throw new Unauthorized();
                                }
                            } else {
                                if ($loggedInUser->getId() === $commentOwner->getId()) {
                                    return;
                                }
                            }

                            $page = $params['comment']->getBlog()->getPage();
                            $auth = new Eportfolio_Authenticator($loggedInUser, $page->getOwner(), $page->getPageId());

                            if ($auth->canModify()) {
                                return;
                            }
                        }

                        // Otherwise, not authorised.
                        throw new Unauthorized();
                    }
                )
            )
        );
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new BlogController();
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
