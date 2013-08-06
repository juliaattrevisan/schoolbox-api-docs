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
     * @var array Possible class locations
     */
    static $includePathArray = array('include', 'library');

    /**
     * Initializes variables, registers autoloading functions with PHP and sets include paths
     */
    public static function initialise () {
        /* Set root path */
        self::$rootPath = realpath( dirname(__FILE__) . '/..' );

        //
        // Composer autoloader
        require self::$rootPath.'/vendor/autoload.php';

        /* Register autoloaders with PHP */
        spl_autoload_register(array('Autoloader', 'load'));

        /* Add include paths */
        if ( is_array(self::$includePathArray) && !empty(self::$includePathArray) ) {
            foreach (self::$includePathArray as &$includePath) {
                $includePath = self::$rootPath . '/' . trim($includePath, '/');
            }
            unset($includePath);
        }
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
            self::includeFile($class_file);
        }
    }

    /**
     * @param String $className The name of the relevant class
     * @return String|Bool The path to the file containing the class or false
     */
    private static function getClassFile ($className) {
        $classSegments = preg_split('/[_\\\\]/', $className);
        $ideal_filename = implode('/', $classSegments).'.php';
        if ($ideal_filename = self::fileExists($ideal_filename)) {
            return $ideal_filename;
        }
        return false;
    }

    /**
     * @param String $filename The file name or path to be checked for existance
     * @return Bool Wether or not the file exists
     */
    private static function fileExists ($filename) {
        if ( empty($filename) ) {
            return false;
        }

        //$includeArray = explode(PATH_SEPARATOR, get_include_path());

        foreach (self::$includePathArray as $includePath) {
            $includePath = rtrim($includePath, '/');
            if ( file_exists("$includePath/$filename") ) {
                return "$includePath/$filename";
            }
        }

        return false;
    }

    /**
     * @static
     * @param String $file Includes files
     * @return Bool
     */
    private static function includeFile ($file) {
        require_once($file);
        return true;
    }
}