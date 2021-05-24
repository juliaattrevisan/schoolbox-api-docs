<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Determines whether an article is visible to the public.
 * Must be:
 * * sharable, and
 * * either published or expired
 */
class IsShareable extends ArticleAttribute
{

    /**
     * @param array     $params
     * @param User|null $user
     * @throws InvalidArgumentException
     * @throws Unauthorized
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new InvalidArgumentException('No session user available to test permissions');
        }

        $article = $this->findArticleInParams($params);

        if (!$article->isShareable()) {
            throw new Unauthorized("Article {$article->getId()} is not shareable");
        }
    }
}
