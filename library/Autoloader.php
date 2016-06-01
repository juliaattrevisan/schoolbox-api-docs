<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

class Autoloader {
    /**
     * @var string Root path to installation directory
     */
    static $rootPath = '';

    /**
     * Initializes variables, registers autoloading functions with PHP and sets include paths
     */
    public static function initialise () {
        /* Set root path */
        self::$rootPath = realpath( __DIR__ . '/..' );

        //
        // Composer autoloader
        require self::$rootPath.'/vendor/autoload.php';

        /* Register autoloaders with PHP */
        spl_autoload_register(array('Autoloader', 'load'));

    }

    /**
     * New-style autoloader - attempts to load the class using the more
     * efficient method in getClassFile().
     *
     * This method is used in preference to deprecatedLoad().
     *
     * @param string $class_name The name of the class to be included
     *
     * @return void
     */
    public static function load($class_name) {
        if ($class_file = self::getClassFile($class_name)) {
            require $class_file;
        }
    }

    /**
     * @param String $className The name of the relevant class
     * @return String|Bool The path to the file containing the class or false
     */
    public static function getClassFile ($className) {
        $classSegments = preg_split('/[_\\\\]/', $className);
        $ideal_filename = implode('/', $classSegments).'.php';
        return __DIR__ . '/' .  $ideal_filename;
    }

}
