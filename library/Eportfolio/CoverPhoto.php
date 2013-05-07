<?php
/**
 * CoverPhoto
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Eportfolio;

class CoverPhoto {

	/**
	 * The primary key
	 *
	 * @var
	 */
	private $id;

	/**
	 * The owner of the page
	 *
	 * @var
	 */
	private $uid;

	/**
	 * The blackhole storage hash
	 *
	 * @var
	 */
	private $imagehash;

	/**
	 * Created time for the pic
	 *
	 * @var
	 */
	private $created_at;

	/**
	 * Updated time for the pic
	 *
	 * @var
	 */
	private $updated_at;

	/**
	 * Deleted time for the pic
	 *
	 * @var
	 */
	private $deleted_at;

	/* Getters/Setters
	 */

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
	 * Set id
	 *
	 * @param  $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return
	 */
	public function getId() {
		return $this->id;
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

	/**
	 * Set image hash
	 *
	 * @param  $imagehash
	 */
	public function setImagehash($imagehash) {
		$this->imagehash = $imagehash;
	}

	/**
	 * Get image hash
	 *
	 * @return
	 */
	public function getImagehash() {
		return $this->imagehash;
	}
}