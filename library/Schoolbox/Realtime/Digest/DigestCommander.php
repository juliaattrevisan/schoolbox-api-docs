<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;

use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Command\AbstractCommander;

class DigestCommander extends AbstractCommander {

	const CMD_RELOAD = 'reload';

	/**
	 * @param DigestScheduler $scheduler
	 */
	public function __construct($scheduler) {
		parent::__construct($scheduler, Notif::TOPIC_CMD_DIGEST);
	}


	/**
	 * Call through to scheduler to statically bind queues
	 *
	 * @return void
	 */
	protected function bindQueues() {
		DigestScheduler::bindQueues();
	}


	/**
	 * Alias of advise(self::CMD_RELOAD)
	 */
	public function adviseReload() {
		$this->advise(self::CMD_RELOAD);
	}


	/**
	 * @param DigestScheduler $scheduler
	 * @param string $command
	 * @return bool
	 */
	public function exec($scheduler, $command) {
		if($command == 'reload') {
			$this->reload($scheduler);
		}
	}


	/**
	 * @param DigestScheduler $scheduler
	 * @return bool
	 */
	protected function reload($scheduler) {
		$scheduler->reload();
		return true;
	}

} 