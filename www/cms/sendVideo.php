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
	$file = $fileHandler->getByID($_GET['id']);
	if (!is_array($file))
		exit;

	if ($groupHandler->getPermission($file['category']) < READ_ACCESS)
		exit;

	mb_http_output("pass");

	$filename = Cfg::get('video_dir') . "/" . $_GET['id'] . ".mp4";

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

				file_put_contents( Cfg::get('video_dir') . "/test.txt", $_SERVER['HTTP_RANGE'].'s'.$byteLength.'s'.$byteOffset."\n", FILE_APPEND);

				header(sprintf('Content-Range: bytes %d-%d/%d', $byteOffset, $byteLength - 1, $filesize));  // Decrease by 1 on byte-length since this definition is zero-based index of bytes being sent
			}

			/* Set initial basic headers */
			header('Content-Type: video/mp4', true);
			header_remove('Cache-Control');
			header_remove('Pragma');
			header('Accept-Ranges: bytes', true);

			$byteRange = $byteLength - $byteOffset;


			//header("Content-Disposition: inline; filename=\"" . $_GET['id'].'.mp4' . "\"");
			header('Content-Length: '.$byteRange, true);

			if($handle = fopen($filename, 'r')) {
				fseek($handle, $byteOffset, SEEK_SET);
				while( ftell($handle) < $byteLength && !feof($handle)) {
					echo fread($handle, ($byteLength - ftell($handle)));
				}
			}


			/*while( $bytePool > 0 )
			{
				$chunkSizeRequested = min($bufferSize, $bytePool); ### How many bytes we request on this iteration

				### Try readin $chunkSizeRequested bytes from $handle and put data in $buffer
				$buffer = fread($handle, $chunkSizeRequested);

				### Store how many bytes were actually read
				$chunkSizeActual = strlen($buffer);

				### If we didn't get any bytes that means something unexpected has happened since $bytePool should be zero already
				if( $chunkSizeActual == 0 )
				{
					### For production servers this should go in your php error log, since it will break the output
					trigger_error('Chunksize became 0', E_USER_WARNING);
					break;
				}

				### Decrease byte pool with amount of bytes that were read during this iteration
				$bytePool -= $chunkSizeActual;

				### Write the buffer to output
				print $buffer;

				### Try to output the data to the client immediately
				flush();
			}*/

		}
	}
}

