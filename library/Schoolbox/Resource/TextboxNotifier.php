<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\Item;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Resource\Category;
use Schoolbox\User;

class TextboxNotifier extends ResourceNotifier {


	/**
	 * @param string $roletype
	 * @param Textbox $resource
	 * @param User $author
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyCreated($roletype, $resource, $author, $diff = null) {
		$msgr = new Messenger();

		$resourceId = $resource->getId();
		$category = CategoryMapper::getInstance()->findById($resource->getCategory()->getId()); // THERE HAS TO BE A WAY TO NOT NEED TO DO THIS

		$msg = new Message(
			':author created content in :homepage: ":heading"',
			"/portrait.php?size=square32&userId=".$author->getExternalId(),
			"/homepage/".$category->getId()
		);

		$msgr->broadcastToCategory($category->getId(), $msg, "cms.textbox.$resourceId.created", Logger::INFO, array(
			'author'   => array($author->formatName(), '/search/user/'.$author->getId()),
			'homepage' => array($category->getName(), '/homepage/'.$category->getId()),
			'heading'  => $resource->getHeading()
		), array(
			'roletype' => $roletype
		));

		return $msgr;
	}


	/**
	 * @param string $roletype
	 * @param Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyUpdated($roletype, $resource, $effector, $diff = null) {
		$msgr = new Messenger();

		$resourceId = $resource->getId();
		$category = CategoryMapper::getInstance()->findById($resource->getCategory()->getId()); // THERE HAS TO BE A WAY TO NOT NEED TO DO THIS

		$msg = new Message(
			':author updated content in :homepage: ":heading"',
			"/portrait.php?size=square32&userId=".$effector->getExternalId(),
			"/homepage/".$category->getId()
		);

		$msgr->broadcastToCategory($category->getId(), $msg, "cms.textbox.$resourceId.updated", Logger::INFO, array(
			'author'   => array($effector->formatName(), '/search/user/'.$effector->getId()),
			'homepage' => array($category->getName(), '/homepage/'.$category->getId()),
			'heading'  => $resource->getHeading()
		), array(
			'roletype' => $roletype
		));

		return $msgr;
	}


	/**
	 * @param string $roletype
	 * @param Textbox $resource
	 * @param User $effector
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyDeleted($roletype, $resource, $effector, $diff = null) {
		$msgr = new Messenger();

		$resourceId = $resource->getId();
		$category = CategoryMapper::getInstance()->findById($resource->getCategory()->getId()); // THERE HAS TO BE A WAY TO NOT NEED TO DO THIS

		$msg = new Message(
			':author deleted content from :homepage: ":heading"',
			"/portrait.php?size=square32&userId=".$effector->getExternalId(),
			"/homepage/".$category->getId()
		);

		$msgr->broadcastToCategory($category->getId(), $msg, "cms.textbox.$resourceId.deleted", Logger::INFO, array(
			'author'   => array($effector->formatName(), '/search/user/'.$effector->getId()),
			'homepage' => array($category->getName(), '/homepage/'.$category->getId()),
			'heading'  => $resource->getHeading()
		), array(
			'roletype' => $roletype
		));

		return $msgr;
	}


	/**
	 * Generate n Feed\Items, from the perspective of casual observation (rather than targeted at a particular user).
	 *
	 * @param int $context      - most likely a category ID or ignored
	 * @param string $roletype  - the roletype of the user for whom this feed is being retrieved
	 * @param int $limit        - how many messages to retrieve
	 * @param int $offset       - by default, most recent
	 * @return Collection|Item[]
	 */
	public function getFeed($context, $roletype, $limit = self::HARD_LIMIT, $offset = 0) {
		return $this->getFeedInternal($context, $roletype, $limit, $offset, LogMapper::KIND_CONTENT);
	}


} 