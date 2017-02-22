<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

/**
 * Class RerouterDispatchStrategy
 *
 * @deprecated
 * @package Schoolbox\Realtime\Rerouter
 */
class RerouterDispatchStrategy extends DefaultDispatchStrategy {

	/**
	 * Get the name of the strategy currently being used
	 *
	 * @return string
	 */
	public function getName() {
		return 'Reroute';
	}


	/**
	 * @return string
	 */
	public function getCommand() {
		return SITE_root . "/tools/notif/reroute/worker.php";
	}


	/**
	 * @return array
	 */
	public function getSupportedActions() {
		return array(self::TASK_REROUTE);
	}

}