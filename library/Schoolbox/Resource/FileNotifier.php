<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\Notif\Message\Messenger;
use Schoolbox\User;

class FileNotifier extends ResourceNotifier {


	/**
	 * @param string $roletype
	 * @param File $file
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyCreated($roletype, $file, $effector, $diff = null) {
		$category = CategoryMapper::getInstance()->findById($file->getFolder());

		return $this->notify(
			LogMapper::KIND_FILE, $file->getId(), self::TRIGGER_CREATED,    // "file.{id}.created"
			array($file->getDescription(), '/send.php?id='.$file->getId()), // the :resource param
			$effector, $category, $roletype
		);
	}

	/**
	 * @param string $roletype
	 * @param File $file
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyDeleted($roletype, $file, $effector, $diff = null) {
		$category = CategoryMapper::getInstance()->findById($file->getFolder());
		// abstract this so far it doesn't even make sense any more
		return $this->notify(
			LogMapper::KIND_FILE, $file->getId(), self::TRIGGER_DELETED,    // "file.{id}.deleted"
			array($file->getDescription(), '/send.php?id='.$file->getId()), // the :resource param
			$effector, $category, $roletype
		);
	}

} 