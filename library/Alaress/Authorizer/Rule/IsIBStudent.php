<?php
/**
 * Check whether the External User array represents an IB International Baccalaureate student
 * This can probably distill into a more generic class later, when our External outputs aren't
 * arbitrary arrays that could contain any data type
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsIBStudent extends All
{
    /**
     * IsIBStudent constructor.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct()
    {
        parent::__construct(
            new Callback(array($this, 'authorizeMethod'))
        );
    }

    /**
     * @param array $params
     * @param User  $user
     * @throws Unauthorized
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if (\is_array($params['externalUser']) && (bool)$params['externalUser']['StudentIBFlag']) {
            return;
        }

        throw new Unauthorized();
    }
}
