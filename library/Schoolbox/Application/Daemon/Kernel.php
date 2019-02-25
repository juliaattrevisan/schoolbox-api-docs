<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Application\Daemon;

use Alaress\Application\WrongSapiException;
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
use React\Stomp;
use Schoolbox\Application\CliKernelAbstract;

class Kernel extends CliKernelAbstract
{
    // Options keys for $options passed to this Kernel's constructor
    public const OPTION_LEVEL          = 'level';        // What log level should be reported? Falls back to 'v' if not specified.
    public const OPTION_ERRORS         = 'errors';       // What level of PHP errors should be reported?
    public const OPTION_APP_SHORT_OPTS = 'appShortOpts'; // short options used by the app
    public const OPTION_APP_LONG_OPTS  = 'appLongOpts';  // long options used by the app

    // Command line options recognised by this Daemon kernel
    public const OPT_VERBOSITY     = 'v';         // What level of verbosity should be logged
    public const OPT_PORCELAIN     = 'porcelain'; // Should log items be formatted for text file rather than TTL?
                                                  // If set, causes field name to be prefixed to field value to enable logs to be parsable by scripts
                                                  // eg '[!][2019-02-26 00:29:22.72907][13746]' would become '[!][@550][2019-02-26 00:29:22.72907][pid:13746]'

    /* System resources available in $this->getSoftLimit() and $this->getHardLimit()
     * @see posix_getrlimit()
     */
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
    private $name;

    /** @var LoopInterface */
    private $loop;

    /**
     * All options injected into this Daemon kernel.
     *
     * Used for:
     * 1. Controlling logging behaviour
     * 2. Passed back into the run() function for function/App-specific config
     *
     * @var array
     */
    private $opts;


    /**
     * Was this kernel invoked at a specific log level?
     *
     * @var int|null
     */
    private $logLevel;

    /**
     * Various process limits imposed by the operating system
     *
     * Will be NULL if unlimited
     *
     * @see self::RLIMIT_* constants above
     * @var string[][]|null[][]
     */
    private $limits;


    /**
     * Initialise
     *
     * @param string $name The name of this Daemon
     * @param array  $options Keys are one of the self::OPTION_X constants
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct(string $name, array $options = [])
    {
        $this->validateOptions($options);
        $this->parseCommandLineOpts($options);
        $this->logLevel = $options[self::OPTION_LEVEL] ?? null;

        parent::__construct();

        pcntl_signal(SIGCHLD, SIG_IGN);
        error_reporting($options[self::OPTION_ERRORS] ?? E_ALL ^ E_NOTICE);
        $this->name = $name;

        $this->loop = $this->di->values['react.loop'] = Factory::create();

        foreach (posix_getrlimit() as $key => $value) {
            [$strength, $attr] = explode(' ', $key);
            $this->limits[$attr][$strength] = $value === 'unlimited' ? null : $value;
        }
    }


    /**
     * Check that all kernel options are valid
     *
     * @param array $options
     * @throws \InvalidArgumentException If unrecognised kernel options are injected
     */
    private function validateOptions(array $options): void
    {
        $valid   = [self::OPTION_LEVEL, self::OPTION_ERRORS, self::OPTION_APP_SHORT_OPTS, self::OPTION_APP_LONG_OPTS];
        $invalid = [];
        foreach ($options as $key => $val) {
            if (!\in_array($key, $valid, true)) {
                $invalid[$key] = $val;
            }
        }
        if (\count($invalid)) {
            throw new \InvalidArgumentException("Unrecognised Kernel options: " . \print_r($invalid, true));
        }
    }


    /**
     * Processes command line options and any kernel-invocation overrides
     *
     * @param array $options
     */
    private function parseCommandLineOpts(array $options): void
    {
        // Get all Kernel, App, and Logger options from command line
        $appShortOpts = $options[self::OPTION_APP_SHORT_OPTS] ?? '';
        $appLongOpts  = $options[self::OPTION_APP_LONG_OPTS] ?? [];

        $shortOpts  = $appShortOpts . self::OPT_VERBOSITY;
        $longOpts   = \array_merge($appLongOpts, [self::OPT_PORCELAIN], Logger::getAvailableFields());
        $this->opts = \getopt($shortOpts, $longOpts);
    }

    /**
     * @param string $name
     * @return mixed
     */
    private function getOpt($name)
    {
        return $this->opts[$name] ?? null;
    }


    /**
     * @param string $attribute
     * @return string|null
     * @throws \InvalidArgumentException If passed an unknown process attribute
     * @throws \UnexpectedValueException If no hard limit known for process attribute
     */
    public function getHardLimit(string $attribute): ?string
    {
        if (!\array_key_exists($attribute, $this->limits)) {
            throw new \InvalidArgumentException("Unknown process attribute: '{$attribute}'");
        }
        if (!\array_key_exists('hard', $this->limits[$attribute])) {
            throw new \UnexpectedValueException("No known 'hard' limit for: '{$attribute}'");
        }
        return $this->limits[$attribute]['hard'];
    }


