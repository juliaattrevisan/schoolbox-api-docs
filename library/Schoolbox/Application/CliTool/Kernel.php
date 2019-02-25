<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use ErrorException;
use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;

/**
 * This Kernel is designed for running CLI scripts/tools that are one-offs, for example:
 * * migration/update scripts
 * * jobs/tasks
 *
 * By default, it will write output to STDOUT, and append logs to STDERR
 */
class Kernel extends CliKernelAbstract
{

    /**
     * Handle specific kinds of warnings differently
     *
     * @param int    $errNum
     * @param string $errStr
     * @param string $errFile
     * @param int    $errLine
     * @throws ErrorException
     */
    protected function onWarning($errNum, $errStr, $errFile, $errLine): void
    {
        throw new ErrorException($errStr, 0, $errNum, $errFile, $errLine);
    }


    /**
     * Creates a default logger, suitable for this Kernel
     *
     * @return LoggerInterface
     */
    protected function createDefaultLogger(): LoggerInterface
    {
        // TODO: Implement createDefaultLogger() method.
        return null;
    }
}
