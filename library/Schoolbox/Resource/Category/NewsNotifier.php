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

class NewsNotifier extends AbstractModuleNotifier {

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
	 * Generate n messages for a category, from the perspective of casual observation.
	 *
	 * @param int $category
	 * @param int $limit
	 * @return Collection|TargetedMessage[]
	 */
	public function getFeed($category, $limit) {
		$c = new Collection();

		$options = array(
			'sort' => array(
				'datetime_published' => 'desc'
			),
			'limit' => $limit
		);

		/** @var Collection|News[] $newses */
		$newses = $this->mapper->findByColumn('code', $category, $options)->filterByCallback(function($news) {
			/** @var $news News */
			return $news->getPublishDate()->getUnixTimestamp() < time();
		});

		// really...?
		$ownerCache = array();

		foreach($newses as $news) {
			// hydrate owners :(
			$uId = $news->getOwner()->getId();
			if(!isset($ownerCache[$uId])) {
				$ownerCache[$uId] = $this->userMapper->findById($uId);
			}
			$owner = $ownerCache[$uId];

			$c->merge($this->notifyStudentPublished($news, $owner)->extractAll(null, new BlankTrigger($news->getPublishDate()->getUnixTimestamp())));

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

		$cat = $this->categoryMapper->findById($news->getCategory()->getId());
		$catId = $cat->getId();
		$catName = $cat->getName();

		$authorName = $user->formatName();
		$authorId = $user->getId();

		$msg = new Message($text, $icon, $href);

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