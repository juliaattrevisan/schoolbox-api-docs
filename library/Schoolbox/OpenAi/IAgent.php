<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

/**
 * Interface for AI agents.
 *
 * Agents are expected to wrap around an `IClient` instance, and augment any
 * requests made to that client with:
 * * instructions
 * * history, whether via a list of messages or a session ID
 * * any other context which may be relevant to the agent's task
 *
 * Implementations of this interface are expected to map to a task with a set
 * goal, as opposed to the `IClient` implementation which maps to an endpoint at
 * an AI provider.
 */
interface IAgent
{
    /**
     * Send a request to the AI provider via its underlying client.
     *
     * @param array $payload The payload to be sent.
     *                       This payload should be as simple as possible:
     *                       implementations of this class should be able to
     *                       augment the payload with further instructions and
     *                       context before sending to the AI provider.
     * @return array The response from the provider, after having been extracted
     *               from the body.
     */
    public function request(array $payload): array;

    /**
     * Same as `request`, but instead indicates that the response is to be
     * streamed as it is received and parsed.
     *
     * @param array $payload
     * @return iterable
     */
    public function requestStream(array $payload): iterable;
}
