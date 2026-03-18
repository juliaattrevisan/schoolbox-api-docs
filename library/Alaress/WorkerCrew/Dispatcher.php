<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\Exception\IO\IOException;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RuntimeException;
use SplQueue;

class Dispatcher implements DispatcherInterface
{
    /**
     * Only iterate the queue this many times in dispatch.
     *
     * It is not expected the queue will be iterated anywhere near this many
     * times: this is simply an infinite loop protection.
     */
    private const QUEUE_ITERATION_LIMIT = 10_000;

    /** @var SplQueue */
    protected $q;

    /** @var LoopInterface */
    protected $loop;

    /** @var Pool|null */
    protected $pool = null;

    /** @var TimerInterface|null */
    protected $timer = null;

    /** @var DispatchStrategyInterface */
    protected $strat;

    /** @var LoggerInterface */
    private $log;

    /** @var TaskResponseHandlerInterface */
    private $msgHandler;


    /**
     * @param LoopInterface                $loop
     * @param TaskResponseHandlerInterface $msgHandler
     * @param LoggerInterface              $log
     * @param DispatchStrategyInterface    $strat
     */
    public function __construct($loop, $msgHandler, $log, $strat)
    {
        $this->strat = $strat;

        // pool of workers
        $this->pool = new Pool($loop, $msgHandler, $this->strat, $log);

        // dispatcher depends on a react loop
        $this->loop = $loop;

        // need a reference to this for callback
        $this->msgHandler = $msgHandler;

        // for debugging output
        $this->log = $log;

        // build the queue, retrieving from storage if that's part of the strat, and execute if it contains stuff
        $this->q = new SplQueue();

        if ($this->q->count()) {
            $this->log->notice("Queue is preloaded, draining: queue length is " . $this->q->count());
            $this->attach($this->strat->getQueueDelay());
        }
    }


    /**
     * Daemon is going down, try to make sure the pool dries up gracefully
     */
    public function __destruct()
    {
        $this->pool = null;
    }


    /**
     * @param object $identifier
     * @param string $command
     * @param string $data
     * @throws QueueingException
     */
    public function enqueue($identifier, $command, $data)
    {
        $qLength = $this->q->count();
        $mLength = trim($this->strat->getMaxQueueLength());

        if ($qLength >= $mLength) {
            throw new QueueingException("Cannot enqueue, max queue length of $mLength reached");
        }

        // store the element locally
        $this->q->enqueue([ $identifier, $command, $data ]);
        $this->log->debug("Enqueued '$command': queue length is " . $this->q->count());

        if ($qLength >= $this->strat->getWarningQueueLength()) {
            $this->log->warning("Queue is getting large");
        }

        $this->attach($this->strat->getQueueDelay());
    }


    /**
     * Attempts to dequeue and dispatch all elements in the queue.
     * Nothing will happen if the queue is empty.
     *
     * @param int|null $rate
     */
    public function attach(?int $rate = null)
    {
        // if $rate is null, this came from the pool emitting ready
        if (is_null($rate)) {
            $rate = $this->strat->getQueueDelay();
            $this->detach();
        }

        // if there's a timer, use it, otherwise create it
        if (!($this->timer instanceof TimerInterface)) {
            $this->log->debug("Creating timer to fire at {$rate}ms intervals");
            $this->timer = $this->loop->addPeriodicTimer($rate / 1000, [ $this, 'dispatch' ]);
        }
    }


