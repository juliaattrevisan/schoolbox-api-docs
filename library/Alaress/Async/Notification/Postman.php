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
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Notif;

class Postman {

	/** @var Notif */
	protected $notif;

	/** @var LoggerInterface */
	protected $log;

	/**
	 * @param Notif $notif
	 * @param LoggerInterface $log
	 */
	public function __construct($notif, $log) {
		$this->notif = $notif;
		$this->log = $log;
	}


	/**
	 * Delivers the contents of the given bundle, according to the notifier given
	 *
	 * @param InstanceBundle $bundle
	 * @param array $appendContext
	 * @return int
	 */
	public function deliver($bundle, $appendContext = array()) {
		$i = 0;
		foreach($bundle->getAll() as $destination => $instance) {
            $json = "$instance";

            if (strlen($json) == 0) {
                // message could not be serialized, therefore cannot be delivered
                $this->log->notice("Broken message, was unable to json_encode", array('char' => '?'));
                continue;
            }

			$this->notif->setTopic($destination)->log($instance->getSeverity(), $json, $instance->getContext() + $appendContext);
			$this->log->info("Broadcast a message to '$destination' at level {$instance->getSeverity()}", array('char' => '<'));
			++$i;
		}
		return $i;
	}

} 