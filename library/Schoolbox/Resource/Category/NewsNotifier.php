<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;


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
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class NewsNotifier extends AbstractModuleNotifier implements SourceInterface {

	const TRIGGER_PUBLISHED = 'published';

	/** @var UserMapper */
	protected $userMapper;

	/** @var CategoryMapper */
	protected $categoryMapper;

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->userMapper = UserMapper::getInstance();
		$this->categoryMapper = CategoryMapper::getInstance();
		parent::loadDependencies($dependencies);
	}

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_NEWS;
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		$method = $bundle->getMethod();

		if(in_array($method, array(Mapper::METHOD_UPDATE, Mapper::METHOD_CREATE))) {
			/** @var News $news */
			$news = $bundle->getEvent()->getObject();
			$bundle->attach(self::TRIGGER_PUBLISHED, $news->getPublishDate()->getUnixTimestamp());
		}
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

		/** @var Collection|News[] $articles */
		$articles = $this->mapper->findForFeed($context, $limit, $offset);

		foreach($articles as $news) {
			$c->merge(new Collection($this->generateNotification($roletype, $news, $news->getOwner())->getAll($news->getPostDate()->getUnixTimestamp())));
		}

		return $c;
	}

	/**
	 * Notify a group type that news has been posted
	 *
	 * @param string $roleType
	 * @param News $news
	 * @param User $user
	 * @return Envelope
	 */
	protected function generateNotification($roleType, $news, $user) {

		$envelope = new Envelope();
		$bob = new Builder();


		$icon = sprintf("/portrait.php?userId=%s&size={$this->iconSize}", $user->getExternalId());

		$articleId = $news->getId();
		$heading = $news->getHeading();

		$shortDate  = $news->getPostDate()->getDateOnly();
		$authorName = $user->formatName();

		$cat = $this->categoryMapper->findById($news->getCategory()->getId());
		$catId = $cat->getId();
		$catName = $cat->getName();

		$text = ":user posted the news :title in :catname";
		$href = "/homepage/$catId#news-$articleId";

		$bob->setTitle($text)
			->setHref($href)
			->setIcon($icon)
			->setNamespace($this->getSlug())
			->setTimestamp($news->getPublishDate()->getUnixTimeStamp())
			->setSeverity(Logger::INFO)
			->addTopic("news.$articleId.published")
			->addFilter('roletype', $roleType)
			->setMeta([
				'title'  => $heading,
				'author' => $user->formatName(),
				'date'   => $news->getPostDate()->getLong()
			])
			->setByline("$shortDate by $authorName in $catName")
			->setBody($news->getBody())
			->setParams([
				'user' => new UserParam($user),
				'title' => new Param($heading, $href),
				'catname' => new CategoryParam($cat)
			]);

		$envelope->broadcastToCategory($catId, UserAccess::ACCESS_READ, $bob->build());

		return $envelope;
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStudentPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::STUDENT, $news, $user);
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyStaffPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::STAFF, $news, $user);
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Envelope
	 */
	public function notifyParentPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::PARENT, $news, $user);
	}

} 