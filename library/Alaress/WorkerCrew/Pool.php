<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew;

use Alaress\Async\Listener\Stream\StreamListener;
use Alaress\Exception\IO\IOException;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\IO\Listener\DataIOListener;
use Alaress\WorkerCrew\Worker\IO\Listener\ErrorIOListener;
use Alaress\WorkerCrew\Worker\IO\Listener\LogIOListener;
use Alaress\WorkerCrew\Worker\IO\WrappedReadableResourceStream;
use Alaress\WorkerCrew\Worker\Task;
use Alaress\WorkerCrew\Worker\Worker;
use Alaress\WorkerCrew\Worker\WorkerBundleInterface;
use Evenement\EventEmitter;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;

/**
 * Class Pool
 *
 * @package AlaressWorkerCrew
 */
class Pool extends EventEmitter implements PoolInterface
{

    const DATA_IN   = 0;
    const DEBUG_OUT = 1;
    const DEBUG_ERR = 2;
    const DATA_OUT  = 3;

    /** @var LoopInterface */
    protected $loop;

    /** @var DispatchStrategyInterface */
    protected $strat;

    /** @var WorkerBundleInterface */
    protected $workers;

    /** @var LoggerInterface */
    protected $log;

    /** @var StreamListener[] */
    protected $ioListeners = array();

    /** @var array (IOStream[][]) */
    protected $streams = array();


    /**
     * @param LoopInterface                $loop
     * @param TaskResponseHandlerInterface $msgHandler
     * @param DispatchStrategyInterface    $strat
     * @param LoggerInterface              $log
     */
    public function __construct($loop, $msgHandler, $strat, $log)
    {
        $this->loop    = $loop;
        $this->strat   = $strat;
        $this->log     = $log;
        $this->workers = $this->strat->newWorkerBundle();

        $this->ioListeners = array(
            self::DATA_OUT  => new DataIOListener($log, $this, $strat, $msgHandler),
            self::DEBUG_OUT => new LogIOListener($log),
            self::DEBUG_ERR => new ErrorIOListener($log, $this, $msgHandler),
        );
    }


    /**
     * Kill workers gracefully
     */
    public function __destruct()
    {
        $this->releaseWorkers();
        unset(
            $this->ioListeners,
            $this->workers
        );
    }


    /**
     * Test the health of the pool and attempt to resolve any issues it has
     */
    public function healthCheck()
    {
        $this->log->notice("Checking health of " . $this->strat->getName() . " pool", array('char' => '?'));

        foreach (array_keys($this->streams) as $pid) {
            try {
                $worker  = $this->workers->getByProcessId($pid);
                $status  = $worker->getStatus();
                $process = $worker->getCommand();
                $task    = $worker->getTask();
                $cmd     = $task->getCommand();
                $args    = $task->getArgs();
                $finish  = $task->getFinish();
                $start   = $task->getStart();
                $elapsed = (microtime(true) - $start) * 1000;
                $hardLim = $this->strat->getWorkerExecutionTimeout(self::RELEASE_HARD) ?: 0;
                $softLim = $this->strat->getWorkerExecutionTimeout(self::RELEASE_SOFT) ?: 0;

                $this->log->debug(sprintf('Worker {%d} (%d) `%s` started %s %0.2fms ago', $pid, $status, $process, $cmd, $elapsed));

                if ($finish && $status != Worker::READY) {
                    // check for an unexpected finish state
                    $this->log->notice("Worker {{$pid}} has completed task, but not in READY state");
                    $worker->setStatus(Worker::READY);
                } elseif ($elapsed >= $hardLim) {
                    // if it's over the hard limit, terminate it
                    $this->log->error("Worker {{$pid}} has exceeded hard execution timeout of {$hardLim}ms");
                    $this->releaseWorker($pid, self::RELEASE_HARD);
                } elseif ($elapsed >= $softLim) {
                    // if it's over the soft limit, let it down gently
                    $this->log->warning("Worker {{$pid}} has exceeded soft execution timeout of {$softLim}ms");
                    $this->releaseWorker($pid, self::RELEASE_SOFT);
                } else {
                    $this->log->debug("Worker {{$pid}} fine");
                }
            } catch (OutOfBoundsException $e) {
                $this->log->warning("Worker {{$pid}} has gone away");
                $this->detachWorker($pid);
            }
        }

        // if we're now able to accept a new task, detach the healthchecker
        if ($this->canAccept()) {
            $this->emit('ready');
        }
    }


