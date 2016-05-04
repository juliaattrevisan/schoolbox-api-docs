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
                '/delete'                       => 'postDelete',
                '/modify'                       => 'postModify',
                '/comment/insert'               => 'postCommentInsert',
                '/comment/delete'               => 'postCommentDelete',
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

        // Permissions:
        // TODO add delete settings into route
        // * postDelete: has write access to homepage
        // * postComment: has read access to homepage
        $getFolderIdCallback = function ($params) {
            if ($params['folder'] instanceof Folder\Model) {
                return $params['folder']->getId();
            }
            return null;
        };
        $getBlogFolderIdCallback = function ($params) {
            if ($params['blog'] instanceof BlogModel) {
                return $params['blog']->getId();
            }
            return null;
        };
        $access = Access::getInstance();

        // getArchive: has read access to homepage
        $this->addRoutePermission(
            $authorizer,
            '(getArchiveCurrent|getArchive)',
            new Rule\HasHomepageAccess(UserAccess::ACCESS_READ, $getFolderIdCallback, $access)
        );

        // getInsert: has write access to homepage
        $this->addRoutePermission(
            $authorizer,
            'getInsert',
            new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, $getFolderIdCallback, $access)
        );

        // getItem: has read access to blog's homepage
        $this->addRoutePermission(
            $authorizer,
            'getItem',
            new Rule\HasHomepageAccess(UserAccess::ACCESS_READ, $getBlogFolderIdCallback, $access)
        );

        // getModify: has write access to blog's homepage, or created the post
        $this->addRoutePermission(
            $authorizer,
            'getModify',
            new Rule\Any(
                new Rule\HasHomepageAccess(UserAccess::ACCESS_WRITE, $getBlogFolderIdCallback, $access),
                new Rule\Callback(
                    function ($params, User $user) {
                        if ($params['blog'] instanceof BlogModel) {
                            if ($params['blog']->getOwner()->getId() == $user->getId()) {
                                return;
                            }
                        }
                        throw new Unauthorized();
                    }
                )
            )
        );
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
                    if (array_key_exists('folderId', $params)) {
                        $newParams['folder'] = Folder\Mapper::getInstance()->findById($params['folderId']);
                    }
                    if (array_key_exists('id', $params)) {
                        $newParams['blog'] = BlogMapper::getInstance()->findById($params['id']);
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
