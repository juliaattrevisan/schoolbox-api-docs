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
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
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
    private const DEFAULT_CHUNK_SIZE = 128;

    /**
     * When an image is omitted from the request/response: replace the image
     * data with this in the log.
     */
    private const IMAGE_DATA_OMITTED = 'omitted';

    /**
     * Constructor
     *
     * @param HttpClient $httpClient The HTTP client, for making requests to the AI provider.
     * @param string     $endpoint   The base endpoint for the API provider.
     * @param string     $apiKey     The API key which must be provided to the provider, in order to authenticate
     *                               requests.
     * @param Log\Mapper $logMapper  Mapper for storing logs.
     */
    public function __construct(
        private HttpClient $httpClient,
        private string $endpoint,
        private string $apiKey,
        private Log\Mapper $logMapper,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function request(string $method, array $payload, User $user, string $feature): ResponseInterface
    {
        $request = new Request(
            $method,
            $this->endpoint,
            $this->getAuthorization() + ['Content-Type' => 'application/json'],
            json_encode($payload),
        );
        return $this->httpClient->send(
            $request,
            [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::HTTP_ERRORS     => false,
                RequestOptions::STREAM          => false,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function requestStream(string $method, array $payload, User $user, string $feature, ?int $chunkSize = null): iterable
    {
        $chunkSize ??= self::DEFAULT_CHUNK_SIZE;
        $encodedPayload = json_encode($payload);

        $log = $this->logMapper->createModel()
            ->setUser($user)
            ->setPrompt($encodedPayload)
            ->setFeature($feature)
//            ->setSessionId($aiSessionId)
            ->setEndpoint($this->endpoint);

        $request = new Request(
            $method,
            $this->endpoint,
            $this->getAuthorization() + ['Content-Type' => 'application/json'],
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
            throw new BadResponseException(
                sprintf('Expected text/event-stream response: received %s', $contentType),
                $request,
                $response,
            );
        }

        // Once entire stream is received: log it
    }

    /**
     * Add whatever authorization headers the API provider requires.
     *
     * @return array<string>
     */
    protected function getAuthorization(): array
    {
        return ['Authorization' => 'Bearer ' . $this->apiKey];
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
        $completedEvent = null;

        while (!$stream->eof()) {
            $data = $stream->read($chunkSize);

            // Sleep whilst there's nothing to receive
            if ('' === $data) {
                usleep(100000);
                continue;
            }
            yield $data;

            // Actually listen to the event stream
            // Normalise CRLF to LF
            $data = strtr($data, ["\r\n" => "\n", "\r" => "\n"]);

            $chunkBuffer .= $data;

            // while there's at least one full chunk in the buffer, process it
            while (false !== ($pos = strpos($chunkBuffer, "\n\n"))) {
                $chunk = substr($chunkBuffer, 0, $pos);
                // If this can't be parsed: raise something but still log
                $this->onMessage(EventStream\Message::fromString($chunk), $log);
                $chunkBuffer = substr($chunkBuffer, $pos + 2);
//                $event = $this->parseChunk($chunk);
////                $this->appendChunkToLog($event, $log, $type);
//
////                // Don't echo an 'error' chunk if this isn't going to ckeditor as it won't follow the format of an ApiProblem
////                if ($type === self::FEATURE_CHAT || $event['type'] !== 'error') {
////                    echo "{$chunk}\n\n";
////                }
            }
        }

//        // If we have any remaining data in the buffer, process it
        if (strlen(trim($chunkBuffer)) > 0) {
            $this->onMessage(EventStream\Message::fromString($chunkBuffer), $log);
        }
    }

    private function onMessage(EventStream\Message $message, Log\Model $log): void
    {
        // Prepare the message for logging
        $itemType = $message->eventType;
        $output   = '';
        $data     = [];
        try {
            // Omit partial and completed image data from the output
            $item = json_decode($message->data, true, 512, JSON_THROW_ON_ERROR);
            $data[] = $item;
            if ($itemType === 'response.image_generation_call.partial_image' && isset($item['partial_image_b64'])) {
                $item['partial_image_b64'] = self::IMAGE_DATA_OMITTED;
            }
            if ($itemType === 'response.output_item.done' && isset($item['item']['result'])) {
                $item['item']['result'] = self::IMAGE_DATA_OMITTED;
            }

            // apparently there is a final final image, lets remove that also
            if ($itemType === 'response.completed' && isset($item['response']['output']) && is_array($item['response']['output'])) {
                // $item['response']['output'] is apparently an array, and we need to omit the b64 from each $item['response']['output'][]['result']
                foreach ($item['response']['output'] as $i => $respOutput) {
                    if (isset($respOutput['result'])) {
                        $item['response']['output'][$i]['result'] = self::IMAGE_DATA_OMITTED;
                    }
                }
            }

            $output .= sprintf("event: %s\ndata: %s\n\n", $itemType, json_encode($item, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            // If it's not JSON, still save the raw data
            $output .= sprintf("event: %s\n%s\n\n", $itemType, $message->data);
        }

        // This chunk gets added to the log
        $event = [
            'type' => $itemType,
            'data' => $data,
            'output' => $output,
        ];
        $this->appendChunkToLog($event, $log);

        // Extract the completed event, and add it to the log
        // Is this the final event?
        // There are 3 possible events that indicate the end of a response
        // (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
        if (in_array($itemType, ['response.completed', 'response.failed', 'response.incomplete'])) {
            $completedEvent = $event['data'][0]['response'];

            /* If something went wrong on the openAI side, it'll send us an "error" key
             * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
             * If we get this, send on the human-readable message to the user
             */
            if ($completedEvent["error"] ?? null) {
                $returnCode = 500;

                $error_message = $completedEvent["error"]["message"] ?? '';

                $log->setReturnCode($returnCode)
                    ->setErrorMessage($error_message);
                $this->logMapper->save($log);

                throw new SchoolboxException(
                    $error_message,
                    $returnCode
                );
            }

            /* Did we recieve an incomplete response? i.e. max output tokens reached?
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

                throw new SchoolboxException(
                    $title,
                    $returnCode
                );
            }

            // well we didn't die, so let's assume we got a good response

            /** store the response, and the ai usage */
            $log->setReturnCode(200)
                ->setModel($completedEvent['model'] ?? null)
                ->setInputTokens($completedEvent['usage']['input_tokens'] ?? null)
                ->setOutputTokens($completedEvent['usage']['output_tokens'] ?? null);
            $this->logMapper->save($log);
        }
    }


    private function appendChunkToLog(array $chunkData, Log\Model $log): void
    {
        $log->setResponse(($log->getResponse() ?? '') . $chunkData['output']);

        foreach ($chunkData['data'] as $item) {
            $model = null;
            $inputTokens = null;
            $outputTokens = null;

            // Model, input and output tokens depend on the endpoint
            $model = $item['response']['model'] ?? $item['model'] ?? null;
            $inputTokens = $item['response']['usage']['input_tokens'] ?? $item['usage']['prompt_tokens'] ?? null;
            $outputTokens = $item['response']['usage']['output_tokens'] ?? $item['usage']['completion_tokens'] ?? null;

//            if ($type === self::FEATURE_CHAT) {
//                $model = $item['model'] ?? null;
//                $inputTokens = $item['usage']['prompt_tokens'] ?? null;
//                $outputTokens = $item['usage']['completion_tokens'] ?? null;
//            } else {
//                $model = $item['response']['model'] ?? null;
//                $inputTokens = $item['response']['usage']['input_tokens'] ?? null;
//                $outputTokens = $item['response']['usage']['output_tokens'] ?? null;
//            }

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
}
