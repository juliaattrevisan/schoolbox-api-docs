<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\User;

abstract class Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @abstract
     * @param User $user
     */
    public abstract function render(User $user);
}
