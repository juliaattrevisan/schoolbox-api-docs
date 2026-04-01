<?php

/**
 * Abstract so that it can't be instantiated, so only its static method(s) can be used
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Resource\Folder\Model as Folder;

abstract class Component_Config_Factory
{
    private static array $invalid = array('factory', 'abstract', 'interface');

    /**
     * @static
     * @param \Schoolbox\User                      $user  The currently-logged-in user
     * @param string                               $key
     * @param Folder|Eportfolio_Page_Instance|null $context
     * @param array                                $fields
     * @return Component_Config_Abstract|bool
     */
    public static function build(\Schoolbox\User $user, $key, $context, array $fields)
    {
        if (in_array(strtolower($key), self::$invalid)) {
            return false;
        }

        $classname = 'Component_Config_' . ucfirst(preg_replace_callback("#_[a-z]#s", function ($matches) {
                return ucfirst($matches[0][1]); // because strings are accessible via array notation ^_^
        }, $key));

        if (class_exists($classname)) {
            // echo "Found $classname - will need template $key.html<br/>\n";
            return new $classname($user, $context, $fields);
        } else {
            // echo "Problem: couldn't find $classname ($key.html won't be rendered)<br/>\n";
            return false;
        }
    }
}
