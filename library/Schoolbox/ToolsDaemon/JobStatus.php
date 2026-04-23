<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

/**
 * Struct for a job's status, as returned by `IClient::getJobStatus`.
 */
readonly class JobStatus
{
    /**
     * Constructor
     *
     * @param string      $jobId
     * @param State       $state
     * @param string|null $result
     * @param string|null $error
     */
    public function __construct(
        public string $jobId,
        public State $state,
        public ?string $result = null,
        public ?string $error = null,
    ) {
    }
}
