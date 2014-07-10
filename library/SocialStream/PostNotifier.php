<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;
use Scrub;
use SocialStream_Post_Abstract;
use TextFormatter;

class PostNotifier extends AbstractModuleNotifier {

	const TRIGGER_POSTED  = 'posted';
	const TRIGGER_REPLIED = 'replied';

	/** @var CategoryMapper */
	protected $catMapper;

	/** @var UserAccessMapper */
	protected $userAccessMapper;

	/** @var UserMapper */
	protected $userMapper;

	/**
	 * @return string
	 */
	protected function getSlug() {
		return NSMapper::NS_SSTREAM;
	}


	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		$this->catMapper = CategoryMapper::getInstance();
		$this->userAccessMapper = UserAccessMapper::getInstance();
		$this->userMapper = UserMapper::getInstance();
		parent::loadDependencies($dependencies);
	}


	/**
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		switch($bundle->getMethod()) {
			case Mapper::METHOD_CREATE:
				/** @var SocialStream_Post_Abstract $post (I THINK!) */
				$post = $bundle->getEvent()->getObject();

				// is a child post: ie. comment
				if($post->getParentId()) {
					$bundle->attach(self::TRIGGER_REPLIED, self::IMMEDIATELY);
				}
				// has no parent: ie. fresh post
				else {
					$bundle->attach(self::TRIGGER_POSTED, self::IMMEDIATELY);
				}
				break;
		}
	}


	/**
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @return string
	 */
	protected function getPostIcon($post, $author) {
		if($post instanceof \SocialStream_Post_Url) {
			return '/socialstream/image.php?size=square32&id='.$post->getPostId();
		}
		elseif($post instanceof \SocialStream_Post_Video) {
			return '/portrait.php?size=square32&userId='.$author->getExternalId();
		}
		elseif($post instanceof \SocialStream_Post_Image) {
			return '/socialstream/image.php?size=square32&id='.$post->getPostId();
		}
		// string
		return '/portrait.php?size=square32&userId='.$author->getExternalId();
	}


	/* ASYNC METHODS */

	/**
	 * @param string $roleType
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	protected function notifyPosted($roleType, $post, $author, $diff = null) {
		$msgr = new Messenger();

		/** @var Category $cat */
		$cat = $this->catMapper->findById($post->getHomepageId());

		if($post instanceof \SocialStream_Post_Url) {
			$text = ':author posted a link in :cat: ":stub"';
		}
		elseif($post instanceof \SocialStream_Post_Video) {
			$text = ':author posted a video in :cat: ":stub"';
		}
		elseif($post instanceof \SocialStream_Post_Image) {
			$text = ':author posted a video in :cat: ":stub"';
		}
		// string
		else {
			$text = ':author posted in :cat: ":stub"';
		}

		$href = '/homepage/'.$cat->getId();

		$msg = new Message($text, $this->getPostIcon($post, $author), $href);

		$msgr->broadcastToCategory($cat->getId(), $msg, "sstream.{$post->getPostId()}.posted", Logger::NOTICE, array(
			'author' => array($author->formatName(), '/search/user/'.$author->getId()),
			'cat'    => array($cat->getName(), $href),
			'stub'   => TextFormatter::cutSentence(Scrub::html2text($post->getBody()), 48)
		), array(
			'roletype' => $roleType
		));

		return $msgr;
	}


	/**
	 * @param string $roleType
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	protected function notifyReplied($roleType, $post, $author, $diff = null) {
		$msgr = new Messenger();

		/** @var Category $cat */
		$cat = $this->catMapper->findById($post->getHomepageId());

		// list of user's to notify: parent ("user replied to your post") and siblings ("user ALSO replied to")
		/** @var SocialStream_Post_Abstract $parent */
		$parent = $this->mapper->findById($post->getParentId());

		$parentAuthorId = $parent->getOwnerId();

		// compensate for inconsistent return types -_-
		if($parentAuthorId instanceof User) {
			$parentAuthorId = $parentAuthorId->getId();
		}

		/** @var Collection|SocialStream_Post_Abstract[] $siblings */
		$siblings = $this->mapper->findChildren($post);

		$siblingAuthorsOrIds = $siblings->getColumnValues('ownerId') ?: array();

		$href = '/homepage/'.$cat->getId();
		$icon = $this->getPostIcon($post, $author);

		$parentMsg = new Message(':author replied to your post in :cat: ":stub"', $icon, $href);
		$siblingMsg = new Message(':author also replied to a post in :cat: ":stub"', $icon, $href);

		$msgr->targetToUser($parentAuthorId, $parentMsg, "sstream.{$post->getPostId()}.replied", Logger::NOTICE, array(
			'author' => array($author->formatName(), '/search/user/'.$author->getId()),
			'cat'    => array($cat->getName(), $href),
			'stub'   => TextFormatter::cutSentence(Scrub::html2text($post->getBody()), 48)
		));

		foreach($siblingAuthorsOrIds as $siblingOrId) {
			$siblingId = $siblingOrId instanceof User ? $siblingOrId->getId() : $siblingOrId;
			$msgr->targetToUser($siblingId, $siblingMsg, "sstream.{$post->getPostId()}.replied", Logger::NOTICE, array(
				'author' => array($author->formatName(), '/search/user/'.$author->getId()),
				'cat'    => array($cat->getName(), $href),
				'stub'   => TextFormatter::cutSentence(Scrub::html2text($post->getBody()), 48)
			));
		}

		return $msgr;
	}


	/**
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentPosted($post, $author, $diff = null) {
		return $this->notifyPosted(Role::STUDENT, $post, $author, $diff);
	}


	/**
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStudentReplied($post, $author, $diff = null) {
		return $this->notifyReplied(Role::STUDENT, $post, $author, $diff);
	}


	/**
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffPosted($post, $author, $diff = null) {
		return $this->notifyPosted(Role::STAFF, $post, $author, $diff);
	}


	/**
	 * @param SocialStream_Post_Abstract $post
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffReplied($post, $author, $diff = null) {
		return $this->notifyReplied(Role::STAFF, $post, $author, $diff);
	}

} 