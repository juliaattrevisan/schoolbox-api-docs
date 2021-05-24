<?php

/**
 * Authorization class: verifies that the user owns this booking.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Auth;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Booking\Model;
use Schoolbox\ResourceBooking\Booking\Mapper;

class UserOwnsBooking implements Rule\IAuthorizationRule
{

    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Authorizes that this user owns this booking.
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws Failure\Unauthorized if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // Hopefully, the item has been loaded in the module's preloader.
        // Otherwise, we must fetch it again.
        if ($params['item'] instanceof Model) {
            $item  = $params['item'];
            $event = $item->getEvent();
        } else {
            $item  = $this->getMapper()->findById($params['id']);
            $event = $this->getMapper()->getJoinMapper('event')->findById($item->getEvent()->getId());
        }

        // findById() should hydrate the event join as well
        if ($user->getId() == $event->getOwner()->getId()) {
            return;
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
