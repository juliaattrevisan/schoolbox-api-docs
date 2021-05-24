<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar\Condition;

use Alaress\Rules\Condition;
use Schoolbox\User;

class UserExists extends Condition
{

    /**
     * @param bool $negate
     */
    public function __construct($negate = false)
    {
        parent::__construct(
            function ($input, $previous) {
                return $previous instanceof User && $previous->getId();
            },
            $negate
        );
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        $not = $this->isNegated() ? 'NOT ' : '';

        return "user {$not}EXISTS";
    }
}
