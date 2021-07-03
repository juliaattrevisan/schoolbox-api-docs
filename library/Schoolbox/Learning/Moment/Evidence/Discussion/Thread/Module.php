<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Evidence-specific module for discussion threads.
 */
class Module extends Thread\Module
{
    /**
     * @var string
     */
    protected $basename = 'evidenceDiscussionThreads';


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
                try {
                    $evidence = $this->buildContext()->findById($params['contextId']);
                } catch (MapperException $e) {
                    throw new NotFound("No evidence found for 'contextId' {$params['contextId']}", 0, $e);
                }

                return [
                    'context' => $evidence,
                ];
            }
        );
    }


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return Thread\Context\IThreadLocator
     */
    protected function buildContext(): Thread\Context\IThreadLocator
    {
        /** @var Evidence\Mapper $evidenceMapper */
        $evidenceMapper = $this->getMapperFactory()->get(Evidence\Mapper::class);

        /** @var Thread\Mapper $threadMapper */
        $threadMapper = $this->getMapperFactory()->get(Thread\Mapper::class);

        return new Evidence\Discussion\ThreadLocator($threadMapper, $evidenceMapper);
    }


    /**
     * Builds a factory for building decorators around thread objects.
     *
     * TODO examine if this is even needed?
     *
     * @return Thread\ModelDecorator\IFactory
     */
    protected function buildModelDecoratorFactory(): Thread\ModelDecorator\IFactory
    {
        return new ModelDecorator\Factory();
    }


    /**
     * Builds a factory for building decorators around comment objects.
     *
     * @return Comment\ModelDecorator\IFactory
     */
    protected function buildCommentModelDecoratorFactory(): Comment\ModelDecorator\IFactory
    {
        return new Evidence\Discussion\Comment\ModelDecorator\Factory();
    }


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments in this thread.
     *
     * Users may read comments if any of the following are true:
     * * they are a superuser
     * * they are a staff member
     * * they are the recipient of the evidence
     * * they are the parent of the recipient of the evidence
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        /** @var UserMapper $userMapper */
        return new Auth\Rule\LazyCallback(function () {
            $userMapper = $this->getMapperFactory()->get(UserMapper::class);
            return new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Auth\Rule\HasRole(Role::STAFF),
                new Auth\Rule\IsUserOrParentOfUser(
                    $this->getExternalUser(),
                    $userMapper,
                    static function (array $params) {
                        $evidence = $params['context'];
                        return $evidence->getRecipient()->getId();
                    },
                ),
            );
        });
    }


    /**
     * Builds an authorization rule for whether a user has permission to post
     * comments in this thread.
     *
     * The rules for writing comments are the same as for reading them:
     * @see buildGetCommentsAuthorizationRule
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildPostCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return $this->buildGetCommentsAuthorizationRule();
    }


    /**
     * Builds an authorization rule for whether a user has permission to create
     * a thread.
     *
     * The rules for creating a thread are the same as for reading comments:
     * @see buildGetCommentsAuthorizationRule
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildCreateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return $this->buildGetCommentsAuthorizationRule();
    }


    /**
     * Builds an authorization rule for whether a user has permission to
     * moderate this thread, including deleting comments that another user
     * has made.
     *
     * The rules for moderating a thread are the same as for reading comments:
     * @see buildGetCommentsAuthorizationRule
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildModerateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return $this->buildGetCommentsAuthorizationRule();
    }
}
