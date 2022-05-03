<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use DateTimeInterface;

/**
 * A strongly-typed representation of a message to be sent to the task daemon.
 */
class Message
{
    /**
     * The ID of the Schoolbox instance.
     */
    private string $instanceId;

    /**
     * The host name of the Schoolbox instance.
     *
     * Along with `$domain` forms a fully-qualified domain name for the
     * Schoolbox instance: `${hostname}.${domain}`.
     */
    private string $hostName;

    /**
     * The domain name on which the Schoolbox instance is hosted.
     *
     * Along with `$hostname` forms a fully-qualified domain name for the
     * Schoolbox instance: `${hostname}.${domain}`.
     */
    private string $domain;

    /**
     * The version of the Schoolbox platform which the instance is running.
     *
     * Required because the task daemon may need to run a task differently
     * depending on Schoolbox version.
     */
    private string $version;

    /**
     * The type of instance this is:
     * * prod: Production instance (currently contains live data)
     * * stg: Staging instance (no live data: used for testing by customers
     *   and Schoolbox staff)
     * * dev: Development instance (former type for staging instances: should
     *   be obsolete)
     * * trial: Trial instance (for evaluation use by prospect customers)
     */
    private string $type;

    /**
     * A unique human-readable identifier for the organisation which owns the
     * Schoolbox instance.
     *
     * This will be the same for all of an organisation's instances.
     */
    private string $schoolCode;

    /**
     * The name of the task to run.
     */
    private string $task;

    /**
     * Parameters for the task to run.
     */
    private array $params;

    /**
     * If provided: the task daemon will call this URL after the task has
     * completed.
     */
    private ?string $callbackUrl;

    /**
     * If provided: the task daemon will use these params as the body of the
     * request to `$callbackUrl`.
     *
     * Currently, these params are always passed as JSON.
     */
    private ?array $callbackParams;

    /**
     * If provided: the task daemon will use this as an authorization token
     * when calling `$callbackUrl`.
     */
    private ?string $callbackToken;

    /**
     * The time at which the task was sent to the task daemon.
     */
    private DateTimeInterface $timestamp;

    /**
     * Constructor
     *
     * @param string            $task
     * @param array             $params
     * @param DateTimeInterface $timestamp
     * @param string            $instanceId
     * @param string            $hostName
     * @param string            $domain
     * @param string            $version
     * @param string            $type
     * @param string            $schoolCode
     * @param string|null       $callbackUrl
     * @param array|null        $callbackParams
     * @param string|null       $callbackToken
     */
    public function __construct(
        string $task,
        array $params,
        DateTimeInterface $timestamp,
        string $instanceId,
        string $hostName,
        string $domain,
        string $version,
        string $type,
        string $schoolCode,
        ?string $callbackUrl,
        ?array $callbackParams,
        ?string $callbackToken
    ) {
        $this->task           = $task;
        $this->params         = $params;
        $this->timestamp      = $timestamp;
        $this->instanceId     = $instanceId;
        $this->hostName       = $hostName;
        $this->domain         = $domain;
        $this->version        = $version;
        $this->type           = $type;
        $this->schoolCode     = $schoolCode;
        $this->callbackUrl    = $callbackUrl;
        $this->callbackParams = $callbackParams;
        $this->callbackToken  = $callbackToken;
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
     * @return DateTimeInterface
     */
    public function getTimestamp(): DateTimeInterface
    {
        return $this->timestamp;
    }
}
