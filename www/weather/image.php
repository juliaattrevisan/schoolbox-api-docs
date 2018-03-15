<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
require_once '../../library/Bootstrap.php';

$page = new SitePage;

$real_url = 'http://icons-ak.wxug.com/';
$cache_path = Cfg::get('weather_dir');
$file_path = urldecode($_GET['path']);

if (is_string($file_path) && !stristr($file_path, '/.gif') ) {
    $file_name = basename($file_path);
    if (file_exists($cache_path . '/' . $file_name)) {
        $file_contents = file_get_contents($cache_path . '/' . $file_name);
    } else {
	    // proxy check
	    $ps = new \Proxy\Server(new \Schoolbox\Config\Loader());
	    if ($file_contents = file_get_contents($real_url . '/' . $file_path, null, $ps->getContext())) {
            file_put_contents($cache_path . '/' . $file_name, $file_contents);
	    }
    }

    //$file_info = new finfo(FILEINFO_MIME);
    header('Cache-Control: max-age=31536000, public');
    header('Expires: ' . date(DATE_RFC822, strtotime(' 1 year')));
    header('Pragma: public');
    header('Content-type: image/gif');
    echo $file_contents;
}
else {
    SitePage::redirectToBrokenImageOfSize(50, 50, 404);
}
