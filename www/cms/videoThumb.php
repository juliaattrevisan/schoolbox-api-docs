<?php
/**
 * This file send thumbnails of movies to the user
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

if (is_numeric($_GET['id'])) {
	$page = new SitePage;
	$groupHandler = new Group;
	$fileHandler = new File;

	$file = $fileHandler->getByID($_GET['id']);
	if (!is_array($file))
		error(41);

	if ($groupHandler->getPermission($file['category']) < READ_ACCESS)
		error(5);

	$filename = Cfg::get('video_dir') . '/' . $_GET['id'] . '-64.jpg';

	if(is_readable($filename) ) {
		$filesize = filesize($filename);
		if ($filesize == FALSE) //if we cannot find the file (something is wrong dont link to it)
			error(41);


		//header("Content-Type: image/jpeg");
		header("Pragma: ");
		header("Cache-control: ");
		header('Content-Type: image/jpeg');
		header("Content-Length: $filesize");
		readfile($filename);
	}
	else {
		error(41);
	}

}
