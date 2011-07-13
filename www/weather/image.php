<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
require_once '../../include/SitePage.php';

$page = new SitePage;

$real_url = 'http://g0.gstatic.com/';
$cache_path = Cfg::get('xml_dir').'/../weather/';
$file_path = urldecode($_GET['path']);

if (is_string($file_path)) {
    $file_name = basename($file_path);
    if (file_exists($cache_path.'/'.$file_name)) {
        $file_contents = file_get_contents($cache_path.'/'.$file_name);
    } elseif ($file_contents = file_get_contents($real_url.'/'.$file_path)) {
        file_put_contents($cache_path.'/'.$file_name, $file_contents);
    }

    //$file_info = new finfo(FILEINFO_MIME);

    header('Content-Type: image/gif');
    echo $file_contents;
}
 
 ?>