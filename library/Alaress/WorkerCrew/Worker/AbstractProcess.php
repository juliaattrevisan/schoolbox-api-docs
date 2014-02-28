<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\WorkerCrew\Pooling\DefaultStrategy;
use Alaress\WorkerCrew\Pooling\PoolingInterface;
use Exception;
use InvalidArgumentException;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use Schoolbox\Realtime\Verboser;

abstract class AbstractProcess {

	/** @var PoolingInterface */
	protected $strat;

	/** @var Verboser */
	protected $v;

	/** @var LoopInterface */
	protected $loop;

	/** @var Stream */
	protected $stream;

	/** @var bool */
	protected $buffering = false;

	/** @var string */
	protected $buffer = '';


	/**
	 * @param PoolingInterface $strat
	 * @param Verboser $verboser
	 */
	public function __construct($strat = null, $verboser = null) {
		$this->strat = $strat ?: new DefaultStrategy();
		$this->v = $verboser ?: new Verboser(null);

		$this->loop = Factory::create();

		// listen to STDIN for commands
		$this->stream = new Stream(STDIN, $this->loop);

		$this->stream->on('data', array($this, 'onData'));
		$this->stream->on('close', array($this, 'onClose'));
	}


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
			if(strlen($data) == 1 && $data[0] == PoolingInterface::STR_CANCEL) {
				$this->loop->stop();
				return;
			}

			if(!$this->buffering) {
				$this->buffering = true;
				$this->buffer = '';
			}

			$this->buffer .= $data;

			// if EOT received
			if(strpos($this->buffer, PoolingInterface::STR_END_OF_TRANSMISSION) !== false) {
				$this->parseBuffer();
				$this->buffering = false;
			}

		}
		catch(Exception $e) {
			// all exceptions must be caught, otherwise the react loop explodes and this worker dies
			$this->v->v("Worker exception: ".get_class($e)." ".$e->getMessage());
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
			$this->v->v("Rejecting data '$this->buffer'", '*');
			echo PoolingInterface::STR_NACKNOWLEDGE;
			return;
		}

		// use ob_end_clean to clear any debugging printout that might have occurred while processing
		try {
			$response = $this->process($cmd, $args);
			ob_end_clean();
			echo $this->strat->encodeRecord($response);
			echo PoolingInterface::STR_END_OF_TRANSMISSION;
		}
		catch(Exception $e) {
			ob_end_clean();
			echo PoolingInterface::STR_NACKNOWLEDGE;
		}
	}

	/**
	 * @param string $command
	 * @param string $args
	 * @return mixed
	 */
	protected abstract function process($command, $args);

} 