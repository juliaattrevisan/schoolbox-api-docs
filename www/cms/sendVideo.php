<?php
/**
 * This page streams a video to the browser
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\S3\Stream as S3Stream;
use Schoolbox\Video\Factory\Video as VideoFactory;
use Schoolbox\Video\ResourceVideoMapper;

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

// --------------------------------------------------------------------------------------------------------------------
// Brownhole
// --------------------------------------------------------------------------------------------------------------------

if (isset($_GET['hash'])) {
    $params = [
        'type' => 'storage',
        'id'   => $_GET['hash'],
    ];

    SitePage::localRedir('/cms/sendVideo.php?' . http_build_query($params), false, 301);
}

// --------------------------------------------------------------------------------------------------------------------
// OTFS
// --------------------------------------------------------------------------------------------------------------------

$type = $_GET['type'];
$id   = $_GET['id'];

if (isset($type) && isset($id)) {
    $page         = new SitePage;
    $groupHandler = new Group;

    // ----------------------------------------------------------------------------------------------------------------
    // Grab mappers
    // ----------------------------------------------------------------------------------------------------------------

    $fileMapper       = FileMapper::getInstance();
    $videoQueueMapper = ResourceVideoMapper::getInstance();

    // ----------------------------------------------------------------------------------------------------------------
    // Get video storage object
    // ----------------------------------------------------------------------------------------------------------------

    try {
        $videoFactory = new VideoFactory($id, $type);
        $video        = $videoFactory->produce();
    } catch (MapperException $e) {
        fail();
    }

    /** @noinspection PhpUndefinedVariableInspection */
    $storage = $video->getStorage();

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve stream
    // ----------------------------------------------------------------------------------------------------------------

    $videoStream = getStream($storage);

    if (!$videoStream) {
        fail();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve video size
    // ----------------------------------------------------------------------------------------------------------------

    try {
        /** @var int $videoSize */
        $videoSize = $storage->getSize();

        // Make sure the video actually had content

        if ($videoSize <= 0) {
            fail();
        }
    } catch (FileNotFoundException $e) {
        fail();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve mime type
    // ----------------------------------------------------------------------------------------------------------------

    /** @var string $mimeType */
    $mimeType = $storage->getMimeType()->getMime();

    // ----------------------------------------------------------------------------------------------------------------
    // Prepare for streaming
    // ----------------------------------------------------------------------------------------------------------------

    setResponseOptions();

    // ----------------------------------------------------------------------------------------------------------------
    // Determine range of request for streaming protocol
    // ----------------------------------------------------------------------------------------------------------------

    $byteOffset = 0;
    $byteLength = $videoSize;

    if ($_SERVER['HTTP_RANGE']) {
        $ranges = explode(',', substr($_SERVER['HTTP_RANGE'], 6));
        $parts  = explode('-', $ranges[0]);

        // Offset signifies where we should begin to read the file

        $byteOffset = (int)$parts[0];

        // Length is how long we should read the file according to the browser, and can never go beyond the file size

        if (isset($parts[1]) && $parts[1] != '' && $parts[1] < $videoSize) {
            $byteLength = (int)$parts[1];
        } else {
            $byteLength = $videoSize - 1;
        }

        // Make sure the requested range is satisfiable

        if ($byteOffset > $byteLength) {
            http_response_code(416);
            header("Content-Range: bytes */{$videoSize}");
            exit;
        }

        // Partial content

        http_response_code(206);

        // Decrease by 1 on byte-length since this definition is zero-based index of bytes being sent

        header(sprintf('Content-Range: bytes %d-%d/%d', $byteOffset, $byteLength, $videoSize));
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Set HTTP headers
    // ----------------------------------------------------------------------------------------------------------------

    $byteRange = $byteLength - $byteOffset + 1;

    header_remove("Cache-Control");
    header_remove("Pragma");

    header("Content-Type: {$mimeType}", true);
    header("Accept-Ranges: bytes", true);
    header("Content-Length: {$byteRange}", true);

    fseek($videoStream, $byteOffset, SEEK_SET);
    $readTill = $byteLength;

    // ----------------------------------------------------------------------------------------------------------------
    // Stream the video
    // ----------------------------------------------------------------------------------------------------------------

    while (!feof($videoStream) && ftell($videoStream) <= $byteLength) {
        $sendLength = $byteLength < 8192 ? $byteLength : 8192;
        echo fread($videoStream, $sendLength);
        flush();
        ob_flush();
    }
}

/**
 * Disable compression and multi-byte encoding in output
 */
function setResponseOptions()
{
    // Close session so streaming does not prevent other requests to session due to locking

    session_write_close();

    // Prevent multi-byte encoding as it might mess with video stream

    mb_http_output("pass");

    // Disable output compression

    ini_set('zlib.output_compression', 0);

    if (substr_count(strtolower(php_sapi_name()), 'apache') > 0) {
        apache_setenv('no-gzip', 1);
    }
}

/**
 * Retrieve a chunkable stream from the given storage object
 *
 * @param Storage $storage
 *
 * @return bool|resource
 */
function getStream(Storage $storage)
{
    /** @var Filesystem $fileSystem */
    $fileSystem = $storage->getFilesystem();

    // Open a video stream using the appropriate method based on the underlying storage

    // Flysystem does not allow chunking of data (given that it has to support a multitude of different underlying
    // storage structures, some of which may not support chunking). We need to work around this for S3 (and other
    // future adapters we use) and open our own stream which we can stream back to the user.

    $videoStream = null;

    if ($fileSystem->getAdapter() instanceof AwsS3Adapter) {
        // AWS S3

        // Check for a transcode and use that if available, otherwise use the original video

        /** @var FilesystemInterface $fileSystem */
        $fileSystem = $storage->getFilesystem();

        if ($fileSystem->has($storage->getPath() . "-transcode")) {
            $storage->setPath($storage->getPath() . "-transcode");
            $storage->setSize($fileSystem->getSize($storage->getPath() . "-transcode"));
        }

        $videoStream = S3Stream::open($storage);
    } elseif (($localFilename = getLocalFilesystemPath($storage)) !== false) {
        // Local Disk

        // Check for a transcode and use that if available, otherwise use the original video

        if (file_exists($localFilename . "-transcode")) {
            $localFilename .= "-transcode";
        }

        $videoStream = fopen($localFilename, "r");
    }

    if (is_resource($videoStream)) {
        return $videoStream;
    }

    // Couldn't retrieve a valid stream

    return false;
}

/**
 * Retrieve the path where the video would be stored on local filesystem. Returns false if the file is not readable.
 *
 * @param Storage $storage
 *
 * @return string|bool
 */
function getLocalFilesystemPath(Storage $storage)
{
    $filename = ConfigMapper::getInstance()->get("storage_dir") . "/{$storage->getPath()}";

    if (is_readable($filename)) {
        return $filename;
    }

    return false;
}

/**
 * If we are unable to stream the video this is where we will end up, for now it just fails. We may want to change this
 * to something more meaningful or a placeholder failed video.
 */
function fail()
{
    exit;
}
