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
     * The array key in $params that contains the user ID.
     *
     * @var string
     */
    protected $paramsKey = 'id';

    /**
     * Constructor (obviously)
     *
     * @param string $paramsKey
     */
    public function __construct($paramsKey = 'id')
    {
        $this->paramsKey = $paramsKey;
    }

    /**
     * Validate whether the user has the same user id as the given parameter.
     * When 'participant' is passed as the parameter key, this validate the user against the participant object.
     * @param array $params  Any parameters passed as part of the route.
     * @param User  $user    The user to authorize, if any. Null value assumes
     *                       that no user is logged in.
     * @return true If the request is authorized.
     * @throws \Alaress\Authorizer\Failure\Base if the request isn't authorized.
     * @throws InsufficientHydrationException If route indicates
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // You misconfigured or misapplied your route
        if (!array_key_exists($this->paramsKey, $params)) {
            throw new Base(sprintf('Key "%s" not in route params', $this->paramsKey));
        }

        // No session user?
        if (!$user instanceof User) {
            throw new Unauthenticated("No session user provided");
        }

        $param = $params[$this->paramsKey];

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
