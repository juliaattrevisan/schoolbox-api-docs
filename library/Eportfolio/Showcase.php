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

	/**
	 * When the Showcased Work item was created
	 *
	 * @var
	 */
	private $created_at;

	/**
	 * When the Showcased Work item was updated
	 * @var
	 */
	private $updated_at;

	/**
	 * When the Showcased Work item was softdeleted
	 * @var
	 */
	private $deleted_at;

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

	/**
	 * Set created at
	 *
	 * @param  $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}

	/**
	 * Get created at
	 *
	 * @return
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set deleted at
	 *
	 * @param  $deleted_at
	 */
	public function setDeletedAt($deleted_at) {
		$this->deleted_at = $deleted_at;
	}

	/**
	 * Get deleted at
	 *
	 * @return
	 */
	public function getDeletedAt() {
		return $this->deleted_at;
	}

	/**
	 * Set updated at
	 *
	 * @param  $updated_at
	 */
	public function setUpdatedAt($updated_at) {
		$this->updated_at = $updated_at;
	}

	/**
	 * Get updated at
	 *
	 * @return
	 */
	public function getUpdatedAt() {
		return $this->updated_at;
	}
}