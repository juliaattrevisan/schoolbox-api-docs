<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Comms\News;
use UnexpectedValueException;

/**
 * Common implementation for various Comms News Auth rules
 */
abstract class ArticleAttribute implements IAuthorizationRule
{

    /**
     * @param array  $params
     * @param string $key
     * @return News\Model
     * @throws UnexpectedValueException If no article found in params
     */
    protected function findArticleInParams(array $params, string $key = 'article'): News\Model
    {
        if (!\array_key_exists($key, $params)) {
            throw new UnexpectedValueException("No param found for key '{$key}'");
        }
        return $params[$key];
    }
}
