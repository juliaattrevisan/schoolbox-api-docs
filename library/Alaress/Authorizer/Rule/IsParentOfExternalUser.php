<?php
/**
 * Validates whether the user is the parent of the given user.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Unauthorized;
use External_UserInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class IsParentOfExternalUser implements IAuthorizationRule
{
    /**
     * The array key in $params that contains the user ID.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * The external user interface
     *
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * The user mapper used to retrieve potential children from the database
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor (obviously)
     *
     * @param External_UserInterface $externalDb
     * @param UserMapper             $userMapper
     * @param string                 $paramsKey
     */
    public function __construct(
        External_UserInterface $externalDb,
        UserMapper $userMapper,
        $paramsKey = 'id'
    ) {
        $this->externalDb = $externalDb;
        $this->userMapper = $userMapper;
        $this->paramsKey = $paramsKey;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // You misconfigured or misapplied your route
        if (!array_key_exists($this->paramsKey, $params)) {
            throw new Base(sprintf('Key "%s" not in route params', $this->paramsKey));
        }

        $userId = $params[$this->paramsKey];

        $child = $this->userMapper->findByExternalId($userId, Role::STUDENT);
        $child->loadParents();

        if ($user instanceof User) {
            if ($user->isParentOf($child)) {
                return true;
            }
        }
        throw new Unauthorized();
    }
}
