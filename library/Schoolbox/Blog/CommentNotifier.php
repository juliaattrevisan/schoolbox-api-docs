<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


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
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

class CommentNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_COMMENTED = 'commented';
	const TRIGGER_FOLLOWED  = 'followed';

	/** @var BlogMapper */
	protected $blogMapper;

	/** @var CategoryMapper */
	protected $catMapper;

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->blogMapper = BlogMapper::getInstance();
		$this->catMapper = CategoryMapper::getInstance();
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_BLOG;
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

		/** @var Collection|Comment[] $comments */
		$comments = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($comments as $comment) {
			$c->merge(new Collection($this->notifyCommented($roletype, $comment, $comment->getOwner())->getAll()));
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
	 * @param string $roletype
	 * @param Comment $comment
	 * @param User $author
	 * @return Messenger
	 */
	protected function notifyCommented($roletype, $comment, $author) {
		$msgr = new Messenger();

		$commentId    = $comment->getId();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		/** @var Blog $blog */
		$blog         = $this->blogMapper->findById($comment->getBlog()->getId());
		$blogName     = $blog->getHeading();
		$blogOwnerId  = $blog->getOwner()->getId();

		$categoryId   = $comment->getBlog()->getCategory()->getId();
		$category     = CategoryMapper::getInstance()->findById($categoryId);
		$categoryName = $category->getName();

		$msg = new Message(
			':author commented on :title in :catname: ":comment"',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/homepage/$categoryId"
		);

		// send this to the blog post author
		$msgr->targetToUser($blogOwnerId, $msg, "blog.$commentId.commented", Logger::INFO, array(
			'author'  => array($authorName, "/search/user/$authorId"),
			'title'   => $blogName,
			'catname' => array($categoryName, "/homepage/$categoryId"),
			'comment' => \TextFormatter::cutSentence($comment->getBody(), 48)
		));

		return $msgr;
	}

	/**
	 * @param string $roletype
	 * @param Comment $comment
	 * @param User $author
	 * @return Messenger
	 */
	protected function notifyFollowed($roletype, $comment, $author) {
		$msgr = new Messenger();

		$commentId    = $comment->getId();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		/** @var Blog $blog */
		$blogId       = $comment->getBlog()->getId();
		$blog         = $this->blogMapper->findById($blogId);
		$blogName     = $blog->getHeading();

		$categoryId   = $blog->getCategory()->getId();
		$category     = $this->catMapper->findById($categoryId);
		$categoryName = $category->getName();

		$msg = new Message(
			':author also commented on :title in :catname: ":comment"',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/homepage/$categoryId"
		);

		/** @var Comment[]|Collection $commentCollection */
		$commentCollection = $this->mapper->findByColumn('bid', $blogId);
		/** @var User[]|Collection $userCollection */
		$userCollection = new Collection($commentCollection->getColumnValues('owner'));
		$userIds = array_unique($userCollection->getColumnValues('id'));

		foreach($userIds as $id) {
			// send this to anyone who has commented on the blog in question
			$msgr->targetToUser($id, $msg, "blog.$commentId.followed", Logger::INFO, array(
				'author'  => array($authorName, "/search/user/$authorId"),
				'title'   => $blogName,
				'catname' => array($categoryName, "/homepage/$categoryId"),
				'comment' => \TextFormatter::cutSentence($comment->getBody(), 48)
			));
		}

		return $msgr;
	}

	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented(Role::STUDENT, $comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented(Role::STAFF, $comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented(Role::PARENT, $comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed(Role::STUDENT, $comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed(Role::STAFF, $comment, $author);
	}

	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed(Role::PARENT, $comment, $author);
	}

}