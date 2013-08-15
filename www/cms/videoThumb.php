<?php
/**
 * This file send thumbnails of movies to the user
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';

if (is_numeric($_GET['id']) || isset($_GET['hash'])) {
	$page = new SitePage;
	$groupHandler = new Group;

	$videoQueueMapper = new \Schoolbox\Video\ResourceVideoMapper();

    try {
        if($_GET['type'] == 'storage') {
            if (isset($_GET['hash'])) {
                $video = $videoQueueMapper->findByStorageHash($_GET['hash']);
            }
            else {
                $video = $videoQueueMapper->findByStorageId($_GET['id']);
            }
        }
        else {
            $video = $videoQueueMapper->findByFileId($_GET['id']);

            //Check permissions on video
            if ($groupHandler->getPermission($video->getFile()->getFolder()) < READ_ACCESS)
                SitePage::redirectToBrokenImageOfSize(64, 64, 403);
        }
    }
    catch (Schoolbox\Mapper\Exception $e) {
        /* Video not found */
        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
    }


	if($_GET['type'] == 'poster') {
		$filename = $video->getPosterLocation();
	}
	else {
		$filename = $video->getThumbnailLocation();
	}

	if(is_readable($filename) ) {
		$filesize = filesize($filename);
		if ($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
			error(41);

		header('Cache-Control: max-age=10800, private');
		header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
		header('Pragma: private');
		header('Content-Type: image/jpeg');
		header("Content-Length: $filesize");
		readfile($filename);
	}
	else {
		error(41);
	}

}
