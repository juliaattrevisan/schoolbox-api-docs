<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

/**
 * Struct for a job's output, as returned by `IClient::getJobOutput`.
 */
readonly class JobOutput
{
    /**
     * Constructor
     *
     * @param string $jobId
     * @param array  $stdout
     * @param array  $stderr
     */
    public function __construct(
        public string $jobId,
        public array $stdout,
        public array $stderr,
    ) {
    }
}
