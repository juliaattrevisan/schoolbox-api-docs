<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application;

use Aura\Di\Container;
use Alaress\Exception\IO\IOException;
use ErrorException;
use PDOException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RangeException;
use RuntimeException;
use Monolog;
use Alaress\Lumberjack;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Deprecation\Registry;
use Sentry\Monolog\Handler as SentryHandler;
use Sentry\State\HubInterface;

/**
 * Common implementation for all Cli Kernels
 */
abstract class CliKernelAbstract extends \Alaress\Application\Kernel implements LoggerAwareInterface
{
    // Exit Statuses.
    // Apps may use custom exit statuses so long as they don't collide with these, say starting at 100
    public const EXIT_STATUS_OK           = 0;   // The requested action was successfully performed.
    public const EXIT_STATUS_KERNEL_ERROR = 1;   // Problem in kernel, may have been caused by App
    public const EXIT_STATUS_APP_ERROR    = 2;   // Problem in App

    // Options keys for $options passed to this Kernel's constructor
    public const OPTION_ERRORS    = 'errors';  // What level of PHP errors should be reported?
    public const OPTION_LEVEL     = 'level';   // If specified, kernel will not log at a lower verbosity than this.

    // Command line options common to all CLI Kernels
    public const OPT_LONG_PORCELAIN      = 'porcelain'; // Should log items be formatted for text file rather than TTL?
                                                        // If set, causes field name to be prefixed to field value to enable logs to be parsable by scripts
                                                        // eg '[!][2019-02-26 00:29:22.72907][13746]' would become '[!][@550][2019-02-26 00:29:22.72907][pid:13746]'
    public const OPT_SHORT_VERBOSITY     = 'v';         // What level of verbosity should be logged

    /** @var LoggerInterface */
    private $logger;


    /**
     * If set, don't log at less than this verbosity/level
     *
     * @var null|int
     */
    private $minLogLevel;


    /**
     * @param int|null $minLogLevel
     * @throws RuntimeException
     * @throws \Aura\Di\Exception
     * @throws \Aura\Di\Exception\ContainerLocked
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Di\Exception\ServiceNotObject
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\External\Exception
     */
    public function __construct(?int $minLogLevel, bool $bypassMaintenanceModeCheck = false)
    {
        $this->minLogLevel = $minLogLevel;
        parent::__construct($bypassMaintenanceModeCheck);
    }


    /**
     * @param ConfigLoader $config
     * @return void
     */
    protected static function dieGracefullyIfInMaintenanceMode(ConfigLoader $config): void
    {
        if ($config->get(ConfigMapper::NAME_MAINTENANCE_MODE)) {
            printf(
                "Exiting script \"%s\" because this instance is in maintenance mode.\n",
                str_replace(SITE_root, '', $_SERVER['SCRIPT_FILENAME']),
            );
            exit(self::EXIT_STATUS_APP_ERROR);
        }
    }


    /**
     * @inheritDoc
     * @throws \Throwable
     */
    protected function postInstallDependencies(bool $bypassMaintenanceModeCheck = false): void
    {
        if (!$bypassMaintenanceModeCheck) {
            self::dieGracefullyIfInMaintenanceMode($this->di->get('config'));
        }

        $this->initRoutes();
        $this->initModules();
    }


    /**
     * Returns this kernel's logger, creating a default logger if not already set
     *
     * @return LoggerInterface
     */
    public function logger(): LoggerInterface
    {
        if (!$this->logger instanceof LoggerInterface) {
            $this->logger = $this->createDefaultLogger();
        }

        return $this->logger;
    }


    /**
     * @param \Exception $e
     */
    public function onException($e): void
    {
        $this->callExceptionCallbacks($e);
        try {
            $class = \get_class($e);
            $this->logger()->alert("FATAL $class: {$e->getMessage()} on line {$e->getLine()} in {$e->getFile()}", ['exception' => $e]);
            $this->logger()->error($e->getTraceAsString());
        } catch (IOException $e) {
            // can't do anything, logging stream has been closed
        }
        exit(self::EXIT_STATUS_KERNEL_ERROR);
    }


    /**
     * Check if it was a fatal error that shut us down, and if it was, advise as such
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

        // don't report anything
        // Return instead of `exit(0)`:
        // this shutdown could've been triggered by an `exit()` call, we want to
        // preserve its exit code, and a non-error will exit with 0 anyway.
        if (!isset($error['type'], $cases[$error['type']])) {
            return;
        }

        // report
        $message = sprintf('%s error: %s in %s:%s', $cases[$error['type']], $error['message'], $error['file'], $error['line']);
        try {
            $this->logger()->alert($message);
        } catch (IOException $e) {
            // can't do anything, logging stream has been closed
        }

        // I guess we should raise an exit code here?
        exit(self::EXIT_STATUS_KERNEL_ERROR);
    }


    /**
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @return bool
     * @throws ErrorException
     * @throws \Psr\Container\ContainerExceptionInterface|\Psr\Container\NotFoundExceptionInterface
     */
    public function onError($errNum, $errStr, $errFile, $errLine): ?bool
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
                // Deprecation notices:
                // silence (for now) if raised in a vendor library
                if (!Registry::silenceWarningsForPath($errFile)) {
                    $this->logger()->warning("Warning: $errStr in $errFile:$errLine");
                }
                return true;

