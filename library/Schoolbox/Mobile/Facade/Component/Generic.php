<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Facade\Component;

use Schoolbox\User;

abstract class Generic {

    /**
     * This should return an array of [title, aside, content]
     * @abstract
     * @param User $user
     */
    public abstract function render(User $user);
}
