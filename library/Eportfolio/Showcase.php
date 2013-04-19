<?php
/**
 * Eportfolio_Showcase
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;

class Showcase {

	/**
	 * The showcase item id
	 *
	 * @var int
	 */
	public $id = null;

	/**
	 * The showcased submission box
	 *
	 * @var
	 */
	public $submissionBoxId;

	/**
	 * The showcasing user
	 *
	 * @var
	 */
	public $uid;

	/* Getters/Setters
	 */

	/**
	 * Set id
	 *
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set submission box id
	 *
	 * @param  $submissionBoxId
	 */
	public function setSubmissionBoxId($submissionBoxId) {
		$this->submissionBoxId = $submissionBoxId;
	}

	/**
	 * Get submission box id
	 *
	 * @return
	 */
	public function getSubmissionBoxId() {
		return $this->submissionBoxId;
	}

	/**
	 * Set uid
	 *
	 * @param  $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * Get uid
	 *
	 * @return
	 */
	public function getUid() {
		return $this->uid;
	}
}