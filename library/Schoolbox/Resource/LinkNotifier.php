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
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\User;

class LinkNotifier extends ResourceNotifier {


	/**
	 * @inheritdoc
	 */
	public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0) {
		return $this->getFeedInternal($context, $user->getRole()->getType(), $limit, $offset, LogMapper::KIND_LINK);
	}


	/**
	 * @param string $roletype
	 * @param Link $link
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyCreated($roletype, $link, $effector, $diff = null) {
		$category = $this->folderMapper->findById($link->getFolder()->getId());

		return $this->notify(
			LogMapper::KIND_LINK, $link->getId(), self::TRIGGER_CREATED,    // "link.{id}.created"
			new Param($link->getDescription(), $link->getHref()),           // the :resource param
			$effector, $category, $roletype
		);
	}

	/**
	 * @param string $roletype
	 * @param Link $link
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyDeleted($roletype, $link, $effector, $diff = null) {
        try {
            $category = $this->folderMapper->findById($link->getFolder()->getId());
        } catch (Deleted $e) {
            return new Envelope();
        }

		// abstract this so far it doesn't even make sense any more
		return $this->notify(
			LogMapper::KIND_LINK, $link->getId(), self::TRIGGER_DELETED,    // "link.{id}.deleted"
			new Param($link->getDescription()),                             // the :resource param
			$effector, $category, $roletype
		);
	}


	/**
	 * @param Link $resource
	 * @return string
	 */
	protected function getResourceName($resource) {
		return $resource->getDescription();
	}


	/**
	 * @param Link $resource
	 * @return \Schoolbox\Resource\Folder\Model
	 */
	protected function getResourceCategory($resource) {
		return $this->folderMapper->findById($resource->getFolder()->getId());
	}


	/**
	 * @return string
	 */
	protected function getResourceKind() {
		return LogMapper::KIND_LINK;
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
