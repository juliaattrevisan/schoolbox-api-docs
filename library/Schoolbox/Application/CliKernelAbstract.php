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
use PDOException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use RangeException;
use RuntimeException;

/**
 * Common implementation for all Cli Kernels
 */
abstract class CliKernelAbstract extends \Alaress\Application\Kernel implements LoggerAwareInterface
{

    /** @var LoggerInterface */
    private $logger;


    /**
     * Returns this kernel's logger, creating a default logger if not already set
     *
     * @return LoggerInterface
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function logger(): LoggerInterface
    {
        if (!$this->logger instanceof LoggerInterface) {
            $this->logger = $this->createDefaultLogger();
        }

        return $this->logger();
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
     * Creates a default logger, suitable for this Kernel
     *
     * @return LoggerInterface
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    abstract protected function createDefaultLogger(): LoggerInterface;


    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
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
}
