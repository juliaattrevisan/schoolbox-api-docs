<?php
/**
 * This represents a video that has been placed in the processing queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   2017 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;
use Schoolbox\Video\Mapper as VideoMapper;
use Schoolbox\Video\Model as Video;

class Queue extends ResourceVideo
{
    use CreatedAtDate;
    use UpdatedAtDate;

    /** Define external application names we will use */
    const APPLICATION_AVCONV  = 'avconv';
    const APPLICATION_AVPROBE = 'ffprobe';
    const APPLICATION_FFMPEG  = 'ffmpeg';
    const APPLICATION_FFPROBE = 'ffprobe';

    /** Maximum allowed timeout for processing video, 1 day */
    const MAX_TIMEOUT = 86400;

    /** Default video processing timeout if not set, 1 hour */
    const DEFAULT_TIMEOUT = 3600;

    // Mapper fields

    /** @var string $message */
    protected $message;

    /** @var User $user */
    protected $user;

    /** @var string $type */
    protected $type;

    // Internal use

    /** @var string $audioBitrate */
    private $audioBitrate;

    /** @var string $videoBitrate */
    private $videoBitrate;

    /** @var string $processTimeout Time that ffmpeg will be allowed to run before it is killed */
    private $processTimeout;

    /** @var string $converterExec Primary binary program used for conversion */
    private $converterExec = self::APPLICATION_AVCONV;

    /** @var string $fallbackConv Secondary fallback used for conversion */
    private $fallbackConv = self::APPLICATION_FFMPEG;

    /** @var string $info Primary binary used to finding out details of provided file */
    private $infoExec = self::APPLICATION_AVPROBE;

    /** @var string $fallbackInfo Secondary fallback binary for finding out details of provided file */
    private $fallbackInfo = self::APPLICATION_FFPROBE;

    /** @var array Array of temporary files created during transcode */
    private $temporaryFiles = [];

    /** @var LoggerInterface $log Allow transcode logging */
    private $log = null;

    /** @var StorageMapper $storageMapper */
    private $storageMapper;

    /** @var VideoMapper $videoMapper */
    private $videoMapper;

    /**
     * Queue constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Grab mappers

        $this->storageMapper = StorageMapper::getInstance();
        $this->videoMapper   = VideoMapper::getInstance();

        // Grab transcoding configuration

        $this->audioBitrate = $this->getConfig()->get("audio_bitrate");
        $this->videoBitrate = $this->getConfig()->get("video_bitrate");

        // Get timeout

        $timeout = $this->getConfig()->get("video_processing_timeout");

        if ($timeout <= 0) {
            $this->processTimeout = self::MAX_TIMEOUT;
        } elseif ($timeout < self::MAX_TIMEOUT) {
            $this->processTimeout = $timeout;
        } else {
            $this->processTimeout = self::DEFAULT_TIMEOUT;
        }
    }

    /**
     * Determines if file is audio or video and does corresponding processing
     *
     * @param LoggerInterface|null $logger Optional logger for debugging purposes
     *
     * @returns bool
     * @throws RuntimeException
     * @throws MapperException
     */
    public function process(LoggerInterface $logger = null)
    {
        $this->log = $logger;

        $result = false;

        try {
            if ($this->getStorage()->getMimetype()->isAudio()) {
                /** @noinspection PhpDeprecationInspection */
                $result = $this->processAudio();
            } elseif ($this->getStorage()->getMimeType()->isVideo()) {
                /** @noinspection PhpDeprecationInspection */
                $result = $this->processVideo();
            }
        } catch (RuntimeException $e) {
            // Re-throw any exception we catch
            throw $e;
        } catch (MapperException $e) {
            // Re-throw any exception we catch
            throw $e;
        } finally {
            // But always make sure we clean up our temporary files regardless of the outcome
            $this->removeTemporaryFiles();
        }

        return $result;
    }

    /**
     * Set the duration of the media by parsing commandline output
     */
    private function setDurationFromOutput()
    {
        preg_match("/Duration: ([0-9\\:]+)/", $this->getMessage(), $matches);

        if (isset($matches[1])) {
            $hours = $minutes = $seconds = 0;
            sscanf($matches[1], "%d:%d:%d", $hours, $minutes, $seconds);
            $duration = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
            $this->setDuration($duration);
        }
    }

    /**
     * Create a unique filename without creating the file like tempnam() does
     *
     * @param string $filename
     * @param string $extension
     *
     * @return string
     */
    private function getTempFilename($filename, $extension)
    {
        return sys_get_temp_dir() . "/transcoder-{$filename}-" . date("YmdHis") . ".{$extension}";
    }

    /**
     * Take audio file and transcode into MP3 for playback via HTML5 <audio> tag
     *
     * @returns bool
     * @throws RuntimeException
     * @throws MapperException
     */
    public function processAudio()
    {
        // ------------------------------------------------------------------------------------------------------------
        // Setup
        // ------------------------------------------------------------------------------------------------------------

        $filenameSource         = $this->getLocalFilename($this->getStorage());
        $filenameTranscodeAudio = $this->getTempFilename("{$this->getStorage()->getFilename()}-transcode", "mp3");

        // Remember all the temporary files we create so when we error out or finish we can unlink them

        $this->temporaryFiles[] = $filenameTranscodeAudio;

        // Get parameters

        $commandTranscode              = $this->getCommandTranscode();
        $audioBitrateEscaped           = escapeshellarg("{$this->audioBitrate}k");
        $processTimeoutEscaped         = escapeshellarg($this->processTimeout);
        $filenameSourceEscaped         = escapeshellarg($filenameSource);
        $filenameTranscodeAudioEscaped = escapeshellarg($filenameTranscodeAudio);

        // ------------------------------------------------------------------------------------------------------------
        // Transcode audio
        // ------------------------------------------------------------------------------------------------------------

        $transcodeString =
            "nice {$commandTranscode}" .
            " -y" .
            ' -nostats' .
            " -i {$filenameSourceEscaped}" .
            " -map 0:a" .
            " -c:a libmp3lame" .
            " -b {$audioBitrateEscaped}k" .
            " -ar:a 44100" .
            " -timelimit {$processTimeoutEscaped}" .
            " {$filenameTranscodeAudioEscaped}" .
            " 2>&1";

        $this->log("Transcoding audio");

        $this->executeCommand($transcodeString);

        // ------------------------------------------------------------------------------------------------------------
        // Upload transcode to storage
        // ------------------------------------------------------------------------------------------------------------

        $this->uploadTranscode($filenameTranscodeAudio);

        // Try to retrieve audio duration from command output

        $this->setDurationFromOutput();

        return true;
    }

    /**
     * Takes existing file and transcode into web format and creates thumbnail
     *
     * @return bool
     * @throws RuntimeException
     * @throws MapperException
     */
    public function processVideo()
    {
        // ------------------------------------------------------------------------------------------------------------
        // Setup
        // ------------------------------------------------------------------------------------------------------------

        $filenameSource             = $this->getLocalFilename($this->getStorage());
        $filenameTranscodeVideo     = $this->getTempFilename("{$this->getStorage()->getFilename()}-transcode", "mp4");
        $filenameTranscodeThumbnail = $this->getTempFilename("{$this->getStorage()->getFilename()}-thumbnail", "jpg");
        $filenameTranscodePoster    = $this->getTempFilename("{$this->getStorage()->getFilename()}-poster", "jpg");

        // Remember all the temporary files we create so when we error out or finish we can unlink them

        $this->temporaryFiles[] = $filenameTranscodeVideo;
        $this->temporaryFiles[] = $filenameTranscodeThumbnail;
        $this->temporaryFiles[] = $filenameTranscodePoster;

        // Get parameters

        $commandMediaInformation           = $this->getCommandMediaInformation();
        $commandTranscode                  = $this->getCommandTranscode();
        $strictMode                        = $commandTranscode == self::APPLICATION_FFMPEG ? " -strict -2 " : "";
        $videoBitrateEscaped               = escapeshellarg("{$this->videoBitrate}k");
        $audioBitrateEscaped               = escapeshellarg("{$this->audioBitrate}k");
        $processTimeoutEscaped             = escapeshellarg($this->processTimeout);
        $filenameTranscodeVideoEscaped     = escapeshellarg($filenameTranscodeVideo);
        $filenameTranscodeThumbnailEscaped = escapeshellarg($filenameTranscodeThumbnail);
        $filenameTranscodePosterEscaped    = escapeshellarg($filenameTranscodePoster);
        $filenameSourceEscaped             = escapeshellarg($filenameSource);

        // ------------------------------------------------------------------------------------------------------------
        // Get video metadata
        // ------------------------------------------------------------------------------------------------------------

        $infoCommandline =
            "{$commandMediaInformation} " .
            " -show_streams {$filenameSourceEscaped} " .
            " 2>&1";

        $infoMessage   = $this->executeCommand($infoCommandline);
        $videoMetadata = $this->getVideoMetadata($infoMessage);

        // ------------------------------------------------------------------------------------------------------------
        // Transcode video
        // ------------------------------------------------------------------------------------------------------------

        $transcodeCommandline =
            "nice {$commandTranscode}" .
            " -y" .
            ' -nostats' .
            " -i {$filenameSourceEscaped}" .
            " -c:v libx264 {$videoMetadata["videoScale"]}" .
            " -preset slow" .
            " -b:v {$videoBitrateEscaped}" .
            " -pix_fmt yuv420p " .
            " -b:a {$audioBitrateEscaped}" .
            " -r 24" .
            " -ar:a 44100" .
            " -timelimit {$processTimeoutEscaped}" .
            " {$strictMode}" .
            " -ac 2 {$filenameTranscodeVideoEscaped}" .
            " 2>&1";

        $this->log("Transcoding video");

        $this->executeCommand($transcodeCommandline);

        // Try to retrieve video duration from command output

        $this->setDurationFromOutput();

        // ------------------------------------------------------------------------------------------------------------
        // Upload transcoded video to storage and update the file size
        // ------------------------------------------------------------------------------------------------------------

        $this->uploadTranscode($filenameTranscodeVideo);

        // ------------------------------------------------------------------------------------------------------------
        // Create thumbnail & poster if the video had a valid duration, otherwise skip it
        // ------------------------------------------------------------------------------------------------------------

        if ($this->getDuration() > 0) {
            // Create thumbnail

            $thumbnailSecondsEscaped = escapeshellarg(round(($this->getDuration() / 3), 2));

            $thumbnailCommandline =
                "nice {$commandTranscode}" .
                " -y" .
                ' -nostats' .
                " -i {$filenameSourceEscaped}" .
                " -f image2 " .
                " -ss {$thumbnailSecondsEscaped}" .
                " -vf {$videoMetadata["thumbScale"]}crop=64:64" .
                " -frames:v 1" .
                " {$filenameTranscodeThumbnailEscaped}" .
                " 2>&1";

            $this->executeCommand($thumbnailCommandline);
            $this->log("Video thumbnail generated");

            // Upload thumbnail to storage

            $this->log("Uploading video thumbnail");
            $thumbnail = $this->uploadPreviewImage($filenameTranscodeThumbnail, VideoMapper::TYPE_THUMBNAIL);

            // Create poster

            $posterCommandline =
                "nice {$commandTranscode} " .
                " -y" .
                ' -nostats' .
                " -i {$filenameSourceEscaped}" .
                " -f image2 " .
                " -ss {$thumbnailSecondsEscaped}" .
                " -frames:v 1" .
                " {$filenameTranscodePosterEscaped}" .
                " 2>&1";

            $this->executeCommand($posterCommandline);
            $this->log("Video poster generated");

            // Upload poster to storage

            $this->log("Uploading video poster");
            $poster = $this->uploadPreviewImage($filenameTranscodePoster, VideoMapper::TYPE_POSTER);
        } else {
            $this->log("Skipping thumbnail and poster generation");
        }

        // ------------------------------------------------------------------------------------------------------------
        // Create record in 'video' table to link the video and its preview images
        // ------------------------------------------------------------------------------------------------------------

        try {
            /** @var Video $video */
            $video = $this->videoMapper->createModel();
            $video->setVideo($this->getStorage());

            // Set thumbnails if we generated them

            if (isset($poster) && isset($thumbnail))  {
                $video->setPoster($poster);
                $video->setThumbnail($thumbnail);
            }

            $this->videoMapper->save($video);
        } catch (MapperException $e) {
            throw new RuntimeException("Error creating video record: {$e->getMessage()}");
        }

        return true;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Storage object shenanigans
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Upload the transcode over top of the existing file in storage
     *
     * @param string $filename
     */
    private function uploadTranscode($filename) {
        $this->log("Uploading transcode to storage");
        $this->storage->getFilesystem()->update($this->storage->getPath(), file_get_contents($filename));
        $this->storage->setSize(filesize($filename));
        $this->storageMapper->save($this->storage);

        $this->log("Deleting local transcode file");
        unlink($filename);
    }

    /**
     * Returns the storage objects local filesystem filename. This path should never be assumed to be correct as
     * the file may be stored off-site in cloud storage. Test for the existence of the file before using.
     *
     * @param Storage $sourceObject
     *
     * @return string
     */
    private function getSourceLocation($sourceObject)
    {
        return $this->getConfig()->get("storage_dir") . "/" . $sourceObject->getPath();
    }

    /**
     * Take a storage object, makes sure it is stored locally (if not, it downloads it from storage to the local disk)
     * and then returns the local filename to be used when performing the various transcodes
     *
     * @param Storage $storage Storage object
     *
     * @return string Filename on disk
     */
    private function getLocalFilename(Storage $storage)
    {
        // Check if the file exists on local storage

        $mediaFilename = $this->getSourceLocation($storage);

        clearstatcache();

        if (!file_exists($mediaFilename) || filesize($mediaFilename) <= 0) {
            // Try to retrieve the media file using Flysystem (may be located off-site on S3) and copy across to the
            // local disk in a temporary file

            $this->log("Media Source Location:  Flysystem");
            $this->log("Downloading file to local disk");
            $mediaFilename = $this->getTempFilename("{$storage->getFilename()}-source", "{$storage->getExtension()}");
            $mediaFileOut  = fopen($mediaFilename, "w");
            $this->getStorage()->pipe($mediaFileOut);
            fclose($mediaFileOut);

            // Remember that we created this temporary file so we can delete it later

            $this->temporaryFiles[] = $mediaFilename;

            // If the file is zero bytes we have nothing to transcode, so bomb out

            clearstatcache();

            if (filesize($mediaFilename) <= 0) {
                throw new RuntimeException("Unable to locate media file for processing");
            }
        } else {
            $this->log("Media Source Location:  Local disk");
        }

        return $mediaFilename;
    }

    /**
     * Upload preview image to storage
     *
     * @param string $fileName Local filename to be uploaded
     * @param string $size     Size constant
     *
     * @return Storage
     */
    private function uploadPreviewImage(string $fileName, string $size)
    {
        $baseName = mb_pathinfo($this->getStorage()->getBasename(), PATHINFO_FILENAME) . "-{$size}.jpg";

        $filenameTemp = $this->getTempFilename(
            mb_pathinfo($fileName, PATHINFO_FILENAME),
            "jpg"
        );

        rename($fileName, $filenameTemp);

        try {
            return $this->storageMapper->accept($baseName, $filenameTemp);
        } catch (MapperException $e) {
            throw new RuntimeException("Error creating OTFS storage object: {$e->getMessage()}");
        } finally {
            // Remove the thumbnail
            $this->log("Deleting temporary {$size} file from disk.");
            unlink($filenameTemp);
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Functions to help determine dimensions when scaling videos and thumbnails
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Return video metadata
     *
     * @param string $infoMessage Output of the video information command line
     *
     * @return array
     */
    private function getVideoMetadata($infoMessage)
    {
        $width  = 0;
        $height = 0;

        $videoScale = "";
        $thumbScale = "";

        if (preg_match("/width=(\\d+)/", $infoMessage, $matches)) {
            list(, $width) = $matches;
        }

        if (preg_match("/height=(\\d+)/", $infoMessage, $matches)) {
            list(, $height) = $matches;
        }

        if ($width && $height) {
            list($containedWidth, $containedHeight) = $this->contain([$width, $height], [1280, 720]);
            $videoScale = sprintf("-vf scale=%d:%d", intval($containedWidth / 2) * 2, intval($containedHeight / 2) * 2);

            list($thumbWidth, $thumbHeight) = $this->cover([$width, $height], [64, 64]);
            $thumbScale = sprintf("scale=%d:%d,", intval($thumbWidth / 2) * 2, intval($thumbHeight / 2) * 2);
        }

        return [
            'width'      => $width,
            'height'     => $height,
            'videoScale' => $videoScale,
            'thumbScale' => $thumbScale,
        ];
    }

    /**
     * Ensures specified dimensions are constrained with maximums
     *
     * @param array $dimensions    [width, height]
     * @param array $maxDimensions [maxWidth, maxHeight]
     *
     * @return array [containedWidth, containedHeight]
     */
    private function contain($dimensions, $maxDimensions)
    {
        list($width, $height) = $dimensions;
        list($maxWidth, $maxHeight) = $maxDimensions;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $height = $height * ($maxWidth / $width);
            $width  = $maxWidth;
        }

        if ($maxHeight > 0 && $height > $maxHeight) {
            $width  = $width * ($maxHeight / $height);
            $height = $maxHeight;
        }

        return [$width, $height];
    }

    /**
     * Resize dimensions so that they are as small as possible while still fully covering the minimum dimensions
     *
     * @param array $dimensions      [width, height]
     * @param array $coverDimensions [coverWidth, coverHeight]
     *
     * @return array [coveredWidth, coveredHeight]
     */
    private function cover($dimensions, $coverDimensions)
    {
        // Expand dimensions, and contain to coverDimensions

        list($width, $height) = $dimensions;
        list($coverWidth, $coverHeight) = $coverDimensions;
        $newRatio = max($coverWidth / $width, $coverHeight / $height);

        return [$width * $newRatio, $height * $newRatio];
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Boring helper functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Returns the filename of the utility used to perform the media transcode
     *
     * @return string
     */
    private function getCommandTranscode()
    {
        if ($this->isCommandAvailable($this->converterExec)) {
            return $this->converterExec;
        } else {
            return $this->fallbackConv;
        }
    }

    /**
     * Returns the filename of the utility used to retrieve media information
     *
     * @return string
     */
    private function getCommandMediaInformation()
    {
        if ($this->isCommandAvailable($this->infoExec)) {
            return $this->infoExec;
        } else {
            return $this->fallbackInfo;
        }
    }

    /**
     * Tests if a given executable is available on the system
     *
     * @param string $executable
     *
     * @return bool
     */
    private function isCommandAvailable($executable)
    {
        exec("which " . escapeshellarg($executable), $unused, $result);

        return !$result;
    }

    /**
     * Helper function to execute a shell command and log the output in a readable format in 'video_queue' table
     *
     * @param string $command Command-line to execute
     *
     * @return string
     * @throws RuntimeException
     */
    private function executeCommand($command)
    {
        $outputArray = [];
        $return      = null;

        exec($command, $outputArray, $return);

        $message = $this->getMessage();

        $outputString = implode("\n", $outputArray);

        $this->setMessage("{$message}\n{$command}\n{$outputString}");

        if ($return != 0) {
            throw new RuntimeException("{$command}\n{$outputString}");
        }

        return $outputString;
    }

    /**
     * Remove any temporary files associated with the storage object that were creating during transcoding
     */
    private function removeTemporaryFiles()
    {
        foreach ($this->temporaryFiles as $filename) {
            // Suppress output of unlink as it is possible the file was never created in the first place

            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            @unlink($filename);
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Debugging functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * To allow logging during transcode
     *
     * @param $message
     */
    private function log($message)
    {
        // Make sure we actually have an instance of a logger before we try and log anything

        if ($this->log instanceof LoggerInterface) {
            $this->log->info("    - {$message}");
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Getters & Setters
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @return Storage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param Storage $storage
     *
     * @return self|$this
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     *
     * @return self|$this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     *
     * @return self|$this
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     *
     * @return self|$this
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }
}
