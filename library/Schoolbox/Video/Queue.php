<?php

/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video;

use League\Flysystem\FilesystemAdapter;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Resource\Storage\Transform;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Queue
 *
 * This represents a video that has been placed in the processing queue
 *
 * @package Schoolbox\Video
 */
class Queue extends ResourceVideo
{
    use CreatedAtDate;
    use UpdatedAtDate;

    /** Define external application names we will use */
    private const APPLICATION_FFMPEG = 'ffmpeg';
    private const APPLICATION_FFPROBE = 'ffprobe';

    /** Maximum allowed timeout for processing video, 1 day */
    const MAX_TIMEOUT = 86400;

    /** Default video processing timeout if not set, 1 hour */
    const DEFAULT_TIMEOUT = 3600;

    // Mapper fields

    /** @var string $message */
    protected $message;

    /**
     * We may or may not know the user who's action resulted in this entity being created.
     *
     * @var null|User $user
     */
    private $user;

    /** @var string $type */
    protected $type;

    // Internal use

    /** @var string $audioBitrate */
    private $audioBitrate;

    /** @var string $videoBitrate */
    private $videoBitrate;

    /** @var int $processTimeout Time that ffmpeg will be allowed to run before it is killed */
    private $processTimeout;

    /** @var string $converterExec Primary binary program used for conversion */
    private $converterExec =  self::APPLICATION_FFMPEG;

    /** @var string $info Primary binary used to finding out details of provided file */
    private $infoExec = self::APPLICATION_FFPROBE;

    /** @var array Array of temporary files created during transcode */
    private $temporaryFiles = [];

    /** @noinspection PhpUndefinedClassInspection */
    /** @var LoggerInterface $log Allow transcode logging */
    private $log = null;

    /** @var StorageMapper $storageMapper */
    private $storageMapper;

