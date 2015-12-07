<?php
/**
 * This page fetches and sends video files that have been transcoded into a web view format
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Resource\LogMapper;

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

$type = $_GET['type'];
$id   = $_GET['id'];
if (isset($_GET['hash'])) {
    // Old URL for brownhole videos: ?hash={brownholeHash}
    // If you get one of these, permanent redirect to the new one.
    $params         = $_GET;
    $params['type'] = 'storage';
    $params['id']   = $_GET['hash'];
    unset($params['hash']);
    SitePage::localRedir('/cms/sendVideo.php?' . http_build_query($params), false, 301);
}
if (isset($type) && isset($id)) {
    $page             = new SitePage;
    $groupHandler     = new Group;
    $fileMapper       = Schoolbox\Resource\File\Mapper::getInstance();
    $videoQueueMapper = new \Schoolbox\Video\ResourceVideoMapper();

    try {
        $videoFactory = new \Schoolbox\Video\Factory\Video($id, $type);
        $video        = $videoFactory->produce();
    } catch (Schoolbox\Mapper\Exception $e) {
        /* Video not found */
        exit;
    }

    /*Close session so streaming does not prevent other requests to session due to locking
     *http://konrness.com/php5/how-to-prevent-blocking-php-requests/
     */
    session_write_close();

    /* Prevent multibyte encoding as it might mess with video stream */
    mb_http_output("pass");
    ini_set('zlib.output_compression', 0);
    if (substr_count(strtolower(php_sapi_name()),'apache') > 0) { apache_setenv('no-gzip', 1); }

    $filename = $video->getFileLocation();
    // Get mimetype of encoded file, not from upload
//    $fileinfo = new finfo(FILEINFO_MIME_TYPE);
//    $mimetype = $fileinfo->file($filename);
//    unset($fileinfo);
    switch (pathinfo($filename, PATHINFO_EXTENSION)) {
        case 'mp3':
            $mimetype = 'audio/mpeg';
            break;
        case 'mp4':
            $mimetype = 'video/mp4';
            break;
        default:
            // assume it wasn't encoded at all
            $mimetype = $video->getFile()->getMimeType()->getMime();
            break;
    }

    if ($type == \Schoolbox\Video\QueueMapper::FILE_TYPE_SBFS) {
        if ($video->getFile() instanceof Schoolbox\Resource\File\Model) {
            LogMapper::getInstance()->logRead($video->getFile(), $_SESSION['user']);
        }
    }

    if (is_readable($filename)) {
        $filesize = @filesize($filename);
        if ($filesize) {
            $byteOffset = 0;
            $byteLength = $filesize;

            /* Determine range of request for streaming protocol */
            if ($_SERVER['HTTP_RANGE']) {
                $ranges = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
                $parts  = explode('-', $ranges[0]);


                // Offset signifies where we should begin to read the file
                $byteOffset = (int)$parts[0];

                // Length is for how long we should read the file according to the browser,
                // and can never go beyond the file size
                if (isset($parts[1]) && $parts[1] != '' && $parts[1] < $filesize) {
                    $byteLength = (int)$parts[1];
                } else {
                    $byteLength = $filesize - 1;
                }

                if ($byteOffset > $byteLength) {
                    header('HTTP/1.1 416 Requested Range Not Satisfiable');
                    header('Content-Range: bytes */' . $filesize); // Required in 416.
                    exit;
                }

                header('HTTP/1.1 206 Partial content');
                // Decrease by 1 on byte-length since this definition is zero-based index of bytes being sent
                header(sprintf('Content-Range: bytes %d-%d/%d', $byteOffset, $byteLength, $filesize));
            }

            /* Set initial basic headers */
            header('Content-Type: ' . $mimetype, true);
            header_remove('Cache-Control');
            header_remove('Pragma');
            header('Accept-Ranges: bytes', true);

            $byteRange = $byteLength - $byteOffset + 1;
            header('Content-Length: ' . $byteRange, true);

            if ($handle = fopen($filename, 'rb')) {
                fseek($handle, $byteOffset, SEEK_SET);
                $readTill = $byteLength;

                while (!feof($handle) && ftell($handle) <= $byteLength) {
                    echo fread($handle, 8192);
                    flush();
                    ob_flush();
                }
            }
        }
    }
}
