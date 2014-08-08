<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Worker;


use Alaress\WorkerCrew\IPC\ProtocolInterface;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Psr\Log\LoggerInterface;

class PipeWriter {

	/** @var ProtocolInterface */
	protected $proto;

	/** @var LoggerInterface */
	protected $log;

	/** @var resource */
	protected $downpipe;

	/**
	 * @param DispatchStrategyInterface $strat
	 * @param LoggerInterface $log
	 * @param resource $downpipe
	 */
	public function __construct($strat, $log, $downpipe) {
		$this->proto = $strat->getProtocol();
		$this->log = $log;
		$this->downpipe = $downpipe;
	}


	/**
	 * Write a string to the worker process, ensuring to be sensitive of pipe buffering
	 *
	 * @param string $string
	 * @param bool $err
	 * @return self
	 */
	public function write($string, $err = false) {
		$chunk = $this->proto->chunkLength();
		$strlen = strlen($string);
		$end = $err ? $this->proto->reject() : $this->proto->end();

		for ($written = 0; $written < $strlen; $written += $fwrite) {
			$shard = str_pad(substr($string, $written, $chunk), $chunk, $end);
			$fwrite = fwrite($this->downpipe, $shard);
			if ($fwrite === false) {
				$this->log->critical("Broken pipe, could not write to target");
				break;
			}
		}
		fflush($this->downpipe);

		if($overhang = $written % $chunk) {
			$this->log->error("$overhang bytes unflushed");
		}

		$this->log->info(sprintf("Sent %d bytes to target (%d x %d-byte chunks)", $written, $written / $chunk, $chunk));

		if($written < $strlen) {
			$this->log->critical("Pipe communication terminated prematurely after $written bytes, ", $strlen - $written, " unsent");
		}

		return $this;
	}

} 