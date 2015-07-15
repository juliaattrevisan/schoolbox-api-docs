<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;


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
use Schoolbox\UserMapper;
use Scrub;
use TextFormatter;

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
	 * @return Messenger
	 */
	protected function generateNotification($roleType, $news, $user) {
		$msgr = new Messenger();

		$icon = sprintf("/portrait.php?userId=%s&size={$this->iconSize}", $user->getExternalId());

		$articleId = $news->getId();
		$heading = $news->getHeading();

		$cat = $this->categoryMapper->findById($news->getCategory()->getId());
		$catId = $cat->getId();
		$catName = $cat->getName();

		$text = ":user posted the news :title in :catname";
		$href = "/homepage/$catId#news-$articleId";

		$authorName = $user->formatName();
		$authorId = $user->getId();

		$msg = new Message($text, $icon, $href, array(
			'subtitle' => 'Posted on '.$news->getPublishDate()->getDateOnly(),
			'abstract' => TextFormatter::cutSentence(Scrub::html2text($news->getBody()), 300)
		));

		// tell everyone that article $articleId has been posted
		$msgr->broadcastToCategory($catId, $msg, "news.$articleId.published", Logger::INFO,
			array(
				'user'     => array($authorName, "/search/user/$authorId"),
				'title'    => $heading,
				'catname'  => array($catName,  "/homepage/$catId")
			),
			array(
				'roletype' => $roleType
			)
		);

		return $msgr;
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::STUDENT, $news, $user);
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::STAFF, $news, $user);
	}

	/**
	 * @param News $news
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentPublished($news, $user, $diff = null) {
		return $this->generateNotification(Role::PARENT, $news, $user);
	}

} 