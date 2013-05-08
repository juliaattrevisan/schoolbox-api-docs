<?php
/**
 * Eportfolio_Permission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Eportfolio;

class Permission {

	/**
	 * The permission id
	 *
	 * @var int
	 */
	private $id = null;

	/**
	 * The page to which the permission applies
	 *
	 * @var int
	 */
	private $page_id = -1;

	/**
	 * The type of permission
	 *
	 * @var string
	 */
	private $type = '';

	/**
	 * Permission value (usually an id)
	 *
	 * @var string
	 */
	private $value = '';

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
	 * Set page id
	 *
	 * @param int $page_id
	 */
	public function setPageId($page_id) {
		$this->page_id = $page_id;
	}

	/**
	 * Get page id
	 *
	 * @return int
	 */
	public function getPageId() {
		return $this->page_id;
	}

	/**
	 * Set type
	 *
	 * @param string $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set value
	 *
	 * @param string $value
	 */
	public function setValue($value) {
		$this->value = $value;
	}

	/**
	 * Get value
	 *
	 * @return string
	 */
	public function getValue() {
		return $this->value;
	}
}