    /**
     * Detaches the Dispatcher from the loop
     */
    protected function detach()
    {
        // don't try to remove a timer that isn't there
        if ($this->timer instanceof TimerInterface) {
            $this->log->debug("Detaching dispatcher (queue length is {$this->q->count()})");
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
    }


    /**
     * Drain as many items from the queue as workers will accept.
     * Fired by the periodic timer, and also re-triggered when a worker becomes ready.
     *
     * @param TimerInterface $timer
     */
    public function dispatch(TimerInterface $timer): void
    {
        // there's nothing in the queue
        if (!$this->q->count()) {
            if ($this->pool->canDispatcherFlywheel()) {
                $this->log->debug("Queue is empty: flywheel spinning");
                return;
            }

            $this->loop->cancelTimer($timer);
            $this->timer = null;

            $this->log->debug("Queue is empty: detaching timer");
            return;
        }

        // Loop over the queue and dispatch commands, as long as there's workers
        // to handle them and the iteration safeguard is not reached
        $i = 1;
        while ($this->q->count()) {
            $i++;
            if ($i > self::QUEUE_ITERATION_LIMIT) {
                $this->log->warning(
                    sprintf(
                        "Queue iteration limit of %d has been unexpectedly reached: stopping iteration. This is probably a code issue.",
                        self::QUEUE_ITERATION_LIMIT,
                    )
                );
                break;
            }
            if (!$this->dispatchSingleCommand()) {
                break;
            }
        }
    }


    /**
     * Dispatch a single command to a worker.
     *
     * Intended to be run within a loop whilst there are still workers available
     * to handle commands.
     *
     * @return bool True if the loop should continue, false if it should break.
     */
    private function dispatchSingleCommand(): bool
    {
        /**
         * @var object $identifier
         * @var string $cmd
         * @var array  $data
         */
        [$identifier, $cmd, $data] = $this->q->bottom();

        // decide whether to delegate this to a Worker, or return it straight into the handler
        if (!in_array($cmd, $this->pool->getSupportedActions())) {
            try {
                // we obtained the data from q::bottom() so just drop the reference
                $this->q->dequeue();
                $this->log->info("Internally handling '$cmd'");
                $this->msgHandler->handleResponse($identifier, $cmd, (array)$data);
            } catch (Exception $e) {
                // we need to catch (almost) any and all exception here, lest we explode our reactor
                $this->log->warning(get_class($e) . " suppressed: " . $e->getMessage(), ['exception' => $e]);
            }
            return true;
        }

        // all workers are tied up and ceiling has been reached - please wait
        if (!$this->pool->canAccept()) {
            $timeout = $this->strat->getWorkerIdleTimeout();
            $this->log->debug("No workers available, setting healthcheck timeout for {$timeout}ms");
            // detach and wait for pool to notify us of a free worker
            $this->detach();
            $this->pool->once('ready', [$this, 'attach']);
            // attach a timer to check the health of the pool every minute until a worker returns and cancels it
            $this->timer = $this->loop->addPeriodicTimer($timeout / 1000, [$this->pool, 'healthCheck']);
            return false;
        }

        // pop the task from the queue and dispatch it
        try {
            // we obtained the data from q::bottom() so just drop the reference
            $this->q->dequeue();
            $pid = $this->pool->accept($identifier, $cmd, $data, $this->q->count());
            $this->log->debug("Command '$cmd' pushed to worker {{$pid}}", ['char' => '>']);
        } catch (IOException $e) {
            $this->log->warning("Could not communicate with worker: " . $e->getMessage());
        } catch (RuntimeException $e) {
            $this->log->critical("Failed to spawn worker: " . $e->getMessage(), ['exception' => $e]);
            if ($e instanceof AMQPRuntimeException) {
                // AMQP exceptions must be rethrown:
                // these suggest this daemon's queue is inaccessible, and thus
                // the daemon must be restarted
                throw $e;
            }
        } catch (Exception $e) {
            // we need to catch any and all exception here, lest we explode our reactor
            // (except for AMQP exceptions, as mentioned above)
            $this->log->warning(get_class($e) . " suppressed: " . $e->getMessage(), ['exception' => $e]);
        }
        return true;
    }

    /**
     * @return array
     */
    public function getPoolStatus()
    {
        return $this->pool->getStatus();
    }


    /**
     * Signals that all workers should be terminated
     *
     * @param bool $hard
     * @return void
     */
    public function releaseWorkerPool($hard = true)
    {
        $this->pool->releaseWorkers($hard);
    }
}
