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

    /** @var Di\Container */
    private $di;

    /** @var resource */
    private $outStream;

    /** @var LoggerInterface */
    private $logger;


    /**
     * Common app setup implementation
     *
     * @param Di\Container    $di
     * @param resource        $outStream
     * @param LoggerInterface $logger
     */
    public function setup(Di\Container $di, $outStream, LoggerInterface $logger): void
    {
        $this->di        = $di;
        $this->outStream = $outStream;
        $this->logger    = $logger;
    }


    /**
     * @param string[] $appOptions
     */
    abstract public function run(array $appOptions): void;


    /**
     * Helper to fetch a CLI option value.
     *
     * Supports options that are still have colon chars suffixed to indicate required/optional status
     * eg 'c', 'c:', 'c::' will all resolve to 'c'
     *
     * @see getopt()
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


    /**
     * @return Di\Container
     */
    protected function getDi(): Di\Container
    {
        return $this->di;
    }


    /**
     * @return resource
     */
    protected function getOutStream()
    {
        return $this->outStream;
    }


    /**
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
