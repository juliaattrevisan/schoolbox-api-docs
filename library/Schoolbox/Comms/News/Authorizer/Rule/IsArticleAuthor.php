<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Checks whether a user is the author of an article
 */
class IsArticleAuthor extends ArticleAttribute
{

    /**
     * Checks whether a user is the author of the article in $params
     *
     * @param array     $params
     * @param User|null $user
     * @throws Unauthorized
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        $article = $this->findArticleInParams($params);

        if (!$user) {
            throw new UnexpectedValueException('$user cannot be null');
        }
        if (!$article->getAuthor()) {
            throw new UnexpectedValueException('Article author must be hydrated');
        }
        if ($user->getId() !== $article->getAuthor()->getId()) {
            throw new Unauthorized("User {$user->getId()} is not this article author {$article->getAuthor()->getId()}");
        }
    }
}
