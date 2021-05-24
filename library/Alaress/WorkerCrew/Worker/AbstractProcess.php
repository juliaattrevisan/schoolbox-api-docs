<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Worker;

use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\IO\Listener\InputStreamListener;
use Alaress\WorkerCrew\Worker\IO\WrappedReadableResourceStream;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\DuplexResourceStream as Stream;
use RuntimeException;

abstract class AbstractProcess implements WorkerInterface
{
    const IDLE_TIMEOUT = 120; // max 2 minutes of idleness

    /** @var LoopInterface */
    protected $loop;

    /** @var Stream */
    protected $stream;

    /** @var bool */
    protected $buffering = false;

    /** @var DispatchStrategyInterface */
    protected $strat;

    /** @var LoggerInterface */
    protected $log;

    /**
     * Timer for ensuring a worker doesn't sit idle for too long
     *
     * @var TimerInterface
     */
    protected $timeout;


    /**
     * @param LoopInterface             $loop
     * @param LoggerInterface           $log
     * @param PipeWriter                $writer
     * @param DispatchStrategyInterface $strat
     */
    public function __construct(
        LoopInterface $loop,
        LoggerInterface $log,
        PipeWriter $writer,
        DispatchStrategyInterface $strat
    ) {
        $this->log   = $log;
        $this->loop  = $loop;
        $this->strat = $strat;

        // listen to STDIN for commands
        $listener = new InputStreamListener($log, $loop, $strat->getProtocol(), $writer, $this);
        new WrappedReadableResourceStream(
            fopen('php://stdin', 'r'),
            $loop,
            $listener,
            $this->strat->getProtocol()->chunkLength()
        );

        $this->loop->addPeriodicTimer(0.1, array($this, 'signalDispatch'));

        $this->idle();

        pcntl_signal(SIGTERM, array($this, 'onTerminate'));
    }


    /**
     * Check if a signal has been raised
     */
    public function signalDispatch()
    {
        pcntl_signal_dispatch();
    }


    /**
     * Start the loop
     */
    public function run()
    {
        $this->loop->run();
    }


    /**
     * When a terminate signal is raised
     */
    public function onTerminate()
    {
        $this->log->info("GOT SIGTERM", array('char' => '>'));
        $this->loop->stop();
    }


    /**
     * @param string $buffer
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @return string
     */
    public function parse($buffer)
    {
        // we've stopped idling! cancel the timeout
        $this->active();

        try {
            // split the datastring supplied
            list($cmd, $args) = $this->strat->decomposeTaskString($buffer);
            // if not a supported command, reject
            if (!in_array($cmd, $this->strat->getSupportedActions())) {
                throw new InvalidArgumentException("Rejecting command '$cmd'");
            }
        } catch (InvalidArgumentException $e) {
            $this->log->error("Rejecting data '$buffer'", ['exception' => $e]);
            throw $e;
        }

        $response = $this->process($cmd, $args);
        $this->log->info("Processing successful");
        gc_collect_cycles();
        $flat = $this->strat->getProtocol()
                            ->flatten($response);
        $size = strlen($flat);
        $this->log->debug("Flattened response is $size bytes");

        // resume idling
        $this->idle();

        return $flat;
    }


    /**
     * Set the idle timeout. Idempotent method.
     */
    private function idle()
    {
        if (!$this->timeout instanceof TimerInterface) {
            $this->log->debug("Worker idling");
            $this->timeout = $this->loop->addTimer(self::IDLE_TIMEOUT, [ $this, 'onTerminate' ]);
        }
    }


    /**
     * Clear the idle timeout. Idempotent method.
     */
    private function active()
    {
        if ($this->timeout instanceof TimerInterface) {
            $this->log->debug("Worker active");
            $this->loop->cancelTimer($this->timeout);
            $this->timeout = null;
        }
    }



    /**
     * @param string $command
     * @param array  $args
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return mixed
     */
    abstract protected function process($command, $args);
}
