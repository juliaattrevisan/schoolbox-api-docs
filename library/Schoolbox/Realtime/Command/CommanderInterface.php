<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Command;


use Alaress\Scheduler\CommandableInterface;

interface CommanderInterface {

	/**
	 * @param $commandable CommandableInterface
	 * @param string $command
	 * @return bool
	 */
	public function exec($commandable, $command);
} 