    /**
     * Queue constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Grab mappers

        $this->storageMapper = StorageMapper::getInstance();
    }

    /**
     * Determines if file is audio or video and does corresponding processing
     *
     * @param FilesystemAdapter    $filesystem Filesystem to write transcodes to
     * @param LoggerInterface|null $logger     Optional logger for debugging purposes
     *
     * @return bool
     *
     * @throws MapperException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function process(FilesystemAdapter $filesystem, LoggerInterface $logger = null)
    {
        // Grab transcoding configuration

        $this->audioBitrate = $this->getConfig()->get("audio_bitrate");
        $this->videoBitrate = $this->getConfig()->get("video_bitrate");

        // Get timeout

        $timeout = (int)$this->getConfig()->get("video_processing_timeout");

        if ($timeout <= 0) {
            $this->processTimeout = self::MAX_TIMEOUT;
        } elseif ($timeout < self::MAX_TIMEOUT) {
            $this->processTimeout = $timeout;
        } else {
            $this->processTimeout = self::DEFAULT_TIMEOUT;
        }

        $this->log = $logger;

        $result = false;

        try {
            if ($this->getStorage()->getMimetype()->isAudio()) {
                $result = $this->processAudio($filesystem);
            } elseif ($this->getStorage()->getMimeType()->isVideo()) {
                $result = $this->processVideo($filesystem);
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
     * @param FilesystemAdapter $filesystem Filesystem to write transcodes to
     * @return bool
     *
     * @throws MapperException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function processAudio(FilesystemAdapter $filesystem)
    {
        // ------------------------------------------------------------------------------------------------------------
        // Setup
        // ------------------------------------------------------------------------------------------------------------

        $filenameSource         = $this->getLocalFilename($this->getStorage());
        $filenameTranscodeAudio = $this->getTempFilename($this->getStorage()->getHash(), "mp3");

        // Remember all the temporary files we create so when we error out or finish we can unlink them

        $this->temporaryFiles[] = $filenameTranscodeAudio;

        // Get parameters

        $cpus = (int)shell_exec('nproc');
        $commandTranscode              = $this->getCommandTranscode();
        $audioBitrateEscaped           = escapeshellarg("{$this->audioBitrate}k");
        $processTimeoutEscaped         = escapeshellarg($this->processTimeout * $cpus);
        $filenameSourceEscaped         = escapeshellarg($filenameSource);
        $filenameTranscodeAudioEscaped = escapeshellarg($filenameTranscodeAudio);

        // ------------------------------------------------------------------------------------------------------------
        // Transcode audio
        // ------------------------------------------------------------------------------------------------------------

        $transcodeString =
            "nice {$commandTranscode}" .
            ' -y' .
            ' -nostats' .
            ' -hide_banner' .
            " -i {$filenameSourceEscaped}" .
            ' -map 0:a' .
            " -b {$audioBitrateEscaped}" .
            ' -ar:a 44100' .  //44Khz sampling
            ' -ac 2' .   //Stereo audio
            " -timelimit {$processTimeoutEscaped}" .
            " {$filenameTranscodeAudioEscaped}" .
            ' 2>&1';

        $this->log("Transcoding audio");

        $this->executeCommand($transcodeString);

        // ------------------------------------------------------------------------------------------------------------
        // Upload transcode to storage
        // ------------------------------------------------------------------------------------------------------------

        $this->uploadTranscode($filesystem, $filenameTranscodeAudio);

        // Try to retrieve audio duration from command output

        $this->setDurationFromOutput();

        return true;
    }

    /**
     * Takes existing file and transcode into web format and creates thumbnail
     *
     * @param FilesystemAdapter $filesystem Filesystem to write transcodes to
     * @return bool
     *
     * @throws MapperException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function processVideo(FilesystemAdapter $filesystem)
    {
        // ------------------------------------------------------------------------------------------------------------
        // Setup
        // ------------------------------------------------------------------------------------------------------------

        $filenameSource             = $this->getLocalFilename($this->getStorage());
        $filenameTranscodeVideo     = $this->getTempFilename($this->getStorage()->getHash(), "mp4");
        $filenameTranscodeThumbnail = $this->getTempFilename($this->getStorage()->getHash() . '-thumbnail', "jpg");
        $filenameTranscodePoster    = $this->getTempFilename($this->getStorage()->getHash() . '-poster', "jpg");

        // Remember all the temporary files we create so when we error out or finish we can unlink them

        $this->temporaryFiles[] = $filenameTranscodeVideo;
        $this->temporaryFiles[] = $filenameTranscodeThumbnail;
        $this->temporaryFiles[] = $filenameTranscodePoster;

        // Get parameters

        $cpus = (int)shell_exec('nproc');
        $commandMediaInformation           = $this->getCommandMediaInformation();
        $commandTranscode                  = $this->getCommandTranscode();
        $strictMode                        = " -strict -2 ";
        $videoBitrateEscaped               = escapeshellarg("{$this->videoBitrate}k");
        $audioBitrateEscaped               = escapeshellarg("{$this->audioBitrate}k");
        $processTimeoutEscaped             = escapeshellarg($this->processTimeout * $cpus);
        $filenameTranscodeVideoEscaped     = escapeshellarg($filenameTranscodeVideo);
        $filenameTranscodeThumbnailEscaped = escapeshellarg($filenameTranscodeThumbnail);
        $filenameTranscodePosterEscaped    = escapeshellarg($filenameTranscodePoster);
        $filenameSourceEscaped             = escapeshellarg($filenameSource);

        // ------------------------------------------------------------------------------------------------------------
        // Get video metadata
        // ------------------------------------------------------------------------------------------------------------

        $infoCommandline =
            "{$commandMediaInformation} " .
            ' -hide_banner' .
            " -show_streams {$filenameSourceEscaped} " .
            ' 2>&1';

        $infoMessage   = $this->executeCommand($infoCommandline);
        $videoMetadata = $this->getVideoMetadata($infoMessage);

        // ------------------------------------------------------------------------------------------------------------
        // Transcode video
        // ------------------------------------------------------------------------------------------------------------

        $transcodeCommandline =
            "nice {$commandTranscode}" .
            ' -y' .
            ' -nostats' .
            ' -hide_banner' .
            " -i {$filenameSourceEscaped}" .
            ' -max_muxing_queue_size 1024' .  //Set queue size larger to work around bug in some videos
            " -c:v libx264 {$videoMetadata["videoScale"]}" .
            ' -preset slow' .
            " -b:v {$videoBitrateEscaped}" .
            ' -pix_fmt yuv420p ' . //Colour depth yuv420p for compat
            ' -movflags faststart ' . //Move meta data to start of file for streaming
            ' -profile:v high ' . //High profile, not compatible with iPhone 3 but that is ancient
            ' -r 30' . // 30 fps
            ' -c:a aac' . // AAC encoding
            " -b:a {$audioBitrateEscaped}" .
            ' -ar:a 44100' . //44Khz audio
            ' -ac 2' .   //Stereo audio
            " -timelimit {$processTimeoutEscaped}" .
            " {$strictMode}" .
            " {$filenameTranscodeVideoEscaped}" .
            ' 2>&1';

        $this->log("Transcoding video");

        $this->executeCommand($transcodeCommandline);

        // Try to retrieve video duration from command output

        $this->setDurationFromOutput();

        // ------------------------------------------------------------------------------------------------------------
        // Upload transcoded video to storage and update the file size
        // ------------------------------------------------------------------------------------------------------------

        $this->uploadTranscode($filesystem, $filenameTranscodeVideo);

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
                ' -hide_banner' .
                " -i {$filenameSourceEscaped}" .
                ' -max_muxing_queue_size 1024' .
                ' -f image2 ' .
                " -ss {$thumbnailSecondsEscaped}" .
                " -vf {$videoMetadata["thumbScale"]}crop=64:64" .
                ' -frames:v 1' .
                " {$filenameTranscodeThumbnailEscaped}" .
                ' 2>&1';

            $this->executeCommand($thumbnailCommandline);
            $this->log("Video thumbnail generated");

            // Upload thumbnail to storage

            $this->log("Uploading video thumbnail");
            $this->uploadPreviewImage($filesystem, $filenameTranscodeThumbnail, $this->getThumbnailPath());

            // Create poster

            $posterCommandline =
                "nice {$commandTranscode} " .
                ' -y' .
                ' -nostats' .
                ' -hide_banner' .
                " -i {$filenameSourceEscaped}" .
                ' -max_muxing_queue_size 1024' .
                ' -f image2 ' .
                " -ss {$thumbnailSecondsEscaped}" .
                ' -frames:v 1' .
                " {$filenameTranscodePosterEscaped}" .
                ' 2>&1';

            $this->executeCommand($posterCommandline);
            $this->log("Video poster generated");

            // Upload poster to storage

            $this->log("Uploading video poster");
            $this->uploadPreviewImage($filesystem, $filenameTranscodePoster, $this->getPosterPath());
        } else {
            $this->log("Skipping thumbnail and poster generation");
        }

        return true;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Storage object shenanigans
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @param FilesystemAdapter $filesystem Filesystem to write transcodes to
     * @param string $filename
     */
    private function uploadTranscode(FilesystemAdapter $filesystem, $filename)
    {
        $this->log('Saving transcode to storage');
        $filesystem->put($this->getTranscodePath(), file_get_contents($filename));

        $this->log('Deleting local transcode file');
        unlink($filename);
    }


