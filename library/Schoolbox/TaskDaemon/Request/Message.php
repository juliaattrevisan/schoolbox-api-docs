<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request;

use Alaress_Date_Format;
use Schoolbox\Instance;

/**
 * A strongly-typed representation of a message to be sent to the task daemon.
 */
class Message
{
    /**
     * Information about the Schoolbox instance.
     */
    private Instance $instance;

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
     * If provided: the task daemon will use this as an authorization token
     * when calling `$callbackUrl`.
     */
    private ?string $callbackToken;

    /**
     * The time at which the task was sent to the task daemon.
     */
    private Alaress_Date_Format $timestamp;

    /**
     * Constructor
     *
     * @param string              $task
     * @param array               $params
     * @param Alaress_Date_Format $timestamp
     * @param Instance            $instance
     * @param string|null         $callbackUrl
     * @param string|null         $callbackToken
     */
    public function __construct(
        string $task,
        array $params,
        Alaress_Date_Format $timestamp,
        Instance $instance,
        ?string $callbackUrl,
        ?string $callbackToken
    ) {
        $this->instance      = $instance;
        $this->task          = $task;
        $this->params        = $params;
        $this->callbackUrl   = $callbackUrl;
        $this->callbackToken = $callbackToken;
        $this->timestamp     = $timestamp;
    }

    /**
     * @return Instance
     */
    public function getInstance(): Instance
    {
        return $this->instance;
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
     * @return string|null
     */
    public function getCallbackToken(): ?string
    {
        return $this->callbackToken;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getTimestamp(): Alaress_Date_Format
    {
        return $this->timestamp;
    }
}
