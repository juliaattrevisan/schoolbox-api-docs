<?php
/**
 * Abstract so that it can't be instantiated, so only its static method(s) can be used
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Resource\Folder\Model as Folder;

abstract class Component_Config_Factory
{

    private static $invalid = array('factory', 'abstract', 'interface');

    /**
     * @static
     * @param $key
     * @param Folder $folder
     * @param $fields
     * @return Component_Config_Abstract|bool
     */
    public static function build($key, $folder, $fields)
    {
        if (in_array(strtolower($key), self::$invalid)) {
            return false;
        }

        $classname = 'Component_Config_' . ucfirst(preg_replace_callback("#_[a-z]#s", function($matches) {
            return ucfirst($matches[0][1]); // because strings are accessible via array notation ^_^
        }, $key));

        if (class_exists($classname)) {
            // echo "Found $classname - will need template $key.html<br/>\n";
            return new $classname($folder, $fields);
        } else {
            // echo "Problem: couldn't find $classname ($key.html won't be rendered)<br/>\n";
            return false;
        }
    }
}
