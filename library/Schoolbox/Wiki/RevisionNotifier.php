<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use Alaress\Async\Notification\Instance\Instance;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

class RevisionNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_REVISED = 'revised';

	/** @var WikiMapper */
	protected $wikiMapper;

	/** @var CategoryMapper */
	protected $catMapper;

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->wikiMapper = WikiMapper::getInstance();
		$this->catMapper = CategoryMapper::getInstance();
		parent::loadDependencies($dependencies);
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_WIKI;
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		// we only care about creation
		if($bundle->getMethod() == Mapper::METHOD_CREATE) {
			/** @var Revision $revision */
			$revision = $bundle->getEvent()->getObject();
			$bundle->attach(self::TRIGGER_REVISED, $revision->getCreated()->getUnixTimestamp());
		}
	}

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
		$c = new Collection();

		// be sure the target method exists
		if(!$this->mapper instanceof FeedableMapperInterface) {
			return $c;
		}

		/** @var Collection|Revision[] $revisions */
		$revisions = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($revisions as $revision) {
			$c->merge(
				new Collection($this->notifyRevised($roletype, $revision, $revision->getUser())->getAll($revision->getCreated()->getUnixTimestamp()))
			);
		}
		return $c;
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


	/* ASYNC NOTIFY METHODS */

	/**
	 * @param string $roleType
	 * @param Revision $revision
	 * @param User $author
	 * @return Messenger
	 */
	public function notifyRevised($roleType, $revision, $author) {
		$msgr = new Messenger();

		// Hydrate revision because it might not have been when passed -_-
		$revision     = $this->mapper->findById($revision->getId());
		$revisionId   = $revision->getId();
		$revisionName = $revision->getHeading();

		/** @var Wiki $wiki */
		$wiki = $this->wikiMapper->findById($revision->getWiki()->getId());

		/** @var Category $cat */
		$cat = $this->catMapper->findById($wiki->getCategory()->getId());

		$revisionSlug = $wiki->getSlug();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		$categoryId   = $cat->getId();
		$categoryName = $cat->getName();

		$msg = new Message(
			':author revised the wiki :title in :catname',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/wiki/$categoryId/$revisionSlug"
		);

		$msgr->broadcastToCategory($categoryId, $msg, "wiki.$revisionId.created", Logger::INFO, array(
			'author'  => array($authorName, "/search/user/$authorId"),
			'title'   => array($revisionName, "/wiki/$categoryId/$revisionSlug"),
			'catname' => array($categoryName, "/homepage/$categoryId")
		), array(
			'roletype' => $roleType
		));

		return $msgr;
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::STUDENT, $revision, $author);
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::STAFF, $revision, $author);
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::PARENT, $revision, $author);
	}

} 