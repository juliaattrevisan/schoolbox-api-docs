<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\Feed;
use Schoolbox\Feed\Item;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;

class CommentNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_COMMENTED = 'commented';
	const TRIGGER_FOLLOWED  = 'followed';

	/** @var BlogMapper */
	protected $blogMapper;

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->blogMapper = BlogMapper::getInstance();
	}

	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		// we only care about create
		if($bundle->getMethod() == Mapper::METHOD_CREATE) {

			/** @var Comment $comment */
			$comment = $bundle->getEvent()->getObject();

			$bundle->attachEach(array(
				self::TRIGGER_COMMENTED => $comment->getCreated()->getUnixTimestamp(),
				self::TRIGGER_FOLLOWED  => $comment->getCreated()->getUnixTimestamp()
			));
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

		/** @var Collection|Comment[] $comments */
		$comments = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($comments as $comment) {
			$c->merge(Feed::getItemsFromMessenger($this->notifyStudentCommented($comment, $comment->getOwner()), $comment->getCreated()->getUnixTimestamp()));
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


	/* ASYNC NOTIFICATION METHODS */

	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentCommented($comment, $author, $diff = array()) {
		$msgr = new Messenger();


		$commentId       = $comment->getId();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		$categoryId   = $comment->getBlog()->getCategory()->getId();
		$categoryName = $comment->getBlog()->getCategory()->getName();


		$msg = new Message(
			':author commented on :title in :catname',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/homepage/$categoryId",
			array(
				'subtitle',
				'abstract'
			)
		);

		$msgr->broadcastToCategory($categoryId, $msg, "blog.$commentId.commented", Logger::INFO, array(
			'author'  => array($authorName, "/search/user/$authorId"),
			'title'   => $comment->getBlog()->getHeading(),
			'catname' => array($categoryName, "/homepage/$categoryId")
		), array(
			'roletype' => Role::STUDENT
		));

		return $msgr;
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffCommented($comment, $author, $diff = array()) {
		$msgr = new Messenger();
		return $msgr;

	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentFollowed($comment, $author, $diff = array()) {
		$msgr = new Messenger();
		return $msgr;

	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffFollowed($comment, $author, $diff = array()) {
		$msgr = new Messenger();
		return $msgr;

	}

}