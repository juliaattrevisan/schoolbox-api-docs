<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\Cli\Debug;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use ErrorException;
use Exception;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use RuntimeException;

abstract class AbstractProcess extends PipeWriter {

	/** @var LoopInterface */
	protected $loop;

	/** @var Stream */
	protected $stream;

	/** @var bool */
	protected $buffering = false;

	/** @var string */
	protected $buffer = '';

	/** @var DispatchStrategyInterface */
	protected $strat;


	/**
	 * @param LoopInterface $loop
	 * @param DispatchStrategyInterface $strat
	 * @param Debug $debug
	 */
	public function __construct($loop, $strat = null, $debug = null) {
		// cast optional args
		$strat = $strat ?: new DefaultDispatchStrategy();
		$debug = $debug ?: new Debug(null);

		// we open stream(3) as our data output stream
		parent::__construct($strat, $debug, fopen("php://fd/3", 'w'));
		$this->strat = $strat;

		$this->loop = $loop;

		// listen to STDIN for commands
		$this->stream = new Stream(STDIN, $this->loop);
		$this->stream->bufferSize = $this->strat->getProtocol()->chunkLength();

		$this->stream->on('data', array($this, 'onData'));
		$this->stream->on('close', array($this, 'onClose'));
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
		$this->debug->v("GOT SIGTERM", '>');
		$this->loop->stop();
		exit;
	}

	/**
	 * @param string $data
	 * @param Stream $stream
	 * @return mixed
	 */
	public function onData($data, $stream) {
		try {
			// cease and desist received
			if($this->strat->getProtocol()->isCancelled($data)) {
				$this->onTerminate();
				return;
			}

			if(!$this->buffering) {
				$this->buffering = true;
				$this->buffer = '';
			}

			$this->buffer .= $data;

			// if EOT received
			if($this->strat->getProtocol()->isEnded($data)) {
				ob_start();

				try {
					$this->buffering = false;
					$this->write($this->parseBuffer());
					$this->debug->v("Task Complete");
				}
				catch(ErrorException $e) {
					$msg = sprintf("Error encountered: '%s' in %s:%s", $e->getMessage(), $e->getFile(), $e->getLine());
					$this->debug->v($msg);
					$this->write($msg, true);
				}
				catch(Exception $e) {
					$msg = sprintf("Failed to process: %s - '%s'", get_class($e), $e->getMessage());
					$this->debug->v($msg);
					$this->write($msg, true);
				}

				if($buffer = ob_get_contents()) {
					$this->debug->v("Captured output: $buffer", 'd');
				}
				ob_end_clean();

				$this->buffering = false;
			}

		}
		catch(Exception $e) {
			// all exceptions must be caught, otherwise the react loop explodes and this worker dies
			$msg = sprintf("Worker exception: %s - '%s'", get_class($e), $e->getMessage());
			$this->debug->v($msg);
			$this->write($msg, true);
		}
	}


	/**
	 * @param Stream $stream
	 * @return mixed
	 */
	public function onClose($stream) {
		// stream is closing, I can't communicate with the parent, that's it, I'm out!
		$this->loop->stop();
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @return string
	 */
	protected function parseBuffer() {
		try {
			// split the datastring supplied
			list($cmd, $args) = $this->strat->decomposeTaskString($this->buffer);
			// if not a supported command, reject
			if(!in_array($cmd, $this->strat->getSupportedActions())) {
				throw new InvalidArgumentException("Rejecting command '$cmd'", '*');
			}
		}
		catch(InvalidArgumentException $e) {
			$this->debug->v("Rejecting data '$this->buffer'", '*');
			throw $e;
		}

		$response = $this->process($cmd, $args);
		$this->debug->v("Processing successful");
		$flat = $this->strat->getProtocol()->flatten($response);
		$size = strlen($flat);
		$this->debug->v("Flattened response is $size bytes", 'd');

		return $flat;
	}

	/**
	 * @param string $command
	 * @param array $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected abstract function process($command, $args);

} 