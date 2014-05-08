<?php
/**
 * EventMetadataNotifier
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Monolog\Logger;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;

class EventMetadataNotifier extends AbstractModuleNotifier {

	const TRIGGER_CHANGED = 'changed';

	/**
	 * Namespace slug, as defined in msg_namespace
	 *
	 * @var string
	 */
	private $msgNamespaceSlug = 'eotcmetadatastatus';

	/**
	 * Get le slug
	 *
	 * @return string
	 */
	protected function getSlug() {
		return $this->getMsgNamespaceSlug();
	}

	/**
	 * An outdated interface method?
	 *
	 * @param string $poop
	 * @param string $label
	 * @return string|void
	 */
	public function buildTopic($poop, $label) {
	}

	/**
	 * Instantiate all your dependencies within yourself.
	 *
	 * @param mixed $dependencies
	 * @return void
	 */
	public function loadDependencies($dependencies = null) {
		// subconstructor: load any mapper dependencies etc. here
		$this->namespace = NSMapper::getInstance()->findOneBySlug($this->getSlug());
	}

	/**
	 * Bundle triggers
	 *
	 * @param TriggerBundle $bundle
	 * @return void
	 */
	public function bundleTriggers($bundle) {
		if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
//			$bundle->attach(self::TRIGGER_CHANGED, self::IMMEDIATELY); // (it's just a mapping of label => timestamp, nothing more)
		}

		if ($bundle->getMethod() == Mapper::METHOD_UPDATE) {
			$bundle->attach(self::TRIGGER_CHANGED, self::IMMEDIATELY); // (it's just a mapping of label => timestamp, nothing more)
		}
	}

	/**
	 * GOTCHA! METHOD MUST MATCH: 'notify' . strtoupper(`msg_source`.`role_type`) . strtoupper(`msg_source`.`key`)
	 *
	 * @param \EOTC\EventMetadata $metadata
	 * @param $creator
	 * @param array $diff
	 * @return Messenger
	 */
	public function notifyStaffChanged($metadata, $creator, $diff = null) {
		$msgr = new Messenger();

		/* Find a bunch of people to whom this notification should be sent
		 * At the moment I am electing to notify all EOTC permission holders
		 * whenever the status of any event changes
		 */
		$permissionMapper = new \EOTC\EventPermissionMapper();
		$allUserIds = $permissionMapper->findUserIds();

		$userMapper = new \Schoolbox\UserMapper();
		$allUsers = $userMapper->findByColumn('id', $allUserIds);

		/* Only get the first event belonging to this metadata
		 * In series events, multiple events share the same metadata
		 * but we only need to use the name of the event
		 */
		$allEvents = $this->mapper->findEventsForMetadata($metadata);

		if ($allEvents->count()) {
			$url = "/modules/eotc/status/modify/{$allEvents->first()->getId()}";

			$msg = new Message(":someone changed the status for the EOTC event {$allEvents->first()->getNote()}",	// paramaterised, PDO-style
				'/portrait.php?size=square32&userId='.$creator->getExternalId(),
				$url
			);

			foreach ($allUsers as $currentUser) {
				$msgr->targetToUser($currentUser->getId(), $msg, "{$this->getMsgNamespaceSlug()}.{$metadata->getId()}.changed", Logger::INFO, array(
					// map your params to values, if you use an array: 0 => label, 1 => href
					'someone' => array($creator->formatName(), '/search/user/'.$creator->getId())
				));
			}
		}

		return $msgr;
	}

	/**
	 * Log stuff to a file due to asynchronous nature
	 *
	 * @param $message
	 */
	private function chimp($message) {
		$filepointer = fopen('/vagrant/data/eotcmetadatastatus.log', 'a+');
		fputs($filepointer, $message);
		fclose($filepointer);
	}

	/**
	 * Set msg namespace slug
	 *
	 * @param string $msgNamespaceSlug
	 */
	private function setMsgNamespaceSlug($msgNamespaceSlug) {
		$this->msgNamespaceSlug = $msgNamespaceSlug;
	}

	/**
	 * Get msg namespace slug
	 *
	 * @return string
	 */
	private function getMsgNamespaceSlug() {
		return $this->msgNamespaceSlug;
	}
}
