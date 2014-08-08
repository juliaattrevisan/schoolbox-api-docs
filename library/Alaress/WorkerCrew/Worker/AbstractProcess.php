<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\Lumberjack\Logger;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use ErrorException;
use Exception;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
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
	 * @param LoggerInterface $log
	 */
	public function __construct($loop, $strat = null, $log = null) {
		// cast optional args
		$strat = $strat ?: new DefaultDispatchStrategy();
		$log   = $log   ?: new Logger(null);

		// we open stream(3) as our data output stream
		parent::__construct($strat, $log, fopen("php://fd/3", 'w'));
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
		$this->log->info("GOT SIGTERM", array('char' => '>'));
		$this->loop->stop();
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
					$this->log->debug("Task Complete");
				}
				catch(ErrorException $e) {
					$msg = sprintf("Error encountered: '%s' in %s:%s", $e->getMessage(), $e->getFile(), $e->getLine());
					$this->log->warning($msg);
				}
                catch(PDOException $e) {
                    throw $e;
                }
				catch(Exception $e) {
					$msg = sprintf("Failed to process: %s - '%s'", get_class($e), $e->getMessage());
					$this->log->error($msg);
				}

				if($buffer = ob_get_contents()) {
					$this->log->debug("Captured output: $buffer");
				}
				ob_end_clean();

				$this->buffering = false;
			}

		}
        catch(PDOException $e) {
            $this->log->emergency("PDOException: ".$e->getMessage(), array($e));
            $this->loop->stop();
        }
		catch(Exception $e) {
			// all exceptions must be caught, otherwise the react loop explodes and this worker dies
			$msg = sprintf("Worker exception: %s - '%s'", get_class($e), $e->getMessage());
			$this->log->critical($msg, array($e));
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
			$this->log->error("Rejecting data '$this->buffer'", array($e));
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
	 * @param array $args
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 * @return mixed
	 */
	protected abstract function process($command, $args);

} 