    /**
     * Request to the pool to accept the given command. Returns the PID of the process to which the task was assigned.
     *
     * @param object $identifier
     * @param string $cmd
     * @param string $args
     * @param int    $remainingTaskCount
     * @throws RuntimeException
     * @return int
     */
    public function accept($identifier, $cmd, $args, $remainingTaskCount)
    {
        $pid = $this->getAvailableWorker($remainingTaskCount);

        switch ($pid) {
            case -1: // there is nowhere you can put this, you should not have requested
                throw new RuntimeException("No process is available to handle this request, did you check first?");
            case 0: // no current processes available, but you can create one
                $pid = $this->attachWorker();
                $new = true;
                $this->log->debug("Created new worker with PID $pid");
                break;
            default: // any positive integer is the PID of the process we will be sending this to
                $new = false;
                break;
        }

        try {
            // attempt to assign the given task to the worker found
            $this->log->debug("Tasking $pid with $cmd");
            $this->assignTask($pid, $cmd, $args, $identifier);
        } catch (IOException $e) {
            // communication problem with the given worker
            $this->detachWorker($pid);

            // if we're not able to communicate with the process we only just created, that's special...
            if ($new) {
                throw new RuntimeException("Could not communicate with freshly created process", 0, $e);
            }

            // ...otherwise just rethrow the IO exception
            throw $e;
        }

        return $pid;
    }


    /**
     * Spawns a new worker
     *
     * @return int
     * @throws RuntimeException
     */
    protected function attachWorker()
    {
        $this->log->notice("Attaching worker to {$this->strat->getName()} pool", array('char' => '+'));
        $cmd = $this->strat->getCommand();

        $descriptorSpec = array(
            self::DATA_IN   => array("pipe", "r"), // stdin     is a pipe that the child will read from
            self::DATA_OUT  => array("pipe", "w"), // stream(3) is a pipe that the child will write to
            self::DEBUG_OUT => array("pipe", "w"), // stdout    is a pipe that the child will write to
            self::DEBUG_ERR => array("pipe", "w")  // stderr    is a pipe that the child will write to
        );

        $process = "exec $cmd " . $this->strat->getFlags();

        if (!($pp = proc_open($process, $descriptorSpec, $pipes))) {
            throw new RuntimeException("Cannot launch worker process");
        }

        // disable stream blocking for the proc IO
        foreach (array_keys($pipes) as $i) {
            stream_set_blocking($pipes[$i], 0);
        }

        $status = proc_get_status($pp);

        if (!$status['running']) {
            throw new RuntimeException("Worker process terminated unexpectedly");
        }

        $pid = $status['pid'];

        $worker = new Worker($status['command'], $pp, $pipes, $this->strat);
        $worker->attachLogger($this->log);

        $this->workers->attach($pid, $worker);

        // create listenable stream objects
        foreach ($this->ioListeners as $k => $listener) {
            // @developers: look at the stream listeners for callback functionality
            $this->streams[$pid][$k] = new WrappedReadableResourceStream(
                $pipes[$k],
                $this->loop,
                $this->ioListeners[$k]
            );
        }

        return $pid;
    }


    /**
     * @param int $pid
     */
    protected function detachWorker($pid)
    {
        foreach ($this->streams[$pid] as $fd => $stream) {
            /** @var WrappedReadableResourceStream $stream */
            $stream->detachListener($this->ioListeners[$fd]);
            $stream->close();
        }
        unset($this->streams[$pid]);
        $this->workers->detach($pid);
        $this->log->debug("Detached worker {{$pid}}");
    }


    /**
     * @param int    $pid
     * @param string $cmd
     * @param mixed  $args
     * @param object $identifier
     * @throws RuntimeException
     */
    protected function assignTask($pid, $cmd, $args, $identifier)
    {
        if (!$this->workers->contains($pid)) {
            throw new RuntimeException("Cannot assign task to nonexistent worker: $pid");
        }
        $this->workers->getByProcessId($pid)->setTask($identifier, $cmd, $args);
    }


    /**
     * Returns the current status of the pool
     *
     * @return array
     */
    public function getStatus()
    {
        return array(
            'workers' => $this->workers->getStatus()
        );
    }


    /**
     * Returns whether or not a new command can be accepted.
     *
     * @return bool
     */
    public function canAccept()
    {
        return $this->strat->canAccept($this->workers);
    }


    /**
     * Returns the command that will be executed each time a worker is spawned by this strategy.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->strat->getCommand();
    }


    /**
     * Return:
     *   the PID of an available worker,
     *   0 if none are available but it is permissible to create one, or
     *   -1 if none are available, and no more may be spawned right now.
     *
     * @param int $remainingTaskCount
     * @return int
     */
    public function getAvailableWorker($remainingTaskCount)
    {
        return $this->strat->getAvailableWorker($this->workers, $remainingTaskCount);
    }


