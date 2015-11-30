<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
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
	 * @return Envelope
	 */
	public function notifyRevised($roleType, $revision, $author) {

		// Hydrate revision because it might not have been when passed -_-
		$revision     = $this->mapper->findById($revision->getId());
		$revisionId   = $revision->getId();
		$revisionName = $revision->getHeading();

		/** @var Wiki $wiki */
		$wiki = $this->wikiMapper->findById($revision->getWiki()->getId());
		$wikiId = $wiki->getId();

		/** @var Category $cat */
		$cat = $this->catMapper->findById($wiki->getCategory()->getId());

		$authorExtId  = $author->getExternalId();

		$categoryId   = $cat->getId();

		$href = "/wiki/revision/$wikiId/$revisionId";

        $envelope = new Envelope();
        $bob = new Builder();

        $bob->setTitle(':author revised the wiki :title in :folder')
            ->setHref($href)
            ->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
            ->addTopic("wiki.$revisionId.created")
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->setParams([
                'author' => new UserParam($author),
                'title'  => new Param($revisionName, $href),
                'folder' => new CategoryParam($cat)
            ])
            ->setFilter([
                'roletype' => $roleType
            ]);

        $envelope->broadcastToCategory($categoryId, UserAccess::ACCESS_READ, $bob->build());

		return $envelope;
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::STUDENT, $revision, $author);
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::STAFF, $revision, $author);
	}

	/**
	 * @param Revision $revision
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentRevised($revision, $author, $diff = array()) {
		return $this->notifyRevised(Role::PARENT, $revision, $author);
	}

} 
