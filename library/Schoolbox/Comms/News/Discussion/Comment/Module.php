<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment;

use Alaress\Authorizer as Auth;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;

/**
 * Comms-specific module for discussion thread comments.
 */
class Module extends Comment\Module
{
    /**
     * @var string
     */
    protected $basename = 'commsDiscussionComments';


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
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Comment\Authorizer\IsCommentAuthor(),
            new Auth\Rule\LazyCallback(function () {
                /** @var Auth\Authorizer $authorizer */
                $authorizer = $this->getInjector()->get('authorizer');
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanGetComments($authorizer, $this->buildContext())
                );
            })
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to delete
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     */
    protected function buildDeleteCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Comment\Authorizer\IsCommentAuthor(),
            new Auth\Rule\LazyCallback(function () {
                /** @var Auth\Authorizer $authorizer */
                $authorizer = $this->getInjector()->get('authorizer');
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanModerateThread($authorizer, $this->buildContext())
                );
            })
        );
    }

}
