<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Authorizer as Auth;
use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Thread;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception;

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
        // Currently this route applies to all endpoints.
        // Eventually it will need to apply only to ones that contain a comment.
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Auth\Rule\Callback(function (array $params) {
                $comment = $params['comment'];
                if (
                    $comment instanceof Model
                    && $this->buildContext()->isContextForThread($comment->getThread(), $params['context'])
                ) {
                    return;
                }
                throw new NotFound();
            })
        );
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Thread\Mapper::class),
        );
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
        try {
            return [
                'comment' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
            ];
        } catch (Exception $e) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * @return ErrorMapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getErrorMapper(): ErrorMapper
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
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
     * @return Thread\Context\IThreadLocator
     */
    abstract protected function buildContext(): Thread\Context\IThreadLocator;


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
