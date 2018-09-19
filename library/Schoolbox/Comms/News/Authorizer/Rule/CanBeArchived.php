<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;
use UnexpectedValueException;

class CanBeArchived extends ArticleAttribute
{
    /**
     * @inheritdoc
     * @throws UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        $article = $this->findArticleInParams($params);
        if (!$article->canBeArchived()) {
            throw new Unauthorized("Cannot submit article. Current status: {$article->getStatus()}");
        }
    }
}
