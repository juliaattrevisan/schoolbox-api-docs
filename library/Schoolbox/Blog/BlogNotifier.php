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
use Alaress\Async\Notification\Parameter\ParameterInterface;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;

class BlogNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_POSTED = 'posted';


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
		// we only care about notifying blog post creation
		if($bundle->getMethod() == Mapper::METHOD_CREATE) {
			/** @var Blog $blog */
			$blog = $bundle->getEvent()->getObject();
			$bundle->attach(self::TRIGGER_POSTED, $blog->getCreated()->getUnixTimestamp());
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

		if(!$this->mapper instanceof FeedableMapperInterface) {
			return $c;
		}

		/** @var Blog[]|Collection $blogs */
		$blogs = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($blogs as $blog) {
			$c->merge(new Collection($this->notifyPosted($roletype, $blog, $blog->getOwner())->getAll($blog->getCreated()->getUnixTimestamp())));
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
	 * @param Blog $blog
	 * @param User $author
	 * @return Envelope
	 */
	protected function notifyPosted($roletype, $blog, $author) {
		$envelope = new Envelope();

		$blogId       = $blog->getId();

		$authorExtId  = $author->getExternalId();

		$categoryId   = $blog->getCategory()->getId();
		$category     = CategoryMapper::getInstance()->findById($categoryId);

		$bob = new Builder();

		$bob->setTitle(':author blogged about :title in :catname')
			->setParams(array(
				'author' => new UserParam($author, ParameterInterface::STACKING_REPLACE),
				'title'  => new Param($blog->getHeading()),
				'catname' => new CategoryParam($category)
			))
			->setIcon("/portrait.php?userId=$authorExtId&size={$this->iconSize}")
			->setHref("/homepage/$categoryId#blog_$blogId")
			->setSeverity(Logger::INFO)
			->setNamespace($this->getSlug())
			->addTopic("blog.$blogId.posted")
			->setTimestamp($blog->getCreated()->getUnixTimestamp())
			->addFilter('roletype', $roletype)
			->setBody($blog->getBody());

		$envelope->broadcastToCategory($categoryId, UserAccess::ACCESS_READ, $bob->build());

		return $envelope;
	}


	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::STAFF, $blog, $author);
	}


	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::STUDENT, $blog, $author);
	}

	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::PARENT, $blog, $author);
	}

} 