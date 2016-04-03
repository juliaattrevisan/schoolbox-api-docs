<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Collection;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\User;

class FileNotifier extends ResourceNotifier {


	/**
	 * Generate n Instances, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context     - most likely a category ID or ignored
	 * @param string $roletype - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit       - how many messages to retrieve
	 * @param int $offset      - by default, most recent
	 * @return Collection|Instance[]
	 */
	public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0) {
		return $this->getFeedInternal($context, $roletype, $limit, $offset, LogMapper::KIND_FILE);
	}


	/**
	 * @param string $roletype
	 * @param File $file
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyCreated($roletype, $file, $effector, $diff = null) {
		$category = $this->getResourceCategory($file);

		return $this->notify(
			LogMapper::KIND_FILE, $file->getId(), self::TRIGGER_CREATED,    // "file.{id}.created"
			new Param($file->getDescription(), '/send.php?id='.$file->getId()), // the :resource param
			$effector, $category, $roletype
		);
	}

	/**
	 * @param string $roletype
	 * @param File $file
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyDeleted($roletype, $file, $effector, $diff = null) {
		$category = $this->getResourceCategory($file);
		// abstract this so far it doesn't even make sense any more
		return $this->notify(
			LogMapper::KIND_FILE, $file->getId(), self::TRIGGER_DELETED,    // "file.{id}.deleted"
			new Param($file->getDescription()),                             // the :resource param
			$effector, $category, $roletype
		);
	}


	/**
	 * @param File $resource
	 * @return string
	 */
	protected function getResourceName($resource) {
		return $resource->getDescription();
	}


	/**
	 * @param File $resource
	 * @return Category
	 */
	protected function getResourceCategory($resource) {
		return $this->folderMapper->findById($resource->getFolder()->getId());
	}


	/**
	 * @return string
	 */
	protected function getResourceKind() {
		return LogMapper::KIND_FILE;
	}


	/**
	 * @return string
	 */
	protected function getResourceNameProperty() {
		return 'description';
	}


	/**
	 * @return string
	 */
	protected function getResourceCategoryProperty() {
		return 'folder';
	}

} 