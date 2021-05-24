<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif\Queue\Bindings;

use Schoolbox\User;

interface GeneratorInterface
{

    /**
     * @param User $user
     * @return string[][]
     */
    public function generateBindingsForUser(User $user);
}
