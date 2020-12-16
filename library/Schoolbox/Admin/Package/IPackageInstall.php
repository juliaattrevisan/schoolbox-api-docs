<?php

/*
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2020, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Package;

use Schoolbox\User;

/**
 * All installable packages should implement this interface to ensure they can be run
 * @package Schoolbox\Admin\Package
 */
interface IPackageInstall
{
    /**
     * This function is called to install a package
     * @param User $user
     */
    public function install(User $user): void;
}
