<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment;

use Alaress\Authorizer as Auth;
use Schoolbox\Comms\News;
use Schoolbox\Config\ConfigMapper;
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
     * @return Thread\Context\IThreadLocator
     * @throws \InvalidArgumentException
     */
    protected function buildContext(): Thread\Context\IThreadLocator
    {
        /** @var News\Mapper   $newsMapper */
        $newsMapper   = $this->getMapperFactory()->get(News\Mapper::class);

        /** @var Thread\Mapper $threadMapper */
        $threadMapper = $this->getMapperFactory()->get(Thread\Mapper::class);

        return new News\Discussion\ThreadLocator($threadMapper, $newsMapper);
    }


    /**
     * Builds an authorization rule for whether a user has permission to read
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     * @throws \InvalidArgumentException
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsConfigOn(ConfigMapper::COMMS_COMMENTS_ENABLED, $this->getConfig()),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Comment\Authorizer\IsCommentAuthor(),
                new Auth\Rule\LazyCallback(function () {
                    /** @var Auth\Authorizer $authorizer */
                    $authorizer = $this->getInjector()->get('authorizer');
                    return new Thread\Authorizer\CanActUponContext(
                        new News\Discussion\Permission\CanGetComments($authorizer)
                    );
                })
            )
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to delete
     * comments.
     *
     * @return Auth\Rule\IAuthorizationRule
     * @throws \InvalidArgumentException
     */
    protected function buildDeleteCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsConfigOn(ConfigMapper::COMMS_COMMENTS_ENABLED, $this->getConfig()),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Comment\Authorizer\IsCommentAuthor(),
                new Auth\Rule\LazyCallback(function () {
                    /** @var Auth\Authorizer $authorizer */
                    $authorizer = $this->getInjector()->get('authorizer');
                    return new Thread\Authorizer\CanActUponContext(
                        new News\Discussion\Permission\CanModerateThread($authorizer)
                    );
                })
            )
        );
    }
}
