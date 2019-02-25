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
interface App
{

    /**
     * @param Di\Container    $di
     * @param LoggerInterface $logger
     * @param string[]        $appOptions
     */
    public function run(Di\Container $di, LoggerInterface $logger, array $appOptions): void;
}
