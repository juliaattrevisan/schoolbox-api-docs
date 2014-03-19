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
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\TriggerBundle;
use Schoolbox\Role;
use Schoolbox\User;

class ArticleNotifier extends AbstractModuleNotifier {

	const TRIGGER_POSTED = 'posted';


	/**
	 * At minimum, this method should accept the label and role for which to build a topic.
	 * If more parameters are required in an implementation, PHP will allow you to include optional parameters to the
	 * method signature.
	 *
	 * @param string $eventLabel
	 * @param string $roleType
	 * @param int $articleId
	 * @return string
	 */
	protected function buildTopic($eventLabel, $roleType, $articleId = 0) {
		return "notify.roletype.$roleType.news.$articleId.$eventLabel";
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// noop: no dependencies
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {

		/** @var Article $article */
		$article = $bundle->getObject();

		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
			case Mapper::METHOD_UPDATE:
				$stamp = $article->getPublishDate()->getUnixTimestamp();
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
	 * Notify a group type that news has been posted
	 *
	 * @param string $groupType
	 * @param Article $article
	 * @param User $user
	 * @return Messenger
	 */
	protected function generateNotification($groupType, $article, $user) {
		$msgr = new Messenger();

		$text = ":user posted :title under :topic";
		$icon = $article->getImage()->getFilename();
		$href = '/news/item/' . $article->getId();

		$articleId = $article->getId();
		$heading = $article->getHeading();

		$authorName = $user->formatName();
		$authorId = $user->getId();

		$topicName = $article->getTopic()->getName();
		$url = new \Url($topicName);
		$topicSlug = $url->getSlug();

		$msg = new Message($text, $icon, $href);

		$topic = $this->buildTopic('posted', $groupType, $articleId);

		$msgr->bind($topic, Logger::INFO, $msg, array(
			'user'  => array($authorName, "/search/user/$authorId"),
			'title' => array($heading,    "/news/item/$articleId"),
			'topic' => array($topicName,  "/news/$topicSlug")
		));

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
