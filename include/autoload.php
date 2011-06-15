<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

function __autoload ($class_name) {

    $root_path = dirname(__FILE__) . '/..';

    $class_segment_arr = preg_split('/(?<=\\w)(?=[A-Z])/', $class_name);

    /* Create array of potential paths and file names */
    $class_path_arr = array('/include/', '/library/');

    $class_filename_arr = array(lcfirst($class_name).'.php');

    if ( is_array($class_segment_arr) && !empty($class_segment_arr) ) {

        foreach ($class_segment_arr as $i => $class_segment) {

            /* Paths */
            $leading_segment_arr = array_slice($class_segment_arr, 0, $i+1);

            /* Special case for External models */
            if ($class_segment_arr[0] == 'External') {
                $leading_segment_arr[0] = Config::get('external_type');
            }

            $current_path = strtolower( join('/', $leading_segment_arr) );

            $class_path_arr[] = '/include/'.$current_path.'/';
            $class_path_arr[] = '/library/'.$current_path.'/';

            /* File names */
            $following_segment_arr = array_slice($class_segment_arr, $i, count($class_segment_arr));

            $class_filename_arr[] = join('', $following_segment_arr).'.php';
            $class_filename_arr[] = strtolower(join('-', $following_segment_arr).'.php');
        }
    }

    foreach ($class_path_arr as $class_path) {

        foreach ($class_filename_arr as $class_filename) {

            if ( file_exists($root_path . $class_path . $class_filename) ) {
                require_once($root_path . $class_path . $class_filename);

                return true;
            }

        }

    }

    return false;
}

?>