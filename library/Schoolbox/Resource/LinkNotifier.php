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
use Schoolbox\Collection;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\User;

class LinkNotifier extends ResourceNotifier {


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
		return $this->getFeedInternal($context, $roletype, $limit, $offset, LogMapper::KIND_LINK);
	}


	/**
	 * @param string $roletype
	 * @param Link $link
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyCreated($roletype, $link, $effector, $diff = null) {
		$category = CategoryMapper::getInstance()->findById($link->getFolder());

		return $this->notify(
			LogMapper::KIND_LINK, $link->getId(), self::TRIGGER_CREATED,    // "link.{id}.created"
			array($link->getDescription(), $link->getUrl()),                // the :resource param
			$effector, $category, $roletype
		);
	}

	/**
	 * @param string $roletype
	 * @param Link $link
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyDeleted($roletype, $link, $effector, $diff = null) {
		$category = CategoryMapper::getInstance()->findById($link->getFolder());
		// abstract this so far it doesn't even make sense any more
		return $this->notify(
			LogMapper::KIND_LINK, $link->getId(), self::TRIGGER_DELETED,    // "link.{id}.deleted"
			array($link->getDescription(), $link->getUrl()),                // the :resource param
			$effector, $category, $roletype
		);
	}

} 