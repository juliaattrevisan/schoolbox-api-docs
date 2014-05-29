<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Daemon;

use Alaress\Cli\Debug;
use Aura\Di;
use ErrorException;
use Exception;
use RangeException;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use RuntimeException;


class Kernel extends \Alaress\Application\Kernel {

	/** @var string */
	protected $name;

	/** @var LoopInterface */
	protected $loop;

	/** @var array */
	protected $opts;

	/** @var Debug */
	protected $debug;

	/**
	 * Initialise
	 *
	 * @param string $name
	 * @param array $opts
	 */
	public function __construct($name, $opts = array()) {
		$this->boot = new Bootstrap($this);
		$this->boot();

		error_reporting(E_ALL ^ E_NOTICE);
		$this->name = $name;
		$this->opts = $opts;

		$this->loop = Factory::create();
	}


	/**
	 * @return LoopInterface
	 */
	public function getLoop() {
		return $this->loop;
	}


	/**
	 * @param string $name
	 * @return mixed
	 */
	public function getOpt($name) {
		return isset($this->opts[$name]) ? $this->opts[$name] : null;
	}


	/**
	 * Go. Do stuff.
	 *
	 * @param callable $callable
	 * @return void
	 */
	public function run($callable = null) {
		// if verbose output is on
		if($this->getOpt('verbose') === true) {
			$this->debug = new Debug(STDOUT);

			echo <<<EOL

 ||
 || Schoolbox {$this->name} Daemon
 ||


EOL;

		}
		else {
			$this->debug = new Debug(null);
		}

		// not much point going on if we've got nothing to do
		if(!is_callable($callable)) {
			$this->debug->v("No daemon to launch, aborting", '*');
			return;
		}

		try {
			call_user_func($callable, $this->di, $this->loop, $this->debug, $this->opts);
		}
		catch(Exception $e) {
			$this->debug->v("{$this->name} daemon failed to launch: " . $e->getMessage(), '!');
		}

		try {
			$this->getLoop()->run();
			$this->debug->v($this->name . " daemon terminating.");
		}
		catch(Exception $e) {
			$this->debug->v("{$this->name} daemon exception: " . $e->getMessage(), '!');
		}
	}


	/**
	 * @param int $errNum
	 * @param string $errStr
	 * @param string $errFile
	 * @param int $errLine
	 * @return bool
	 * @throws ErrorException
	 */
	public function handleError($errNum, $errStr, $errFile, $errLine) {
		if($this->debug instanceof Debug) {
			$this->debug->v("$errStr - $errFile::$errLine", 'e');
		}

		if (!(error_reporting() & $errNum)) {
			// This error code is not included in error_reporting
			return true;
		}

		switch($errNum) {
			case E_RECOVERABLE_ERROR:
			case E_NOTICE:
				// for any of these errors, we want an exception we can catch
				throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);

			case E_WARNING:
				// this will throw an exception if it needs to
				$this->handleWarning($errNum, $errStr, $errFile, $errLine);
				return true;

			default:
				// we don't know how to deal with this, so let the stock error handler deal with it
				return false;
		}
	}


	/**
	 * Handle specific kinds of warnings differently
	 *
	 * @param int    $errNum
	 * @param string $errStr
	 * @param string $errFile
	 * @param int    $errLine
	 * @throws Exception
	 */
	protected function handleWarning($errNum, $errStr, $errFile, $errLine) {
		if(strpos($errStr, 'stream_select') === 0) {
			// this is fine to be let through
			return;
		}
		if(strpos($errStr, 'unable to shutdown socket') === 0) {
			// this is fine to be let through
			return;
		}
		if(strpos($errStr, 'unserialize') === 0) {
			// throw a specific kind of exception
			throw new RuntimeException($errStr, $errNum);
		}
		if(strpos($errStr, 'fclose') === 0) {
			throw new RangeException($errStr, $errNum);
		}
		if(strpos($errStr, 'pclose') === 0) {
			throw new RangeException($errStr, $errNum);
		}



		throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
	}


	/**
	 * @param Exception $e
	 */
	public function handleException($e) {
		if($this->debug instanceof Debug) {
			$this->debug->v('FATAL ' . get_class($e) . ': ' . $e->getMessage(), '!');
		}
		exit;
	}

}