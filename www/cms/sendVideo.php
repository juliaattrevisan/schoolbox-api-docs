<?php
/**
 * This page fetches and sends video files that have been transcoded into a web view format
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/SitePage.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

if (is_numeric($_GET['id'])) {
	$page = new SitePage;
	$groupHandler = new Group;
	$fileHandler = new File;
	$videoQueueMapper = new \Schoolbox\Video\ResourceVideoMapper();
	$video = $videoQueueMapper->findById($_GET['id']);

	if (!is_object($video))
		exit;

	if ($groupHandler->getPermission($video->getFile()->getFolder()) < READ_ACCESS)
		exit;

	/*Close session so streaming does not prevent other requests to session due to locking
	 *http://konrness.com/php5/how-to-prevent-blocking-php-requests/
	 */
	session_write_close();

	/* Prevent multibyte encoding as it might mess with video stream */
	mb_http_output("pass");


	$filename = $video->getVideoLocation();

	$fileHandler->logAction($_GET['id'], 'access');

	if(is_readable($filename) ) {
		$filesize = @filesize($filename);
		if ($filesize) {

			$byteOffset = 0;
			$byteLength = $filesize;


			/* Determine range of request for streaming protocol */
			if( $_SERVER['HTTP_RANGE'] )
			{
				$ranges = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
				$parts = explode('-', $ranges[0]);

				header('HTTP/1.1 206 Partial content');

				// Offset signifies where we should begin to read the file
				$byteOffset = (int)$parts[0];

				// Length is for how long we should read the file according to the browser, and can never go beyond the file size
				if( isset($parts[1]) && $parts[1] ) {
					$byteLength = min( (int)$parts[1], $byteLength - $byteOffset);
				}
				else {
					$byteLength = $filesize;
				}

				header(sprintf('Content-Range: bytes %d-%d/%d', $byteOffset, $byteLength - 1, $filesize));  // Decrease by 1 on byte-length since this definition is zero-based index of bytes being sent
			}

			/* Set initial basic headers */
			header('Content-Type: video/mp4', true);
			header_remove('Cache-Control');
			header_remove('Pragma');
			header('Accept-Ranges: bytes', true);

			$byteRange = $byteLength - $byteOffset;

			header('Content-Length: '.$byteRange, true);

			if($handle = fopen($filename, 'r')) {
				fseek($handle, $byteOffset, SEEK_SET);
				while( ftell($handle) < $byteLength && !feof($handle)) {
					echo fread($handle, ($byteLength - ftell($handle)));
				}
			}
		}
	}
}

