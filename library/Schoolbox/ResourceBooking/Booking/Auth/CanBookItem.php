<?php
/**
 * Authorization class: verifies that the user can create a booking.
 *
 * If the item has moderationType:
 * * Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_MANAGED:
 *   only a moderator can book it
 * * Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_MODERATED:
 *   anyone who can create bookings can book it, but only a moderator may confirm
 * * Schoolbox\ResourceBooking\Item\ModerationType\Model::TYPE_OPEN
 *   anyone who can create bookings can book it
 *
 * The user can create a booking for this item if:
 * * they're a staff member (may be covered in another auth rule)
 * * they're a moderator for this item
 *
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Auth;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\UserAccess;

class CanBookItem implements Rule\IAuthorizationRule {

    /**
     * @var Item\Mapper
     */
    protected $mapper;

    /**
     * Authorizes that this user is able to book this item.
     *
     * If this item is managed:
     * * User must be a moderator for this item, or have moderator permissions
     *   on resource booking service
     *
     * If this item is not managed:
     * * staff can book this item as well
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws Failure\Forbidden if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null) {
        // TODO simplify this rule, and define it as separate ones
        if ($params['item'] instanceof Item\Model) {
            $item = $params['item'];
        }
        else {
            $item = $this->getMapper()->findById($params['id']);
        }
        $moderateRule = new Item\Auth\CanModerateItem();
        $moderateRule->setMapper($this->getMapper());
        $auth = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL),
            $moderateRule
        );
        if (!($item->getModerationType()->getId() == Item\ModerationType\Model::TYPE_MANAGED)) {
            $auth->prependRule(new Rule\HasRole(Role::STAFF));
        }
        $auth->authorize($params, $user);
    }

    /**
     * @param  Item\Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper) {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getMapper() {
        return $this->mapper;
    }

}
