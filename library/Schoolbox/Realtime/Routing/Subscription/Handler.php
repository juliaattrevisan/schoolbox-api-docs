<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription;


use Alaress\WorkerCrew\DispatcherInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use React\Stomp\AckResolver;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use Schoolbox\Realtime\Routing\DispatchStrategy;

class Handler implements TaskResponseHandlerInterface
{
    const TRANSACTION_CACHE_TIMEOUT = 0.5;
    const TRANSACTION_COUNT_LIMIT   = 80;

    /** @var Manager */
    protected $manager;

    /** @var Client */
    protected $stomp;

    /** @var LoggerInterface */
    protected $log;


    /**
     * @param Manager         $manager
     * @param Client          $stomp
     * @param LoggerInterface $log
     */
    public function __construct(Manager $manager, Client $stomp, LoggerInterface $log)
    {
        $this->manager = $manager;
        $this->stomp   = $stomp;
        $this->log     = $log;
    }


    /**
     * Start listening to a particular instance
     *
     * @param string              $instanceId
     * @param LoopInterface       $loop
     * @param DispatcherInterface $dispatcher
     * @throws LogicException
     */
    public function listen($instanceId, LoopInterface $loop, DispatcherInterface $dispatcher)
    {
        // this closure is executed every $tx->put() is called in self::handle()
        // Adds the frame to a loop cache, and dispatches appropriately
        $tx = new Transaction($loop, function(TimerInterface $timer) use ($dispatcher) {
            $dispatcher->enqueue($timer, DispatchStrategy::TASK_ROUTE, $timer->getData());
        }, self::TRANSACTION_CACHE_TIMEOUT, self::TRANSACTION_COUNT_LIMIT);


        $this->stomp->subscribeWithAck("$instanceId.route", 'client-individual', function (Frame $frame, AckResolver $resolver) use ($tx) {
            try {
                $this->assertPayload($frame->body);

                $this->handle($frame, $tx);
                $resolver->ack();
                return;

            } catch (InvalidArgumentException $e) {
                $this->log->warning($e->getMessage());
                $resolver->ack();
                return;

            } catch (\Exception $e) {
                $this->log->error($e->getMessage());
            }

            $resolver->nack();
        });
    }


    /**
     * @param string $payload
     * @throws InvalidArgumentException
     */
    protected function assertPayload($payload)
    {
        try {
            $data = json_decode($payload, true);

        } catch (\Exception $e) {
            // cast exception (most likely ErrorException)
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        // check arrayness
        if (!is_array($data)) {
            throw new InvalidArgumentException("Payload must be an array, got ".gettype($data));
        }

        $requiredKeys = ['level_name', 'channel', 'message', 'context'];

        if (count($diff = array_diff($requiredKeys, array_keys($data)))) {
            throw new InvalidArgumentException("Keys ".json_encode(array_values($diff))." missing");
        }
    }


    /**
     * @param Frame       $frame
     * @param Transaction $tx
     */
    protected function handle(Frame $frame, Transaction $tx)
    {
        $data = json_decode($frame->body, true);

        $topic = "{$data['level_name']}.{$data['channel']}";

        try {
            $userIds = $this->manager->getSubscribers($topic);

            $userCount = count($userIds);

            if ($userCount > 16) {
                $suffix = "$userCount users";
            } else {
                $suffix = json_encode($userIds);
            }

            $this->log->info("Routing message via '$topic' to $suffix");

            $tx->put(sha1($frame->dump()), [ $frame, $userIds ]);

        } catch (InvalidArgumentException $e) {
            $this->log->info("Nobody subscribed to '$topic'");
        }
    }


    /**
     * Handles a response from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleResponse($identifier, $command, $data)
    {
        $this->log->notice("Reroute count: $data[0]");
    }


    /**
     * Handles an error from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleError($identifier, $command, $data)
    {
        $this->log->notice("Received worker error for '$command': $data");
    }

}
