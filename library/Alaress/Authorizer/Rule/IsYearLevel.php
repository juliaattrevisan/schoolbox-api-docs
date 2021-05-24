<?php

/**
 * Check whether the schoolbox user record year value is equivalent to $year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Unauthorized;

class IsYearLevel extends All
{
    /**
     * @var array
     */
    private $years;

    /**
     * IsSuperuser constructor.
     * @param array $years An array of possible values
     * @throws \InvalidArgumentException
     */
    public function __construct(array $years)
    {
        $this->years = $years;

        parent::__construct(
            new Callback(array($this, 'authorizeMethod'))
        );
    }

    /**
     * @param array     $params
     * @param User|null $user
     * @return void
     * @throws Unauthorized
     */
    public function authorizeMethod(array $params = array(), User $user = null)
    {
        if ($params['user'] instanceof User && $params['user']->getId()) {
            $year = $params['user']->getYear() ? $params['user']->getYear()->getName() : '';

            if (\in_array($year, $this->years, false)) {
                return;
            }
        }

        throw new Unauthorized();
    }
}
