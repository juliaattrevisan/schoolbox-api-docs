<?php
/**
 * Determine if a pastoral record should be viewable by the logged in user based on audienced roles/users/groups
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;

class IsInAudience implements IAuthorizationRule
{
    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
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

        /** @var PastoralAccessModel $accessCurrent */
        foreach ($record->getAccess() as $accessCurrent) {
            if ($accessCurrent->hasAccess($user)) {
                return true;
            }
        }

        throw new Unauthorized();
    }
}