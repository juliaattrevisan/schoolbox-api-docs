<?php

/**
 * Determine if a pastoral comment is owned by the logged in user
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\User;

class IsCommentOwner implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!isset($params['comment'])) {
            if (!isset($params['commentId'])) {
                throw new Unauthorized();
            } else {
                try {
                    $params['comment'] = PastoralCommentMapper::getInstance()->findById($params['commentId']);
                } catch (MapperException $e) {
                    throw new Unauthorized();
                }
            }
        }

        if (!$params['comment'] instanceof PastoralComment) {
            throw new Unauthorized();
        }

        if ($params['comment']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        throw new Unauthorized();
    }
}
