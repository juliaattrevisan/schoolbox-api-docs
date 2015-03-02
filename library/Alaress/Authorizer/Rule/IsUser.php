<?php
/**
 * Validates whether the user is the given ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Alaress\Authorizer\Failure\Base;
use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\User;

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
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        // You misconfigured or misapplied your route
        if (!array_key_exists($this->paramsKey, $params)) {
            throw new Base(sprintf('Key "%s" not in route params', $this->paramsKey));
        }
        if ($user instanceof User && $user->getId() == $params[$this->paramsKey]) {
            return true;
        }
        throw new Unauthorized();
    }
}
