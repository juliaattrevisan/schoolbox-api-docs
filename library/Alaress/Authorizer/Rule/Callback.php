<?php

/**
 * Callback authorization rule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;

class Callback implements IAuthorizationRule
{

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param callable $callback A callback method for authorization: expected
     *                           to match IAuthorizationRule::authorize() params.
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null, $target = null)
    {
        return call_user_func($this->callback, $params, $user, $target);
    }
}
