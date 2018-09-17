<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Comms\News;
use UnexpectedValueException;

abstract class ArticleAttribute implements IAuthorizationRule
{

    /**
     * @param array  $params
     * @param string $key
     * @return News\Model
     * @throws UnexpectedValueException
     */
    protected function findArticleInParams(array $params, string $key = 'articleId'): News\Model
    {
        if (!\array_key_exists($key, $params)) {
            throw new UnexpectedValueException("No param found for key '{$key}'");
        }
        return $params[$key];
    }
}
