<?php
/**
 * Model for submission box notifications.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox;

class Notification {

	const TYPE_OVERDUE  = 'overdue';
	const TYPE_UPCOMING = 'upcoming';
	const TYPE_RETURNED = 'returned';

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
	protected $enabled = false;

	/**
	 * @var bool
	 */
	protected $sent = false;

	/**
	 * @param boolean $enabled
	 * @return $this
	 */
	public function setEnabled($enabled) {
		$this->enabled = $enabled;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getEnabled() {
		return $this->enabled;
	}

	/**
	 * @param boolean $sent
	 * @return $this
	 */
	public function setSent($sent) {
		$this->sent = $sent;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getSent() {
		return $this->sent;
	}

	/**
	 * @param int $submission_box_id
	 * @return $this
	 */
	public function setSubmissionBoxId($submission_box_id) {
		$this->submission_box_id = $submission_box_id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getSubmissionBoxId() {
		return $this->submission_box_id;
	}

	/**
	 * @param string $type
	 * @return $this
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}
}
