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
     * @param array $payload
     * @return array The response from the provider.
     */
    public function request(array $payload): array;
}
