<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

/**
 * Authorization rule for determining if a user may comment on a pastoral
 * record.
 *
 * Staff may always comment: students or parents may only comment if the
 * record allows comment from that role type.
 *
 * This rule presumes that the user has permission to view the record:
 * @see \Schoolbox\Pastoral\Authorizer\Rule\IsInAudience
 */
class IsCommentAllowed implements IAuthorizationRule
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

        if ($user->getRole()->isStaff()) {
            return true;
        }
        if ($user->getRole()->isStudent() && $record->getStudentCommentsAllowed()) {
            return true;
        }
        if ($user->getRole()->isParent() && $record->getParentCommentsAllowed()) {
            return true;
        }
        throw new Unauthorized();
    }
}
