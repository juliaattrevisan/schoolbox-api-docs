<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\ScheduleException;
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
		parent::loadDependencies($dependencies);
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
			$c->merge(new Collection($this->notifyCommented($comment, $comment->getOwner())->getAll($comment->getCreated()->getUnixTimestamp())));
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


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @return Builder
	 */
	protected function createBuilder($comment, $author) {
		$authorExtId  = $author->getExternalId();

		/** @var Blog $blog */
		$blog         = $this->blogMapper->findById($comment->getBlog()->getId());
		$blogName     = $blog->getHeading();

		$categoryId   = $blog->getCategory()->getId();
		$category     = CategoryMapper::getInstance()->findById($categoryId);

		$bob = new Builder();

		$bob->setParams(array(
			    'author'  => new UserParam($author),
			    'title'   => new Param($blogName),
			    'catname' => new CategoryParam($category)
		    ))
		    ->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
		    ->setHref("/homepage/$categoryId#blogComment_".$blog->getId()) // dedupe per blog
		    ->setSeverity(Logger::INFO)
		    ->setTimestamp($comment->getCreated()->getUnixTimestamp())
		    ->setNamespace($this->getSlug())
		    ->setBody($comment->getBody())
		    ->setByline($author->formatName("{firstname} wrote"));

		return $bob;
	}

	/* ASYNC NOTIFICATION METHODS */

	/**
	 * @param Comment $comment
	 * @param User $author
	 * @return Envelope
	 */
	protected function notifyCommented($comment, $author) {
		$envelope = new Envelope();

		$commentId   = $comment->getId();
		$blog        = $this->blogMapper->findById($comment->getBlog()->getId());
		$blogOwnerId = $blog->getOwner()->getId();

		$bob = $this->createBuilder($comment, $author)
					->setTitle(':author commented on :title in :catname')
					->addTopic("blog.$commentId.commented");

		// send this to the blog post author
		$envelope->targetToUser($blogOwnerId, $bob->build());
		return $envelope;
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @return Envelope
	 */
	protected function notifyFollowed($comment, $author) {
		$envelope = new Envelope();

		/** @var Blog $blog */
		$blog      = $this->blogMapper->findById($comment->getBlog()->getId());
		$commentId = $comment->getId();

		$bob = $this->createBuilder($comment, $author)
		            ->setTitle(':author also commented on :title in :catname')
		            ->addTopic("blog.$commentId.followed");

		/** @var Comment[]|Collection $commentCollection */
		$commentCollection = $this->mapper->findByColumn('bid', $blog->getId());

		/** @var User[]|Collection $userCollection */
		$userCollection = new Collection($commentCollection->getColumnValues('owner'));
		$userIds = array_unique($userCollection->getColumnValues('id'));

		foreach($userIds as $id) {
			// send this to anyone who has commented on the blog in question
			$envelope->targetToUser($id, $bob->build());
		}

		return $envelope;
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented($comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented($comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentCommented($comment, $author, $diff = array()) {
		return $this->notifyCommented($comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed($comment, $author);
	}


	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed($comment, $author);
	}

	/**
	 * @param Comment $comment
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentFollowed($comment, $author, $diff = array()) {
		return $this->notifyFollowed($comment, $author);
	}

}