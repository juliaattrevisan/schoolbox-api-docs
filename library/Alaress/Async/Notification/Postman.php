<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification;


use Alaress\Async\Broker\IBroker;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Psr\Log\LoggerInterface;


class Postman
{

    /** @var IBroker */
    private $broker;

    /** @var LoggerInterface */
    private $log;


    /**
     * @param IBroker         $broker
     * @param LoggerInterface $log
     */
	public function __construct(IBroker $broker, LoggerInterface $log)
    {
        $this->broker = $broker;
        $this->log    = $log;
    }


	/**
	 * Delivers the contents of the given bundle, according to the notifier given
	 *
	 * @param InstanceBundle $bundle
	 * @param array $appendContext
	 * @return int
	 */
	public function deliver(InstanceBundle $bundle, array $appendContext = [])
    {
		$i = 0;

		foreach ($bundle->getAll() as $routeKey => $instance) {
            $array = $instance->jsonSerialize();
		    $json = json_encode($array);

            if ($json === '') {
                // message could not be serialized, therefore cannot be delivered
                $this->log->notice('Broken message, was unable to json_encode', ['char' => '?']);
                continue;
            }

            try {
                $this->broker->publish(Instance::jsonUnserialize($json, "INFO.$routeKey"));
            } catch (\InvalidArgumentException $e) {
                $this->log->notice('Broken message, was unable to json_decode', ['char' => '?']);
            }

			$this->log->info("Broadcast a message to '$routeKey'", ['char' => '<']);
			++$i;
		}

		return $i;
	}

}
