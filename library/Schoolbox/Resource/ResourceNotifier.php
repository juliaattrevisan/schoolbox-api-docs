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
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;

abstract class ResourceNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_CREATED = 'created';
	const TRIGGER_UPDATED = 'updated';
	const TRIGGER_DELETED = 'deleted';


	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_CMS;
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// noop
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
	 * This version is special for CMS: in addition to the roletypes, also notify the system for logging
	 *
	 * @param string $label
	 * @return array
	 */
	public function retrieveCallbacks($label) {
		$callbacks = parent::retrieveCallbacks($label);
		$method = 'notifySystem'.ucfirst($label);
		if(method_exists($this, $method)) {
			$callbacks[] = array($this, $method);
		}

		return $callbacks;
	}


	/**
	 * Notify the system that a CMS event occurred (will be sent to cmsLog table)
	 *
	 * @param string $action
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @throws ScheduleException
	 * @return bool
	 */
	protected function notifySystem($action, $resource, $effector) {
		if($resource instanceof Category) {
			$id = $resource->getId();
			$type = 'folder';
		}
		elseif($resource instanceof File) {
			$id = $resource->getId();
			$type = 'file';
		}
		elseif($resource instanceof Link) {
			$id = $resource->getId();
			$type = 'link';
		}
		elseif($resource instanceof Textbox) {
			$id = $resource->getId();
			$type = 'content';
		}
		else {
			throw new ScheduleException("Invalid resource type given");
		}

		$notif = new Notif(Notif::TOPIC_CMS);
		return $notif->notice("CRUD", array(
			'object'   => $type,
			'objectId' => $id,
			'action'   => $action,
			'user'     => $effector->getId()
		));

	}


	/**
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context     - most likely a category ID or ignored
	 * @param string $roletype - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit       - how many messages to retrieve
	 * @param int $offset      - by default, most recent
	 * @param string $filter   - optionally limit to a particular kind of resource
	 * @return Collection|Instance[]
	 */
	protected function getFeedInternal($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0, $filter) {
		$logs = LogMapper::getInstance()->findForFeed($context, $filter, $limit, $offset);

		$c = new Collection();

		foreach($logs as $log) {

			try {
				$resource = $log->getObject();
				$user = $log->getUser();
				$time = $log->getTimestamp()->getUnixTimestamp();

				switch($log->getAction()) {
					case LogMapper::ACTION_CREATE:
						$c->merge(new Collection($this->notifyCreated($roletype, $resource, $user)->getAll($time)));
						break;
					case LogMapper::ACTION_UPDATE:
						$c->merge(new Collection($this->notifyUpdated($roletype, $resource, $user)->getAll($time)));
						break;
					case LogMapper::ACTION_DELETE:
						$c->merge(new Collection($this->notifyDeleted($roletype, $resource, $user)->getAll($time)));
						break;
				}
			}
			catch(Mapper\Exception $e) {
				// mute failures where things were logged about resources that no longer exist
			}

		}
		return $c;
	}


	/**
	 * The generic-est of all messengers. Resources follow a pretty rigid pattern.
	 *
	 * @param string $kind
	 * @param int $id
	 * @param string $action
	 * @param array|string $param
	 * @param User $effector
	 * @param Category $category
	 * @param string $roletype
	 * @return Messenger
	 */
	protected function notify($kind, $id, $action, $param, $effector, $category, $roletype) {
		$msgr = new Messenger();

		$categoryId    = $category->getId();
		$categoryName  = $category->getName();

		$effectorId    = $effector->getId();
		$effectorExtId = $effector->getExternalId();
		$effectorName  = $effector->formatName();

		$icon = "/portrait.php?userId=$effectorExtId&size=square32";
		$href = "/homepage/$categoryId";

		$message = new Message(":effector $action the $kind :resource in :category", $icon, $href);

		$msgr->broadcastToCategory($categoryId, $message, "cms.$kind.$id.$action", Logger::INFO, array(
			'effector' => array($effectorName, "/search/user/$effectorId"),
			'resource' => $param,
			'category' => array($categoryName, "/homepage/$categoryId")
		), array(
			'roletype' => $roletype
		));

		return $msgr;
	}


	/* ASYNC METHODS */

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return bool
	 */
	public function notifySystemCreated($resource, $effector) {
		return $this->notifySystem(LogMapper::ACTION_CREATE, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return bool
	 */
	public function notifySystemUpdated($resource, $effector, $diff) {
		return $this->notifySystem(LogMapper::ACTION_UPDATE, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return bool
	 */
	public function notifySystemDeleted($resource, $effector) {
		return $this->notifySystem(LogMapper::ACTION_DELETE, $resource, $effector);
	}


	/**
	 * @param string $roleType
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyUpdated($roleType, $resource, $effector, $diff = null) {
		$msgr = new Messenger();

		// setup data so as to abstract the different resource types

		$effectorId    = $effector->getId();
		$effectorExtId = $effector->getExternalId();
		$effectorName  = $effector->formatName();

		$resourceId    = $resource->getId();

		if($resource instanceof Category) {
			$resourceName  = $resource->getName();
			$category      = $resource->getParent();
			$resourceKind  = LogMapper::KIND_FOLDER;
			$nameField     = 'name';
			$categoryField = 'parent';
		}
		else {
			$resourceName  = $resource->getDescription();
			$category      = CategoryMapper::getInstance()->findById($resource->getFolder());
			$nameField     = 'description';
			$categoryField = 'category';

			if($resource instanceof File) {
				$resourceKind = LogMapper::KIND_FILE;
			}
			elseif($resource instanceof Link) {
				$resourceKind = LogMapper::KIND_LINK;
			}
			else {
				return $msgr;
			}
		}

		$categoryId    = $category->getId();
		$categoryName  = $category->getName();

		$icon = "/portrait.php?userId=$effectorExtId&size=square32";
		$href = "/homepage/$categoryId";

		// if renamed...
		if(isset($diff[$nameField])) {
			$message = new Message(":user renamed the $resourceKind :oldname to :newname", $icon, $href);

			$msgr->broadcastToCategory($categoryId, $message, "cms.$resourceKind.$resourceId.renamed", Logger::INFO, array(
				'user'    => array($effectorName, "/search/user/$effectorId"),
				'oldname' => $diff[$nameField][1],
				'newname' => array($categoryName, "/homepage/$categoryId")
			), array(
				'roletype' => $roleType
			));
			unset($diff['name']);
		}

		// if moved elsewhere...
		if(isset($diff[$categoryField])) {
			$message = new Message(":user moved the $resourceKind :name into :category", $icon, $href);

			$msgr->broadcastToCategory($categoryId, $message, "cms.$resourceKind.$resourceId.moved", Logger::INFO, array(
				'user'     => array($effectorName, "/search/user/$effectorId"),
				'name'     => $resourceName,
				'category' => array($categoryName, "/homepage/$categoryId")
			), array(
				'roletype' => $roleType
			));
			unset($diff[$categoryField]);
		}

		// if changed in some other way...
		if(count($diff)) {
			$message = new Message(
				":user updated the $resourceKind :name",
				"/portrait.php?userId=$effectorExtId&size=square32",
				"/homepage/$categoryId"
			);

			$msgr->broadcastToCategory($categoryId, $message, "cms.$resourceKind.$resourceId.updated", Logger::INFO, array(
				'user' => array($effectorName, "/search/user/$effectorId"),
				'name' => array($categoryName, "/homepage/$categoryId")
			), array(
				'roletype' => $roleType
			));
		}

		return $msgr;
	}

	/**
	 * @param string $roletype
	 * @param Category|File|Link|Textbox $resource
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public abstract function notifyCreated($roletype, $resource, $author, $diff = null);

	/**
	 * @param string $roletype
	 * @param Category|File|Link|Textbox $resource
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public abstract function notifyDeleted($roletype, $resource, $author, $diff = null);


	/* ASYNC METHODS */

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyStudentCreated($resource, $effector) {
		return $this->notifyCreated(Role::STUDENT, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentUpdated($resource, $effector, $diff = null) {
		return $this->notifyUpdated(Role::STUDENT, $resource, $effector, $diff);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyStudentDeleted($resource, $effector) {
		return $this->notifyDeleted(Role::STUDENT, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyStaffCreated($resource, $effector) {
		return $this->notifyCreated(Role::STAFF, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffUpdated($resource, $effector, $diff = null) {
		return $this->notifyUpdated(Role::STAFF, $resource, $effector, $diff);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyStaffDeleted($resource, $effector) {
		return $this->notifyDeleted(Role::STAFF, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyParentCreated($resource, $effector) {
		return $this->notifyCreated(Role::PARENT, $resource, $effector);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentUpdated($resource, $effector, $diff = null) {
		return $this->notifyUpdated(Role::PARENT, $resource, $effector, $diff);
	}

	/**
	 * @param Category|File|Link|Textbox $resource
	 * @param User $effector
	 * @return Messenger
	 */
	public function notifyParentDeleted($resource, $effector) {
		return $this->notifyDeleted(Role::PARENT, $resource, $effector);
	}

}