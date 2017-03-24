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
use Alaress\WorkerCrew\Transaction\ITransaction;
use Alaress\WorkerCrew\TXRX;
use InvalidArgumentException;
use OverflowException;
use Psr\Log\LoggerInterface;

class Handler implements IConsumer
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


    /**
     * @param Resolver        $resolver
     * @param LoggerInterface $log
     * @param TXRX            $txrx
     * @param ITransaction    $transaction
     */
    public function __construct(Resolver $resolver, LoggerInterface $log, TXRX $txrx, ITransaction $transaction)
    {
        $this->resolver = $resolver;
        $this->log    = $log;
        $this->txrx   = $txrx;
        $this->transaction = $transaction;
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
            $this->handle($instance);
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
     * @param Instance $instance
     * @throws InvalidArgumentException
     */
    private function handle(Instance $instance)
    {
        // typically there's only 1, but the possibility of more
        foreach ($instance->getTopics() as $topic) {
            try {
                $userIds = $this->resolver->getSubscribedUserIds($topic);

                $userCount = count($userIds);

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
