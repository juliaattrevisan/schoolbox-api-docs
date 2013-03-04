<?php
/**
 * Model for submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;
use Schoolbox;

class SubmissionBoxNotification {
	/**
	 * @var int
	 */
	protected $submission_box_id;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var bool
	 */
	protected $enabled;

	/**
	 * @var bool
	 */
	protected $sent;

	/**
	 * @param boolean $enabled
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;
	}

	/**
	 * @return boolean
	 */
	public function getEnabled() {
		return $this->enabled;
	}

	/**
	 * @param boolean $sent
	 */
	public function setSent($sent) {
		$this->sent = $sent;
	}

	/**
	 * @return boolean
	 */
	public function getSent() {
		return $this->sent;
	}

	/**
	 * @param int $submission_box_id
	 */
	public function setSubmissionBoxId($submission_box_id) {
		$this->submission_box_id = $submission_box_id;
	}

	/**
	 * @return int
	 */
	public function getSubmissionBoxId() {
		return $this->submission_box_id;
	}

	/**
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
}
