<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Discussion\Comment;

use Alaress\Authorizer as Auth;
use InvalidArgumentException;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\Learning\Moment\Evidence;
use Schoolbox\Role;
use Schoolbox\UserMapper;

/**
 * Evidence-specific module for discussion thread comments.
 */
class Module extends Comment\Module
{

    /**
     * @var string
     */
    protected $basename = 'evidenceDiscussionComments';


    /**
     * Builds a context object for retrieving and persisting objects that are
     * related to a discussion thread.
     *
     * @return Thread\Context\IThreadLocator
     * @throws InvalidArgumentException
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
     * Builds an authorization rule for whether a user has permission to read
     * comments.
     *
     * The rules for reading comments are the same as for reading threads:
     * * they are a superuser
     * * they are a staff member
     * * they are the recipient of the evidence
     * * they are the parent of the recipient of the evidence
     *
     * @return Auth\Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    protected function buildGetCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        /** @var UserMapper $userMapper */
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
    }


    /**
     * Builds an authorization rule for whether a user has permission to delete
     * comments.
     *
     * Any user who may read comments may delete comments, except for the
     * recipient's parents.
     *
     * @return Auth\Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    protected function buildDeleteCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\HasRole(Role::STAFF),
            new Auth\Rule\IsUser(
                static function (array $params) {
                    $evidence = $params['context'];
                    return $evidence->getRecipient()->getId();
                },
            ),
        );
    }
}
