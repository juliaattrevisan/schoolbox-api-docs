<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

/**
 * Interface for tools daemon clients.
 *
 * The tools daemon is able to receive requests to run a command-line tool from
 * a pre-configured list, with a pre-defined whitelist of options.
 */
interface IClient
{
    /**
     * Send a request to run a tool.
     *
     * @param JobRequest $request A struct encapsulating the parameters of the job to run.
     * @return string The job ID received from the tools daemon: this may then be used to fetch the job's completion
     *                status and its output.
     * @throws ClientException if request was rejected, not received, etc.
     */
    public function run(JobRequest $request): string;

    /**
     * Fetch the status of the job, identified by `$jobId`.
     *
     * @param string $jobId
     * @return JobStatus
     * @throws ClientException if request was rejected
     */
    public function getJobStatus(string $jobId): JobStatus;

    /**
     * Fetch the output of the job, identified by `$jobId`.
     *
     * @param string $jobId
     * @return JobOutput
     * @throws ClientException if request was rejected
     */
    public function getJobOutput(string $jobId): JobOutput;
}
