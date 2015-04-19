<?php
/**
 * This represents a video that has been placed in the processing queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

class Queue extends ResourceVideo
{

    protected $id;
    protected $fileID;
    protected $type;
    protected $hash;
    protected $status;
    protected $message;
    protected $duration;
    protected $created;
    protected $modified;


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
     * Determines if file is audio or video and does corresponding processing
     * @returns boolean
     * @throws Exception_Schoolbox
     */
    public function process()
    {
        $sourceFile = $this->getSourceFile();

        if($sourceFile->getMimetype()->isAudio()){
            return $this->processAudio($this->getSourceLocation($sourceFile));
        }
        else if($sourceFile->getMimeType()->isVideo()){
            return $this->processVideo($this->getSourceLocation($sourceFile));
        }

    }


    /**
     * Takes audio file and transcodes into mp3 for playback via <audio>
     * @returns boolean
     * @throws Exception_Schoolbox
     */
    public function processAudio($sourceFile)
    {
        $executable = $this->getConverterExecutable();

        $processString = "nice $executable -y -i " . escapeshellarg($sourceFile) .' -c:a libmp3lame -b 96k -ar:a 44100'
            . ' -timelimit ' . escapeshellarg($this->config->get('video_processing_timeout')) . ' '
            . escapeshellarg($this->getAudioLocation()) . ' 2>&1';

        exec($processString, $message, $return);
        $this->setMessage(implode("\n", $message));

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
            return false;
        }

    }


    /**
     * Takes existing file and transcodes into web format and creates thumbnail
     *
     * @returns boolean
     * @throws Exception_Schoolbox
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
            return false;
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

        // Get rotation information, for ipad and iphones that record upside down and use metadata to rotate
        $rotation = 0;
        $rotate   = '';
        exec('which exiftool', $unused, $errorcode);
        if (!$errorcode) {
            $rotationCmd = 'exiftool -Rotation ' . escapeshellarg($sourceFile) . "|sed 's/^[^:]*: //'";
            exec($rotationCmd, $rotationOut, $return);
            if (!$return && isset($rotationOut[0])) {
                $rotation = $rotationOut[0];
            }

            if ($rotation) {
                switch ($rotation) {
                    case 90:
                        $rotate = 'transpose=1';
                        break;
                    case 180:
                        $rotate = 'transpose=1,transpose=1';
                        break;
                    case 270:
                        $rotate = 'transpose=2';
                        break;
                    default:
                        $rotate = '';
                }

                if ($scale && $rotate) {
                    $scale .= ',' . $rotate;
                } else {
                    $scale = $rotate;
                }

            }
        }


        /* Process video to 512kb in libx264 format for mobile and html5 streaming */
        $processString = "nice $executable -y -i " . escapeshellarg($sourceFile) .
            " -c:v libx264 $scale -preset slow -b:v 512k -b:a 128k -r 24 -ar:a 44100" .
            " -timelimit " . escapeshellarg($this->config->get('video_processing_timeout')) . ' ' .
            ($strict ? '-strict -2 ' : '') .
            '-ac 2 ' . escapeshellarg($this->getVideoLocation()) . ' 2>&1';

        exec($processString, $message, $return);
        $this->setMessage(implode("\n", $message));

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
            ' -vf '.$thumbScale.'crop=64:64' . (($rotate) ? ',' . $rotate : '') .
            ' -frames:v 1 ' .
            escapeshellarg($this->getThumbnailLocation()) . ' 2>&1';
        $posterString     = "nice $executable -y -i " . escapeshellarg($sourceFile) .
            ' -f image2 -ss ' . $thumbnailSeconds .
            ' -frames:v 1 ' . (($rotate) ? '-vf ' . $rotate : '') . ' ' .
            escapeshellarg($this->getPosterLocation()) . ' 2>&1';

        if ($return == 0) {
            exec($thumbnailString, $output, $return);
            exec($posterString, $output, $return);

            if ($return == 0) {
                return true;
            } else {
                return false;
            }
        }
        return false;


    }

    /**
     * Returns the source object related to this video queue item
     * This has been deprecated to be replaced by a proper join on hash
     *
     * @return \Schoolbox\Resource\Storage\Model
     * @deprecated
     */
    private function getSourceFile()
    {
        $newStorage = \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($this->getHash());
        return $newStorage;
    }

    /**
     * This returns the location of the original file on disk
     * This has been deprecated as it needs to be replaced by a Flysystem equivalent
     *
     * @param $sourceObject
     * @return string
     * @deprecated
     */
    private function getSourceLocation($sourceObject)
    {
        $configLoader = new \Schoolbox\Config\Loader();
        return $configLoader->get('storage_dir'). '/' . $sourceObject->getPath();
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

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function setModified($modified)
    {
        $this->modified = $modified;
    }

    public function getModified()
    {
        return $this->modified;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
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
}
