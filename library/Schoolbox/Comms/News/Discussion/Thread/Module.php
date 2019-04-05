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
     * @return Thread\Context\IContext
     */
    protected function buildContext(): Thread\Context\IContext
    {
        /** @var News\Mapper   $newsMapper */
        /** @var Thread\Mapper $threadMapper */
        $newsMapper   = $this->getMapperFactory()->get(News\Mapper::class);
        $threadMapper = $this->getMapperFactory()->get(Thread\Mapper::class);
        return new News\Discussion\Context($threadMapper, $newsMapper);
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
                return new Thread\Authorizer\CanActUponThread(
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
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanPostComment($authorizer)
                );
            })
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to create
     * a thread.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildCreateThreadAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        // TODO add a preloader that fetches the context, and authorize against that
        return new Auth\Rule\IsLoggedIn();
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
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanModerateThread($authorizer)
                );
            })
        );
    }
}
