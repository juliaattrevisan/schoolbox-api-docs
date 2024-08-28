<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread\Context\IThreadLocator;

/**
 * Module for discussion thread requests.
 *
 * This is an abstract class, so a concrete implementation must be created for
 * each place that uses discussion threads.
 */
abstract class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $getAuthorizer      = $this->buildGetCommentsAuthorizationRule();
        $postAuthorizer     = $this->buildPostCommentsAuthorizationRule();
        $moderateAuthorizer = $this->buildModerateThreadAuthorizationRule();


        // Routes
        $this->register(
            'GET',
            '/{id:\d+}',
            'getThread',
            $getAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'GET',
            '/{id:\d+}/comments',
            'getCommentsByThread',
            $getAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'POST',
            '',
            'createThread',
            $this->buildCreateThreadAuthorizationRule()
        );
        $this->register(
            'POST',
            '/{id:\d+}/comments',
            'createCommentInThread',
            $postAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'PUT',
            '/{id:\d+}/open',
            'openThread',
            $moderateAuthorizer,
            [$this, 'preloadThread']
        );
        $this->register(
            'PUT',
            '/{id:\d+}/close',
            'closeThread',
            $moderateAuthorizer,
            [$this, 'preloadThread']
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
        // All routes except for create
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Auth\Rule\Callback(function (array $params): void {
                // Only if the thread exists
                $thread = $params['thread'] ?? null;
                if (!$thread) {
                    return;
                }
                if (!$this->buildContext()->isContextForThread($thread, $params['context'])) {
                    throw new NotFound();
                }
            })
        );
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Comment\Mapper::class),
            $this->getMapperFactory()->get(Mapper::class),
            $this->buildContext(),
            $this->buildModelDecoratorFactory(),
            $this->buildCommentModelDecoratorFactory(),
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Preload the requested thread.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function preloadThread(array $params): array
    {
        return [
            'thread' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
    }


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return IThreadLocator
     */
    abstract protected function buildContext(): IThreadLocator;


    /**
     * Builds a factory for building decorators around thread models.
     *
     * Required so that the controller may render links dependent on the module
     * that uses the variant.
     *
     * @return ModelDecorator\IFactory
     */
    abstract protected function buildModelDecoratorFactory(): ModelDecorator\IFactory;


    /**
     * Builds a factory for building decorators around thread comment models.
     *
     * Required so that the controller may render links dependent on the module
     * that uses the variant.
     *
     * @return Comment\ModelDecorator\IFactory
     */
    abstract protected function buildCommentModelDecoratorFactory(): Comment\ModelDecorator\IFactory;


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule;


    /**
     * Builds an authorization rule for whether a user has permission to post
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildPostCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule;


    /**
     * Builds an authorization rule for whether a user has permission to create
     * a thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildCreateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule;


    /**
     * Builds an authorization rule for whether a user has permission to
     * moderate this thread, including deleting comments that another user
     * has made.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    abstract protected function buildModerateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule;
}
