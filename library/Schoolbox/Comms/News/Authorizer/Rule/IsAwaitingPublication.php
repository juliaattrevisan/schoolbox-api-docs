<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use UnexpectedValueException;

class IsAwaitingPublication extends ArticleAttribute
{
    /**
     * @param array     $params
     * @param User|null $user
     * @throws Unauthorized
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], ?User $user = null): void
    {
        $article = $this->findArticleInParams($params);

        if (!$article->isAwaitingPublication()) {
            throw new Unauthorized("Article {$article->getId()} is not awaiting publication");
        }
    }
}
