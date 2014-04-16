<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\Feed;
use Schoolbox\Feed\Item;
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
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->namespace = NSMapper::getInstance()->findOneBySlug(NSMapper::NS_WIKI);
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
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context - most likely a category ID or ignored
	 * @param int $limit - how many messages to retrieve
	 * @param int $offset - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $limit = self::SOFT_LIMIT, $offset = 0) {
		/** @var Wiki[]|Collection $wikis */
		$wikis = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($wikis as $wiki) {
			$c->merge(Feed::getItemsFromMessenger(
				$this->notifyStudentCreated($wiki, $wiki->getLatestRevision()->getUser()),
				$wiki->getCreated()->getUnixTimestamp()
			));
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
			"/homepage/$categoryId",
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