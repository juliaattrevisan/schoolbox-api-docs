<?php

/**
 * Authorization class: verifies that the user can moderate a booking.
 *
 * If the user is a moderator for the booking's item, they can moderate the
 * booking.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Auth;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\ResourceBooking\Booking\Model;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Booking\Mapper;

class CanModerateBooking implements Rule\IAuthorizationRule
{

    /**
     * @var Mapper
     */
    protected $mapper;

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
        if ($user instanceof User) {
            if ($params['item'] instanceof Model) {
                // search for item
                foreach ($params['item']->getResource()->getModeratorList() as $moderator) {
                    if ($moderator->getId() == $user->getId()) {
                        return;
                    }
                }
            }
            if ($this->getMapper()->isUserModeratorOfBookingItem($params['id'], $user->getId())) {
                return;
            }
        }
        throw new Failure\Unauthorized();
    }

    /**
     * @param  Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
