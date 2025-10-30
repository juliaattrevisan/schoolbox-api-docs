<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;

/**
 * A standard client for an AI provider.
 */
class Client implements IClient
{
    /**
     * Default chunk size when reading a streaming response.
     */
    private const int DEFAULT_CHUNK_SIZE = 128;

    /**
     * The endpoint which OpenAI-compatible clients use for their Responses
     * feature.
     */
    private const string ENDPOINT_RESPONSES = '/responses';

    /**
     * The endpoint which OpenAI-compatible clients use for their Chat
     * Completion feature.
     */
    private const string ENDPOINT_CHAT_COMPLETIONS = '/chat/completions';

    /**
     * Constructor
     *
     * @param HttpClient            $httpClient  The HTTP client, for making requests to the AI provider.
     * @param UriInterface          $baseUrl     The base URL for the API provider, including:
     *                                           * the base domain and path
     *                                           * any query string which is added to all requests
     * @param array<string, string> $authHeaders The headers (as a key-value pair) which must be provided to the
     *                                           provider, in order to authenticate requests.
     *                                           * OpenAI accepts `["Authorization" => "Bearer $apiKey"]`
     *                                           * Azure's OpenAI compat layer accepts `['api-key' => $apiKey]`
     *
     * @param Log\Mapper            $logMapper   Mapper for storing logs.
     */
    public function __construct(
        private HttpClient $httpClient,
        private UriInterface $baseUrl,
        private array $authHeaders,
        private Log\Mapper $logMapper,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function createResponse(array $payload, User $user, string $feature, ?string $aiSessionId = null): ResponseInterface
    {
        $url     = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log     = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);
        $request = new Request('POST', $url, $this->authHeaders);
        return $this->handleNonStreamedResponse(
            $request,
            $payload,
            $log,
            new EventStream\LogHandler\ResponseMessage(),
        );
    }

