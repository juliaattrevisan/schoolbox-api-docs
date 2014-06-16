<?php
/**
 * Authorization method - checks whether the user has certain permissions on a
 * given service.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Forbidden;

class HasServicePermission implements IAuthorizationRule {

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

    public function __construct($service, $accessLevel) {
        $this->service     = $service;
        $this->accessLevel = $accessLevel;
    }

    public function authorize(array $params = array(), User $user = null) {
        if ($user instanceof User) {
            // Check this user's permissions against this item's service and
            // access level.
            $accessList = $user->getAccessList();
            if (!empty($accessList)) {
                foreach ($accessList as $access) {
                    if ($access->getService() == $this->service
                        && $access->getAccessLevel() >= $this->accessLevel
                    ) {
                        return true;
                    }
                }
            }
        }
        throw new Forbidden();
    }

}
