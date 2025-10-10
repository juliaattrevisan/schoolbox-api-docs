<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\User;

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
     * Call the create model response API, and receive a standard HTTP response:
     * * https://platform.openai.com/docs/api-reference/responses/create
     *
     * @param array  $payload The request body to send.
     * @param User   $user    The user making the request: will be logged.
     * @param string $feature An identifier for the feature being requested:
     *                        will be logged.
     * @return ResponseInterface The response from the AI provider.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function createResponse(array $payload, User $user, string $feature): ResponseInterface;

    /**
     * Call the create model response API, and receive a stream:
     * * https://platform.openai.com/docs/api-reference/responses-streaming
     *
     * @param array    $payload   The request body to send.
     * @param User     $user      The user making the request: will be logged.
     * @param string   $feature   An identifier for the feature being requested:
     *                            will be logged.
     * @param int|null $chunkSize If provided, read this many bytes for each stream iteration:
     *                            otherwise, allow the implementation to decide.
     * @return iterable<string> Each chunk of the stream as it's received.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function createResponseStream(array $payload, User $user, string $feature, ?int $chunkSize = null): iterable;

    /**
     * Send a request to the configured AI provider and its endpoint.
     *
     * This method should also handle logging of the request and its response,
     * and also handoff to any anonymisation service before logging.
     *
     * @param string $method  The HTTP method to request.
     * @param array  $payload The request body to send.
     * @param User   $user    The user making the request: will be logged.
     * @param string $feature An identifier for the feature being requested:
     *                        will be logged.
     * @return ResponseInterface The response from the AI provider.
     *                           Note that the response may be an event stream:
     *                           if so, the calling method must handle the
     *                           stream.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function request(string $method, array $payload, User $user, string $feature): ResponseInterface;

    /**
     * Same as `request`, but instead indicates that the response body is to be
     * streamed as it is received.
     *
     * This is split into a separate function, because streams need to be
     * handled differently to standard responses.
     * TODO consider adding stream() helper in another class? `stream($this->request())`
     *
     * @param string   $method    The HTTP method to request.
     * @param array    $payload   The request body to send.
     * @param User     $user      The user making the request: will be logged.
     * @param string   $feature   An identifier for the feature being requested:
     *                            will be logged.
     * @param int|null $chunkSize If provided, read this many bytes for each stream iteration:
     *                            otherwise, allow the implementation to decide.
     * @return iterable<string> Each chunk of the stream as it's received.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function requestStream(string $method, array $payload, User $user, string $feature, ?int $chunkSize = null): iterable;
}
