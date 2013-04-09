<?php
/**
 * Extends a standard schoolbox file into a video file
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;
use Schoolbox\ConfigLoader;

use Schoolbox\Resource\File;

class ResourceVideo {
	private $type;
	private $status;
	private $duration;
	private $file;

	/**
	 * Creates object and sets up sub-objects.
	 */
	public function __construct() {
		$this->setConfig( new ConfigLoader() );
	}


	public function getVideoLocation() {
		return $this->config->get('video_dir').'/'.$this->getType().'-'.$this->getFile()->getId() . '.mp4';
	}

	public function getThumbnailLocation() {
		return $this->config->get('video_dir').'/'.$this->getType().'-'.$this->getFile()->getId() . '-64.jpg';
	}

	private function setConfig($config) {
		$this->config = $config;
	}

	public function setDuration($duration) {
		$this->duration = $duration;
	}

	public function getDuration() {
		return $this->duration;
	}

	public function setMessage($message) {
		$this->message = $message;
	}

	public function getMessage() {
		return $this->message;
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

	public function setFile($file) {
		$this->file = $file;
	}

	public function getFile() {
		return $this->file;
	}




}