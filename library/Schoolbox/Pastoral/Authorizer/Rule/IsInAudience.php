<?php
/**
 * Determine if a pastoral record should be viewable by the logged in user based on audienced roles/users/groups
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;

class IsInAudience implements IAuthorizationRule
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

        // Give owners permission to their own records regardless of other permissions

        if ($record->getOwner()->getId() == $user->getId()) {
            return true;
        }

        // Check the audience of item

        $accessLevel = (new AccessLevelCensor())->getAccessLevel($record, $user);

        if ($accessLevel == PastoralAccessMapper::ACCESS_DENY) {
            throw new Unauthorized();
        }

        return true;
    }
}
