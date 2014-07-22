<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Alaress\Scheduler\Trigger\TriggerInterface;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;

interface InstantTriggerInterface extends TriggerInterface {

	/**
	 * @return AbstractModuleNotifier
	 */
	public function getModuleNotifier();

}