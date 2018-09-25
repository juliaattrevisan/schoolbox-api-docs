<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
