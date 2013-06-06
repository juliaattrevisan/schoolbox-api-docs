<?php
/**
 * This represents a video that has been placed in the processing queue
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;
use Schoolbox\ConfigLoader;
use Schoolbox\Video\QueueMapper;

class Queue {

	private $id;
	private $fileID;
	private $type;
	private $status;
	private $message;
	private $duration;
	private $created;
	private $modified;


	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
		$this->setConfig( new ConfigLoader() );
	}

	/**
	 * Takes existing file and transcodes into web format and creates thumbnail
	 *
	 * @returns boolean
	 * @throws Exception_Schoolbox
	 */

	public function processVideo() {


		if ($this->getType() == QueueMapper::FILE_TYPE_SBFS) {
			$sourceStore = $this->config->get('file_dir');
			$sourceFile = $sourceStore . '/' . $this->fileID . '.dat';
		}
		else {
			throw new \Exception_Schoolbox('Processing of this type of video is not implemented');
		}

		//
		// Choose whether to use AVconv or FFMpeg, depending on whether AVconv
		// exists or not.
		// FFMpeg needs strict flag, as MP4 is technically experimental
		$executable = 'avconv';
		$strict     = false;
		exec('which avconv', $unused, $errorcode);
		if ($errorcode) {
			$executable = 'ffmpeg';
			$strict     = true;
		}
		/* Process video to 512kb in libx264 format for mobile and html5 streaming */
		$processString = "nice $executable -y -i " . escapeshellarg($sourceFile) .
			' -c:v libx264 -preset slow -b 512k ' .
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
		$thumbnailString  = "nice $executable -y -i " . escapeshellarg($sourceFile) . ' -f image2 -ss '.$thumbnailSeconds.' -vf scale=-1:64,crop=64:64 -frames:v 1 ' . escapeshellarg($this->getThumbnailLocation()).' 2>&1';
		$posterString     = "nice $executable -y -i " . escapeshellarg($sourceFile) . ' -f image2 -ss '.$thumbnailSeconds.' -frames:v 1 ' . escapeshellarg($this->getPosterLocation()).' 2>&1';

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

	public function getVideoLocation() {
		return $this->config->get('video_dir').'/'.$this->getType().'-'.$this->getFileID() . '.mp4';
	}

	public function getThumbnailLocation() {
		return $this->config->get('video_dir').'/'.$this->getType().'-'.$this->getFileID() . '-64.jpg';
	}

	public function getPosterLocation() {
		return $this->config->get('video_dir').'/'.$this->getType().'-'.$this->getFileID() . '-poster.jpg';
	}

	private function setConfig($config) {
		$this->config = $config;
	}

	public function isComplete() {
		if($this->getStatus() == QueueMapper::STATUS_COMPLETED)
			return true;
		else
			return false;
	}



	public function setCreated($created) {
		$this->created = $created;
	}

	public function getCreated() {
		return $this->created;
	}

	public function setFileID($fileID) {
		$this->fileID = $fileID;
	}

	public function getFileID() {
		return $this->fileID;
	}

	public function setId($id) {
		$this->id = $id;
	}

	public function getId() {
		return $this->id;
	}

	public function setMessage($message) {
		$this->message = $message;
	}

	public function getMessage() {
		return $this->message;
	}

	public function setModified($modified) {
		$this->modified = $modified;
	}

	public function getModified() {
		return $this->modified;
	}

	public function setStatus($status) {
		$this->status = $status;
	}

	public function getStatus() {
		return $this->status;
	}

	public function setType($type) {
		$this->type = $type;
	}

	public function getType() {
		return $this->type;
	}

	public function setDuration($duration) {
		$this->duration = $duration;
	}

	public function getDuration() {
		return $this->duration;
	}



}