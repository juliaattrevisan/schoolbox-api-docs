<?php
/**
 * This file send thumbnails of movies to the user
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$type = $_GET['type'];
$id   = $_GET['id'];
$size = $_GET['size'];
if (isset($_GET['hash'])) {
	// Old URL for brownhole videos: ?hash={brownholeHash}&size={whatever}
	// If you get one of these, permanent redirect to the new one.
	$params = $_GET;
	$params['type'] = 'storage';
	$params['id']   = $_GET['hash'];
	unset($params['hash']);
	SitePage::localRedir('/cms/videoThumb.php?'.http_build_query($params), false, 301);
}
if (isset($type) && isset($id) ) {
	$page = new SitePage;
	$groupHandler = new Group;

	$videoQueueMapper = new \Schoolbox\Video\ResourceVideoMapper();

    try {
		$thumbnailFactory = new \Schoolbox\Video\Factory\Thumbnail($id, $type);
		$video = $thumbnailFactory->produce();
    }
    catch (Schoolbox\Mapper\Exception $e){
        /* Video not found */
        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
    }

    if ($size == 'poster') {
   		$filename = $video->getPosterLocation();
           $pixels = 400;
   	}
   	else {
   		$filename = $video->getThumbnailLocation();
           $pixels = 64;
   	}



	if(is_readable($filename) ) {
		$filesize = filesize($filename);
		if ($filesize == FALSE) { //if we cannot find the file (something is wrong dont link to it)
            SitePage::redirectToBrokenImageOfSize(64, 64, 500);
        }

		header('Cache-Control: max-age=10800, private');
		header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
		header('Pragma: private');
		header('Content-Type: image/jpeg');
		header("Content-Length: $filesize");
		readfile($filename);
	}
	else {
        SitePage::redirectToBrokenImageOfSize(64,64, 500);

    }
} else {
   SitePage::redirectToBrokenImageOfSize(64, 64, 500);
}
