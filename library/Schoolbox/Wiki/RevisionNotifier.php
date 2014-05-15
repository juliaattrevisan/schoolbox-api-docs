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

class RevisionNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_REVISED = 'revised';

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
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context     - most likely a category ID or ignored
	 * @param string $roleType - the roletype of the user for whom this feed is being generated
	 * @param int $limit       - how many messages to retrieve
	 * @param int $offset      - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $roleType, $limit = self::SOFT_LIMIT, $offset = 0) {
		/** @var Revision[]|Collection $revisions */
		$revisions = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($revisions as $revision) {
			$c->merge(Feed::getItemsFromMessenger(
				$this->notifyStudentRevised($revision, $revision->getUser()),
				$revision->getCreated()->getUnixTimestamp()
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
	 * @param Revision $revision
	 * @param User $author
	 * @return Messenger
	 */
	public function notifyRevised($roleType, $revision, $author) {
		$msgr = new Messenger();

		$revisionId   = $revision->getId();
		$revisionName = $revision->getHeading();
		$revisionSlug = $revision->getWiki()->getSlug();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		$categoryId   = $revision->getWiki()->getCategory()->getId();
		$categoryName = $revision->getWiki()->getCategory()->getName();

		$msg = new Message(
			':author revised the wiki :title in :catname',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/homepage/$categoryId",
			array(
				'subtitle',
				'abstract'
			)
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