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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Editor\OpenAI\Log;
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
     * When an image is omitted from the request/response: replace the image
     * data with this in the log.
     */
    private const string IMAGE_DATA_OMITTED = 'omitted';

    /**
     * The endpoint which OpenAI-compatible clients use for their Responses
     * feature.
     */
    private const string ENDPOINT_RESPONSES = '/responses';

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
        $encodedPayload = json_encode($payload);

        $url = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);

        $request = new Request(
            'POST',
            $this->baseUrl->withPath(rtrim($this->baseUrl->getPath(), '/') .  self::ENDPOINT_RESPONSES),
            $this->authHeaders + ['Content-Type' => 'application/json'],
            $encodedPayload,
        );

        try {
            $response = $this->httpClient->send(
                $request,
                [
                    RequestOptions::ALLOW_REDIRECTS => false,
                    RequestOptions::HTTP_ERRORS     => false,
                    RequestOptions::STREAM          => false,
                ],
            );
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
        // extract the body, and also log it
        $responseBody = $response->getBody()->getContents();
        $data         = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        $this->finaliseLog($data, $log);

        // Restore the body in its streamable glory
        return $response->withBody(Utils::streamFor($responseBody));
    }

    /**
     * @inheritdoc
     */
    public function createResponseStream(array $payload, User $user, string $feature, ?string $aiSessionId = null, ?int $chunkSize = null): iterable
    {
        $chunkSize ??= self::DEFAULT_CHUNK_SIZE;
        $encodedPayload = json_encode($payload);

        $url = $this->generateUrl(self::ENDPOINT_RESPONSES);
        $log = $this->generateLog($user, $feature, $aiSessionId, $url, $payload);

        $request = new Request(
            'POST',
            $url,
            $this->authHeaders + ['Content-Type' => 'application/json'],
            $encodedPayload,
        );

        $response = $this->httpClient->send(
            $request,
            [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::STREAM          => true,
            ],
        );

        // This needs to be an event stream: otherwise raise an exception
        $contentType = $response->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'text/event-stream')) {
            foreach ($this->readEvents($response->getBody(), $chunkSize, $log) as $stream) {
                yield $stream;
            }
        } else {
            // Log the fact this isn't a stream, too
            throw new BadResponseException(
                sprintf('Expected text/event-stream response: received %s', $contentType),
                $request,
                $response,
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletion(array $payload, User $user, string $feature, ?string $aiSessionId = null): ResponseInterface
    {
        // TODO: Implement createChatCompletion() method.
    }

    /**
     * @inheritdoc
     */
    public function createChatCompletionStream(array $payload, User $user, string $feature, ?string $aiSessionId = null, ?int $chunkSize = null): iterable
    {
        // TODO: Implement createChatCompletionStream() method.
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

    private function sendRequest(
        UriInterface $url,
        bool $stream,
        array $payload,
        User $user,
        string $feature,
        ?string $aiSessionId = null,
    ): ResponseInterface {
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
     * @return iterable<string>
     */
    private function readEvents(StreamInterface $stream, int $chunkSize, Log\Model $log): iterable
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
                yield $this->filterForOutput($message)->__toString();
                $this->onMessage($message, $log);
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
            yield $this->filterForOutput($message)->__toString();
            $this->onMessage($message, $log);
        }
    }

    /**
     * Write this message to the log.
     *
     * @param EventStream\Message $message
     * @param Log\Model           $log
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    private function onMessage(EventStream\Message $message, Log\Model $log): void
    {
        // Prepare the message for logging
        $eventType = $message->eventType;
        $output    = '';
        $data      = [];
        try {
            // Omit partial and completed image data from the output
            $item   = json_decode($message->data, true, 512, JSON_THROW_ON_ERROR);
            $data[] = $item;
            if ($eventType === 'response.image_generation_call.partial_image' && isset($item['partial_image_b64'])) {
                $item['partial_image_b64'] = self::IMAGE_DATA_OMITTED;
            }
            if ($eventType === 'response.output_item.done' && isset($item['item']['result'])) {
                $item['item']['result'] = self::IMAGE_DATA_OMITTED;
            }

            // apparently there is a final final image, lets remove that also
            if ($eventType === 'response.completed' && isset($item['response']['output']) && is_array($item['response']['output'])) {
                // $item['response']['output'] is apparently an array, and we need to omit the b64 from each $item['response']['output'][]['result']
                foreach ($item['response']['output'] as $i => $respOutput) {
                    if (isset($respOutput['result'])) {
                        $item['response']['output'][$i]['result'] = self::IMAGE_DATA_OMITTED;
                    }
                }
            }

            $output .= sprintf("event: %s\ndata: %s\n\n", $eventType, json_encode($item, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            // If it's not JSON, still save the raw data
            $output .= sprintf("event: %s\n%s\n\n", $eventType, $message->data);
        }

        // This chunk gets added to the log
        $this->appendMessageToLog($output, $data, $log);

        // Extract the completed event, and add it to the log
        // Is this the final event?
        // There are 3 possible events that indicate the end of a response
        // (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
        if (in_array($eventType, ['response.completed', 'response.failed', 'response.incomplete'])) {
            $this->finaliseLog($data[0]['response'], $log);
        }
    }

    /**
     * Append the message to the log, including:
     * * the message itself (with redundant field filtered)
     * * the model the API endpoint used
     * * the input and output tokens used
     *
     * @param string    $output The message, formatted as an event stream, with filtered fields.
     * @param array     $data   The unfiltered parsed data from the message.
     * @param Log\Model $log    The log to write to.
     * @return void
     */
    private function appendMessageToLog(string $output, array $data, Log\Model $log): void
    {
        $log->setResponse(($log->getResponse() ?? '') . $output);
        foreach ($data as $item) {
            // Model, input and output tokens depend on the endpoint
            $model        = $item['response']['model'] ?? $item['model'] ?? null;
            $inputTokens  = $item['response']['usage']['input_tokens'] ?? $item['usage']['prompt_tokens'] ?? null;
            $outputTokens = $item['response']['usage']['output_tokens'] ?? $item['usage']['completion_tokens'] ?? null;

            if ($model) {
                $log->setModel($model);
            }
            if ($inputTokens) {
                $log->setInputTokens($inputTokens);
            }
            if ($outputTokens) {
                $log->setOutputTokens($outputTokens);
            }
        }
    }

    /**
     * @param array $completedEvent
     * @param Log\Model $log
     * @return void
     * @throws \Schoolbox\Mapper\Exception
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

        /** store the response, and the ai usage */
        $log->setReturnCode(200)
            ->setModel($completedEvent['model'] ?? null)
            ->setInputTokens($completedEvent['usage']['input_tokens'] ?? null)
            ->setOutputTokens($completedEvent['usage']['output_tokens'] ?? null);
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
}
