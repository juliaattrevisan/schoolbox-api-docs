<?php
/**
 * IsInAudience - Determine if a pastoral record should be viewable by the logged in user based on audienced roles/users
 *
 * @author      Damian Sloane <steve.chan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\Authorizer\Rule;

use Alaress\Authorizer\Rule\IAuthorizationRule;
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
        if (!isset($params['record'])) {
            throw new Unauthorized();
        }

        $role = $user->getRole();

        // Give owners permission to their own records regardless of other permissions

        if ($params['record']->getOwner()->getId() == $user->getId()) {
            return true;
        }

        // Check if the record is private make sure the user is allowed access

        if ($params['record']->getPrivate() && !$params['privateAllowed']) {
            throw new Unauthorized();
        }

        // If we get through to here we need to check the audience of item

        /** @var PastoralAccessModel $accessCurrent */
        foreach ($params['access'] as $accessCurrent) {
            // Access records are either role based of user based.

            if (is_numeric($accessCurrent->getRole()->getId())) {
                // This access record is a role one, check if the user is in this role

                if ($role->getId() == $accessCurrent->getRole()->getId()) {
                    return true;
                }
            } else {
                // This access record is a user based one, check if this is the user

                if ($accessCurrent->getUser()->getId() == $user->getId()) {
                    return true;
                }
            }
        }

        // BZZZZZT! You're outta here.

        throw new Unauthorized();
    }
}