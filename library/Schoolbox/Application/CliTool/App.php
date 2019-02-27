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
     * Whether this App should persist changes, as opposed to just simulating them
     *
     * @var bool
     */
    private $perform;


    /**
     * Common app setup implementation
     *
     * @param Di\Container    $di
     * @param resource        $outStream
     * @param LoggerInterface $logger
     * @param bool            $perform
     */
    public function setup(Di\Container $di, $outStream, LoggerInterface $logger, bool $perform): void
    {
        $this->di        = $di;
        $this->outStream = $outStream;
        $this->logger    = $logger;
        $this->perform   = $perform;
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
     * If option DOES accept values:
     *     returns that value, or NULL if not specified
     * If option does NOT accept values:
     *     if a long option:
     *         returns a bool indicating whether the option was specified at least once.
     *     else a short option
     *         returns true if specified once, or array if specified multiple times
     *
     * @param string $name
     * @param array  $appOptions
     * @return mixed
     */
    protected function getOpt(string $name, array $appOptions)
    {
        // Does this option accept values?
        if (\preg_match('/:$/', $name)) {
            // return the value
            return $appOptions[\rtrim($name, ':')] ?? null;
        }

        // Does not accept values, and not specified
        if(!\array_key_exists($name, $appOptions)){
            return false;
        }

        // Does not accept values, was specified at least once.
        return \is_array($appOptions[$name]) ? $appOptions[$name] : true;
    }

    /**
     * Returns a kernel's command line option's value
     *
     * If option does accept values:
     *     returns that value, or NULL if not specified
     * If option does not accept values:
     *     if a long option:
     *         returns a bool indicating whether the option was specified at least once.
     *     else a short option
     *         returns true if specified once, or array if specified multiple times
     *
     * @param string $name Must have colon chars required/optional decoration still appended.
     * @return mixed
     */
    private function getKernelOpt(string $name)
    {

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

    /**
     * @return bool
     */
    protected function perform(): bool
    {
        return $this->perform;
    }
}
