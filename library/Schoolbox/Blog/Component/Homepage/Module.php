<?php
/**
 * Module definition for normal (non-eportfolio) blog CRUD actions
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Blog\Component\Homepage;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Blog\Blog\Mapper as BlogMapper;
use Schoolbox\Blog\Blog\Model as BlogModel;
use Schoolbox\Blog\Blog\Controller as BlogController;
use Schoolbox\Blog\Comment\Model as BlogCommentModel;
use Schoolbox\Blog\Comment\Mapper as BlogCommentMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use \Schoolbox\User;
use Twig_Loader_Chain;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'blog';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
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

        // getArchive, postCommentInsert: has read access to homepage
        // getItem: has read access to blog's homepage
        $this->addRoutePermission(
            $authorizer,
            '(getArchiveCurrent|getArchive|postCommentInsert|getItem)',
            $this->getAuthenticationRule(UserAccess::ACCESS_READ)
        );

        // getInsert, postInsert, postDelete: has write access to homepage
        // postCommentDelete: has write access to homepage, or created the comment
        // getModify, postModify: has write access to blog's homepage, or created the post
        $this->addRoutePermission(
            $authorizer,
            '(getInsert|postInsert|postDelete|getModify|postModify|postCommentDelete)',
            $this->getAuthenticationRule(UserAccess::ACCESS_WRITE)
        );
    }

    /**
     * @param string $accessLevel UserAccess::ACCESS_* constant
     *
     * @return Rule\IAuthorizationRule
     */
    protected function getAuthenticationRule($accessLevel)
    {
        // Allow any user who owns either the blog or comment to do stuff
        $ownershipRule = new Rule\Callback(
            function ($params, User $user) {
                if ($params['blog'] instanceof BlogModel) {
                    if ($params['blog']->getOwner()->getId() == $user->getId()) {
                        return;
                    }
                }
                if ($params['comment'] instanceof BlogCommentModel) {
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
                if ($params['folder'] instanceof Folder\Model) {
                    return $params['folder']->getId();
                }
                return null;
            },
            Access::getInstance()
        );
        return new Rule\Any($ownershipRule, $permissionRule);
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
                        $comment              = BlogCommentMapper::getInstance()->findById($params['commentId']);
                        $newParams['comment'] = $comment;
                        // Load blog in next step
                        $params['id']         = $comment->getBlog()->getId();
                    }
                    if (array_key_exists('id', $params)) {
                        /** @var BlogModel $blog */
                        $blog               = BlogMapper::getInstance()->findById($params['id']);
                        $newParams['blog']  = $blog;
                        // Load folder in next step
                        $params['folderId'] = $blog->getFolder()->getId();
                    }
                    if (array_key_exists('folderId', $params)) {
                        $newParams['folder'] = Folder\Mapper::getInstance()->findById($params['folderId']);
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
