<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\EventListener;
use Alaress\WorkerCrew\Transaction\ITransaction;
use Alaress\WorkerCrew\TXRX;
use InvalidArgumentException;
use OverflowException;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Routing\Cache\ReadableCache;

class Handler implements IConsumer, EventListener
{
    const TRANSACTION_CACHE_TIMEOUT = 0.5;
    const TRANSACTION_COUNT_LIMIT   = 80;

    /** @var Resolver */
    protected $resolver;

    /** @var ITransaction */
    protected $transaction;

    /** @var LoggerInterface */
    protected $log;

    /** @var TXRX */
    private $txrx;

    /** @var ReadableCache */
    private $cache;


    /**
     * @param Resolver        $resolver
     * @param LoggerInterface $log
     * @param TXRX            $txrx
     * @param ITransaction    $transaction
     * @param ReadableCache   $cache
     */
    public function __construct(Resolver $resolver, LoggerInterface $log, TXRX $txrx, ITransaction $transaction, ReadableCache $cache)
    {
        $this->resolver = $resolver;
        $this->log    = $log;
        $this->txrx   = $txrx;
        $this->transaction = $transaction;
        $this->cache = $cache;
    }


    /**
     * Handle a message sent from a broker to which you've subscribed
     *
     * @param Instance|boolean   $instance
     * @param IResolver  $resolver
     * @param array|null $context
     * @throws DuplicateResolutionException
     * @return void
     */
    public function onMessage($instance, IResolver $resolver, ?array $context)
    {
        if (!$instance instanceof Instance) {
            $this->log->error('[Routing Subscription Handler] instance received is broken');
            $resolver->reject();
            return;
        }
        try {
            $this->handleMessage($instance, $context);
            $resolver->acknowledge();
            return;
        } catch (InvalidArgumentException $e) {
            $this->log->warning($e->getMessage());
            $resolver->acknowledge();
            return;
        } catch (\Exception $e) {
            $this->log->error($e->getMessage());
        }

        $resolver->reject();
    }


    /**
     * Invoked when we receive events via a redis subscription
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event)
    {
        if (!$event instanceof Message) {
            // we're not interested in pings
            return;
        }

        // events will be delivered on a channel like "routed.vagrantx.rebind"
        list(, $instanceId, $action) = explode('.', $event->getChannel(), 3);

        $payload = $event->getPayload();

        $suffix = $payload ? ' with payload ' . var_export($payload, true) : '';

        $this->log->info("Received signal to $action $instanceId$suffix", [ 'char' => '>' ]);

        switch ($action) {
            // so far this is the only action available
            case 'reload':
                $this->cache->readIntoRouter($instanceId, $this->resolver->getRouter());
                break;
        }
    }


    /**
     * @param Instance $instance
     * @param array    $topics
     * @throws InvalidArgumentException
     */
    private function handleMessage(Instance $instance, array $topics = null)
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
            } catch (OverflowException $e) {
                // recursion detected
                $this->log->error($e->getMessage());
            }
        }
    }


    /**
     * Handle brokering exceptions
     *
     * @param \Exception $e
     * @return void
     */
    public function onException(\Exception $e)
    {
        $this->log->error(get_class($e) . ': ' . $e->getMessage());
    }
}
