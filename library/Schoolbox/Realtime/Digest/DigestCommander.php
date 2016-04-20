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
	 */
	public function __construct() {
		parent::__construct(Notif::TOPIC_CMD_DIGEST);
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
			$scheduler->log->notice("Received signal to reload");
			return $this->reload($scheduler);
		}
		$scheduler->log->warning("Unrecognised command: $command");
		return false;
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