    /**
     * Return the list of commands that can be delegated to workers
     *
     * @return array
     */
    public function getSupportedActions()
    {
        return $this->strat->getSupportedActions();
    }


    /**
     * Evaluate the segment of data given and return a Worker STATE based on it
     *
     * @param string $data
     * @return int
     */
    public function checkState($data)
    {
        return $this->strat->checkState($data);
    }


    /**
     * Combine the two given parameters into a string that can be piped as a command to the worker process
     *
     * @param string $command
     * @param mixed  $arguments
     * @return string
     */
    public function composeTaskString($command, $arguments)
    {
        return $this->strat->composeTaskString($command, $arguments);
    }


    /**
     * Separate the given parameter into an array of two elements: command and args.
     *
     * @param string $taskString
     * @throws InvalidArgumentException
     * @return array
     */
    public function decomposeTaskString($taskString)
    {
        return $this->strat->decomposeTaskString($taskString);
    }


    /**
     * Converts some data to be in a format safe for socket transmission from Worker to Server
     *
     * @param mixed $data
     * @return string
     */
    public function encodeRecord($data)
    {
        return $this->strat->encodeRecord($data);
    }


    /**
     * Returns, in millis, how long the react loop should wait between dispatches from the queue
     *
     * @return int
     */
    public function getQueueDelay()
    {
        return $this->strat->getQueueDelay();
    }


    /**
     * Returns whether the Dispatcher should keep spinning, or spin down.
     * If false is returned, this method should reset any internal counter.
     *
     * @return bool
     */
    public function canDispatcherFlywheel()
    {
        return $this->strat->canDispatcherFlywheel();
    }


    /**
     * Method to advise the pool that the Dispatcher is idle.
     *
     * @return void
     */
    public function dispatcherIdle()
    {
        $this->strat->dispatcherIdle();
    }


    /**
     * Method to advise the pool that the Dispatcher is engaged.
     *
     * @return void
     */
    public function dispatcherEngage()
    {
        $this->strat->dispatcherEngage();
    }


    /**
     * Return the number of seconds a Worker should be idle before self-destructing
     *
     * @return int
     */
    public function getWorkerIdleTimeout()
    {
        return $this->strat->getWorkerIdleTimeout();
    }


    /**
     * Terminate all workers
     *
     * @param bool $hard
     */
    public function releaseWorkers($hard = self::RELEASE_HARD)
    {
        $this->log->debug(($hard ? 'Terminating' : 'Releasing') . " all workers");
        foreach (array_keys($this->streams) as $pid) {
            $this->releaseWorker($pid, $hard);
        }
    }


    /**
     * Terminate a worker
     *
     * @param int  $pid
     * @param bool $hard
     */
    public function releaseWorker($pid, $hard = self::RELEASE_HARD)
    {
        $this->log->info(($hard ? 'Terminating' : 'Releasing') . " worker {{$pid}}", array('char' => '-'));
        if ($hard) {
            $this->workers->terminateProcess($pid);
            $this->detachWorker($pid);
        } else {
            $this->workers->releaseProcess($pid);
        }
    }


    /**
     * Returns the PID of the process associated with the given stream or socket
     *
     * @param resource $resource
     * @throws OutOfBoundsException
     * @return int
     */
    public function getProcessIdByResource($resource)
    {
        return $this->workers->getProcessIdByResource($resource);
    }


    /**
     * Returns the Task currently assigned to the worker with the given PID.
     * Will throw OutOfBoundsException if no Worker with the given PID is found.
     *
     * @param int $pid
     * @throws OutOfBoundsException
     * @return Task
     */
    public function getCurrentTaskByProcessId($pid)
    {
        return $this->workers->getByProcessId($pid)->getTask();
    }


    /**
     * Request to set the status of the worker with the given PID.
     * The implementing class may elect to ignore this.
     *
     * @param int $pid
     * @param int $status
     * @throws OutOfBoundsException
     * @return int
     */
    public function setWorkerStatus($pid, $status)
    {
        // first, set the worker to the given state (throws OOB if necessary)
        $worker = $this->workers->getByProcessId($pid);

        $oldStatus = $worker->getStatus();
        $newStatus = $worker->setStatus($status);

        // don't bother telling us about attempts to change to the same state
        if ($status != $oldStatus) {
            $success = $status == $newStatus ? "Successfully" : "UNSUCCESSFULLY";
            $this->log->debug("$success transitioned {{$pid}} from $oldStatus to $status");
        }

        // return the result, knowing it may not have changed
        return $newStatus;
    }


    /**
     * Calculates the worker state based on the Event supplied.
     *
     * @param string $data
     * @return int
     */
    public function calculateState($data)
    {
        return $this->strat->checkState($data);
    }
}
