<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Url;

class ArticleNotifier extends AbstractModuleNotifier {

	const TRIGGER_POSTED = 'posted';

	/** @var RoleMapper */
	protected $roleMapper;

	/**
	 * At minimum, this method should accept the label and role for which to build a topic.
	 * If more parameters are required in an implementation, PHP will allow you to include optional parameters to the
	 * method signature.
	 *
	 * @param string $eventLabel
	 * @param string $roleId
	 * @param int $topicId
	 * @param int $articleId
	 * @return string
	 */
	protected function buildTopic($eventLabel, $roleId, $topicId = 0, $articleId = 0) {
		return "notify.role.$roleId.news.topic.$topicId.article.$articleId.$eventLabel";
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->roleMapper = RoleMapper::getInstance();
		$this->namespace = NSMapper::getInstance()->findOneBySlug(NSMapper::NS_NEWS);
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {

		/** @var Article $article */
		$article = $bundle->getEvent()->getObject();

		$stamp = $article->getPublishDate()->getUnixTimestamp();

		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
				$bundle->attach(self::TRIGGER_POSTED, $stamp);
				break;
			case Mapper::METHOD_UPDATE:
				// in the future
				if($stamp >= time()) {
					$bundle->attach(self::TRIGGER_POSTED, $stamp);
				}
				// in the past: detach any existing
				else {
					$bundle->detach(self::TRIGGER_POSTED);
				}
				break;
			case Mapper::METHOD_DELETE:
				$bundle->detach(self::TRIGGER_POSTED);
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
		// this class doesn't do feeds
		return new Collection();
	}


	/**
	 * Notify a group type that news has been posted
	 *
	 * @param string $roleType
	 * @param Article $article
	 * @param User $user
	 * @return Messenger
	 */
	protected function generateNotification($roleType, $article, $user) {
		$msgr = new Messenger();

		// self hydration -_-
		/** @var Article $article */
		$article = $this->mapper->findById($article->getId());

		if($article->getImage()->getId()) {
			$icon = sprintf("/storage/image.php?hash=%s&size=square64", $article->getImage()->getHash());
		}
		else {
			$icon = sprintf("/portrait.php?userId=%s&size=square32", $user->getExternalId());
		}

		$text = ":user posted :title under :topic";
		$href = '/news/item/' . $article->getId();

		$articleId = $article->getId();
		$heading = $article->getHeading();

		$authorName = $user->formatName();
		$authorId = $user->getId();

		$topicName = $article->getTopic()->getName();
//		$topicId = $article->getTopic()->getId();
		$url = new Url($topicName);
		$topicSlug = $url->getSlug();

		$msg = new Message($text, $icon, $href);

		$roleIds = $article->getRoles()->filterBy('type', $roleType)->getColumnValues('id');
		$campusIds = $article->getCampuses()->getColumnValues('id');

		// if there's no overlap, nobody can theoretically receive this message, so don't send it
		if(!(count($roleIds) && count($campusIds))) {
			return $msgr;
		}

		// tell everyone that article $articleId has been posted
		$msgr->broadcastToRoleType($roleType, $msg, "news.$articleId.posted", Logger::INFO,
			array(
				'user'     => array($authorName, "/search/user/$authorId"),
				'title'    => array($heading,    "/news/item/$articleId"),
				'topic'    => array($topicName,  "/news/$topicSlug")
			),
			array(
				'role'   => $roleIds,
				'campus' => $campusIds,
			)
		);

		return $msgr;
	}


	/**
	 * Notify students that news has been posted.
	 *
	 * @param Article $article
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentPosted($article, $user, $diff = array()) {
		return $this->generateNotification(Role::STUDENT, $article, $user);
	}

	/**
	 * Notify staff that news has been posted.
	 *
	 * @param Article $article
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffPosted($article, $user, $diff = array()) {
		return $this->generateNotification(Role::STAFF, $article, $user);
	}

	/**
	 * Notify parents that news has been posted.
	 *
	 * @param Article $article
	 * @param User $user
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyParentPosted($article, $user, $diff = array()) {
		return $this->generateNotification(Role::PARENT, $article, $user);
	}

}