            default:
                // we don't know how to deal with this, so let the stock error handler deal with it
                $this->logger()->error("Don't know how to handle $errStr");
                return false;
        }
    }


    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }


    /**
     * Check that all kernel options are valid
     *
     * @param array $options
     * @throws \InvalidArgumentException If unrecognised kernel options are injected
     */
    protected function validateOptions(array $options): void
    {
        $valid   = $this->getValidConstructionOptions();
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
     * Returns the set of construction options that are valid for the kernel
     *
     * @return string[]
     */
    protected function getValidConstructionOptions(): array
    {
        return [self::OPTION_LEVEL, self::OPTION_ERRORS];
    }


    /**
     * Returns the set of short command line opts recognised by this Kernel
     *
     * @return string[]
     */
    protected function getKernelShortOpts(): array
    {
        return [self::OPT_SHORT_VERBOSITY];
    }


    /**
     * Returns the set of long command line opts recognised by this Kernel
     *
     * @return string[]
     */
    protected function getKernelLongOpts(): array
    {
        return \array_merge(Lumberjack\Logger::getAvailableFields(), [self::OPT_LONG_PORCELAIN]);
    }


    /**
     * Creates a default logger, suitable for this Kernel
     *
     * @return LoggerInterface
     */
    abstract protected function createDefaultLogger(): LoggerInterface;


    /**
     * Returns the level to log at, selecting the most verbose of:
     * 1. command-line verbosity
     * 2. Kernel construction arg
     *
     * @param null|bool|array $v Value of command line -v option
     * @return int
     */
    protected function getLoggingLevel($v): int
    {
        $levels = [$this->getLoggingLevelFromVerbosity($v)];
        if ($this->minLogLevel) {
            $levels[] = $this->minLogLevel;
        }
        return \min($levels);
    }


    /**
     * Ensures this kernel is being run in appropriate environment.
     *
     * The different Kernels have implementation tied to their Server API environments.
     * eg, the Daemon kernel uses Process Control functions, whereas the WebServer kernel requires a request.
     *
     * @see http://php.net/manual/en/function.php-sapi-name.php
     * @throws \UnexpectedValueException If this Kernel has been constructed in an invalid environment.
     */
    protected function checkServerApiEnvironment(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new \UnexpectedValueException('Cannot use Cli Kernel in Web Server environment.');
        }
    }


    /**
     * Register any out-the-top exception handling required
     *
     * @return void
     */
    protected function registerExceptionHandling(): void
    {
        \set_exception_handler([$this, 'onException']);
    }


    /**
     * Register any shutdown handling required
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerShutdownHandling(): void
    {
        parent::registerShutdownHandling();
        $this->getShutdownHandler()->registerFunction([$this, 'onShutdown']);
    }


    /**
     * Register any error handling required
     *
     * @return void
     */
    protected function registerErrorHandling(): void
    {
        \set_error_handler([$this, 'onError']);
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
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    abstract protected function onWarning($errNum, $errStr, $errFile, $errLine): void;


    /**
     * Add Sentry handler into logging system if possible
     *
     * Returns whether the Sentry handler is added
     *
     * @param Lumberjack\Logger $logger
     * @return bool
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    protected function addSentryHandler(Lumberjack\Logger $logger): bool
    {
        /** @var HubInterface $sentryClient */
        $sentryClient  = $this->getDependencyInjector()->get('sentry');
        $sentryHandler = new SentryHandler($sentryClient, Monolog\Logger::CRITICAL);
        /** @var Monolog\Formatter\LineFormatter $formatter */
        $formatter = $sentryHandler->getFormatter();
        $formatter->includeStacktraces();
        $logger->pushHandler($sentryHandler);
        return true;
    }


    /** @inheritDoc */
    public static function installDependencies(Container $di): Container
    {
        parent::installDependencies($di);
        return $di;
    }

    /** @inheritDoc */
    protected static function getKnownUserClassesFromSession(Container $di): array
    {
        // No session in CLI context.
        return [];
    }


    /**
     * Determine logging level via verbose setting
     * verbose is set by -vvv.  Each v represents a value in an array.
     * So -v is level 1, -vv is level 2, -vvv is level 3
     *
     * @param null|bool|array $v
     * @return int
     */
    private function getLoggingLevelFromVerbosity($v): int
    {
        if (null === $v) {
            $threshold = 0;
        } elseif (!\is_array($v)) {
            $threshold = 1;
        } else {
            $threshold = \min(3, \count($v));
        }

        $limits = [
            0 => Monolog\Logger::ERROR,    // nil verbosity: exceptional behaviour only
            1 => Monolog\Logger::NOTICE,   // min verbosity: significant events
            2 => Monolog\Logger::INFO,     // mod verbosity: most things
            3 => Monolog\Logger::DEBUG,     // max verbosity: EV-ER-Y-THING...
        ];
        return $limits[$threshold];
    }
}
