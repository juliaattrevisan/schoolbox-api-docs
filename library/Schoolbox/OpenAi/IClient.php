<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface for AI clients.
 *
 * This encapsulates a single endpoint for an AI provider, e.g.:
 * * Chat completion:
 *   * OpenAI (https://platform.openai.com/docs/api-reference/chat/create)
 *   * Azure  (https://learn.microsoft.com/en-us/azure/ai-foundry/openai/reference-preview-latest#create-chatcompletion)
 * * Responses:
 *   * OpenAI (https://platform.openai.com/docs/api-reference/responses/create)
 *   * Azure  (https://learn.microsoft.com/en-us/azure/ai-foundry/openai/reference-preview-latest#create-response)
 *
 * Implementations of this interface are expected to map to a single AI provider
 * endpoint, instead of to a task with a set goal: that is the purpose of the
 * `IAgent` interface.
 */
interface IClient
{
    /**
     * Send a request to the configured AI provider and its endpoint.
     *
     * This method should also handle logging of the request and its response,
     * and also handoff to any anonymisation service before logging.
     *
     * @param array $payload
     * @return ResponseInterface The response from the AI provider.
     *                           Note that the response may be an event stream:
     *                           if so, the calling method must handle the
     *                           stream.
     */
    public function request(array $payload): ResponseInterface;
}
