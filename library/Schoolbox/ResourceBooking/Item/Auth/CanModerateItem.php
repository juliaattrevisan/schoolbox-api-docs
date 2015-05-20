<?php
/**
 * Authorization class: verifies that the user can moderate an item.
 *
 * Doesn't check if user is a moderator of the resource booking service.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Item\Auth;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\ResourceBooking\Item\Model;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item\Mapper;

class CanModerateItem implements Rule\IAuthorizationRule
{
    /**
     * Authorizes that this user is a moderator for this item.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws Failure\Unauthorized if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // Authorize results should be cached, as hitting DB for each perm
        // check is expensive.
        if ($params['item'] instanceof Model) {
            // search for item
            foreach ($params['item']->getModeratorList() as $moderator) {
                if ($moderator->getId() == $user->getId()) {
                    return;
                }
            }
        }
        throw new Failure\Unauthorized();
    }
}
