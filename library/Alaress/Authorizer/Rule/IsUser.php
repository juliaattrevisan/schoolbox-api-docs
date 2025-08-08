<?php

/**
 * Validates whether the user is the given ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Unauthenticated;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\User;

use function is_scalar;

class IsUser implements IAuthorizationRule
{
    /**
     * The source for the value within `$params` which is used to determine the
     * user to compare to the current user.
     *
     * This value may be either:
     * * a string or int, in which case it's a direct key lookup in `$params`
     * * a callable, in which case the callable is called (with `$params` and
     *   the current user as parameters), and is expected to return the value
     *
     * @var string|callable
     */
    protected $paramSource = 'id';

    /**
     * Constructor (obviously)
     *
     * @param string|callable $paramSource
     */
    public function __construct($paramSource = 'id')
    {
        $this->paramSource = $paramSource;
    }

    /**
     * Validate whether the user has the same user id as the given parameter.
     * When 'participant' is passed as the parameter key, this validate the user against the participant object.
     * @param array     $params Any parameters passed as part of the route.
     * @param User|null $user   The user to authorize, if any. Null value
     *                          assumes that no user is logged in.
     * @return true If the request is authorized.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     * @throws InsufficientHydrationException If route indicates
     */
    public function authorize(array $params = [], ?User $user = null)
    {
        // No session user?
        if (!$user instanceof User) {
            throw new Unauthenticated("No session user provided");
        }


        // Extract the param from the param source
        if (
            (is_string($this->paramSource))
            && array_key_exists($this->paramSource, $params)
        ) {
            $param = $params[$this->paramSource];
        } elseif (is_callable($this->paramSource)) {
            $param = call_user_func($this->paramSource, $params, $user);
        } else {
            throw new Base('`$paramSource` parameter is neither an array key nor a callable');
        }


        // Now check that the param matches the user somehow.
        if ($param instanceof User) {
            if ($param->getId() === $user->getId()) {
                return true;
            }
        } elseif (is_scalar($param)) {
            if ((int)$param === $user->getId()) {
                return true;
            }
        } elseif ($param instanceof Participant) {
            if (!$param->getUser()) {
                throw new InsufficientHydrationException("Participant.user not hydrated");
            }
            if ($user->getId() === $param->getUser()->getId()) {
                return true;
            }
        }

        throw new Unauthorized();
    }
}
