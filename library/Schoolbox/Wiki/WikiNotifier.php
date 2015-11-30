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
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class WikiNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_CREATED = 'created';

    /** @var RevisionMapper */
    protected $revisionMapper;

    /** @var UserMapper */
    protected $userMapper;

	/** @var Folder\Mapper */
	protected $folderMapper;

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_WIKI;
	}


    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['revisionMapper'])) {
            $dependencies['revisionMapper'] = RevisionMapper::getInstance();
        }
	    if (empty($dependencies['userMapper'])) {
		    $dependencies['userMapper'] = UserMapper::getInstance();
	    }
	    if (empty($dependencies['folderMapper'])) {
		    $dependencies['folderMapper'] = Folder\Mapper::getInstance();
	    }

        parent::loadDependencies($dependencies);
    }


    /**
     * @param RevisionMapper $revisionMapper
     * @return static
     */
    protected function setRevisionMapper($revisionMapper)
    {
        $this->revisionMapper = $revisionMapper;
        return $this;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


	/**
	 * @param Folder\Mapper $folderMapper
	 * @return static
	 */
	protected function setFolderMapper($folderMapper)
	{
		$this->folderMapper = $folderMapper;
		return $this;
	}

    /**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		// we only care about creation
		if($bundle->getMethod() == Mapper::METHOD_CREATE) {
			/** @var Wiki $wiki */
			$wiki = $bundle->getEvent()->getObject();
			$bundle->attach(self::TRIGGER_CREATED, $wiki->getCreated()->getUnixTimestamp());
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

		/** @var Wiki[]|Collection $wikis */
		$wikis = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($wikis as $wiki) {
            $first = $this->revisionMapper->findOneByColumn('wikiID', $wiki->getId(), [ 'created' => 'asc' ]);
            try {
                $user = $this->userMapper->findById($first->getUser()->getId());
            } catch (Mapper\Exception $e) {
                $user = $this->userMapper->createModel();
            }
			$c->merge(
				new Collection($this->notifyCreated($roletype, $wiki, $user)->getAll($wiki->getCreated()->getUnixTimestamp()))
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
	 * @param Wiki $wiki
	 * @param User $author
	 * @return Envelope
	 */
	protected function notifyCreated($roleType, $wiki, $author) {
		$envelope = new Envelope();

		$wikiId       = $wiki->getId();
		$wikiSlug     = $wiki->getSlug();

		$author       = $this->userMapper->findById($author->getId());
		$authorExtId  = $author->getExternalId();

		$folder       = $this->folderMapper->findById($wiki->getCategory()->getId());
		$folderId     = $folder->getId();

		$bob = new Builder();

		$bob->setTitle(':author created a new wiki in :catname')
			->setParams([
				'author'  => new UserParam($author),
				'catname' => new CategoryParam($folder)
			])
			->setHref("/wiki/$folderId/$wikiSlug")
			->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
			->setSeverity(Logger::INFO)
			->setTopics([ "wiki.$wikiId.created" ])
			->setFilter([ 'roletype' => $roleType ])
			->setNamespace($this->getSlug());

		$envelope->broadcastToCategory($folderId, UserAccess::ACCESS_READ, $bob->build());

		return $envelope;
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::STUDENT, $wiki, $author);
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::STAFF, $wiki, $author);
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::PARENT, $wiki, $author);
	}

} 
