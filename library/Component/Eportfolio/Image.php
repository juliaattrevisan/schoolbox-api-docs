<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Component\Eportfolio;


class Image {

	/**
	 * Primary key
	 *
	 * @var
	 */
	public $id;

	/**
	 * User id
	 *
	 * @var
	 */
	public $user_id;

	/**
	 * File id
	 *
	 * @var
	 */
	public $file_id;

	/* Getters/Setters
	 */

	/**
	 * Set file id
	 *
	 * @param mixed $file_id
	 */
	public function setFileId($file_id) {
		$this->file_id = $file_id;
	}

	/**
	 * Get file id
	 *
	 * @return mixed
	 */
	public function getFileId() {
		return $this->file_id;
	}

	/**
	 * Set id
	 *
	 * @param mixed $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return mixed
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set user id
	 *
	 * @param mixed $user_id
	 */
	public function setUserId($user_id) {
		$this->user_id = $user_id;
	}

	/**
	 * Get user id
	 *
	 * @return mixed
	 */
	public function getUserId() {
		return $this->user_id;
	}
}