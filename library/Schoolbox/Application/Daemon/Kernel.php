<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Daemon;

use Alaress\Exception\IO\IOException;
use Alaress\Lumberjack\Logger;
use ErrorException;
use Exception;
use PDOException;
use Psr\Log\LoggerInterface;
use RangeException;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Monolog\Handler\RavenHandler;
use Monolog\Logger as Monologger;


class Kernel extends \Alaress\Application\Kernel
{
    const RLIMIT_CORE_FILESIZE = 'core';
    const RLIMIT_CPU_TIME = 'cpu';
    const RLIMIT_DATA_SEGMENT = 'data';
    const RLIMIT_FILESIZE = 'filesize';
    const RLIMIT_FILE_DESCRIPTORS = 'openfiles';
    const RLIMIT_PROCESSES = 'maxproc';
    const RLIMIT_MEMORY_LOCK = 'memlock';
    const RLIMIT_RESIDENT_SET = 'rss';
    const RLIMIT_STACK_SIZE = 'stack';
    const RLIMIT_TOTAL_MEMORY = 'totalmem';

    /** @var string */
    protected $name;

    /** @var LoopInterface */
    protected $loop;

    /** @var array */
    protected $opts;

    /** @var LoggerInterface */
    protected $log;

    /**
     * Various process limits imposed by the operating system
     *
     * @var string[][]
     */
    protected $limits;

    /**
     * Initialise
     *
     * @param string $name
     * @param array  $opts
     */
    public function __construct($name, $opts = [])
    {
        $this->setOpts($opts);
        $this->boot = new Bootstrap($this);
        $this->boot();

        pcntl_signal(SIGCHLD, SIG_IGN);
        error_reporting(isset($opts['errors']) ? $opts['errors'] : E_ALL ^ E_NOTICE);
        $this->name = $name;

        $this->loop = $this->di->values['react.loop'] = Factory::create();

        foreach (posix_getrlimit() as $key => $value) {
            list ($strength, $attr) = explode(' ', $key);
            $this->limits[$attr][$strength] = $value === 'unlimited' ? null : $value;
        }
    }


    /**
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }


    /**
     * @param array $opts
     */
    protected function setOpts($opts)
    {
        $this->opts = $opts;
        // If level not set, logging level determined by verbose
        if (!isset($this->opts['level'])) {
            $this->opts['level'] = $this->getLoggingLevel();
        }
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getOpt($name)
    {
        return isset($this->opts[$name]) ? $this->opts[$name] : null;
    }


    /**
     * @param string $attribute
     * @return string
     */
    public function getHardLimit($attribute)
    {
        return $this->limits[$attribute]['hard'];
    }


    /**
     * @param string $attribute
     * @return string
     */
    public function getSoftLimit($attribute)
    {
        return $this->limits[$attribute]['soft'];
    }


    /**
     * Go. Do stuff.
     *
     * @param callable $callable
     * @throws Exception
     * @throws \Schoolbox\External\Exception
     */
    public function run($callable = null)
    {
        if (posix_isatty(STDOUT) && !is_null($this->getOpt('verbose'))) {
            echo <<<EOL

 ||
 || Schoolbox {$this->name} Daemon
 ||


EOL;
        }

        // not much point going on if we've got nothing to do
        if (!is_callable($callable)) {
            $this->logger()->critical("No daemon to launch, aborting");
            return;
        }

        try {
            call_user_func($callable, $this->di, $this->loop, $this->logger(), $this->opts);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), [$e]);
            throw $e;
        } catch (Exception $e) {
            $this->logger()->critical("{$this->name} daemon failed to launch: " . $e->getMessage(), [$e]);
            throw $e;
        }

