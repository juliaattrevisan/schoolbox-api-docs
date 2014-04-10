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
use Schoolbox\ISoftDeletable;

class Pad implements ISoftDeletable {

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
	 * Homepage id
	 *
	 * @var string
	 */
	private $homepageId = '';

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
	 * Whether this pad is locked
	 *
	 * @var int
	 */
	private $lock = 0;

	/**
	 * Created at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $createdAt = null;

	/**
	 * Updated at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $updatedAt = null;

	/**
	 * Deleted at
	 *
	 * @var \Alaress_Date_Format
	 */
	private $deletedAt = null;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Determine whether this pad is locked or not
	 *
	 * @return bool
	 */
	public function isLocked() {
		if ($this->getLock() == 1) {
			return true;
		}

		return false;
	}

	/**
	 * Lock this pad
	 */
	public function lock() {
		$this->setLock(1);
	}

	/**
	 * Unlock this pad
	 */
	public function unlock() {
		$this->setLock(0);
	}

	/**
	 * Client-facing version of the name, which includes
	 * the lock status of the pad
	 *
	 * @return string
	 */
	public function getDisplayName() {
		$name = $this->getName();

		if ($this->isLocked()) {
			$name .= ' (Locked)';
		}

		return $name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function isDeleted() {
		return ($this->getDeletedAt() instanceof \Alaress_Date_Format);
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

	/**
	 * Set lock
	 *
	 * @param int $lock
	 */
	public function setLock($lock) {
		$this->lock = $lock;
	}

	/**
	 * Get lock
	 *
	 * @return int
	 */
	public function getLock() {
		return $this->lock;
	}

	/**
	 * Set homepage id
	 *
	 * @param string $homepageId
	 */
	public function setHomepageId($homepageId) {
		$this->homepageId = $homepageId;
	}

	/**
	 * Get homepage id
	 *
	 * @return string
	 */
	public function getHomepageId() {
		return $this->homepageId;
	}
}