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

use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class HasServicePermission implements IAuthorizationRule
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
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        if ($user instanceof User) {
            // What's the scalar identifier value?
            if (is_callable($this->serviceIdentifier)) {
                $identifier = call_user_func($this->serviceIdentifier, $params, $user);
            } else {
                $identifier = $this->serviceIdentifier;
            }

            // Check this user's permissions against this item's service and
            // access level.
            $accessList = $user->getAccessList();
            if (!empty($accessList)) {
                foreach ($accessList as $access) {
                    if ($access->getService() == $this->service
                        && $access->getAccessLevel() >= $this->accessLevel
                    ) {
                        if (!isset($this->serviceIdentifier)) {
                            return true;
                        }
                        if ($identifier == $access->getServiceIdentifier()) {
                            return true;
                        }
                    }
                }
            }

            // Nothing in direct permissions for a service?
            // check role level access (HOMEPAGE_SERVICE only)
            if ($this->service == UserAccess::SERVICE_HOMEPAGE) {
                // Get the role access, and use it to check the homepage perms
                try {
                    /** @var \Schoolbox\Permission\Access\RoleAccess $roleAccess */
                    $roleAccess = \Schoolbox\Permission\Access\RoleAccessMapper::getInstance()
                        ->findOneByColumnList(
                            array(
                                'cid' => $identifier,
                                'gid' => $user->getRole()->getId(),
                            )
                        );
                    $permission = $roleAccess->getType();
                } catch (Exception $e) {
                    $permission = UserAccess::ACCESS_DENY;
                }

                // Check each subject's permission against the user's permissions
                if ($permission < UserAccess::ACCESS_READ) {
                    /** @var \Schoolbox\Resource\Category $homepage */
                    $homepage = \Schoolbox\Resource\CategoryMapper::getInstance()->findById($identifier);
                    foreach ($homepage->getCodes() as $code) {
                        $isFound = false;
                        foreach ($user->getSubjectCollection() as $subject) {
                            if ($code->getCode() == $subject['code']) {
                                $isFound    = true;
                                $permission = $user->getRole()->isStaff()
                                    ? UserAccess::ACCESS_WRITE
                                    : UserAccess::ACCESS_READ
                                ;
                                break;
                            }
                        }
                        if ($isFound) {
                            break;
                        }
                    }
                }
                if ($permission >= $this->accessLevel) {
                    return true;
                }
            }
        }
        throw new Unauthorized();
    }
}
