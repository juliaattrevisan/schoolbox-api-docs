<?php
/**
 * Alaress_Lock
 *
 * Really simple process locking mechanism. Stores the PID in the file passed in
 * the constructor.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Alaress_Lock {

	protected $filename;

	/**
	 * Constructor
	 *
	 * @param string $filename The file to use as the lock. It will contain the
	 *                         PID of the current process.
	 *
	 */
	public function __construct($filename) {
		$this->filename = $filename;
	}

	/**
	 * Is the process locked?
	 *
	 * @return bool
	 */
	public function isLocked() {
		if (!file_exists($this->filename)) {
			return false;
		}
		//
		// Get the list of all processes matching the PID in the lockfile.
		$pid    = file_get_contents($this->filename);
		$cmd    = sprintf('ps -p %s --no-headers', escapeshellarg($pid));
		$output = trim(shell_exec($cmd));
		if (empty($output)) {
			//
			// Process no longer exists: kill lockfile and continue
			$this->release();
			return false;
		}
		//
		// The process is still running, so it's still locked.
		return true;
	}

	/**
	 * Acquires the lock, if possible.
	 *
	 * @return bool Was it acquired?
	 */
	public function acquire() {
		if ($this->isLocked()) {
			return false;
		}
		file_put_contents($this->filename, getmypid());
		return true;
	}

	/**
	 * Releases the lock.
	 *
	 * @return void
	 */
	public function release() {
		if (file_exists($this->filename)) {
			unlink($this->filename);
		}
	}
}
