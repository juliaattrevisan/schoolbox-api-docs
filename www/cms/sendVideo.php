<?php

/**
 * This page streams a video to the browser
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Exception\UnreachableException;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\File\Mimetype;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\S3\Adapter as S3Adapter;
use Schoolbox\Resource\Storage\S3\InvalidPresignedUrlDurationExecption;
use Schoolbox\Resource\Storage\S3\PreSignedUrlService;
use Schoolbox\Video\Factory\Video as VideoFactory;
use Schoolbox\Video\ResourceVideo;
use Schoolbox\Video\ResourceVideoMapper;

require_once __DIR__ . '/../../conf/core/require.php';
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
    /** @var SitePage $page */
    $page = $kernel->get('sitePage');
    $page->initSession();
    $groupHandler = new Group();

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

    // ----------------------------------------------------------------------------------------------------------------
    // Prepare for streaming
    // ----------------------------------------------------------------------------------------------------------------

    setResponseOptions();

    $storage = $video->getStorage();
    /** @var Filesystem $fileSystem */
    $fileSystem = $kernel->get('filesystem.storage');
    /** @var IConfigLoader $config */
    $config = $kernel->getDependencyInjector()->get('config');

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve mime type
    // ----------------------------------------------------------------------------------------------------------------

    if ($storage->getMimetype() instanceof Mimetype) {
        if ($storage->getMimeType()->isAudio()) {
            // If audio trancode, assume transcode format is audio/mpeg (mp3)
            $mimeType = 'audio/mpeg';
        } elseif ($storage->getMimeType()->isVideo()) {
            // If video trancode, assume transcode format is video/mp4 (mp4)
            $mimeType = 'video/mp4';
        } else {
            $mimeType = 'application/octet-stream';
        }
    } else {
        // If unknown, just flail about and choose the original file mimetype
        $mimeType = 'application/octet-stream';
    }


    // Redir to the presigned URL if it is enabled
    if (
        $fileSystem->getAdapter() instanceof S3Adapter
        && $config->get(ConfigMapper::NAME_S3_PRESIGNED_URL_ENABLED)
    ) {
        $cacheControl = ($_GET['cache'] ?? '0') ? ['private', 'must-revalidate'] : ['no-store'];

        $preSignedUrlService = new PreSignedUrlService($config);

        try {
            $preSignedUrlDataObject = $preSignedUrlService->generate(
                $video->getStorage(),
                Alaress_Date_Format::now()->addDays(1),
                $video->getTranscodePath(),
                $cacheControl,
                PreSignedUrlService::DISPOSITION_INLINE,
                $mimeType
            );
        } catch (InvalidPresignedUrlDurationExecption $e) {
            throw new UnreachableException($e);
        }

        $preSignedUrlService->download($preSignedUrlDataObject);
        exit;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve stream
    // ----------------------------------------------------------------------------------------------------------------

    // Open a video stream using the appropriate method based on the underlying storage

    // Flysystem does not allow chunking of data (given that it has to support a multitude of different underlying
    // storage structures, some of which may not support chunking). We need to work around this for S3 (and other
    // future adapters we use) and open our own stream which we can stream back to the user.
    $videoStream   = null;
    try {
        $videoStream = $fileSystem->readStream($video->getTranscodePath());
    } catch (FileNotFoundException $e) {
        fail();
    }
    if (!is_resource($videoStream)) {
        fail();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Retrieve video size
    // ----------------------------------------------------------------------------------------------------------------

    /** @var int $videoSize */
    $videoSize = $fileSystem->getSize($video->getTranscodePath());

    // Make sure the video actually had content

    if ($videoSize <= 0) {
        fail();
    }


    // ----------------------------------------------------------------------------------------------------------------
    // Determine range of request for streaming protocol
    // ----------------------------------------------------------------------------------------------------------------

    $byteOffset = 0;
    $byteLength = $videoSize;

    // Set HTTP headers
    header_remove("Cache-Control");
    header_remove("Pragma");
    header("Content-Type: {$mimeType}", true);
    header("Accept-Ranges: bytes", true);

    if (!isset($_SERVER['HTTP_RANGE'])) {
        // Just stream the whole video
        header("Content-Length: {$byteLength}", true);
        fpassthru($videoStream);
        exit;
    }

    // Stream a range
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

    // Output content length header
    $byteRange = $byteLength - $byteOffset + 1;
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
 * Retrieve the path where the video would be stored on local filesystem. Returns false if the file is not readable.
 *
 * @param ResourceVideo $video
 * @param IConfigLoader $config
 *
 * @return string|bool
 */
function getLocalFilesystemPath(ResourceVideo $video, IConfigLoader $config)
{
    $filename = $config->get("storage_dir") . '/' . $video->getTranscodePath();

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
