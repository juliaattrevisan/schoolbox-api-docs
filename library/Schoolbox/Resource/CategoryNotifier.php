<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\Collection;
use Schoolbox\Feed\Item;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\User;

class CategoryNotifier extends ResourceNotifier {

	const TRIGGER_UPDATED = 'updated';
	const TRIGGER_RENAMED = 'renamed';
	const TRIGGER_MOVED   = 'moved';
	const TRIGGER_CREATED = 'created';
	const TRIGGER_DELETED = 'deleted';


	/**
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context - most likely a category ID or ignored
	 * @param string $roletype - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit - how many messages to retrieve
	 * @param int $offset - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $roletype, $limit = self::SOFT_LIMIT, $offset = 0) {
		return $this->getFeedInternal($context, $roletype, $limit, $offset, LogMapper::KIND_FOLDER);
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {

	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_CMS;
	}

	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
				$bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
				break;

			case Mapper::METHOD_UPDATE:
				$bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
				break;

			case Mapper::METHOD_DELETE:
				$bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
				break;
		}
	}

	/**
	 * Returns the metadata pertaining to this source.
	 * Useful to RSS
	 *
	 * @return mixed
	 */
	public function getChannelMetadata() {
		return null;
	}


	/**
	 * Notify relevant students that this category has been created
	 *
	 * @param string $roleType
	 * @param Category $category
	 * @param User $creator
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyCreated($roleType, $category, $creator, $diff = null) {
		return $this->notify(
			LogMapper::KIND_FOLDER, $category->getId(), self::TRIGGER_CREATED,
			array($category->getName(), "/homepage/".$category->getId()),
			$creator, $category->getParent(), $roleType
		);
	}



	/**
	 * @param string $roleType
	 * @param Category $category
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyDeleted($roleType, $category, $effector, $diff = null) {
		return $this->notify(
			LogMapper::KIND_FOLDER, $category->getId(), self::TRIGGER_DELETED,
			$category->getName(), $effector, $category->getParent(), $roleType
		);
	}


} 