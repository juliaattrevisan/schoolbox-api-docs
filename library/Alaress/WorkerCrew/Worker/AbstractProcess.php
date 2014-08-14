<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\Worker\IO\IOStream;
use Alaress\WorkerCrew\Worker\IO\Listener\InputStreamListener;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use RuntimeException;

abstract class AbstractProcess implements WorkerInterface {

	/** @var LoopInterface */
	protected $loop;

	/** @var Stream */
	protected $stream;

	/** @var bool */
	protected $buffering = false;

	/** @var DispatchStrategyInterface */
	protected $strat;


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
        $stream   = new IOStream(fopen('php://stdin', 'r'), $loop, $listener);
        $stream->bufferSize = $this->strat->getProtocol()->chunkLength();

		$this->loop->addPeriodicTimer(0.1, array($this, 'signalDispatch'));

		pcntl_signal(SIGTERM, array($this, 'onTerminate'));
	}


	/**
	 * Check if a signal has been raised
	 */
	public function signalDispatch() {
		pcntl_signal_dispatch();
	}


	/**
	 * Start the loop
	 */
	public function run() {
		$this->loop->run();
	}


	/**
	 * When a terminate signal is raised
	 */
	public function onTerminate() {
		$this->log->info("GOT SIGTERM", array('char' => '>'));
		$this->loop->stop();
	}


	/**
     * @param string $buffer
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @return string
	 */
	public function parse($buffer) {
		try {
			// split the datastring supplied
			list($cmd, $args) = $this->strat->decomposeTaskString($buffer);
			// if not a supported command, reject
			if(!in_array($cmd, $this->strat->getSupportedActions())) {
				throw new InvalidArgumentException("Rejecting command '$cmd'");
			}
		}
		catch(InvalidArgumentException $e) {
			$this->log->error("Rejecting data '$buffer'", array($e));
			throw $e;
		}

		$response = $this->process($cmd, $args);
		$this->log->info("Processing successful");
		$flat = $this->strat->getProtocol()->flatten($response);
		$size = strlen($flat);
		$this->log->debug("Flattened response is $size bytes");

		return $flat;
	}


    /**
     * @param string $command
     * @param array  $args
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @return mixed
     */
    protected abstract function process($command, $args);

} 