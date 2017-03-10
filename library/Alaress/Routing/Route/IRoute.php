<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Routing\Route;

use InvalidArgumentException;

interface IRoute
{

    /**
     * Returns the data payload held by this route
     *
     * @return array
     * @throws InvalidArgumentException   if there is no data to retrieve
     */
    public function data();

}