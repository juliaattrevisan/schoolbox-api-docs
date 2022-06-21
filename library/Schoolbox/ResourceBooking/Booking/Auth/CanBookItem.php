<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking\Auth;

use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Failure;
use Schoolbox\User;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Class CanBookItem
 *
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
 * @package Schoolbox\ResourceBooking\Booking\Auth
 */
class CanBookItem implements Rule\IAuthorizationRule
{
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
     * * any user in the item's role list can book this item
     *
     * @param  array $params Any parameters passed as part of the route.
     * @param  User  $user   The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     *
     * @throws Failure\Unauthorized if the request isn't authorized.
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // TODO simplify this rule, and define it as separate ones
        if (($params['item'] ?? null) instanceof Item\Model) {

            /** @var Item\Model $item */
            $item = $params['item'];
        } else {

            /** @var Item\Model $item */
            $item = $params['item'] = $this->getMapper()->findById($params['id']);
        }
        $auth = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL),
            new Item\Auth\CanModerateItem()
        );
        if (!($item->getModerationType()->getId() == Item\ModerationType\Model::TYPE_MANAGED)) {
            // Add a callback rule:
            // if $user's role is allowed in $item's list of role permissions,
            // allow $user to book the item.
            $auth->prependRule(
                new Rule\Callback(
                    function ($params = array(), User $user = null) use ($item) {
                        if ($user instanceof User) {
                            foreach ($item->getRoleList() as $rolePermission) {
                                if ($rolePermission->getRole()->getId() == $user->getRole()->getId()) {
                                    if ($rolePermission->getAllowed()) {
                                        return true;
                                    }
                                }
                            }
                        }
                        throw new Failure\Unauthorized();
                    }
                )
            );
        }
        $auth->authorize($params, $user);
    }

    /**
     * @param  Item\Mapper $mapper
     * @return $this
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @return Item\Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }
}
