<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification;


use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Cli\Debug;

class Postman {

	/** @var LoggerInterface */
	protected $logger;

	/** @var Debug */
	protected $debug;

	/**
	 * @param LoggerInterface $logger
	 * @param Debug $debug
	 */
	public function __construct($logger, $debug) {
		$this->logger = $logger;
		$this->debug = $debug;
	}


	/**
	 * Delivers the contents of the given bundle, according to the notifier given
	 *
	 * @param InstanceBundle $bundle
	 * @return int
	 */
	public function deliver($bundle) {
		$i = 0;
		foreach($bundle->getAll() as $destination => $instance) {
			$this->logger->setTopic($destination)->log($instance->getSeverity(), "$instance", $instance->getContext());
			$this->debug->v("Broadcast a message to '$destination' at level {$instance->getSeverity()}: '$instance'");
			++$i;
		}
		return $i;
	}

} 