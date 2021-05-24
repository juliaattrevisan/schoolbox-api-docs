<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment\Authorizer;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Discussion\Comment;
use Schoolbox\User;

/**
 * Authorizer for determining whether a user is the author of a comment.
 */
class IsCommentAuthor implements IAuthorizationRule
{
    /**
     * @inheritdoc
     *
     * @param array     $params
     * @param User|null $user
     * @return void
     */
    public function authorize(array $params = [], User $user = null): void
    {
        $comment = $params['comment'] ?? null;
        if (!$comment instanceof Comment\Model) {
            throw new Unauthorized('Could not find comment in "comment" param');
        }

        $author = $comment->getAuthor();
        if ($user && $author && $user->getId() === $author->getId()) {
            return;
        }

        throw new Unauthorized('Current user is not author of comment');
    }
}
