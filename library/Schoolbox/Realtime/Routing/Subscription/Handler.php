<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * @param Instance  $instance
     * @param IResolver $resolver
     * @throws DuplicateResolutionException
     * @return void
     */
    public function onMessage($instance, IResolver $resolver)
    {
        try {
            $this->handleMessage($instance);
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
     * @throws InvalidArgumentException
     */
    private function handleMessage(Instance $instance)
    {
        // typically there's only 1, but the possibility of more
        foreach ($instance->getTopics() as $topic) {
            try {
                $userIds = $this->resolver->getSubscribedUserIds($topic);

                $userCount = count($userIds);

                if ($userCount === 0) {
                    $this->log->info("Nobody is subscribed to '$topic', dropping message");
                    return;
                }

                if ($userCount > 16) {
                    $suffix = "$userCount users";
                } else {
                    $suffix = json_encode($userIds);
                }

                $this->log->info("Routing message via '$topic' to $suffix");

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
