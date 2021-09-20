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
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
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
        return new Auth\Rule\LazyCallback(
            function () {
                /** @var UserMapper $userMapper */
                $userMapper = $this->getMapperFactory()->get(UserMapper::class);
                return new Auth\Rule\All(
                    new Auth\Rule\IsLoggedIn(),
                    new Auth\Rule\Any(
                        new Auth\Rule\IsSuperuser(),
                        new Auth\Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                        new Auth\Rule\IsUserOrParentOfUser(
                            $this->getExternalUser(),
                            $userMapper,
                            static function (array $params) {
                                /** @var Evidence\Model $evidence */
                                $evidence = $params['context'];
                                return $evidence->getRecipient()->getId();
                            },
                        ),
                    )
                );
            }
        );
    }


    /**
     * Builds an authorization rule for whether a user has permission to delete
     * comments.
     *
     * The rules for deleting comments are:
     * * Staff/superuser may delete any comment
     * * Any user may delete comments which they created
     *
     * @return Auth\Rule\IAuthorizationRule
     * @throws InvalidArgumentException
     */
    protected function buildDeleteCommentsAuthorizationRule(): Auth\Rule\IAuthorizationRule
    {
        return new Auth\Rule\All(
            new Auth\Rule\IsLoggedIn(),
            new Auth\Rule\Any(
                new Auth\Rule\IsSuperuser(),
                new Auth\Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                new Auth\Rule\IsUser(
                    static function (array $params) {
                        /** @var Comment\Model $comment */
                        $comment = $params['comment'];
                        $author  = $comment->getAuthor();

                        // The comment's author should never be null, as a comment
                        // cannot be created with a null author.
                        if ($author) {
                            return $author->getId();
                        }
                        throw new InsufficientHydrationException('Comment author is not hydrated');
                    },
                ),
            ),
        );
    }
}
