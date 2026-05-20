<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

/**
 * Null client for the tools daemon: fulfils the client interface, but does
 * nothing useful.
 */
class NullClient implements IClient
{
    /**
     * @inheritdoc
     */
    public function run(JobRequest $request): string
    {
        throw new ClientException('Tools daemon null client is unable to run jobs');
    }

    /**
     * @inheritdoc
     */
    public function getJobStatus(string $jobId): JobStatus
    {
        return new JobStatus($jobId, State::UNKNOWN);
    }

    /**
     * @inheritdoc
     */
    public function getJobOutput(string $jobId): JobOutput
    {
        return new JobOutput($jobId, [], []);
    }
}
