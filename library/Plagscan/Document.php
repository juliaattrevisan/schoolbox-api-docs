<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Plagscan_Document extends Model {
	const STATUS_QUEUED = 1;

	const STATUS_PROCESSING = 2;

	const STATUS_COMPLETE = 3;

	/**
	 * Status of the document, if it's returned as unscannable?
	 */
	const STATUS_UNSCANNABLE = -1;

	/**
	 * @var int
	 */
	public $id;

	/**
	 * The file from $_FILES?
	 *
	 * @var array
	 */
	public $file;

	/**
	 * @var int
	 */
	public $fileId;

	/**
	 * Is this even used?
	 *
	 * @var ?
	 */
	public $plagscan;

	/**
	 * @var int
	 */
	public $plagscanId;

	/**
	 * @var int
	 */
	public $state;

	/**
	 * @var int
	 */
	public $status;

	/**
	 * @var string
	 */
	public $html;

	/**
	 * @var float
	 */
	public $score;

	/**
	 * @param  int $fileId
	 *
	 * @return $this
	 */
	public function setFileId($fileId) {
		$this->fileId = $fileId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getFileId() {
		return $this->fileId;
	}

	/**
	 * @param  string $html
	 *
	 * @return $this
	 */
	public function setHtml($html) {
		$this->html = $html;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHtml() {
		return $this->html;
	}

	/**
	 * @param  int $id
	 *
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  int $plagscanId
	 *
	 * @return $this
	 */
	public function setPlagscanId($plagscanId) {
		$this->plagscanId = $plagscanId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPlagscanId() {
		return $this->plagscanId;
	}

	/**
	 * @param  float $score
	 *
	 * @return $this
	 */
	public function setScore($score) {
		$this->score = $score;
		return $this;
	}

	/**
	 * @return float
	 */
	public function getScore() {
		return $this->score;
	}

	/**
	 * @param  int $state
	 *
	 * @return $this
	 */
	public function setState($state) {
		$this->state = $state;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * @param  int $status
	 *
	 * @return $this
	 */
	public function setStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param  array $file
	 *
	 * @return $this
	 */
	public function setFile($file) {
		$this->file = $file;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getFile() {
		return $this->file;
	}

}