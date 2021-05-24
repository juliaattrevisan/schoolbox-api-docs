<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\Exception\IO\IOException;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use RuntimeException;
use SplQueue;

class Dispatcher implements DispatcherInterface
{

    /** @var SplQueue */
    protected $q;

    /** @var LoopInterface */
    protected $loop;

    /** @var Pool */
    protected $pool;

    /** @var TimerInterface */
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
    public function __construct($loop, $msgHandler, $log, $strat = null)
    {

        $this->strat = $strat ? : new DefaultDispatchStrategy();

        // pool of workers
        $this->pool = new Pool($loop, $msgHandler, $this->strat, $log);

        // dispatcher depends on a react loop
        $this->loop = $loop;

        // need a reference to this for callback
        $this->msgHandler = $msgHandler;

        // for debugging output
        $this->log = $log;

        // build the queue, retrieving from storage if that's part of the strat, and execute if it contains stuff
        $this->q = $this->strat->buildQueue();

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
        unset($this->pool);
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

        // call for persistence
        $this->strat->penqueue($identifier, $command, [ $data ]);

        // store the element locally
        $this->q->enqueue([ $identifier, $command, $data ]);
        $this->log->info("Enqueued '$command': queue length is " . $this->q->count());

        if ($qLength >= $this->strat->getWarningQueueLength()) {
            $this->log->warning("Queue is getting large");
        }

        $this->attach($this->strat->getQueueDelay());
    }


    /**
     * Attempts to dequeue and dispatch all elements in the queue.
     * Nothing will happen if the queue is empty.
     *
     * @param int $rate
     */
    public function attach($rate = null)
    {
        // notify the pool that we're engaged
        $this->pool->dispatcherEngage();

        // this came from the pool emitting ready
        if (is_null($rate)) {
            $rate = $this->strat->getQueueDelay();
            $this->detach();
        }

        $rate = (int)$rate;

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
            $this->pool->dispatcherIdle();
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
    }


    /**
     * This method will be fired every self::LOOP_DELAY millis
     *
     * @param TimerInterface $timer
     * @throws PDOException
     */
    public function dispatch(TimerInterface $timer)
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

            $this->pool->dispatcherIdle();
            return;
        }

        /**
         * @var object $identifier
         * @var string $cmd
         * @var array  $data
         */
        list($identifier, $cmd, $data) = $this->q->bottom();

        // decide whether to delegate this to a Worker, or return it straight into the handler
        if (!in_array($cmd, $this->pool->getSupportedActions())) {
            try {
                // we obtained the data from q::bottom() so just drop the reference
                $this->q->dequeue();
                $this->log->info("Internally handling '$cmd'");
                $this->msgHandler->handleResponse($identifier, $cmd, (array)$data);
            } catch (Exception $e) {
                // we need to catch (almost) any and all exception here, lest we explode our reactor
                $this->log->warning(get_class($e) . " suppressed: " . $e->getMessage(), [ 'exception' => $e ]);
            }
            // regardless of whether or not we succeeded, we've finished handling this message, so dequeue it
            $this->strat->pdequeue($identifier);
            return;
        }

        // all workers are tied up and ceiling has been reached - please wait
        if (!$this->pool->canAccept()) {
            $timeout = $this->strat->getWorkerIdleTimeout();
            $this->log->debug("No workers available, setting healthcheck timeout for {$timeout}ms");
            // detach and wait for pool to notify us of a free worker
            $this->detach();
            $this->pool->once('ready', [ $this, 'attach' ]);
            // attach a timer to check the health of the pool every minute until a worker returns and cancels it
            $this->timer = $this->loop->addPeriodicTimer($timeout / 1000, [ $this->pool, 'healthCheck' ]);
            return;
        }

        // pop the task from the queue and run the next task
        try {
            // we obtained the data from q::bottom() so just drop the reference
            $this->q->dequeue();
            $pid = $this->pool->accept($identifier, $cmd, $data, $this->q->count());
            $this->log->info("Command '$cmd' pushed to worker {{$pid}}", [ 'char' => '>' ]);
        } catch (IOException $e) {
            $this->log->warning("Could not communicate with worker: " . $e->getMessage());
        } catch (RuntimeException $e) {
            $this->log->critical("Failed to spawn worker: " . $e->getMessage(), ['exception' => $e]);
        } catch (Exception $e) {
            // we need to catch any and all exception here, lest we explode our reactor
            $this->log->warning(get_class($e) . " suppressed: " . $e->getMessage(), ['exception' => $e]);
        }
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
