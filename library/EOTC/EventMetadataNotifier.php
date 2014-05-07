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
	 * Get le slug
	 *
	 * @return string
	 */
	protected function getSlug() {
		return 'eotcmetadatastatus';
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

		$msg = new Message(":someone changed the EOTC event status.",	// paramaterised, PDO-style
			'/portrait.php?size=square32&userId='.$creator->getExternalId(),
			'/turtle/'.$metadata->getId()
		);


		$mapper = new \EOTC\EventMapper();

//		$allPeople = new \Schoolbox\Collection();
//
//		foreach ($allPeople as $currentPeople) {
//		}

		$msgr->targetToUser(151, $msg, "eotc.metadata.{$metadata->getId()}.changed", Logger::INFO, array(
			// map your params to values, if you use an array: 0 => label, 1 => href
			'someone' => array($creator->formatName(), '/search/user/'.$creator->getId())
		));

		return $msgr;
	}

	/**
	 * Log stuff to a file due to asynchronous nature
	 *
	 * @param $message
	 */
	private function log($message) {
		$filepointer = fopen('/vagrant/data/mort.log', 'a+');
		fputs($filepointer, $message);
		fclose($filepointer);
	}
}
