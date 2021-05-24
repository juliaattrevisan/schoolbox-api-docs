<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;

class IsNotYetApproved extends ArticleAttribute
{

    /**
     * @param array     $params
     * @param User|null $user
     * @throws Unauthorized
     * @throws \UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        $article = $this->findArticleInParams($params);

        if ($article->isApproved()) {
            throw new Unauthorized("Article {$article->getId()} is already accepted");
        }
    }
}
