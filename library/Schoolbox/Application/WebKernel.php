<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application;

use \Database;
use Schoolbox\Application\WebRequest\ShutdownHandler;

/**
 * Common ancestor for all Web Requests
 */
abstract class WebKernel extends \Alaress\Application\Kernel
{
    /**
     * @inheritdoc
     * @throws \Schoolbox\External\Exception
     */
    protected function initialiseExternalConnections(): void
    {
        $database = Database::getInstance();
        // Catch-22:
        // I don't know if debug is enabled until I connect to the database.
        // The only solution is to allow the config query to run and then throw
        // away my connection, and create a fresh one with debug enabled.
        $database->connect(SQL_dsn, SQL_user, SQL_passwd);
    }

    /**
     * @inheritdoc
     */
    protected function registerErrorHandling(): void
    {
        // noop. WebRequest does not use custom error handling
        set_error_handler(function ($code, $message, $file, $line) {
            $this->shutdownSafely([
                'type' => $code,
                'message' => $message,
                'file' => $file,
                'line' => $line
            ], debug_backtrace());
            return true;
        }, E_USER_ERROR | E_ERROR | E_CORE_ERROR | E_RECOVERABLE_ERROR);
    }

    /**
     * @inheritdoc
     */
    protected function registerExceptionHandling(): void
    {
        set_exception_handler([$this, 'shutdownSafely']);
    }

    /**
     * @inheritdoc
     */
    protected function registerShutdownHandling(): void {
        ShutdownHandler::instance()->registerFunction([$this, 'shutdownSafely']);
    }
}
