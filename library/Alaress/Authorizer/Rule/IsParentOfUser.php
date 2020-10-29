<?php

/**
 * Validates whether the user is the parent of the given user.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

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
     * @var string|callable
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
     * An internal user authorizer that this authorizer uses to determine that
     * the current user is a parent of the user found by `$this->userIdSource`.
     */
    private IsUser $userAuthorizer;


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
        $this->externalDb     = $externalDb;
        $this->userMapper     = $userMapper;
        $this->userIdSource   = $userIdSource;
        $this->userAuthorizer = new IsUser($this->userIdSource);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], User $user = null)
    {
        if ($user) {
            foreach ($user->getChildren() as $child) {
                try {
                    $this->userAuthorizer->authorize($params, $child);
                    return true;
                } catch (Unauthorized $e) {
                    // continue
                }
            }
        }

        throw new Unauthorized();
    }
}
