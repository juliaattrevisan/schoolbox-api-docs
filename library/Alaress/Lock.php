<?php

/**
 * Alaress_Lock
 *
 * Really simple process locking mechanism. Stores the PID in the file passed in
 * the constructor.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Alaress_Lock
{
    /**
     * The file to use as the lock. It will contain the PID of the current
     * process, and its start time.
     *
     * @var string
     */
    protected $filename;

    /**
     * Constructor
     *
     * @param string $filename
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * Is the process locked?
     *
     * @return bool
     */
    public function isLocked()
    {
        if (!file_exists($this->filename)) {
            return false;
        }

        // Get the list of all processes matching the PID in the lockfile.
        [$pid, $startTimestamp] = $this->parseLockfile();
        $cmd                    = sprintf('ps -p %s -o etimes --no-headers', escapeshellarg($pid));
        $output                 = trim(shell_exec($cmd));
        if (empty($output)) {
            // Process no longer exists: kill lockfile and continue
            $this->release();
            return false;
        }

        // No start timestamp at all?
        // This is probably a lockfile from before that timestamp was stored:
        // fall back to old behavior (i.e. assume it's still running)
        if ($startTimestamp === 0) {
            return true;
        }

        // The process is still running, but has its PID been reassigned?
        // If the process started after the start time in the lock file, it must
        // be a different process: release
        $elapsedTime           = (int)$output;
        $processStartTimestamp = $_SERVER['REQUEST_TIME'] - $elapsedTime;
        if ($processStartTimestamp > $startTimestamp) {
            $this->release();
            return false;
        }

        return true;
    }

    /**
     * Acquires the lock, if possible.
     *
     * @return bool Was it acquired?
     */
    public function acquire()
    {
        if ($this->isLocked()) {
            return false;
        }

        // Data that goes in the lockfile:
        // * PID
        // * process' start time
        file_put_contents(
            $this->filename,
            implode(',', [getmypid(), $_SERVER['REQUEST_TIME']])
        );
        return true;
    }

    /**
     * Releases the lock.
     *
     * @return void
     */
    public function release()
    {
        if (file_exists($this->filename)) {
            unlink($this->filename);
        }
    }

    /**
     * Kills the process in the lockfile.
     *
     * @param int $signal The signal to send, defaults to SIGKILL.
     * @return bool Was the process killed?
     */
    public function killProcess($signal = SIGKILL)
    {
        if (file_exists($this->filename)) {
            [$pid] = $this->parseLockfile();
            if (posix_kill($pid, $signal)) {
                return true;
            }
        }
        return false;
    }


    /**
     * Parse the lockfile's contents
     *
     * @return array&int[] [pid, start timestamp]
     */
    private function parseLockfile(): array
    {
        return array_map(
            'intval',
            array_pad(
                explode(',', file_get_contents($this->filename), 2),
                2,
                0
            )
        );
    }
}
