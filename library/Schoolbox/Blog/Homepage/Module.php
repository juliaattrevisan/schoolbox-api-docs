<?php

/**
 * Module definition for normal (non-eportfolio) blog CRUD actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Schoolbox\Blog\Homepage\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Blog\Homepage\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Homepage\Post\Controller as BlogController;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as BlogModel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'blog';


    /**
     * {@inheritdoc}
     */
    public function initRoutes(string $basePath)
    {
        $routes = [
            'GET' => [
                '/modify/new/{folderId}'             => 'getInsert',
                '/modify/{id}'                       => 'getModify',
                '/{id}'                              => 'getItem',
                '/archive/{folderId}'                => 'getArchiveCurrent',
                '/archive/{folderId}/{year}/{month}' => 'getArchive',
            ],
            'POST' => [
                '/delete/{id}'                       => 'postDelete',
                '/insert/{folderId}'                 => 'postInsert',
                '/modify/{id}'                       => 'postModify',
                '/comment/insert/{id}'               => 'postCommentInsert',
                '/comment/delete/{commentId}'        => 'postCommentDelete',
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
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // All routes: must be logged in
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());

        // getArchive, postCommentInsert: has read access to homepage
        // getItem: has read access to blog's homepage
        $this->addRoutePermission(
            $authorizer,
            '(getArchiveCurrent|getArchive|postCommentInsert|getItem)',
            $this->buildAuthorizationRule(UserAccess::ACCESS_READ)
        );

        // getInsert, postInsert, postDelete: has write access to homepage
        // postCommentDelete: has write access to homepage, or created the comment
        // getModify, postModify: has write access to blog's homepage, or created the post
        $this->addRoutePermission(
            $authorizer,
            '(getInsert|postInsert|postDelete|getModify|postModify|postCommentDelete)',
            $this->buildAuthorizationRule(UserAccess::ACCESS_WRITE)
        );
    }

    /**
     * @param string $accessLevel UserAccess::ACCESS_* constant
     *
     * @return Rule\IAuthorizationRule
     */
    protected function buildAuthorizationRule($accessLevel)
    {
        // Allow any user who owns either the blog or comment to do stuff
        $ownershipRule = new Rule\Callback(
            function ($params, User $user): void {
                if (($params['blog'] ?? null) instanceof BlogModel) {
                    if ($params['blog']->getOwner()->getId() == $user->getId()) {
                        return;
                    }
                }
                if (($params['comment'] ?? null) instanceof BlogCommentModel) {
                    if ($params['comment']->getOwner()->getId() == $user->getId()) {
                        return;
                    }
                }
                throw new Unauthorized();
            }
        );
        // Allow anyone who has $accessLevel on this folder to do stuff
        $permissionRule = new Rule\HasHomepageAccess(
            $accessLevel,
            function ($params) {
                if (($params['folder'] ?? null) instanceof Folder) {
                    return $params['folder']->getId();
                }
                if (($params['blog'] ?? null) instanceof BlogModel) {
                    return $params['blog']->getFolder()->getId();
                }
                return null;
            },
            Access::getInstance()
        );
        return new Rule\Any(new Rule\IsSuperuser(), $ownershipRule, $permissionRule);
    }

    /**
     * Loads folder and blog object into requests which use them.
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
                        /** @var BlogCommentModel $comment */
                        $comment              = $this->getBlogCommentMapper()->findById($params['commentId']);
                        $newParams['comment'] = $comment;
                        // Load blog in next step
                        $params['id']         = $comment->getBlog()->getId();
                    }
                    if (array_key_exists('id', $params)) {
                        /** @var BlogModel $blog */
                        $blog               = $this->getBlogMapper()->findById($params['id']);
                        $newParams['blog']  = $blog;
                        // Load folder in next step
                        $params['folderId'] = $blog->getFolder()->getId();
                    }
                    if (array_key_exists('folderId', $params)) {
                        $newParams['folder'] = $this->getFolderMapper()->findById($params['folderId']);
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
     * @return BlogCommentMapper
     */
    protected function getBlogCommentMapper()
    {
        return $this->getMapperFactory()->get(BlogCommentMapper::class);
    }

    /**
     * @return BlogMapper
     */
    protected function getBlogMapper()
    {
        return $this->getMapperFactory()->get(BlogMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
