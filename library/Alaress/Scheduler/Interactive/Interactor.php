<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler\Interactive;


use Alaress\Cli\Debug;
use Alaress\Scheduler\StompScheduler;

/**
 * Designed to accept data that ultiamtely came from STDIN, to output various data of the state of the scheduler
 *
 * Class Interactor
 * @package Alaress\Scheduler\Interactive
 */
class Interactor {

	const CMD_STATUS = 'status';
	const CMD_HELP   = 'help';

	/** @var StompScheduler */
	protected $scheduler;

	/** @var Debug */
	protected $debug;

	/**
	 * @param StompScheduler $scheduler
	 */
	public function __construct($scheduler) {
		$this->scheduler = $scheduler;
		$this->debug = $scheduler->debug;
	}

	/**
	 * Handle data from STDIN: expect it to contain a trailing newline
	 *
	 * @param string $data
	 */
	public function handle($data) {

		$fields = explode(' ', trim($data));
		$cmd = array_shift($fields);

		switch($cmd) {
			case self::CMD_STATUS:
				$this->debug->v(print_r($this->scheduler->getStatus($fields), true));
				break;
			case self::CMD_HELP:
				$this->debug->v(<<<EOL
HELP
    status => valid options: stomp
EOL
);
				break;
			default:
				$this->debug->v("Unrecognised command: '$cmd'", '*');
		}

	}
} 