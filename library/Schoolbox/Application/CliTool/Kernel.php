<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Schoolbox\Application\CliKernelAbstract;

class Kernel extends CliKernelAbstract
{

    /**
     * Initialise connections to databases, etc.
     *
     * @return void
     */
    protected function initialiseExternalConnections(): void
    {
        // TODO: Implement initialiseExternalConnections() method.
    }


    /**
     * Initialise debugging
     *
     * @return void
     */
    protected function initialiseDebugging(): void
    {
        // TODO: Implement initialiseDebugging() method.
    }


    /**
     * Register any error handling required
     *
     * @return void
     */
    protected function registerErrorHandling(): void
    {
        // TODO: Implement registerErrorHandling() method.
    }


    /**
     * Register any out-the-top exception handling required
     *
     * @return void
     */
    protected function registerExceptionHandling(): void
    {
        // TODO: Implement registerExceptionHandling() method.
    }


    /**
     * Register any shutdown handling required
     *
     * @return void
     */
    protected function registerShutdownHandling(): void
    {
        // TODO: Implement registerShutdownHandling() method.
    }
}
