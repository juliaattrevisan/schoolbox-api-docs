<?php
/**
 * Abstract so that it can't be instantiated, so only its static method(s) can be used
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Config_Factory {

    private static $invalid = array( 'factory', 'interface' );

    /**
     * @static
     * @param $key
     * @return Component_Config_Interface|bool
     */
    public static function build($key) {

        if(in_array(strtolower($key), self::$invalid)) {
            return false;
        }

        $classname = 'Component_Config_' . ucfirst($key);
        if(class_exists($classname)) {
            return new $classname();
        }
        else {
            return false;
        }
    }

}
