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

		parent::__construct($strat, $debug, STDOUT);
		$this->strat = $strat;

		$this->loop = $loop;

		// listen to STDIN for commands
		$this->stream = new Stream(STDIN, $this->loop);
		$this->stream->bufferSize = $this->strat->getProtocol()->chunkLength();

		$this->stream->on('data', array($this, 'onData'));
		$this->stream->on('close', array($this, 'onClose'));
	}


	/**
	 * Start the loop
	 */
	public function run() {
		$this->loop->run();
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
				$this->loop->stop();
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
					$this->debug->d("Task Complete");
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