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
    public function createResponse(array $payload, Log\Model $log): ResponseInterface
    {
        $url      = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log      = $log->setEndpoint($url->__toString());
        $request  = new Request('POST', $url, $this->authHeaders);
        $response = $this->sendRequest($request, $payload, $log, false);
        return $this->handleNonStreamedResponse(
            $response,
            $log,
            new LogHandler\ResponseMessage(),
        );
    }

    /**
     * @inheritdoc
     */
    public function createResponseStream(
        array $payload,
        Log\Model $log,
        ?int $chunkSize = null,
        ?callable $streamListener = null,
    ): iterable {
        $url      = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log      = $log->setEndpoint($url->__toString());
        $request  = new Request('POST', $url, $this->authHeaders);
        $response = $this->sendRequest($request, $payload, $log, true);
        return $this->handleStreamedResponse(
            $response,
            $log,
            new LogHandler\ResponseMessage(),
            $chunkSize ?? self::DEFAULT_CHUNK_SIZE,
            $streamListener,
        );
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletion(array $payload, Log\Model $log): ResponseInterface
    {
        $url     = $this->generateUrl(self::ENDPOINT_CHAT_COMPLETIONS);
        $log      = $log->setEndpoint($url->__toString());
        $request = new Request('POST', $url, $this->authHeaders);
        $response = $this->sendRequest($request, $payload, $log, false);
        return $this->handleNonStreamedResponse(
            $response,
            $log,
            new LogHandler\ChatCompletionsMessage(),
        );
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletionStream(
        array $payload,
        Log\Model $log,
        ?int $chunkSize = null,
        ?callable $streamListener = null,
    ): iterable {
        $url      = $this->generateUrl(self::ENDPOINT_CHAT_COMPLETIONS);
        $log      = $log->setEndpoint($url->__toString());
        $request  = new Request('POST', $url, $this->authHeaders);
        $response = $this->sendRequest($request, $payload, $log, true);
        return $this->handleStreamedResponse(
            $response,
            $log,
            new LogHandler\ChatCompletionsMessage(),
            $chunkSize ?? self::DEFAULT_CHUNK_SIZE,
            $streamListener,
        );
    }

    /**
     * @inheritdoc
     */
    public function createLog(
        array $payload,
        ?string $inputText = null,
        ?User $user = null,
        ?string $feature = null,
        ?string $aiSessionId = null,
    ): Log\Model {
        /** @noinspection PhpUnhandledExceptionInspection - this will never throw */
        $log = $this->logMapper->createModel()
            ->setPrompt(json_encode($payload));
        if (isset($inputText)) {
            $log = $log->setInputText($inputText);
        }
        if (isset($user)) {
            $log = $log->setUser($user);
        }
        if (isset($feature)) {
            $log = $log->setFeature($feature);
        }
        if (isset($aiSessionId)) {
            $log = $log->setSessionId($aiSessionId);
        }
        return $log;
    }

    /**
     * @inheritdoc
     */
    public function saveLog(Log\Model $log): void
    {
        $this->logMapper->save($log);
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
        Log\Model $log,
        bool $stream,
    ): ResponseInterface {
        try {
            return $this->httpClient->send(
                $request,
                [
                    RequestOptions::ALLOW_REDIRECTS => false,
                    RequestOptions::STREAM          => $stream,
                    RequestOptions::JSON            => $payload,
                ],
            );
        } catch (BadResponseException $e) {
            $log->setReturnCode($e->getResponse()->getStatusCode())
                ->setErrorMessage($e->getMessage());
            $this->saveLog($log);

            // Extract body from response, and put it in output
            $responseBody = $e->getResponse()->getBody()->getContents();
            $responseJson = json_decode($responseBody, true);
            throw new SchoolboxException(
                $responseJson['error']['message'] ?? $e->getMessage(),
                $e->getCode(),
                $e,
                $e->getResponse()->getStatusCode(),
            );
        } catch (ClientExceptionInterface $e) {
            $log->setReturnCode(null)
                ->setErrorMessage($e->getMessage());
            $this->saveLog($log);
            throw new SchoolboxException(
                $e->getMessage(),
                $e->getCode(),
                $e,
                500,
            );
        }
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
     * @param ResponseInterface      $response   The response from the AI provider.
     * @param Log\Model              $log        The log to write to.
     * @param LogHandler\ILogHandler $logHandler The log handler to use for the response.
     * @return ResponseInterface The response from the AI provider.
     * @throws JsonException   if somehow the response body couldn't be decoded
     * @throws MapperException if somehow the log couldn't be saved
     */
    private function handleNonStreamedResponse(
        ResponseInterface $response,
        Log\Model $log,
        LogHandler\ILogHandler $logHandler,
    ): ResponseInterface {
        // Do something with the response:
        // extract the body, and also log it.
        // OpenAI responses to non-streamed requests are a single JSON object
        // (at least in the case of responses and chat completions), so it can
        // be decoded and processed as a single object.
        $responseBody = $response->getBody()->getContents();
        $responseJson = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $log = $logHandler->handleResponse($responseJson, $log);
        $this->saveLog($log);

        // Restore the body in its streamable glory
        return $response->withBody(Utils::streamFor($responseBody));
    }

    /**
     * Request a streamed response from the AI provider, and handle the logging
     * and exceptions appropriately.
     *
     * @param ResponseInterface      $response       The response from the AI provider.
     * @param Log\Model              $log            The log to write to.
     * @param LogHandler\ILogHandler $logHandler     The log handler to use for each message.
     * @param int                    $chunkSize      How many bytes to read from each stream iteration.
     * @param callable|null          $streamListener A function to call for each event in the stream, as it is received.
     * @return iterable<EventStream\Message> Each message in the stream as it's received.
     * @throws MapperException if somehow the log couldn't be saved
     */
    private function handleStreamedResponse(
        ResponseInterface $response,
        Log\Model $log,
        LogHandler\ILogHandler $logHandler,
        int $chunkSize,
        ?callable $streamListener = null,
    ): iterable {
        // This needs to be an event stream: otherwise raise an exception
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'text/event-stream')) {
            foreach ($this->readEvents($response->getBody(), $chunkSize) as $message) {
                // The log should be saved in an incomplete form whenever an
                // event is read, so in the case of failure something is logged.
                yield clone $message; // clone as the logHandler may modify it

                // Update the log, and save it:
                // even if that raises an exception, still save the log
                try {
                    $logHandler->handleMessage($message, $log, $streamListener);
                } finally {
                    $this->saveLog($log);
                }
            }

            // If nothing happened: set a default return code, and save log
            $log->setReturnCode($log->getReturnCode() ?? 200);
            $this->saveLog($log);
        } else {
            // This is probably an error, so just finalise the log with whatever
            // we got
            $responseBody = $response->getBody()->getContents();
            $responseJson = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
            $log = $logHandler->handleResponse($responseJson, $log);
            $this->saveLog($log);

            // TODO: is this actually going to show an error state to the frontend??
            // Convert it to a single message I guess, if finalising the log
            // didn't raise an exception?
            yield new EventStream\Message($responseBody);
        }
    }
}
