<?php
/**
 * Generate video thumbnail
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

require_once __DIR__ . '/../../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\BasicRequest');
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Video\Factory\Thumbnail as VideoThumbnailFactory;
use Schoolbox\Video\Mapper as VideoMapper;
use Schoolbox\Video\Model as Video;
use Schoolbox\Video\QueueMapper;

$type = $_GET['type'];
$id   = $_GET['id'];
$size = $_GET['size'];

// TODO: Destroy these shenanigans

// Handle old-school brown-hole videos- this is anything with the "hash" get parameter set (believe this is
// only used in social stream now)

if (isset($_GET['hash'])) {
    $params         = $_GET;
    $params['type'] = 'storage';
    $params['id']   = $_GET['hash'];

    unset($params['hash']);

    SitePage::localRedir('/cms/videoThumb.php?' . http_build_query($params), false, 301);
    exit;
}

if (isset($type) && isset($id)) {
    $page          = new SitePage;
    $videoMapper   = VideoMapper::getInstance();
    $storageMapper = StorageMapper::getInstance();

    try {
        $thumbnailFactory = new VideoThumbnailFactory($id, $type);
        $videoQueue       = $thumbnailFactory->produce();

        // If it has failed transcoding, display sad mountain

        if ($videoQueue->getStatus() == QueueMapper::STATUS_FAILED) {
            SitePage::localRedir("/images/core/video-failed-thumb.png");
            exit;
        }

        // If it hasn't finished processing, display please wait image

        if ($videoQueue->getStatus() != QueueMapper::STATUS_COMPLETED) {
            SitePage::localRedir("/images/core/video-processing-thumb.png");
            exit;
        }
    } catch (Exception $e) {
        // If anything goes wrong, display sad mountain
        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
    }

    try {
        // Try to find a video record with associated poster and thumbnail links

        /** @var Video $video */
        $video = $videoMapper->findOneByColumn('video_hash', $videoQueue->getStorage()->getHash());

        if ($size == VideoMapper::TYPE_POSTER) {
            $preview = $video->getPoster();
        } else {
            $preview = $video->getThumbnail();
        }

        header('Cache-Control: max-age=10800, private');
        header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
        header('Pragma: private');
        header('Content-Type: image/jpeg');
        header("Content-Length: {$preview->getSize()}");
        echo $preview->read();
        exit;
    } catch (MapperException $e) {
        // No video record could be found, try local file system

        $configMapper = ConfigMapper::getInstance();

        // Try to find in legacy location

        $fileName = "{$configMapper->get("video_dir")}/{$videoQueue->getStorage()->getHash()}";

        if ($size == VideoMapper::TYPE_POSTER) {
            $fileName .= "-poster.jpg";
        } else {
            $fileName .= "-64.jpg";
        }

        // Check if it was readable in legacy location

        if (is_readable($fileName)) {
            // Check it had a valid file size

            $fileSize = filesize($fileName);

            if ($fileSize == false) {
                SitePage::redirectToBrokenImageOfSize(64, 64, 500);
            }

            // Load it in from local disk

            header('Cache-Control: max-age=10800, private');
            header('Expires: ' . date(DATE_RFC822, strtotime(' 2 day')));
            header('Pragma: private');
            header('Content-Type: image/jpeg');
            header("Content-Length: $fileSize");
            readfile($fileName);
            exit;
        }
    } catch (Exception $e) {
        // If anything else goes wrong, fall through to sad mountain

        SitePage::redirectToBrokenImageOfSize(64, 64, 500);
        exit;
    }
}

// If goes wrong, head over to sad mountain

SitePage::redirectToBrokenImageOfSize(64, 64, 500);