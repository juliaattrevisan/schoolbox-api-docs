<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;


use Alaress\Scheduler\Trigger\BlankTrigger;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\Feed;
use Schoolbox\Feed\Item;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\External\TargetedMessage;
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

		$this->namespace = NSMapper::getInstance()->findOneBySlug(NSMapper::NS_NEWS);
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
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context - most likely a category ID or ignored
	 * @param int $limit - how many messages to retrieve
	 * @param int $offset - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $limit = self::SOFT_LIMIT, $offset = 0) {

		$newses = $this->mapper->findForFeed($context, $limit, $offset);

		$c = new Collection();
		foreach($newses as $news) {
			$messenger = $this->notifyStudentPublished($news, $news->getOwner());
			$timestamp = $news->getPublishDate()->getUnixTimestamp();
			$c->merge(Feed::getItemsFromMessenger($messenger, $timestamp));
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

		$icon = sprintf("/portrait.php?userId=%s&size=square32", $user->getExternalId());

		$text = ":user posted the news :title in :catname";
		$href = '/homepage/' . $news->getId();

		$articleId = $news->getId();
		$heading = $news->getHeading();

		$cat = $news->getCategory();
		$catId = $cat->getId();
		$catName = $cat->getName();

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