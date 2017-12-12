<?php
/**
 * This represents a video that has been placed in the processing queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Queue extends ResourceVideo
{
    use CreatedAtDate;
    use UpdatedAtDate;

    /** @var string */
    protected $message;

    /** @var string */
    private $type;

    /** @var string */
    private $audioBitrate;

    /** @var string */
    private $videoBitrate;

    /**
     * Time that ffmpeg will be allowed to run before it is killed
     * @var string
     */
    private $processTimeout;

    /**
     * Primary binary program used for conversion
     * @var string
     */
    private $converterExec  = 'avconv';

    /**
     * Secondary fallback used for conversion, found on systems other than ubuntu (like rhel)
     * @var string
     */
    private $fallbackConv   = 'ffmpeg';

    /**
     * Primary binary used to finding out details of provided file
     * @var string
     */
    private $infoExec       = 'avprobe';

    /**
     * Secondary fallback binary for finding out details of provided file
     * @var string
     */
    private $fallbackInfo   = 'ffprobe';

    /**
     * Maximum allowed timeout for processing video, 1 day
     */
    const MAX_TIMEOUT = 86400;

    /**
     * Default video processing timeout if not set, 1 hour
     */
    const DEFAULT_TIMEOUT = 3600;

    /**
     * Queue constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // Grab bitrates from config

        $this->audioBitrate = $this->getConfig()->get('audio_bitrate');
        $this->videoBitrate = $this->getConfig()->get('video_bitrate');
        $timeout = $this->getConfig()->get('video_processing_timeout');
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
     * @returns boolean
     * @throws SchoolboxException
     */
    public function process()
    {
        if ($this->getStorage()->getMimetype()->isAudio()){
            return $this->processAudio($this->getSourceLocation($this->getStorage()));
        }
        else if($this->getStorage()->getMimeType()->isVideo()){
            return $this->processVideo($this->getSourceLocation($this->getStorage()));
        }

        return false;
    }


    /**
     * Takes audio file and transcodes into mp3 for playback via <audio>
     *
     * @param $sourceFile
     * @returns boolean
     * @throws \RuntimeException
     */
    public function processAudio($sourceFile)
    {
        $executable = $this->getConverterExecutable();

        $processString = "nice $executable -y -i " . escapeshellarg($sourceFile) . " -map 0:a -c:a libmp3lame -b {$this->audioBitrate}k -ar:a 44100"
            . ' -timelimit ' . escapeshellarg($this->processTimeout) . ' '
            . escapeshellarg($this->getAudioLocation()) . ' 2>&1';

        exec($processString, $message, $return);
        $this->setMessage($processString . "\n" . implode("\n", $message));

        preg_match('/Duration: ([0-9\:]+)/', $this->getMessage(), $match);

        if (isset($match[1])) {
            $hours = $minutes = $seconds = 0;
            sscanf($match[1], "%d:%d:%d", $hours, $minutes, $seconds);
            $duration = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
            $this->setDuration($duration);
        }

        if ($return == 0) {
            return true;
        } else {
            throw new \RuntimeException($processString . "\n" . implode("\n",$message));
        }

    }

    /**
     * Takes existing file and transcodes into web format and creates thumbnail
     *
     * @param $sourceFile
     * @returns boolean
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     */

    public function processVideo($sourceFile)
    {
        $executable = $this->getConverterExecutable();
        $info_exec  = $this->getInfoExecutable();

        // FFMpeg needs strict flag, as MP4 is technically experimental
        if($executable == $this->fallbackConv) {
            $strict = true;
        }
        else {
            $strict = false;
        }

        // Get information about width and height of video file
        exec("$info_exec -show_streams " . escapeshellarg($sourceFile) . ' 2>&1', $info_message, $info_return);
        $info_message = implode("\n", $info_message);
        if (!($info_return == 0)) {
            throw new \UnexpectedValueException("Unable to determine video size: $info_message");
        }
        $width  = 0;
        $height = 0;
        $scale  = '';
        if ($info_return == 0) {
            if (preg_match('/width=(\\d+)/', $info_message, $matches)) {
                list(, $width) = $matches;
            }
            if (preg_match('/height=(\\d+)/', $info_message, $matches)) {
                list(, $height) = $matches;
            }
        }

        // Get a scale factor for this video
        if ($width && $height) {
            // Max size: 1280x720
            list($containedWidth, $containedHeight) = $this->contain(
                array($width, $height),
                array(1280, 720)
            );
            $scale = sprintf(
                '-vf scale=%d:%d',
                intval($containedWidth / 2) * 2,
                intval($containedHeight / 2) * 2
            );
        }

        /* Process video to 512kb in libx264 format for mobile and html5 streaming */
        $processString = "nice $executable -y -i " . escapeshellarg($sourceFile) .
            " -c:v libx264 $scale -preset slow -b:v {$this->videoBitrate}k -pix_fmt yuv420p -b:a {$this->audioBitrate}k -r 24 -ar:a 44100" .
            " -timelimit " . escapeshellarg($this->processTimeout) .
            ' -nostats' .
            ($strict ? ' -strict -2' : '') .
            ' -ac 2 ' . escapeshellarg($this->getVideoLocation()) . ' 2>&1';

        exec($processString, $message, $return);
        $this->setMessage($processString . "\n" . implode("\n", $message));

        preg_match('/Duration: ([0-9\:]+)/', $this->getMessage(), $match);

        if (isset($match[1])) {
            $hours = $minutes = $seconds = 0;
            sscanf($match[1], "%d:%d:%d", $hours, $minutes, $seconds);
            $duration = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
            $this->setDuration($duration);
        }

        /* Generate thumbnail of video 64x64 pixels, 1/3rd of the video in, center of frame */
        $thumbScale = '';
        if ($width && $height) {
            list($thumbWidth, $thumbHeight) = $this->cover(
                array($width, $height),
                array(64, 64)
            );
            $thumbScale = sprintf(
                'scale=%d:%d,',
                intval($thumbWidth / 2) * 2,
                intval($thumbHeight / 2) * 2
            );
        }
        $thumbnailSeconds = $this->getDuration() > 0 ? round(($this->getDuration() / 3), 2) : 10;
        $thumbnailString  = "nice $executable -y -i " . escapeshellarg($sourceFile) .
            ' -f image2 -ss ' . $thumbnailSeconds .
            ' -vf '.$thumbScale.'crop=64:64'.
            ' -nostats' .
            ' -frames:v 1 ' .
            escapeshellarg($this->getThumbnailLocation()) . ' 2>&1';
        $posterString     = "nice $executable -y -i " . escapeshellarg($sourceFile) .
            ' -f image2 -ss ' . $thumbnailSeconds .
            ' -nostats' .
            ' -frames:v 1 ' .
            escapeshellarg($this->getPosterLocation()) . ' 2>&1';

        if ($return == 0) {
            exec($thumbnailString, $output, $return);
            $this->setMessage($this->getMessage() . "\n" . $thumbnailString . "\n" . implode("\n", $output));
            if ($return != 0) {
                throw new \RuntimeException($thumbnailString . "\n" . implode("\n",$output));
            }

            exec($posterString, $output, $return);
            $this->setMessage($this->getMessage() . "\n" . $posterString . "\n" . implode("\n", $output));
            if ($return == 0) {
                return true;
            } else {
                throw new \RuntimeException($posterString . "\n" . implode("\n",$output));
            }
        }
        throw new \RuntimeException($processString . "\n" . implode("\n",$message));


    }

    /**
     * This returns the location of the original file on disk
     * This has been deprecated as it needs to be replaced by a Flysystem equivalent
     *
     * @param Storage $sourceObject
     * @return string
     * @deprecated
     */
    private function getSourceLocation($sourceObject)
    {
        return $this->getConfig()->get('storage_dir'). '/' . $sourceObject->getPath();
    }

    /**
     * Returns converter executable
     * @return string
     */
    private function getConverterExecutable()
    {
        if($this->isExecAvailable($this->converterExec)){
            return $this->converterExec;
        }
        else {
            return $this->fallbackConv;
        }
    }

    /**
     * Returns info retrieval executable
     * @return string
     */
    private function getInfoExecutable() {
        if($this->isExecAvailable($this->infoExec)) {
            return $this->infoExec;
        }
        else {
            return $this->fallbackInfo;
        }
    }

    /**
     * Tests is a given executable is available on the system
     * @param $executable
     * @return bool
     */
    private function isExecAvailable($executable)
    {
        exec('which '.escapeshellarg($executable), $unused, $errorcode);
        if ($errorcode) {
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Ensures $dimensions are contained within $maxDimensions
     *
     * @param array $dimensions    [width, height]
     * @param array $maxDimensions [maxWidth, maxHeight]
     *
     * @return array [containedWidth, containedHeight]
     */
    protected function contain($dimensions, $maxDimensions)
    {
        list($width, $height)       = $dimensions;
        list($maxWidth, $maxHeight) = $maxDimensions;
        if ($maxWidth > 0 && $width > $maxWidth) {
            $height = $height * ($maxWidth / $width);
            $width  = $maxWidth;
        }
        if ($maxHeight > 0 && $height > $maxHeight) {
            $width  = $width * ($maxHeight / $height);
            $height = $maxHeight;
        }
        return array($width, $height);
    }

    /**
     * Resizes $dimensions so that they are as small as possible while still
     * fully covering $minDimensions.
     *
     * @param array $dimensions      [width, height]
     * @param array $coverDimensions [coverWidth, coverHeight]
     *
     * @return array [coveredWidth, coveredHeight]
     */
    protected function cover($dimensions, $coverDimensions)
    {
        // Expand $dimensions, then contain it to $coverDimensions
        list($width, $height)           = $dimensions;
        list($coverWidth, $coverHeight) = $coverDimensions;
        $newRatio = max($coverWidth / $width, $coverHeight / $height);
        return array($width * $newRatio, $height * $newRatio);
    }

    /** Here be the getters/setters, nothing else to see here */

    /**
     * @return Storage
     */
    public function getStorage() {
        return $this->storage;
    }

    /**
     * @param IStorage $storage
     * @return $this
     */
    public function setStorage($storage) {
        $this->storage = $storage;
        return $this;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @param string $type
     * @return $this
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }    


}
