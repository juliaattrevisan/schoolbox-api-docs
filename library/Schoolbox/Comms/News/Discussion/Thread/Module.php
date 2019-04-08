<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread;

use Alaress\Authorizer as Auth;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread;

/**
 * Comms-specific module for discussion threads.
 */
class Module extends Thread\Module
{
    /**
     * @var string
     */
    protected $basename = 'commsDiscussionThreads';


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return Thread\Context\IContextLocator
     */
    protected function buildContext(): Thread\Context\IContextLocator
    {
        /** @var News\Mapper   $newsMapper */
        /** @var Thread\Mapper $threadMapper */
        $newsMapper   = $this->getMapperFactory()->get(News\Mapper::class);
        $threadMapper = $this->getMapperFactory()->get(Thread\Mapper::class);
        return new News\Discussion\ContextLocator($threadMapper, $newsMapper);
    }


    /**
     * Builds a factory for building wrappers around thread objects.
     *
     * @return Thread\ModelWrapper\IFactory
     */
    protected function buildModelWrapperFactory(): Thread\ModelWrapper\IFactory
    {
        return new ModelWrapper\Factory();
    }


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () {
                /** @var Auth\Authorizer $authorizer */
                $authorizer = $this->getInjector()->get('authorizer');
                return new Thread\Authorizer\CanActUponContext(
                    new News\Discussion\Permission\CanGetComments($authorizer)
                );
            })
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to post
     * comments in this thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildPostCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () {
                /** @var Auth\Authorizer $authorizer */
                $authorizer = $this->getInjector()->get('authorizer');
                return new Thread\Authorizer\CanActUponContext(
                    new News\Discussion\Permission\CanPostComment($authorizer)
                );
            })
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to create
     * a thread.
     *
     * (In this case, it's the same permission as the moderate thread rule: it
     *  likely will be in all cases, but I don't know that for sure.)
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildCreateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return $this->buildModerateThreadAuthorizationRule();
    }


    /**
     * Builds an authorization rule for whether a user has permission to
     * moderate this thread, including deleting comments that another user
     * has made.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildModerateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () {
                /** @var Auth\Authorizer $authorizer */
                $authorizer = $this->getInjector()->get('authorizer');
                return new Thread\Authorizer\CanActUponContext(
                    new News\Discussion\Permission\CanModerateThread($authorizer)
                );
            })
        );
    }
}
