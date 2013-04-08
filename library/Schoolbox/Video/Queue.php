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

class Queue {

	private $id;
	private $fileID;
	private $type;
	private $status;
	private $message;
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

		$videoStore = $this->config->get('video_dir');
		$sourceStore = $this->config->get('file_dir');

		if ($this->type == QueueMapper::FILE_TYPE_SBFS)
			$sourceFile = $sourceStore . '/' . $this->fileID . '.dat';
		else
				{
					throw new \Exception_Schoolbox();
				}

		$processString = 'avconv -i ' . escapeshellarg($sourceFile) . ' -c:v libx264 -preset slow -b 512k ' . escapeshellarg($videoStore.'/'.$this->fileID . '.mp4').' 2>&1';
		$thumbnailString = 'avconv -i ' . escapeshellarg($sourceFile) . ' -f image2 -ss 10 -s 64x64 -frames:v 1 ' . escapeshellarg($videoStore.'/'.$this->fileID . '-64.jpg').' 2>&1';
		print $processString;
		exec($processString, $message, $return);
		$this->setMessage(implode( "\n", $message));
		print $this->message;
		if($return == 0){
			exec($thumbnailString, $output, $return);
			if($return == 0)
				return true;
			else
				return false;
		}
		return false;



	}

	private function setConfig($config) {
		$this->config = $config;
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



}