    /**
     * Returns the storage objects local filesystem filename. This path should never be assumed to be correct as
     * the file may be stored off-site in cloud storage. Test for the existence of the file before using.
     *
     * @param Storage $sourceObject
     * @return string
     */
    private function getSourceLocation($sourceObject)
    {
        return $this->getConfig()->get('storage_dir') . '/' . FilesystemProxy::getPathFromHash($sourceObject->getHash());
    }


    /**
     * Take a storage object, makes sure it is stored locally (if not, it downloads it from storage to the local disk)
     * and then returns the local filename to be used when performing the various transcodes
     *
     * @param Storage $storage Storage object
     * @return string Filename on disk
     * @throws RuntimeException
     */
    private function getLocalFilename(Storage $storage)
    {
        // Check if the file exists on local storage

        $mediaFilename = $this->getSourceLocation($storage);

        if (!file_exists($mediaFilename) || filesize($mediaFilename) <= 0) {
            // Try to retrieve the media file using Flysystem (may be located off-site on S3) and copy across to the
            // local disk in a temporary file

            $this->log('Media Source Location:  Flysystem');
            $this->log('Downloading file to local disk');
            $mediaFilename = $this->getTempFilename("{$storage->getHash()}-source", $storage->getExtension());
            $mediaFileOut  = fopen($mediaFilename, "w");
            stream_copy_to_stream(
                $this->storageMapper->getStorageFilesystemProxy()->readStream($this->getStorage()),
                $mediaFileOut,
            );
            fclose($mediaFileOut);

            // Remember that we created this temporary file so we can delete it later

            $this->temporaryFiles[] = $mediaFilename;

            // If the file is zero bytes we have nothing to transcode, so bomb out
            if (filesize($mediaFilename) <= 0) {
                throw new RuntimeException('Unable to locate media file for processing');
            }
        } else {
            $this->log('Media Source Location:  Local disk');
        }

        return $mediaFilename;
    }


    /**
     * Upload preview image to storage
     *
     * @param FilesystemAdapter $filesystem      Filesystem to write transcodes to
     * @param string            $fileName        Local filename to be uploaded
     * @param string            $destinationPath Destination to which the image should be uploaded
     * @throws \League\Flysystem\FileNotFoundException
     * @throws MapperException
     */
    private function uploadPreviewImage(
        FilesystemAdapter $filesystem,
        string $fileName,
        string $destinationPath
    ): void {

        // If it already exists we have to update, otherwise write

        if ($filesystem->has($destinationPath)) {
            $filesystem->update($destinationPath, file_get_contents($fileName));
        } else {
            $filesystem->write($destinationPath, file_get_contents($fileName));
        }

        $this->createStorageTransform($destinationPath, filesize($fileName));
        // Remove the thumbnail

        $this->log('filename: ' . $destinationPath);

        $this->log("Deleting temporary preview image file from disk.");
        unlink($fileName);
    }

