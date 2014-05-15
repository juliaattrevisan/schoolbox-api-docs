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
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;

class BlogNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_POSTED = 'posted';

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
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context     - most likely a category ID or ignored
	 * @param string $roleType - the roletype of the user for whom this feed is being generated
	 * @param int $limit       - how many messages to retrieve
	 * @param int $offset      - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $roleType, $limit = self::SOFT_LIMIT, $offset = 0) {

		/** @var Blog[]|Collection $blogs */
		$blogs = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($blogs as $blog) {
			$c->merge(Feed::getItemsFromMessenger($this->notifyPosted($roleType, $blog, $blog->getOwner()), $blog->getCreated()->getUnixTimestamp()));
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
	 * @return Messenger
	 */
	protected function notifyPosted($roletype, $blog, $author) {
		$msgr = new Messenger();

		$blogId       = $blog->getId();

		$authorId     = $author->getId();
		$authorName   = $author->formatName();
		$authorExtId  = $author->getExternalId();

		$categoryId   = $blog->getCategory()->getId();
		$category     = CategoryMapper::getInstance()->findById($categoryId);
		$categoryName = $category->getName();


		$msg = new Message(
			':author blogged about :title in :catname',
			"/portrait.php?userId=$authorExtId&size=square32",
			"/homepage/$categoryId"
		);

		$msgr->broadcastToCategory($categoryId, $msg, "blog.$blogId.posted", Logger::INFO, array(
			'author'  => array($authorName, "/search/user/$authorId"),
			'title'   => $blog->getHeading(),
			'catname' => array($categoryName, "/homepage/$categoryId")
		), array(
			'roletype' => $roletype
		));

		return $msgr;
	}


	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::STAFF, $blog, $author);
	}


	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::STUDENT, $blog, $author);
	}

	/**
	 * @param Blog $blog
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentPosted($blog, $author, $diff = null) {
		return $this->notifyPosted(Role::PARENT, $blog, $author);
	}

} 