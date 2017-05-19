<?php
/**
 * Determine if a pastoral record is owned by the logged in user
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsOwner implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
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

        if ($params['record']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        throw new Unauthorized();
    }
}