<?php
/**
 * Pad
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Etherpad;


class Pad {

	/**
	 * Identifier
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * Etherpad Pad ID
	 *
	 * @var null
	 */
	private $padId = '';

	/**
	 * Etherpad Group ID
	 *
	 * @var string
	 */
	private $groupId = '';

	/**
	 * Actual pad name given by user
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Pad body
	 *
	 * @var string
	 */
	private $body = '';

	/**
	 * Created at
	 *
	 * @var string
	 */
	private $createdAt = '';

	/**
	 * Updated at
	 *
	 * @var string
	 */
	private $updatedAt = '';

	/**
	 * Deleted at
	 *
	 * @var string
	 */
	private $deletedAt = '';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

	/**
	 * Set body
	 *
	 * @param string $body
	 */
	public function setBody($body) {
		$this->body = $body;
	}

	/**
	 * Get body
	 *
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * Set created at
	 *
	 * @param string $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return string
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param string $deletedAt
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return string
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set group id
	 *
	 * @param string $groupId
	 */
	public function setGroupId($groupId) {
		$this->groupId = $groupId;
	}

	/**
	 * Get group id
	 *
	 * @return string
	 */
	public function getGroupId() {
		return $this->groupId;
	}

	/**
	 * Set id
	 *
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set pad id
	 *
	 * @param null $padId
	 */
	public function setPadId($padId) {
		$this->padId = $padId;
	}

	/**
	 * Get pad id
	 *
	 * @return null
	 */
	public function getPadId() {
		return $this->padId;
	}

	/**
	 * Set updated at
	 *
	 * @param string $updatedAt
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Get updated at
	 *
	 * @return string
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}
}