    /**
     * @inheritdoc
     */
    public function createResponseStream(array $payload, User $user, string $feature, ?string $aiSessionId = null, ?int $chunkSize = null): iterable
    {
        $url     = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log     = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);
        $request = new Request('POST', $url, $this->authHeaders);
        return $this->handleStreamedResponse(
            $request,
            $payload,
            $log,
            new EventStream\LogHandler\ResponseMessage(),
            $chunkSize ?? self::DEFAULT_CHUNK_SIZE,
        );
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletion(array $payload, User $user, string $feature, ?string $aiSessionId = null): ResponseInterface
    {
        $url     = $this->generateUrl(self::ENDPOINT_CHAT_COMPLETIONS);
        $log     = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);
        $request = new Request('POST', $url, $this->authHeaders);
        return $this->handleNonStreamedResponse(
            $request,
            $payload,
            $log,
            new EventStream\LogHandler\ResponseMessage(),
        );
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletionStream(array $payload, User $user, string $feature, ?string $aiSessionId = null, ?int $chunkSize = null): iterable
    {
        $url     = $this->generateUrl(self::ENDPOINT_CHAT_COMPLETIONS);
        $log     = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);
        $request = new Request('POST', $url, $this->authHeaders);
        return $this->handleStreamedResponse(
            $request,
            $payload,
            $log,
            new EventStream\LogHandler\ChatCompletionsMessage(),
            $chunkSize ?? self::DEFAULT_CHUNK_SIZE,
        );
    }

    /**
     * Generate the URL to which a request to an OpenAI-compatible AI provider
     * should be sent.
     *
     * @param string&self::ENDPOINT_* $endpoint The endpoint to send to.
     * @return UriInterface
     */
    private function generateUrl(string $endpoint): UriInterface
    {
        return $this->baseUrl->withPath(rtrim($this->baseUrl->getPath(), '/') . $endpoint);
    }

    /**
     * Generate the log for this request.
     *
     * @param User         $user        The user making the request.
     * @param string       $feature     An identifier for the feature being requested.
     * @param string|null  $aiSessionId If provided: this call's session ID (generated by Schoolbox, not OpenAI).
     * @param UriInterface $url         The URL to send the request to.
     * @param array        $payload     The request body to send.
     * @return Log\Model
     * @noinspection PhpDocMissingThrowsInspection - this will never throw
     */
    private function generateLog(
        User $user,
        string $feature,
        ?string $aiSessionId,
        UriInterface $url,
        array $payload,
    ): Log\Model {
        /** @noinspection PhpUnhandledExceptionInspection - this will never throw */
        return $this->logMapper->createModel()
            ->setUser($user)
            ->setPrompt(json_encode($payload))
            ->setFeature($feature)
            ->setSessionId($aiSessionId)
            ->setEndpoint($url->__toString());
    }

    /**
     * Send the request to the AI provider.
     *
     * @param RequestInterface $request The request to send.
     * @param array            $payload The request body to send: will be encoded as JSON.
     * @param bool             $stream  Should a streaming response be requested?
     * @return ResponseInterface
     * @throws BadResponseException     when the response is a 4xx or 5xx
     * @throws ClientExceptionInterface when something else goes wrong with the request
     */
    private function sendRequest(
        RequestInterface $request,
        array $payload,
        bool $stream,
    ): ResponseInterface {
        return $this->httpClient->send(
            $request,
            [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::STREAM          => $stream,
                RequestOptions::JSON            => $payload,
            ],
        );
    }

    /**
     * Read events from the response's body stream.
     *
     * This will read bytes from the stream whilst it's still open, and output
     * them as event objects.
     *
     * @param StreamInterface $stream    The response's body, as a stream.
     * @param int             $chunkSize How many bytes should be read from the
     *                                   stream at once?
     * @return iterable<EventStream\Message>
     */
    private function readEvents(StreamInterface $stream, int $chunkSize): iterable
    {
        $chunkBuffer = '';
        while (!$stream->eof()) {
            $data = $stream->read($chunkSize);

            // Sleep whilst there's nothing to receive
            if ('' === $data) {
                usleep(100000);
                continue;
            }

            // Buffer the stream contents into a set of message objects,
            // for later output, processing and logging
            $data         = strtr($data, ["\r\n" => "\n", "\r" => "\n"]);
            $chunkBuffer .= $data;

            // while there's at least one full chunk in the buffer, process it
            while (false !== ($pos = strpos($chunkBuffer, "\n\n"))) {
                $chunk = substr($chunkBuffer, 0, $pos);

                // If this can't be parsed: raise something but still log
                $message = EventStream\Message::fromString($chunk);

                // Filter fields out of the message as necessary before sending?
                yield $this->filterForOutput($message);
                $chunkBuffer = substr($chunkBuffer, $pos + 2);

                // TODO this should probably raise an exception, to be handled
                //      by the controller?
                // I think that after an error's raised then there's no point
                // outputting any further?
//                // Don't echo an 'error' chunk if this isn't going to ckeditor as it won't follow the format of an ApiProblem
//                if ($type === self::FEATURE_CHAT || $event['type'] !== 'error') {
//                    echo "{$chunk}\n\n";
//                }
            }
        }

        // If we have any remaining data in the buffer, process it
        if (strlen(trim($chunkBuffer)) > 0) {
            $message = EventStream\Message::fromString($chunkBuffer);
            yield $this->filterForOutput($message);
        }
    }

    /**
     * TODO remove this and handle in `ILogHandler` classes
     *
     * @param array     $completedEvent
     * @param Log\Model $log
     * @return void
     * @throws SchoolboxException if the completed event was an error or failure
     * @throws MapperException    if the log couldn't be saved
     */
    private function finaliseLog(array $completedEvent, Log\Model $log): void
    {
        /* If something went wrong on the openAI side, it'll send us an "error" key
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
         * If we get this, send on the human-readable message to the user
         */
        if ($completedEvent["error"] ?? null) {
            $returnCode = 500;

            $errorMessage = $completedEvent["error"]["message"] ?? '';

            $log->setReturnCode($returnCode)
                ->setErrorMessage($errorMessage);
            $this->logMapper->save($log);

            throw new SchoolboxException($errorMessage, $returnCode);
        }

        /* Did we receive an incomplete response? i.e. max output tokens reached?
         * If so, send those details on so we have some idea of why it failed
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-status
         */
        $status = $completedEvent["status"] ?? null;
        if ($status !== "completed") {
            $returnCode = 500;

            $title = sprintf(
                "Failed to generate a response: %s",
                $status ?? "unknown"
            );

            /* there may be some extra details about why it was incomplete */
            $incompleteMessage = $completedEvent["incomplete_details"]["reason"] ?? null;
            if ($incompleteMessage) {
                $title .= sprintf(" (%s)", $incompleteMessage);
            }

            $log->setReturnCode($returnCode)
                ->setErrorMessage($incompleteMessage);
            $this->logMapper->save($log);

            throw new SchoolboxException($title, $returnCode);
        }

        // well we didn't die, so let's assume we got a good response
        // Set the response code only: the model and token usage should already
        // have been set in `appendMessageToLog`.
        $log->setReturnCode(200)
//            ->setModel($completedEvent['model'] ?? null)
//            ->setInputTokens($completedEvent['usage']['input_tokens'] ?? null)
//            ->setOutputTokens($completedEvent['usage']['output_tokens'] ?? null)
        ;
        $this->logMapper->save($log);
    }

    /**
     * Filter unwanted fields out of `$message` before it's output.
     *
     * Currently this filters nothing, but there's scope for determining
     * exactly what gets filtered out.
     *
     * @param EventStream\Message $message
     * @return EventStream\Message
     */
    private function filterForOutput(EventStream\Message $message): EventStream\Message
    {
        return $message;
    }

    /**
     * Request a non-streamed response from the AI provider, and handle the
     * logging and exceptions appropriately.
     *
     * @param RequestInterface                   $request    The request to send.
     * @param array                              $payload    The request body to send.
     * @param Log\Model                          $log        The log to write to.
     * @param EventStream\LogHandler\ILogHandler $logHandler The log handler to use for the response.
     * @return ResponseInterface The response from the AI provider.
     * @throws ClientExceptionInterface if the request fails
     * @throws JsonException            if somehow the response body couldn't be decoded
     * @throws MapperException          if somehow the log couldn't be saved
     */
    private function handleNonStreamedResponse(
        RequestInterface $request,
        array $payload,
        Log\Model $log,
        EventStream\LogHandler\ILogHandler $logHandler,
    ): ResponseInterface {
        try {
            $response = $this->sendRequest($request, $payload, false);
        } catch (BadResponseException $e) {
            $log->setReturnCode($e->getResponse()->getStatusCode())
                ->setErrorMessage($e->getMessage());
            $this->logMapper->save($log);
            throw $e;
        } catch (ClientExceptionInterface $e) {
            $log->setReturnCode(null)
                ->setErrorMessage($e->getMessage());
            $this->logMapper->save($log);
            throw $e;
        }

        // Do something with the response:
        // extract the body, and also log it.
        // OpenAI responses to non-streamed requests are a single JSON object
        // (at least in the case of responses and chat completions), so it can
        // be decoded and processed as a single object.
        $responseBody = $response->getBody()->getContents();
        $responseJson = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
//        $this->finaliseLog($responseJson, $log);
        $log = $logHandler->handleResponse($responseJson, $log);
        $this->logMapper->save($log);

        // Restore the body in its streamable glory
        return $response->withBody(Utils::streamFor($responseBody));
    }

    /**
     * Request a streamed response from the AI provider, and handle the logging
     * and exceptions appropriately.
     *
     * @param RequestInterface                   $request    The request to send.
     * @param array                              $payload    The request body to send.
     * @param Log\Model                          $log        The log to write to.
     * @param EventStream\LogHandler\ILogHandler $logHandler The log handler to use for each message.
     * @param int                                $chunkSize  How many bytes to read from each stream iteration.
     * @return iterable<string> Each message in the stream as it's received.
     * @throws ClientExceptionInterface if the request fails
     * @throws MapperException          if somehow the log couldn't be saved
     */
    private function handleStreamedResponse(
        RequestInterface $request,
        array $payload,
        Log\Model $log,
        EventStream\LogHandler\ILogHandler $logHandler,
        int $chunkSize,
    ): iterable {
        $response = $this->sendRequest($request, $payload, true);

        // This needs to be an event stream: otherwise raise an exception
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'text/event-stream')) {
            foreach ($this->readEvents($response->getBody(), $chunkSize) as $message) {
                // The log should be saved in an incomplete form whenever an
                // event is read, so in the case of failure something is logged.
                yield $message->__toString();

                // Update the log, and save it:
                // even if that raises an exception, still save the log
                try {
                    $logHandler->handleMessage($message, $log);
                } finally {
                    $this->logMapper->save($log);
                }
            }

            // If nothing happened: set a default return code, and save log
            $log->setReturnCode($log->getReturnCode() ?? 200);
            $this->logMapper->save($log);
        } else {
            // This is probably an error, so just finalise the log with whatever
            // we got
            $responseBody = $response->getBody()->getContents();
            $responseJson = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
//            $this->finaliseLog($responseJson, $log);
            $log = $logHandler->handleResponse($responseJson, $log);
            $this->logMapper->save($log);

            // Convert it to a single message I guess, if finalising the log
            // didn't raise an exception?
            yield new EventStream\Message($responseBody)->__toString();
        }
    }
}
