<?php
/**
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Application\CliTool;

use Aura\Di;
use Psr\Log\LoggerInterface;

/**
 * An App is a job/task/application that can be run by the CLI Kernel
 */
abstract class App
{

    /**
     * @param Di\Container    $di
     * @param resource        $outStream
     * @param LoggerInterface $logger
     * @param string[]        $appOptions
     */
    abstract public function run(Di\Container $di, $outStream, LoggerInterface $logger, array $appOptions): void;


    /**
     * Helper to fetch a CLI option value.
     *
     * Supports options that are still have colon chars suffixed to indicate required/optional status
     * eg 'c', 'c:', 'c::' will all resolve to 'c'
     *
     * @param string $optName
     * @param array  $appOptions
     * @param mixed  $default
     * @return mixed
     */
    protected function getOpt(string $optName, array $appOptions, $default = null)
    {
        $trimmed = \rtrim($optName, ':');
        return \array_key_exists($trimmed, $appOptions) ? $appOptions[$trimmed] : $default;
    }
}
