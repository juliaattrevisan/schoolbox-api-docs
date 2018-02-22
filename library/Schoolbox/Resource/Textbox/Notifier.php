<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Textbox;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\LogMapper;
use Schoolbox\Resource\ResourceNotifier;
use Schoolbox\Resource\Textbox;
use Schoolbox\User;

class Notifier extends ResourceNotifier {


	/**
	 * @param string  $action
	 * @param string  $roletype
	 * @param Model   $resource
	 * @param User    $effector
	 * @param array   $diff
	 * @return Envelope
	 */
	protected function notifyAffected($action, $roletype, $resource, $effector, $diff = null) {
		$envelope = new Envelope();

		$resourceId = $resource->getId();
		$category = FolderMapper::getInstance()->findById($resource->getCategory()->getId()); // THERE HAS TO BE A WAY TO NOT NEED TO DO THIS

		$bob = new Builder();

		$heading = $this->getResourceName($resource);
		$href = "/homepage/".$category->getId()."/#textbox".$resourceId;

		$bob->setTitle(":author $action content in :homepage \":heading\"")
		    ->setParams(array(
			    'author'   => new UserParam($effector),
			    'homepage' => new CategoryParam($category),
			    'heading'  => new Param($heading, $href)
		    ))
		    ->setIcon("/portrait.php?size={$this->iconSize}&userId=".$effector->getExternalId())
		    ->setHref($href)
			->setNamespace($this->getSlug())
		    ->setSeverity(Logger::INFO)
		    ->addTopic("cms.textbox.$resourceId.$action")
		    ->addFilter('roletype', $roletype);

		$envelope->broadcastToCategory($category, UserAccess::ACCESS_READ, $bob->build());

		return $envelope;
	}


	/**
	 * @param string $roletype
	 * @param Model $resource
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyCreated($roletype, $resource, $author, $diff = null) {
		return $this->notifyAffected(self::TRIGGER_CREATED, $roletype, $resource, $author, $diff);
	}


	/**
	 * @param string $roletype
	 * @param Model $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyUpdated($roletype, $resource, $effector, $diff = null) {
		return $this->notifyAffected(self::TRIGGER_UPDATED, $roletype, $resource, $effector, $diff);
	}


	/**
	 * @param string $roletype
	 * @param Model $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyDeleted($roletype, $resource, $effector, $diff = null) {
		return $this->notifyAffected(self::TRIGGER_DELETED, $roletype, $resource, $effector, $diff);
	}


	/**
	 * @inheritdoc
	 */
	public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0) {
		return $this->getFeedInternal($context, $user->getRole()->getType(), $limit, $offset, LogMapper::KIND_CONTENT);
	}


	/**
	 * @param Model $resource
	 * @return string
	 */
	protected function getResourceName($resource) {
        if (empty($resource->getHeading())) {
            return "Text Box";
        } else {
            return $resource->getHeading();
        }
	}


	/**
	 * @param Model $resource
	 * @return Folder
	 */
	protected function getResourceCategory($resource) {
		return $resource->getCategory();
	}


	/**
	 * @return string
	 */
	protected function getResourceKind() {
		return LogMapper::KIND_CONTENT;
	}


	/**
	 * @return string
	 */
	protected function getResourceNameProperty() {
		return 'heading';
	}


	/**
	 * @return string
	 */
	protected function getResourceCategoryProperty() {
		return 'category';
	}

}
