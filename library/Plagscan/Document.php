<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\ISoftDeletable;

class Plagscan_Document implements ISoftDeletable {
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
	protected $id;

	/**
	 * @var Submission\File
	 */
	protected $file;


	/**
	 * Is this even used?
	 *
	 * @var ?
	 */
	protected $plagscan;

	/**
	 * @var int
	 */
	protected $plagscanId;

	/**
	 * @var int
	 */
	protected $state;

	/**
	 * @var int
	 */
	protected $status;

	/**
	 * @var string
	 */
	protected $html;

	/**
	 * @var float
	 */
	protected $score;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $deleted;


	/**
	 * @param  string $html
	 *
	 * @return static
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
	 * @return static
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
	 * @return static
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
	 * @return static
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
	 * @return static
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
	 * @return static
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
	 * @param  Submission\File $file
	 *
	 * @return static
	 */
	public function setFile($file) {
		$this->file = $file;
		return $this;
	}

	/**
	 * @return Submission\File
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * @param  Alaress_Date_Format $deleted
	 * @return static
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @return Alaress_Date_Format
	 */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * Is this deleted? (added so there's a consistent method for determining if
	 * a model is deleted)
	 *
	 * @return bool
	 */
	public function isDeleted() {
		return ($this->getDeleted() instanceof Alaress_Date_Format && !$this->getDeleted()->isNull());
	}

}