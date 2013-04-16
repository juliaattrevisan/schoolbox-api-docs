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
    static $includePathArray = array('include', 'library', 'app');

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
        spl_autoload_register(array('Autoloader', 'deprecatedLoad'));

        /* Add include paths */
        if ( is_array(self::$includePathArray) && !empty(self::$includePathArray) ) {
            foreach (self::$includePathArray as &$includePath) {
                $includePath = self::$rootPath . '/' . trim($includePath, '/');
            }
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
     * Old-style autoloader - attempts to load the class using the much less
     * efficient method in getDeprecatedClassFile().
     *
     * @param string $class_name The name of the class to be included
     *
     * @return void
     */
    public static function deprecatedLoad($class_name) {
        if ($class_file = self::getDeprecatedClassFile($class_name)) {
            self::includeFile($class_file);
        }
    }

    /**
     * @param String $className The name of the relevant class
     * @return String|Bool The path to the file containing the class or false
     */
    private static function getClassFile ($className) {
        $classSegments = preg_split('/[_\\\\]/', $className);

        //
        // For efficiency - attempt to load this filename with the PEAR standard
        // class name first.
        $ideal_filename = implode('/', $classSegments).'.php';
        if (self::fileExists($ideal_filename)) {
            return $ideal_filename;
        }

        $unqualClassName = $classSegments[count($classSegments) - 1];
        $pathSegments = $classSegments;

        /* Determine path */
        $path = '';

        while ( array_pop($pathSegments) ) {
            $currentPath = implode('/', $pathSegments);

            if ( self::fileExists($currentPath) ) {
                $path = $currentPath;
                break;
            }
        }

        /* Determine file name */
        $filename = array_pop($classSegments);

        /* Array of expected classfile definitions, in descending order of likelihood */
        $possibleFiles = array();
        $possibleFiles[] = ltrim("$path/$filename.php", '/');
        $possibleFiles[] = ltrim("$path/$unqualClassName/$unqualClassName.php", '/');
        $possibleFiles[] = ltrim("$className.php", '/');

        foreach($possibleFiles as $classFile) {
            if ( self::fileExists($classFile) ) {
                return $classFile;
            }
        }
        return false;
    }

    /**
     * @param String $className The name of the relevant class
     * @return String|Bool The path to the file containing the class or false
     */
    private static function getDeprecatedClassFile ($className) {
        $classSegments = preg_split('/(?<=\\w)(?=[A-Z])/', $className);

        foreach ($classSegments as &$currentSegment) {
            $currentSegment = strtolower($currentSegment);
        }

        $pathSegments = $classSegments;

        /* Determine path */
        $path = '';

        while ( count($pathSegments) ) {
            $currentPath = implode('/', $pathSegments);

            if ( self::fileExists($currentPath) ) {
                $path = $currentPath;

                break;
            }

            array_pop($pathSegments);
        }

        /* Lower case class name */
        $currentFilename = strtolower($className) . '.php';
        $currentFile = ltrim("$path/$currentFilename", '/');

        if ( self::fileExists($currentFile) ) {
            return $currentFile;
        }

        /* Lower case first letter of class name */
        $currentFilename = lcfirst($className) . '.php';
        $currentFile = ltrim("$path/$currentFilename", '/');

        if ( self::fileExists($currentFile) ) {
            return $currentFile;
        }

        /* Dash separated class names */
        while ( count($classSegments) ) {
            $currentFilename = implode('-', $classSegments) . '.php';
            $currentFile = ltrim("$path/$currentFilename", '/');

            if ( self::fileExists($currentFile) ) {
                return $currentFile;
            }

            array_pop($classSegments);
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
                return true;
            }
        }

        return false;
    }

    /**
     * @static
     * @param String $file Includes files from paths defined in Autoloader::$includePathArray
     * @return Bool
     */
    private static function includeFile ($file) {
        if ( empty(self::$includePathArray) ) {
            return false;
        }

        foreach (self::$includePathArray as $includePath) {
            $currentFile = rtrim($includePath, '/') . '/' . $file;

            if ( file_exists($currentFile) ) {
                require_once($currentFile);

                return true;
            }
        }

        return false;
    }
}