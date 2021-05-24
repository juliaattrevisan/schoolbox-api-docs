<?php

 /**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
    public function deliver(InstanceBundle $bundle, array $appendContext = []): int
    {
        $i = 0;

        // expecting event object id to be detected and attached in the context
        $eventObjectId = isset($appendContext[Instance::EVENT_OBJECT_ID]) ? $appendContext[Instance::EVENT_OBJECT_ID] : '';

        foreach ($bundle->getAll() as $routeKey => $instance) {
            // Set event object id where available.
            $instance->setEventObjectId($eventObjectId);
            $array = $instance->jsonSerialize();

            // merge the context appendation
            $array['context'] = array_merge($array['context'], $appendContext);

            $json = json_encode($array);

            if ($json === '') {
                // message could not be serialized, therefore cannot be delivered
                $this->log->notice('Broken message, was unable to json_encode', ['char' => '?']);
                continue;
            }

            try {
                $topic = "INFO.$routeKey";
                $this->broker->publish(Instance::jsonUnserialize($json, $topic), [$topic], $instance->getTTL());
            } catch (\InvalidArgumentException $e) {
                $this->log->notice('Broken message, was unable to json_decode', ['char' => '?']);
            }

            if ($instance->isActionRetractSingle()) {
                $this->log->info("Retracting a message from '$routeKey'", ['char' => '<']);
            } elseif ($instance->isActionRetractAll()) {
                $this->log->info("Retracting all messages from '$routeKey'", ['char' => '<']);
            } else {
                // assume send
                $this->log->info("Broadcasting a message to '$routeKey'", ['char' => '<']);
            }
            ++$i;
        }

        return $i;
    }
}
