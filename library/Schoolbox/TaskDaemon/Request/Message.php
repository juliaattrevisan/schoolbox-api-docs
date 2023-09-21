<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use DateTimeInterface;
use SensitiveParameter;

/**
 * A strongly-typed representation of a message to be sent to the task daemon.
 */
class Message
{
    /**
     * Constructor
     *
     * @param string            $task                  The name of the task to run.
     * @param array             $params                Parameters for the task to run.
     * @param DateTimeInterface $timestamp             The time at which the task was sent to the task daemon.
     * @param string            $instanceId            The ID of the Schoolbox instance.
     * @param string            $hostName              The host name of the Schoolbox instance.
     *                                                 Along with `$domain` forms a fully-qualified domain name for the
     *                                                 Schoolbox instance: `${hostname}.${domain}`.
     * @param string            $domain                The domain name on which the Schoolbox instance is hosted.
     * @param string $version                          The version of the Schoolbox platform which the instance is
     *                                                 running: required because the task daemon may need to run a task
     *                                                 differently depending on Schoolbox version.
     * @param string            $type                  The type of instance this is:
     *                                                 * prod: Production instance (currently contains live data)
     *                                                 * stg: Staging instance (no live data: used for testing by
     *                                                   customers and Schoolbox staff)
     *                                                 * dev: Development instance (former type for staging instances:
     *                                                   should be obsolete)
     *                                                 * trial: Trial instance (for evaluation by prospect customers)
     * @param string            $schoolCode            A unique human-readable identifier for the organisation which
     *                                                 owns the Schoolbox instance.
     *                                                 This will be the same for all of an organisation's instances.
     * @param string|null       $callbackUrl           If provided: the task daemon will call this URL after the task
     *                                                 has completed.
     * @param array|null        $callbackParams        If provided: the task daemon will use these params as the body of
     *                                                 the request to `$callbackUrl`, in JSON-encoded format.
     * @param string|null       $callbackToken         If provided: the task daemon will use this as an authorization
     *                                                 token when calling `$callbackUrl`.
     * @param string|null       $failureCallbackUrl    If provided: the task daemon will call this URL after the task
     *                                                 has failed.
     * @param array|null        $failureCallbackParams If provided: the task daemon will use these params as the body of
     *                                                 the request to `$failureCallbackUrl`, in JSON-encoded format.
     * @param string|null       $failureCallbackToken  If provided: the task daemon will use this as an authorization
     *                                                 token when calling `$failureCallbackUrl`.
     */
    public function __construct(
        private string $task,
        private array $params,
        private DateTimeInterface $timestamp,
        private string $instanceId,
        private string $hostName,
        private string $domain,
        private string $version,
        private string $type,
        private string $schoolCode,
        private ?string $callbackUrl,
        private ?array $callbackParams,
        #[SensitiveParameter] private ?string $callbackToken,
        private ?string $failureCallbackUrl,
        private ?array $failureCallbackParams,
        #[SensitiveParameter] private ?string $failureCallbackToken,
    ) {
    }

    /**
     * @return string
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * @return string
     */
    public function getHostName(): string
    {
        return $this->hostName;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getSchoolCode(): string
    {
        return $this->schoolCode;
    }

    /**
     * @return string
     */
    public function getTask(): string
    {
        return $this->task;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string|null
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    /**
     * @return array|null
     */
    public function getCallbackParams(): ?array
    {
        return $this->callbackParams;
    }

    /**
     * @return string|null
     */
    public function getCallbackToken(): ?string
    {
        return $this->callbackToken;
    }

    /**
     * @return string|null
     */
    public function getFailureCallbackUrl(): ?string
    {
        return $this->failureCallbackUrl;
    }

    /**
     * @return array|null
     */
    public function getFailureCallbackParams(): ?array
    {
        return $this->failureCallbackParams;
    }

    /**
     * @return string|null
     */
    public function getFailureCallbackToken(): ?string
    {
        return $this->failureCallbackToken;
    }

    /**
     * @return DateTimeInterface
     */
    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }

    /**
     * Helper method for retrieving the fully-qualified domain name.
     *
     * @return string
     */
    public function getFullyQualifiedDomainName(): string
    {
        return $this->hostName . '.' . $this->domain;
    }
}
