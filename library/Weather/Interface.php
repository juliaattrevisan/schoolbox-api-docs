<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Weather_Interface {

    /**
     * @abstract
     * @param array $location
     * @return bool
     */
    public function setLocation(array $location);
    public function getForecast();
}
