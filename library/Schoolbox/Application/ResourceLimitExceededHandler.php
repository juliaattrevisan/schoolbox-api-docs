<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Application;

/**
 * Consistently handles case when 'max_execution_time' or 'memory_limit' exceeded
 */
class ResourceLimitExceededHandler
{
    /**
     * Function to call if limit exceeded
     *
     * @var callable
     */
    private $function;

    public function __construct(callable $function)
    {
        $this->function = $function;
    }

    /**
     * Expected to be invoked on shutdown
     *
     * @see register_shutdown_function()
     */
    public function __invoke(): void
    {
        $error = error_get_last();
        // Request exceeded 'max_execution_time'
        if (preg_match("/Maximum execution time of \d+ seconds? exceeded/", $error['message'] ?? '')) {
            call_user_func($this->function);
        }
        // Request exceeded 'memory_limit'
        if (preg_match("/Allowed memory size of \d+ bytes? exhausted/", $error['message'] ?? '')) {
            // Extra 10M over current (not max) memory usage for handling
            ini_set('memory_limit', (string)(memory_get_usage() + 10 * 1024 * 1024));
            call_user_func($this->function);
        }
    }
}