        try {
            $this->getLoop()->run();
            $this->logger()->notice($this->name . " daemon loop ended; preparing to terminate");
        } catch (PDOException $e) {
            $this->logger()->emergency($e->getMessage(), [$e]);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), [$e]);
        } catch (Exception $e) {
            $this->logger()->critical(sprintf("%s daemon received %s: %s", $this->name, get_class($e),
                $e->getMessage()), [$e]);
        }
    }


    /**
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @return bool
     * @throws ErrorException
     */
    public function onError($errNum, $errStr, $errFile, $errLine)
    {
        if (!(error_reporting() & $errNum)) {
            // This error code is not included in error_reporting
            return true;
        }


        switch ($errNum) {
            case E_RECOVERABLE_ERROR:
                // for any of these errors, we want an exception we can catch
                $this->logger()->info("$errStr - $errFile::$errLine", ['char' => 'e']);
                throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_WARNING:
            case E_USER_WARNING:
                // this will throw an exception if it needs to
                $this->onWarning($errNum, $errStr, $errFile, $errLine);
                return true;

            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $this->logger()->warning("Warning: $errStr in $errFile:$errLine");
                return true;

            default:
                // we don't know how to deal with this, so let the stock error handler deal with it
                $this->logger()->error("Don't know how to handle $errStr");
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
    protected function onWarning($errNum, $errStr, $errFile, $errLine)
    {
        if (strpos($errStr, 'stream_select') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to shutdown socket') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to recvfrom') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'unable to select') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'fread') === 0) {
            // this is fine to be let through
            return;
        }
        if (strpos($errStr, 'LaravelDate creation raised') === 0) {
            // iiiiignore.
            return;
        }
        if (strpos($errStr, 'gzuncompress(): data error') === 0) {
            // ignore this too;
            // it's a symptom of a compressed string I can do nothing with
            return;
        }
        if (strpos($errStr, 'Undefined index') === 0) {
            $this->logger()->error("$errStr in $errFile:$errLine");
            return;
        }
        if (strpos($errStr, 'Undefined variable') === 0) {
            $this->logger()->error("$errStr in $errFile:$errLine");
            return;
        }
        if (strpos($errStr, 'fwrite') === 0) {
            throw new IOException($errStr, $errNum);
        }
        if (strpos($errStr, 'unserialize') === 0) {
            // throw a specific kind of exception
            throw new RuntimeException($errStr, $errNum);
        }
        if (strpos($errStr, 'Class __PHP_Incomplete_Class') === 0) {
            // throw a specific kind of exception
            throw new RuntimeException($errStr, $errNum);
        }
        if (strpos($errStr, 'fclose') === 0) {
            throw new RangeException($errStr, $errNum);
        }
        if (strpos($errStr, 'pclose') === 0) {
            throw new RangeException($errStr, $errNum);
        }
        if (strpos($errStr, 'Error while sending QUERY packet') === 0) {
            throw new PDOException($errStr);
        }

        try {
            $this->logger()->info("$errStr in $errFile:$errLine [$errNum]", ['char' => 'e']);
        } catch (IOException $e) {
            // it's possible that the warning was from the STDOUT pipe having been closed,
            // in which case attempting to log is only going to exacerbate the issue
        }

        throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
    }


    /**
     * @param Exception $e
     */
    public function onException($e)
    {
        $this->callExceptionCallbacks($e);
        try {
            $this->logger()->alert('FATAL ' . get_class($e) . ': ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile(), [$e]);
        } catch (IOException $e) {
            // can't do anything, STDOUT has been closed
        }
        exit;
    }


    /**
     * Check if it was a fatal error that shut us down, and if it was, advise as such
     */
    public function onShutdown()
    {
        $error = error_get_last();

        // only report if the last error was one of these
        $cases = [
            E_ERROR         => 'Fatal',
            E_CORE_ERROR    => 'Core',
            E_USER_ERROR    => 'Fatal user',
            E_COMPILE_ERROR => 'Compilation',
        ];

        if (is_callable($this->shutdown)) {
            call_user_func($this->shutdown);
        }

        // don't report anything
        if (!isset($cases[$error['type']])) {
            exit(0);
        }

        // report
        $message = sprintf('%s error: %s in %s:%s', $cases[$error['type']], $error['message'], $error['file'],
            $error['line']);
        try {
            $this->logger()->alert($message);
        } catch (IOException $e) {
            // can't do anything, STDOUT has been closed
        }

        exit(1);
    }


    /**
     * Determine logging level via verbose setting
     * verbose is set by -vvv.  Each v represents a value in an array.
     * So -v is level 1, -vv is level 2, -vvv is level 3
     *
     * @return int
     */
    public function getLoggingLevel()
    {
        $v = $this->getOpt('verbose');

        $threshold = 0;
        if (isset($v)) {
            $threshold = 1;
            if (is_array($v)) {
                $threshold = min(3, count($v));
            }
        }

        $limits = [
            0 => 500, // nil verbosity: exceptional behaviour only
            1 => 250, // min verbosity: significant events
            2 => 200, // mod verbosity: most things
            3 => 100  // max verbosity: EV-ER-Y-THING...
        ];
        return $limits[$threshold];
    }


    /**
     * @return LoggerInterface
     */
    public function logger()
    {
        if (!$this->log instanceof LoggerInterface) {
            // if verbose output is on
            $level     = $this->getOpt('level');
            $fields    = $this->getOpt('fields') ?: [];
            $porcelain = in_array('porcelain', $fields);
            $this->log = new Logger(STDOUT, STDERR, $level, $fields, $porcelain);
        }
        // Add Sentry handler into logging system
        $ravenHandler = new RavenHandler($this->getDependencyInjector()->get('sentry'),Monologger::CRITICAL);
        $this->log->pushHandler($ravenHandler);

        return $this->log;
    }

}
