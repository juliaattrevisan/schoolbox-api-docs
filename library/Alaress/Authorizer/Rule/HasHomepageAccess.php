<?php
/**
 * Does this user have this access level to this homepage?
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class HasHomepageAccess extends All
{
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
     * Homepage identifier.
     *
     * It can be a callable which takes route params as the first parameter,
     * and the user being authorized as the second, and returns an identifier.
     *
     * If it's not callable, it is the static value of the identifier.
     *
     * @var int|callable
     */
    protected $homepageIdentifier;

    /**
     * Access check object
     *
     * @var Access
     */
    protected $access;

    /**
     * Constructor (obviously)
     *
     * @param int          $accessLevel
     * @param int|callable $categoryIdentifier
     * @param Access       $access
     */
    public function __construct($accessLevel, $categoryIdentifier, Access $access)
    {
        $this->accessLevel        = $accessLevel;
        $this->homepageIdentifier = $categoryIdentifier;
        $this->access             = $access;

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
            // What's the scalar identifier value?
            if (is_callable($this->homepageIdentifier)) {
                $identifier = call_user_func($this->homepageIdentifier, $params, $user);
            } else {
                $identifier = $this->homepageIdentifier;
            }

            // Look for this homepage's permission
            try {
                $permission = $this->access->getFolderAccess($user, (int)$identifier);
            } catch (Exception $e) {
                $permission = UserAccess::ACCESS_DENY;
            }
            if ($permission >= $this->accessLevel) {
                return true;
            }
        }
        throw new Unauthorized();
    }
}
