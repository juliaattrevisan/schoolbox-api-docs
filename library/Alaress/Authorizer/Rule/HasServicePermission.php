<?php

/**
 * Authorization method - checks whether the user has certain permissions on a
 * given service.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

class HasServicePermission extends All
{

    /**
     * A value of Schoolbox\Permission\Access\UserAccess::SERVICE_*
     *
     * @var int
     */
    protected $service;

    /**
     * A value of Schoolbox\Permission\Access\UserAccess::ACCESS_*
     *
     * The user must have at least this access level for $this->service to pass
     * this permission check.
     *
     * @var int
     */
    protected $accessLevel;

    /**
     * Optional service identifier.
     *
     * It can be a callable which takes route params as the first parameter,
     * and the user being authorized as the second, and returns an identifier.
     *
     * If it's not callable, it is the static value of the identifier.
     *
     * What exactly this identifies depends on the value of $this->service:
     * * UserAccess::SERVICE_HOMEPAGE: a homepage ID
     * * UserAccess::SERVICE_CALENDAR and UserAccess::SERVICE_NEWS: a campus ID
     * * Anything else: not used
     *
     * @var int|callable
     */
    protected $serviceIdentifier = null;

    /**
     * Constructor (obviously)
     *
     * @param int          $service
     * @param int          $accessLevel
     * @param int|callable $serviceIdentifier
     */
    public function __construct($service, $accessLevel, $serviceIdentifier = null)
    {
        $this->service           = $service;
        $this->accessLevel       = $accessLevel;
        $this->serviceIdentifier = $serviceIdentifier;

        parent::__construct(
            new IsLoggedIn(),
            new Callback(array($this, 'authorizeMethod'))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if ($user instanceof User) {
            // Check this user's permissions against this item's service and
            // access level.
            $accessList = $user->getAccessList();
            if (!empty($accessList)) {
                foreach ($accessList as $access) {
                    if (
                        $access->getService() == $this->service
                        && $access->getAccessLevel() >= $this->accessLevel
                    ) {
                        if (!isset($this->serviceIdentifier)) {
                            return true;
                        }
                        if (is_callable($this->serviceIdentifier)) {
                            $identifier = call_user_func($this->serviceIdentifier, $params, $user);
                        } else {
                            $identifier = $this->serviceIdentifier;
                        }
                        if ($identifier == $access->getServiceIdentifier()) {
                            return true;
                        }
                    }
                }
            }
        }
        throw new Forbidden();
    }
}
