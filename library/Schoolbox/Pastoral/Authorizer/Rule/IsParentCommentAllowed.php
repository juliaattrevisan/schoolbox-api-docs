<?php

/**
 * Determine if parent comment is allowed for the record
 *
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

class IsParentCommentAllowed implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!isset($params['record'])) {
            if (!isset($params['recordId'])) {
                throw new Unauthorized();
            } else {
                try {
                    $params['record'] = PastoralMapper::getInstance()->findById($params['recordId']);
                } catch (MapperException $e) {
                    throw new Unauthorized();
                }
            }
        }

        if (!$params['record'] instanceof Pastoral) {
            throw new Unauthorized();
        }

        /** @var Pastoral $record */
        $record = $params['record'];

        if (!$record->getParentCommentsAllowed()) {
            throw new Unauthorized();
        }
        return true;
    }
}
