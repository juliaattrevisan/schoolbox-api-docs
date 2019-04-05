<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Authorizer as Auth;
use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Module for discussion comment requests.
 *
 * This is an abstract class, so a concrete implementation must be created for
 * each place that uses discussion comments.
 */
abstract class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $deleteAuthorizer = $this->buildDeleteCommentsAuthorizationRule();
        $getAuthorizer    = $this->buildGetCommentsAuthorizationRule();

        // Routes
        $this->register(
            'DELETE',
            '/{id:\d+}',
            'deleteComment',
            $deleteAuthorizer,
            [$this, 'preloadCommentAllowDeleted']
        );
        $this->register(
            'GET',
            '/{id:\d+}/comments',
            'getCommentsByParent',
            $getAuthorizer,
            [$this, 'preloadComment']
        );
    }


    /**
     * All discussion thread routes have a preloader for fetching the context.
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) {
                return [
                    'context' => $this->buildContext()->findById($params['contextId']),
                ];
            }
        );
    }


    /**
     * All discussion thread routes must ensure the context found in the
     * preloader is the same as the context of the requested object.
     *
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer): void
    {
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Auth\Rule\Callback(function (array $params) {
                if (!$this->buildContext()->isContextForThread($params['thread'], $params['context'])) {
                    throw new NotFound();
                }
            })
        );
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'mapper'       => $this->getMapperFactory()->getLazy(Mapper::class),
            'threadMapper' => $this->getMapperFactory()->getLazy(Thread\Mapper::class),
        ];
    }


    /**
     * Preload the requested comment.
     *
     * @param array $params
     * @return array
     * @throws MapperException         if the comment never existed
     * @throws MapperException\Deleted if the comment has been deleted
     */
    public function preloadComment(array $params): array
    {
        return [
            'comment' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
    }


    /**
     * Preload the requested comment. If the comment was deleted, it is still
     * preloaded.
     *
     * @param array $params
     * @return array
     * @throws MapperException if the comment never existed
     */
    public function preloadCommentAllowDeleted(array $params): array
    {
        try {
            return $this->preloadComment($params);
        } catch (MapperException\Deleted $e) {
            return ['comment' => $e->getDeletedObject(),];
        }
    }


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return Thread\Context\IContext
     */
    abstract protected function buildContext(): Thread\Context\IContext;


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule;


    /**
     * Builds an authorization rule for whether a user has permission to delete
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildDeleteCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule;

}
