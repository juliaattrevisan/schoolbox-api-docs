<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Notification\Parameter;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\User;

class UserParam extends Param
{

    /**
     * Use standard vars from the user object, and default to the append stacking mode
     *
     * @param User        $user
     * @param int         $stacking
     * @param string|null $format
     */
    public function __construct($user, $stacking = self::STACKING_APPEND, $format = null)
    {
        $href = $user->isDeleted() ? null : "/search/user/{$user->getId()}";
        parent::__construct($user->formatName($format), $href, $stacking);
    }
}
