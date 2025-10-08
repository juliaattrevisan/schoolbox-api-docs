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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class IsParentOfUser implements IAuthorizationRule
{
    /**
     * The source for the value within `$params` which is used to determine the
     * user to compare to the current user.
     *
     * @see IsUser::$paramSource
     *
     * @var string|callable
     */
    protected $paramSource = 'id';

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
        $this->paramSource    = $userIdSource;
        $this->userAuthorizer = new IsUser($this->paramSource);
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = [], ?User $user = null)
    {
        if ($user) {
            // May need to load children into user, as they are not guaranteed
            // to be within the current user
            $externalUser = new \Schoolbox\External\User();
            $externalUser->loadChildren([$user]);
            // Test each child against the user authorizer:
            // if any child passes that authorizer, pass this authorizer
            foreach ($user->getChildren() as $child) {
                try {
                    $this->userAuthorizer->authorize($params, $child);
                    return true;
                } catch (Unauthorized $e) {
                    // Auth failed for this child, but may for others:
                    // continue
                }
            }
        }


        // No child matched: auth failed
        throw new Unauthorized();
    }
}
