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
     * @param string                     $tool        The tool to run: must match the name of a tool defined in the
     *                                                tools daemon's configuration file.
     * @param array<string>              $options     A list of options to pass to the tool: these will be passed as
     *                                                additional command-line parameters to the tool.
     * @param string|null                $instanceId  If provided: run the tool in the context of this instance.
     *                                                A tool's definition will either mandate an instance ID, or
     *                                                prohibit one.
     * @param array{string, string}|null $environment Any environment variables to pass to the tool.
     *                                                A tool's definition will define its required and allowed
     *                                                environment variables.
     * @return string The job ID received from the tools daemon: this may then be used to fetch the job's completion
     *                status and its output.
     * @throws ClientException if request was rejected, not received, etc.
     */
    public function run(string $tool, array $options, ?string $instanceId = null, ?array $environment = null): string;

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
