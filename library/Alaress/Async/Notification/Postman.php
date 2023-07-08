<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Instance\InstanceBundle;
use Alaress\Routing\Router;
use Alaress\WorkerCrew\Transaction\ITransaction;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription\Resolver;

class Postman
{
    private Resolver $resolver;

    /**
     * @param LoggerInterface $log
     * @param Router          $router
     * @param ITransaction    $transaction
     * @param RouteTXRX       $txrx
     * @param ReadCache       $subscriptionCache
     */
    public function __construct(
        private LoggerInterface $log,
        private Router $router,
        private ITransaction $transaction,
        private RouteTXRX $txrx,
        private ReadCache $subscriptionCache
    ) {
        $this->subscriptionCache->setLog($log);
        $this->reloadRouter();
    }

    /**
     * @return void
     */
    public function reloadRouter()
    {
        $this->subscriptionCache->readIntoRouter(INSTANCE_ID, $this->router);
        $this->resolver = new Resolver($this->router);
    }

    /**
     * Delivers the contents of the given bundle, according to the notifier given
     *
     * @param InstanceBundle $bundle
     * @param array          $appendContext
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

            if (empty($json)) {
                // message could not be serialized, therefore cannot be delivered
                $this->log->notice('Broken message, was unable to json_encode', ['char' => '?']);
                continue;
            }

            $topic = "INFO.$routeKey";
            try {
                $serializable = Instance::jsonUnserialize($json, $topic);
            } catch (\InvalidArgumentException $e) {
                // message could not be unserialized, therefore cannot be delivered
                $this->log->notice('Broken message, was unable to json_decode', ['char' => '?']);
                continue;
            }

            $this->handleMessage($serializable, [$topic]);

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

    /**
     * Moved from routed - Routing\Subscription\Handler
     * @param Instance $instance
     * @param array    $topics
     * @throws \InvalidArgumentException
     */
    private function handleMessage(Instance $instance, array $topics): void
    {
        // if topics are explicitly given, use those rather than what you find in the instance
        $topics = $topics ?: $instance->getTopics();

        // typically there's only 1, but the possibility of more
        foreach ($topics as $topic) {
            try {
                if ($instance->isActionRetractSingle()) {
                    // Retract actions route to nobody in particular
                    $msgType = 'targeted retraction';
                    $suffix  = 'nobody in particular';
                    $userIds = [];
                } elseif ($instance->isActionRetractAll()) {
                    // Retract actions route to nobody in particular
                    $msgType = 'full retraction';
                    $suffix  = 'nobody in particular';
                    $userIds = [];
                } else {
                    $msgType   = 'send';
                    $userIds   = $this->resolver->getSubscribedUserIds($topic);
                    $userCount = \count($userIds);
                    if ($userCount === 0) {
                        $this->log->info("Nobody is subscribed to '$topic', dropping message");
                        return;
                    }

                    if ($userCount > 16) {
                        $suffix = "$userCount users";
                    } else {
                        $suffix = json_encode($userIds);
                    }
                }

                $this->log->info("Routing message $msgType via '$topic' to $suffix");
                $this->txrx->send($instance, $this->transaction, $userIds);
            } catch (\OverflowException $e) {
                // recursion detected
                $this->log->error($e->getMessage());
            }
        }
    }
}
