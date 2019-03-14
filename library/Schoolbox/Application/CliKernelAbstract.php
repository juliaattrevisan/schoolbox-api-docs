<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application;

use Database;
use Alaress\Exception\IO\IOException;
use ErrorException;
use Monolog\Handler\RavenHandler;
use PDOException;
//use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RangeException;
use Raven_Client;
use RuntimeException;
use Monolog;
use Alaress\Lumberjack;

/**
 * Common implementation for all Cli Kernels
 * @todo develop version should use the interfaced method. But that requires SA-8248
 */
//abstract class CliKernelAbstract extends \Alaress\Application\Kernel implements \Psr\Log\LoggerAwareInterface
abstract class CliKernelAbstract extends \Alaress\Application\Kernel
{

    // Exit Statuses.
    // Apps may use custom exit statuses so long as they don't collide with these, say starting at 100
    public const EXIT_STATUS_OK           = 0;   // The requested action was successfully performed.
    public const EXIT_STATUS_KERNEL_ERROR = 1;   // Problem in kernel, may have been caused by App
    public const EXIT_STATUS_APP_ERROR    = 2;   // Problem in App

    /** @var LoggerInterface */
    private $logger;


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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function onException($e): void
    {
        $this->callExceptionCallbacks($e);
        try {
            $class = \get_class($e);
            $this->logger()->alert("FATAL $class: {$e->getMessage()} on line {$e->getLine()} in {$e->getFile()}", ['exception' => $e]);
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (IOException $e) {
            // can't do anything, logging stream has been closed
        }
        exit(self::EXIT_STATUS_KERNEL_ERROR);
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
            exit(self::EXIT_STATUS_OK);
        }

        // report
        $message = sprintf('%s error: %s in %s:%s', $cases[$error['type']], $error['message'], $error['file'], $error['line']);
        try {
            $this->logger()->alert($message);
        } /** @noinspection PhpRedundantCatchClauseInspection */
        catch (IOException $e) {
            // can't do anything, logging stream has been closed
        }

        exit(self::EXIT_STATUS_KERNEL_ERROR);
    }


    /**
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @return bool
     * @throws ErrorException
     * @throws IOException
     * @throws PDOException
     * @throws RangeException
     * @throws RuntimeException
     * @throws \Aura\Di\Exception\ServiceNotFound
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
                $this->logger()->warning("Warning: $errStr in $errFile:$errLine");
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
     * Creates a default logger, suitable for this Kernel
     *
     * @return LoggerInterface
     */
    abstract protected function createDefaultLogger(): LoggerInterface;


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
     */
    protected function registerShutdownHandling(): void
    {
        \register_shutdown_function([$this, 'onShutdown']);
    }


    /**
     * @inheritdoc
     */
    protected function initialiseDebugging(): void
    {
        // no debugging for CLI Kernels
    }


    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     * @throws \Schoolbox\External\Exception
     */
    protected function initialiseExternalConnections(): void
    {
        // MySQL connection configuration
        Database::getInstance()->connect(SQL_dsn, SQL_user, SQL_passwd, ['throw' => true]);
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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    abstract protected function onWarning($errNum, $errStr, $errFile, $errLine): void;


    /**
     * Add Sentry handler into logging system if possible
     *
     * Returns whether the Sentry handler is added
     *
     * @param Lumberjack\Logger $logger
     * @return bool
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    protected function addRavenHandler(Lumberjack\Logger $logger): bool
    {
        if (!$this->getDependencyInjector()->has('sentry')) {
            // If Sentry is not available, don't attempt to log to it
            return false;
        }

        /** @var Raven_Client $ravenClient */
        $ravenClient  = $this->getDependencyInjector()->get('sentry');
        $ravenHandler = new RavenHandler($ravenClient, Monolog\Logger::CRITICAL);
        /** @var Monolog\Formatter\LineFormatter $formatter */
        $formatter = $ravenHandler->getFormatter();
        $formatter->includeStacktraces(true);
        $logger->pushHandler($ravenHandler);
        return true;
    }
}