    /**
     * @param string $attribute
     * @return string|null
     * @throws \InvalidArgumentException If passed an unknown process attribute
     * @throws \UnexpectedValueException If no soft limit known for process attribute
     */
    public function getSoftLimit(string $attribute): ?string
    {
        if (!\array_key_exists($attribute, $this->limits)) {
            throw new \InvalidArgumentException("Unknown process attribute: '{$attribute}'");
        }
        if (!\array_key_exists('soft', $this->limits[$attribute])) {
            throw new \UnexpectedValueException("No known 'soft' limit for: '{$attribute}'");
        }
        return $this->limits[$attribute]['soft'];
    }


    /**
     * Go. Do stuff.
     *
     * @param callable $callable
     * @throws Exception
     * @throws \Schoolbox\External\Exception
     */
    public function run($callable = null): void
    {
        if (posix_isatty(STDOUT)) {
            echo <<<EOL

 ||
 || Schoolbox {$this->name} Daemon
 ||


EOL;
        }

        // not much point going on if we've got nothing to do
        if (!\is_callable($callable)) {
            $this->logger()->critical("No daemon to launch, aborting");
            return;
        }

        try {
            global $argv;
            $flags = \implode(' ', \array_slice($argv, 1));
            $callable($this->di, $this->loop, $this->logger(), $this->opts, $flags);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), ['exception'=>$e]);
            throw $e;
        } catch (Exception $e) {
            $this->logger()->critical("{$this->name} daemon failed to launch: " . $e->getMessage(), ['exception'=>$e]);
            throw $e;
        }

        try {
            $this->loop->run();
            $this->logger()->notice($this->name . " daemon loop ended; preparing to terminate");
        } catch (PDOException $e) {
            $this->logger()->emergency($e->getMessage(), ['exception'=>$e]);
        } catch (\Schoolbox\External\Exception $e) {
            $this->logger()->emergency($e->getMessage(), ['exception'=>$e]);
        } catch (Exception $e) {
            $this->logger()->critical(sprintf("%s daemon received %s: %s", $this->name, \get_class($e),
                $e->getMessage()), ['exception'=>$e]);
        }
    }


    /**
     * Handle specific kinds of warnings differently
     *
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @throws ErrorException
     * @throws IOException
     * @throws PDOException
     * @throws RangeException
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function onWarning($errNum, $errStr, $errFile, $errLine): void
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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function onException($e): void
    {
        $this->callExceptionCallbacks($e);
        try {
            $this->logger()->alert('FATAL ' . \get_class($e) . ': ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile(), ['exception'=>$e]);
        } catch (IOException $e) {
            // can't do anything, STDOUT has been closed
        }
        exit;
    }


    /**
     * Check if it was a fatal error that shut us down, and if it was, advise as such
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function onShutdown(): void
    {
        $error = error_get_last();

        // only report if the last error was one of these
        $cases = [
            E_ERROR         => 'Fatal',
            E_CORE_ERROR    => 'Core',
            E_USER_ERROR    => 'Fatal user',
            E_COMPILE_ERROR => 'Compilation',
        ];

        if (\is_callable($this->shutdown)) {
            \call_user_func($this->shutdown);
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
    private function getLoggingLevel(): int
    {
        $v = $this->getOpt(self::OPT_VERBOSITY);

        $threshold = 0;
        if ($v !== null) {
            $threshold = 1;
            if (\is_array($v)) {
                $threshold = min(3, \count($v));
            }
        }

        $limits = [
            0 => Monolog\Logger::CRITICAL, // nil verbosity: exceptional behaviour only
            1 => Monolog\Logger::NOTICE,   // min verbosity: significant events
            2 => Monolog\Logger::INFO,     // mod verbosity: most things
            3 => Monolog\Logger::DEBUG     // max verbosity: EV-ER-Y-THING...
        ];
        return $limits[$threshold];
    }


    /**
     * @return LoggerInterface
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function createDefaultLogger(): LoggerInterface
    {
        // Derive log verbosity
        $level = $this->logLevel ?? $this->getLoggingLevel();

        // Derive the list of fields to prepend to log items
        $fields = Logger::getAvailableFields();
        foreach ($fields as $key => $field) {
            if (!isset($this->opts[$field])) {
                unset($fields[$key]);
            }
        }

        $porcelain = \array_key_exists(self::OPT_PORCELAIN, $this->opts);
        $logger = new Logger(STDOUT, STDERR, $level, $fields, $porcelain);

        $this->addRavenHandler($logger);

        return $logger;
    }

    /**
     * @inheritdoc
     * @throws WrongSapiException
     */
    protected function checkServerApiEnvironment(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new WrongSapiException('Cannot use Daemon Kernel in Web Server environment.');
        }
    }


    /**
     * Install any internal dependencies into the kernel (routing, etc.)
     *
     * @return void
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     */
    protected function installDependencies(): void
    {
        parent::installDependencies();

        $di = $this->getDependencyInjector();

        $di->set('stomp.factory', $di->lazyNew(
            Stomp\Factory::class, [
            'loop' => $di->lazyValue('react.loop')
        ]));

        $di->set('stomp', $di->lazy(function () use ($di) {
            $conf = $di->get('config');

            return $di->get('stomp.factory')->createClient(
                [
                    'host'     => $conf->get('amqp_host'),
                    'port'     => $conf->get('amqp_stomp_port'),
                    'vhost'    => $conf->get('amqp_vhost'),
                    'login'    => $conf->get('amqp_user'),
                    'passcode' => $conf->get('amqp_pass'),
                ]
            );
        }));

        $di->values['logger'] = $this->logger();
    }
}
