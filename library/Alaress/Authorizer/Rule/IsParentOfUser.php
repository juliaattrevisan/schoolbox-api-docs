<?php

/**
 * Validates whether the user is the parent of the given user.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Unauthorized;
use External_UserInterface;
use Schoolbox\User;
use Schoolbox\UserMapper;

class IsParentOfUser implements IAuthorizationRule
{
    /**
     * The array key in $params that contains the user ID (if a string), or a
     * callable which can retrieve the ID.
     *
     * @var string
     */
    protected $userIdSource = 'id';

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
     * @param string|callable        $userIdSource
     */
    public function __construct(
        External_UserInterface $externalDb,
        UserMapper $userMapper,
        $userIdSource = 'id'
    ) {
        $this->externalDb   = $externalDb;
        $this->userMapper   = $userMapper;
        $this->userIdSource = $userIdSource;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (is_callable($this->userIdSource)) {
            $userId = call_user_func($this->userIdSource, $params, $user);
        } else {
            // You misconfigured or misapplied your route
            if (!array_key_exists($this->userIdSource, $params)) {
                throw new Base(sprintf('Key "%s" not in route params', $this->userIdSource));
            }
            $userId = $params[$this->userIdSource];
        }
        $child = $this->userMapper->findById($userId);
        $child->loadParents();

        if ($user instanceof User) {
            if ($user->isParentOf($child)) {
                return true;
            }
        }
        throw new Unauthorized();
    }
}
