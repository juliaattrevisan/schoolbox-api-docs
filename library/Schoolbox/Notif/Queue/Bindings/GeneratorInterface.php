<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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