    /**
     * @param string   $fileName
     * @param int|null $fileSize
     * @throws MapperException
     * @throws UnexpectedValueException If $fileName is not of expected structure
     */
    private function createStorageTransform(string $fileName, ?int $fileSize): void
    {
        $fileNameParts = explode('-', basename($fileName));
        if (count($fileNameParts) !== 2) {
            throw new UnexpectedValueException("Expected 2 parts, found " . count($fileNameParts));
        }
        [$hash, $cacheKey] = $fileNameParts;
        $transformMapper = Transform\Mapper::getInstance();

        $transformModel = $transformMapper->findByHashAndCacheKey($hash, $cacheKey);

        if ($transformModel) {
            $transformMapper->save($transformModel->setSize($fileSize)->setState(Transform\Model::STATE_DONE));
        } else {
            $transformMapper->createRecord($hash, $cacheKey, $fileSize, Transform\Model::STATE_DONE);
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Functions to help determine dimensions when scaling videos and thumbnails
    // ----------------------------------------------------------------------------------------------------------------


    /**
     * Return video metadata
     *
     * @param string $infoMessage Output of the video information command line
     * @return array
     */
    private function getVideoMetadata($infoMessage)
    {
        $width  = 0;
        $height = 0;

        $videoScale = '';
        $thumbScale = '';


        if (preg_match("/width=(\\d+)/", $infoMessage, $matches)) {
            $width = (int)$matches[1];
        }

        if (preg_match("/height=(\\d+)/", $infoMessage, $matches)) {
            $height = (int)$matches[1];
        }

        if (preg_match("/rotate=(\\d+)/", $infoMessage, $matches)) {
            $rotation = (int)$matches[1];
            if ($rotation === 90 || $rotation === 270) {
                $temp = $height;
                $height = $width;
                $width = $temp;
            }
        }


        if ($width && $height) {
            [$containedWidth, $containedHeight] = $this->contain([$width, $height], [1280, 720]);
            $videoScale = sprintf("-vf scale=%d:%d", $containedWidth, $containedHeight);

            [$thumbWidth, $thumbHeight] = $this->cover([$width, $height], [64, 64]);
            $thumbScale = sprintf("scale=%d:%d,", $thumbWidth, $thumbHeight);
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
     * @return array [containedWidth, containedHeight]
     */
    private function contain($dimensions, $maxDimensions)
    {
        [$width, $height] = $dimensions;
        [$maxWidth, $maxHeight] = $maxDimensions;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $height = $height * ($maxWidth / $width);
            $width  = $maxWidth;
        }

        if ($maxHeight > 0 && $height > $maxHeight) {
            $width  = $width * ($maxHeight / $height);
            $height = $maxHeight;
        }

        return [(int)(ceil($width / 2) * 2), (int)(ceil($height / 2) * 2)];
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

        [$width, $height] = $dimensions;
        [$coverWidth, $coverHeight] = $coverDimensions;
        $newRatio = max($coverWidth / $width, $coverHeight / $height);

        return [(int)(ceil(($width * $newRatio) / 2) * 2), (int)(ceil(($height * $newRatio) / 2) * 2)];
    }


    // ----------------------------------------------------------------------------------------------------------------
    // Boring helper functions
    // ----------------------------------------------------------------------------------------------------------------


    /**
     * Returns the filename of the utility used to perform the media transcode
     *
     * @return null|string
     */
    private function getCommandTranscode(): ?string
    {
        if ($this->isCommandAvailable($this->converterExec)) {
            return $this->converterExec;
        }

        return null;
    }


    /**
     * Returns the filename of the utility used to retrieve media information
     *
     * @return null|string
     */
    private function getCommandMediaInformation(): ?string
    {
        if ($this->isCommandAvailable($this->infoExec)) {
            return $this->infoExec;
        }

        return null;
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
        exec('which ' . escapeshellarg($executable), $unused, $result);

        return !$result;
    }


    /**
     * Helper function to execute a shell command and log the output in a readable format in 'video_queue' table
     *
     * @param string $command Command-line to execute
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

        $this->setMessage("{$message}\n{$command}\n{$outputString}\n----\n");

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
     * @param string $message
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
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param null|User $user
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
