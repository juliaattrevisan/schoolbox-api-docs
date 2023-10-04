<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request\HttpRequest;

use Psr\Http\Message\RequestInterface;
use Schoolbox\TaskDaemon\Request\BaseMessageFactory;

/**
 * Message factory for building a task which performs HTTP requests.
 */
class MessageFactory extends BaseMessageFactory
{
    /**
     * The identifier for this task.
     */
    private const TASK = 'http-request';

    /**
     * The request to send to the task daemon.
     *
     * Only some properties will be sent:
     * * method
     * * URI
     * * body
     * * headers
     */
    private RequestInterface $request;

    /**
     * @inheritdoc
     */
    protected function getTaskType(): string
    {
        return self::TASK;
    }

    /**
     * Set the request to send to the task daemon.
     *
     * @param RequestInterface $request
     * @return static
     */
    public function setRequest(RequestInterface $request): static
    {
        $clone          = clone $this;
        $clone->request = $request;
        return $clone;
    }

    /**
     * @inheritdoc
     */
    protected function buildParams(): array
    {
        return [
            'method'  => $this->request->getMethod(),
            'uri'     => $this->request->getUri()->__toString(),
            'headers' => $this->request->getHeaders(),
            'body'    => $this->request->getBody()->getContents(),
        ];
    }
}
