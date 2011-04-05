<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

$real_url = 'http://www.google.com';
$cache_path = '../../data/cache/weather';
$file_path = $_GET['path'];

if (is_string($file_path)) {
    $file_name = basename($file_path);

    if (file_exists($cache_path.'/'.$file_name)) {
        $file_contents = file_get_contents($cache_path.'/'.$file_name);
    } elseif ($file_contents = file_get_contents($real_url.'/'.$file_path)) {
        file_put_contents($cache_path.'/'.$file_name, $file_contents);
    }

    $file_info = new finfo(FILEINFO_MIME);

    header('Content-Type: '.$file_info->buffer($file_contents));
    echo $file_contents;
}
 
 ?>