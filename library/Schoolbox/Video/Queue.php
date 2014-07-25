<?php
/**
 * This represents a video that has been placed in the processing queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

class Queue extends ResourceVideo {

	protected $id;
	protected $fileID;
	protected $type;
	protected $status;
	protected $message;
	protected $duration;
	protected $created;
	protected $modified;


	/**
	 * Takes existing file and transcodes into web format and creates thumbnail
	 *
	 * @returns boolean
	 * @throws Exception_Schoolbox
	 */

	public function processVideo() {
        $sourceFile = $this->getSourceFile();

		//
		// Choose whether to use AVconv or FFMpeg, depending on whether AVconv
		// exists or not.
		// FFMpeg needs strict flag, as MP4 is technically experimental
		$executable = 'avconv';
		$info_exec  = 'avprobe';
		$strict     = false;
		exec('which avconv', $unused, $errorcode);
		if ($errorcode) {
			$executable = 'ffmpeg';
			$info_exec  = 'ffprobe';
			$strict     = true;
		}

		//
		// Get information about width and height of video file
		exec("$info_exec -show_streams ".escapeshellarg($sourceFile).' 2>&1', $info_message, $info_return);
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

		//
		// Get a scale factor for this video (rounding down to nearest even #)
		if ($width && $height) {
			$scale = sprintf('-vf scale=%d:%d', intval($width/2)*2, intval($height/2)*2);
		}

        // Get rotation information, for ipad and iphones that record upside down and use metadata to rotate
        $rotation = 0;
        $rotate = '';
        exec('which exiftool', $unused, $errorcode);
        if(!$errorcode) {
            $rotationCmd = 'exiftool -Rotation '.escapeshellarg($sourceFile) ."|sed 's/^[^:]*: //'";
            exec($rotationCmd, $rotationOut, $return);
            if(!$return && isset($rotationOut[0])) {
                $rotation = $rotationOut[0];
            }

            if($rotation) {

                switch($rotation) {
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

                if($scale && $rotate) {
                    $scale .= ','.$rotate;
                } else {
                    $scale = $rotate;
                }

            }
        }


		/* Process video to 512kb in libx264 format for mobile and html5 streaming */
		$processString = "nice $executable -y -i " . escapeshellarg($sourceFile) .
			" -c:v libx264 $scale -preset slow -b 512k -r 24 " .
			($strict ? '-strict -2 ' : '').
			'-ac 2 ' . escapeshellarg($this->getVideoLocation()).' 2>&1';

		exec($processString, $message, $return);
		$this->setMessage(implode( "\n", $message));

		preg_match('/Duration: ([0-9\:]+)/',$this->getMessage(), $match);

		if(isset($match[1])) {
			$hours = $minutes = $seconds = 0;
			sscanf($match[1], "%d:%d:%d", $hours, $minutes, $seconds);
			$duration = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
			$this->setDuration($duration);
		}

		/* Generate thumbnail of video 64x64 pixels, 1/3rd of the video in */
		$thumbnailSeconds = $this->getDuration() > 0 ? round(($this->getDuration() / 3), 2) : 10;
		$thumbnailString  = "nice $executable -y -i " . escapeshellarg($sourceFile) . ' -f image2 -ss '.$thumbnailSeconds.' -vf scale=-1:64,crop=64:64'.(($rotate)?','.$rotate:'').' -frames:v 1 ' . escapeshellarg($this->getThumbnailLocation()).' 2>&1';
		$posterString     = "nice $executable -y -i " . escapeshellarg($sourceFile) . ' -f image2 -ss '.$thumbnailSeconds.' -frames:v 1 '.(($rotate)?'-vf '.$rotate:'').' ' . escapeshellarg($this->getPosterLocation()).' 2>&1';

		if($return == 0){
			exec($thumbnailString, $output, $return);
			exec($posterString, $output, $return);

			if($return == 0)
				return true;
			else
				return false;
		}
		return false;



	}

    private function getSourceFile() {
        if ($this->getType() == QueueMapper::FILE_TYPE_SBFS) {
            $sourceStore = $this->config->get('file_dir');
            return $sourceStore . '/' . $this->fileID . '.dat';
        }
        else if($this->getType() == QueueMapper::FILE_TYPE_STORAGE) {
            $sourceStore = $this->config->get('storage_dir');
            return $sourceStore . '/' . $this->fileID . '.dat';
        }
		else if($this->getType() == QueueMapper::FILE_TYPE_SUBMISSION) {
			$sourceStore = $this->config->get('submission_dir');
			return $sourceStore . '/' . $this->fileID . '.dat';
		}
		else if($this->getType() == QueueMapper::FILE_TYPE_MYFILE) {
			$sourceStore = $this->config->get('myfile_dir');
			return $sourceStore . '/' . $this->fileID . '.dat';
		}
        else {
            throw new \Exception_Schoolbox('Processing of this type of video is not implemented');
        }
    }


	public function setCreated($created) {
		$this->created = $created;
	}

	public function getCreated() {
		return $this->created;
	}

	public function setModified($modified) {
		$this->modified = $modified;
	}

	public function getModified() {
		return $this->modified;
	}






}