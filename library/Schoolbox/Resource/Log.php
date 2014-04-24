<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\Assessment\Component\SubmissionBox\FileMapper;
use Schoolbox\User;

class Log {

	/** @var int */
	protected $id;

	/** @var int */
	protected $objectId;

	/** @var string */
	protected $objectKind;

	/** @var string */
	protected $action;

	/** @var User */
	protected $user;


	/**
	 * @param int $id
	 * @return self
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
	 * @param int $objectId
	 */
	public function setObjectId($objectId) {
		$this->objectId = $objectId;
	}

	/**
	 * @return int
	 */
	public function getObjectId() {
		return $this->objectId;
	}

	/**
	 * @param string $objectKind
	 */
	public function setObjectKind($objectKind) {
		$this->objectKind = $objectKind;
	}

	/**
	 * @return string
	 */
	public function getObjectKind() {
		return $this->objectKind;
	}


	/**
	 * @param Link|File|Category $object
	 * @return self
	 */
	public function setObject($object) {
		// splice this out to be kind and ID
		if($object instanceof File) {
			$this->objectId = $object->getId();
			$this->objectKind = LogMapper::KIND_FILE;
		}
		elseif($object instanceof Link) {
			$this->objectId = $object->getId();
			$this->objectKind = LogMapper::KIND_LINK;
		}
		elseif($object instanceof Category) {
			$this->objectId = $object->getId();
			$this->objectKind = LogMapper::KIND_FOLDER;
		}
		else {
			// TODO support for MyFile and MyFolder
		}
		return $this;
	}


	/**
	 * @return Link|File|Category
	 */
	public function getObject() {
		switch($this->objectKind) {
			case LogMapper::KIND_LINK:
				return LinkMapper::getInstance()->findById($this->objectId);
			case LogMapper::KIND_FILE:
				return FileMapper::getInstance()->findById($this->objectId);
			case LogMapper::KIND_FOLDER:
				return CategoryMapper::getInstance()->findById($this->objectId);
		}
		return null;
	}


	/**
	 * @param string $action
	 * @return self
	 */
	public function setAction($action) {
		$this->action = $action;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}


	/**
	 * @param User $user
	 * @return self
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}


	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}


} 