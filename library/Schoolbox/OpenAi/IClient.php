<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\OpenAi\Session\Log;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
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
     * @param array     $payload The request body to send.
     * @param Log\Model $log     The log to write to: will be updated with the response data,
     *                           so that this call may be identified in logs.
     * @return ResponseInterface The response from the AI provider.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function createResponse(array $payload, Log\Model $log): ResponseInterface;

    /**
     * Call the create model response API, and receive a stream:
     * * https://platform.openai.com/docs/api-reference/responses-streaming
     *
     * @param array                      $payload             The request body to send.
     * @param Log\Model                  $log                 The log to write to: will be updated with the
     *                                                        response data, so that this call may be identified in
     *                                                        logs.
     * @param int|null                   $chunkSize           If provided, read this many bytes per stream fetch:
     *                                                        otherwise, allow the client itself to decide.
     * @param EventStream\IListener|null $eventStreamListener If set: use this to modify the message from OpenAI before
     *                                                        sending to the stream.
     * @param ?LogHandler\IListener      $logListener         If set: call this for each message received from OpenAI,
     *                                                        and modify the log accordingly.
     * @return iterable<string> Each message in the stream as it's received.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function createResponseStream(
        array $payload,
        Log\Model $log,
        ?int $chunkSize = null,
        ?EventStream\IListener $eventStreamListener = null,
        ?LogHandler\IListener $logListener = null,
    ): iterable;

    /**
     * Call the create chat completion API, and receive a standard HTTP response:
     * * https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param array     $payload The request body to send.
     * @param Log\Model $log     The log to write to: will be updated with the response data,
     *                           so that this call may be identified in logs.
     * @return ResponseInterface
     */
    public function createChatCompletion(array $payload, Log\Model $log): ResponseInterface;

    /**
     * Call the create chat completion API, and receive a stream:
     * * https://platform.openai.com/docs/api-reference/chat-streaming/streaming
     *
     * @param array                      $payload             The request body to send.
     * @param Log\Model                  $log                 The log to write to: will be updated with the
     *                                                        response data, so that this call may be identified in
     *                                                        logs.
     * @param int|null                   $chunkSize           If provided, read this many bytes per stream fetch:
     *                                                        otherwise, allow the client itself to decide.
     * @param EventStream\IListener|null $eventStreamListener If set: use this to modify the message from OpenAI before
     *                                                        sending to the stream.
     * @param LogHandler\IListener|null  $logListener         If set: call this for each message received from OpenAI,
     *                                                        and modify the log accordingly.
     * @return iterable<string>
     */
    public function createChatCompletionStream(
        array $payload,
        Log\Model $log,
        ?int $chunkSize = null,
        ?EventStream\IListener $eventStreamListener = null,
        ?LogHandler\IListener $logListener = null,
    ): iterable;

    /**
     * Call the create moderations API, and receive a standard HTTP response:
     * * https://platform.openai.com/docs/api-reference/moderations/create
     *
     * @param array     $payload The request body to send.
     * @param Log\Model $log     The log to write to: will be updated with the response data,
     *                           so that this call may be identified in logs.
     * @return ResponseInterface The response from the AI provider.
     * @throws ClientExceptionInterface if something went wrong with the request
     */
    public function createModerations(array $payload, Log\Model $log): ResponseInterface;

    /**
     * Create a log object, which will then be passed into this client's
     * `create*` methods.
     *
     * The agent may modify the log object itself before passing it to this
     * client, or when it is returned by the client.
     *
     * @param array       $payload      The payload which will be sent to the AI provider.
     * @param string|null $inputText    The user input which will be stored within the log. Later, this may be used to
     *                                  display the user's input in the UI.
     * @param User|null   $user         The user making the request.
     * @param string      $feature      An identifier for the feature being requested.
     * @param string      $aiSessionId  This call's session ID (generated by Schoolbox, not the OpenAI API).
     * @param LogFormat|null $logFormat Optionally, the format that log entries should be stored in so they can be seen in the Usage Log
     * @return Log\Model
     * @throws \Schoolbox\Mapper\Exception if the session mapper fails to find or create the parent ai_session row
     */
    public function createLog(
        array $payload,
        ?string $inputText,
        ?User $user,
        string $feature,
        string $aiSessionId,
        ?LogFormat $logFormat = null,
    ): Log\Model;

    /**
     * Save a log object.
     *
     * @param Log\Model $log
     */
    public function saveLog(Log\Model $log): void;
}
