<?php
/**
 * Model for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Schoolbox\ConfigLoader;

class Submission {

	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var SubmissionBox
	 */
	protected $submission_box;

	/**
	 * @var User
	 */
	protected $owner;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $submitted;

	/**
	 * @var bool
	 */
	protected $marked;

	/**
	 * @var bool
	 */
	protected $late;

	/**
	 * @var string
	 */
	protected $mimetype;

	/**
	 * @var string
	 */
	protected $temp_location;

	/**
	 * @var string
	 */
	protected $body;

	/**
	 * @var bool
	 */
	protected $draft = false;

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Creates object and sets up related objects.
	 */
	public function __construct() {
		$this->setSubmissionBox(new SubmissionBox());
		$this->setOwner(new User());
		$this->setConfigLoader(new ConfigLoader());
	}

	/**
	 * Gets the location at which this file would be stored.
	 *
	 * @return string
	 */
	public function getFileLocation() {
		$id = $this->getId();
		if (!isset($id)) {
			return false;
		}
		return $this->config_loader->get('submission_dir') . '/' . $id . '.dat';
	}

	/**
	 * Gets the size of this file (from where it's stored on the file system),
	 * or false if it doesn't exist.
	 *
	 * @return bool|int
	 */
	public function getFilesize() {
		if (($location = $this->getFileLocation()) !== false) {
			if (file_exists($location)) {
				return filesize($location);
			}
		}
		return false;
	}

	/**
	 * Loads whether the submission is late, depending on the submission box's
	 * due date.
	 *
	 * @return $this
	 */
	public function loadIsLate() {
		$late_timestamp = $this->getSubmissionBox()->getDueDate()->getUnixTimestamp();
		$submit_date    = new \DateTime('now');
		$this->setLate($late_timestamp < $submit_date->getTimestamp());
		return $this;
	}

	/**
	 * Is this an online submission?
	 *
	 * This checks this submission's body field. If there's something there, it
	 * is an online submission.
	 *
	 * @return bool
	 */
	public function isOnlineSubmission() {
		return strlen($this->getBody()) > 0;
	}


	/**
	 * Auto-generated methods
	 */


	/**
	 * @param  string $filename
	 *
	 * @return $this
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
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
	 * @param  boolean $late
	 *
	 * @return $this
	 */
	public function setLate($late) {
		$this->late = $late;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getLate() {
		return $this->late;
	}

	/**
	 * @param  boolean $marked
	 *
	 * @return $this
	 */
	public function setMarked($marked) {
		$this->marked = $marked;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getMarked() {
		return $this->marked;
	}

	/**
	 * @param  string $mimetype
	 *
	 * @return $this
	 */
	public function setMimetype($mimetype) {
		$this->mimetype = $mimetype;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMimetype() {
		return $this->mimetype;
	}

	/**
	 * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
	 *
	 * @return $this
	 */
	public function setSubmissionBox($submission_box) {
		$this->submission_box = $submission_box;
		return $this;
	}

	/**
	 * @return \Schoolbox\Assessment\Component\SubmissionBox
	 */
	public function getSubmissionBox() {
		return $this->submission_box;
	}

	/**
	 * @param  \Schoolbox\User $owner
	 *
	 * @return $this
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param  \Alaress_Date_Format $submitted
	 *
	 * @return $this
	 */
	public function setSubmitted($submitted) {
		$this->submitted = $submitted;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getSubmitted() {
		return $this->submitted;
	}

	/**
	 * @param  string $body
	 *
	 * @return $this
	 */
	public function setBody($body) {
		$this->body = $body;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * @param  boolean $draft
	 *
	 * @return $this
	 */
	public function setDraft($draft) {
		$this->draft = $draft;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getDraft() {
		return $this->draft;
	}

	/**
	 * @param  IConfigLoader $config_loader
	 *
	 * @return $this
	 */
	public function setConfigLoader($config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
	 * @return IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}

	/**
	 * @param  string $temp_location
	 * @return $this
	 */
	public function setTempLocation($temp_location) {
		$this->temp_location = $temp_location;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTempLocation() {
		return $this->temp_location;
	}

	/**
	 * Copies file from its temp location to permanent upload location.
	 *
	 * @todo Traits would be great here.
	 *
	 * @return bool
	 */
	public function copyFromTempLocation() {
		$temp_location = $this->getTempLocation();
		$file_location = $this->getFileLocation();
		if (file_exists($temp_location) && $file_location) {
			return copy($temp_location, $file_location);
		}
		return false;
	}

}

