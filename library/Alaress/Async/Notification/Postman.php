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
use Alaress\Routing\Dictionary\SubscriptionDictionary;
use Alaress\Routing\Router;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Transaction\DispatchTransaction;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\MessageList\ListFactory;
use Schoolbox\Realtime\Routing\Cache\Redis\ReadCache;
use Schoolbox\Realtime\Routing\RouteTXRX;
use Schoolbox\Realtime\Routing\Subscription\Resolver as SubscriptionResolver;
use Schoolbox\Realtime\Routing;

class Postman
{
    private SubscriptionResolver $subscriptionResolver;

    private RouteTXRX $txrx;

    private DispatchTransaction $transaction;

    private Router $router;

    /**
     * @param LoggerInterface $log
     * @param ReadCache       $subscriptionCache
     * @param LoopInterface   $loop
     * @param string          $flags
     * @param string          $instanceId
     * @param ClientInterface $redis
     */
    public function __construct(
        private LoggerInterface $log,
        private ReadCache $subscriptionCache,
        LoopInterface $loop,
        string $flags,
        string $instanceId,
        ClientInterface $redis
    ) {
        // the route broker for sending
        $this->router = new Router(new SubscriptionDictionary('.'));
        $this->txrx   = RouteTXRX::buildTransmitter(new ListFactory($instanceId, $redis));

        $this->transaction = new DispatchTransaction(
            $loop,
            new Dispatcher(
                $loop,
                new Routing\DispatchResponder($log),
                $log,
                new Routing\DispatchStrategy(flags: $flags)
            ),
            Routing\DispatchStrategy::TASK_ROUTE
        );
        $this->subscriptionCache->setLog($log);
        $this->reloadRouter();
    }

    /**
     * @return void
     */
    public function reloadRouter()
    {
        $this->subscriptionCache->readIntoRouter(INSTANCE_ID, $this->router);
        $this->subscriptionResolver = new SubscriptionResolver($this->router);
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
        $eventObjectId = $appendContext[Instance::EVENT_OBJECT_ID] ?? '';

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
                    $userIds   = $this->subscriptionResolver->getSubscribedUserIds($topic);
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
