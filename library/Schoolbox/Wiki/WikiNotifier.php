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
use Schoolbox\Role;
use Schoolbox\User;

class WikiNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_CREATED = 'created';


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
			$c->merge(
				new Collection($this->notifyCreated($roletype, $wiki, $wiki->getLatestRevision()->getUser())->getAll($wiki->getCreated()->getUnixTimestamp()))
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
	 * @return Messenger
	 */
	protected function notifyCreated($roleType, $wiki, $author) {
		$msgr = new Messenger();

		$wikiId       = $wiki->getId();
		$wikiName     = $wiki->getLatestRevision()->getHeading();
		$wikiSlug     = $wiki->getSlug();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		$categoryId   = $wiki->getCategory()->getId();
		$categoryName = $wiki->getCategory()->getName();


		$msg = new Message(
			':author created the wiki :title in :catname',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/wiki/$categoryId/$wikiSlug",
			array(
				'subtitle',
				'abstract'
			)
		);

		$msgr->broadcastToCategory($categoryId, $msg, "wiki.$wikiId.created", Logger::INFO, array(
			'author'  => array($authorName, "/search/user/$authorId"),
			'title'   => array($wikiName, "/wiki/$categoryId/$wikiSlug"),
			'catname' => array($categoryName, "/homepage/$categoryId")
		), array(
			'roletype' => $roleType
		));

		return $msgr;
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::STUDENT, $wiki, $author);
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::STAFF, $wiki, $author);
	}

	/**
	 * @param Wiki $wiki
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentCreated($wiki, $author, $diff = array()) {
		return $this->notifyCreated(Role::PARENT, $wiki, $author);
	}

} 