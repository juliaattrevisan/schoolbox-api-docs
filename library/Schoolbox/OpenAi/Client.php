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
     * Constructor
     *
     * @param HttpClient $httpClient The HTTP client, for making requests to the AI provider.
     * @param string     $endpoint   The base endpoint for the API provider.
     * @param string     $apiKey     The API key which must be provided to the provider, in order to authenticate
     *                               requests.
     */
    public function __construct(
        private HttpClient $httpClient,
        private string $endpoint,
        private string $apiKey,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function request(string $method, array $payload): ResponseInterface
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
    public function requestStream(string $method, array $payload, ?int $chunkSize = null): iterable
    {
        $chunkSize ??= self::DEFAULT_CHUNK_SIZE;
        $request = new Request(
            $method,
            $this->endpoint,
            $this->getAuthorization() + ['Content-Type' => 'application/json'],
            json_encode($payload),
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
            foreach ($this->readEvents($response->getBody(), $chunkSize) as $stream) {
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
     * @return iterable<EventStream\Message>
     */
    private function readEvents(StreamInterface $stream, int $chunkSize): iterable
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

            // Normalise CRLF to LF
            $data = strtr($data, ["\r\n" => "\n", "\r" => "\n"]);

            $chunkBuffer .= $data;

            // while there's at least one full chunk in the buffer, process it
            while (false !== ($pos = strpos($chunkBuffer, "\n\n"))) {
                yield EventStream\Message::fromString(substr($chunkBuffer, 0, $pos));

                $chunkBuffer = substr($chunkBuffer, $pos + 2);
//                $event = $this->parseChunk($chunk);
////                $this->appendChunkToLog($event, $log, $type);
//
////                // Don't echo an 'error' chunk if this isn't going to ckeditor as it won't follow the format of an ApiProblem
////                if ($type === self::FEATURE_CHAT || $event['type'] !== 'error') {
////                    echo "{$chunk}\n\n";
////                }
//
//                // Is this the final event?
//                // There are 3 possible events that indicate the end of a response (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
//                if (in_array($event['type'], ['response.completed', 'response.failed', 'response.incomplete'])) {
//                    $completedEvent = $event['data'][0]['response'];
//                    // Keep reading the stream as there *might* be more later that we can still log to debug issues
//                }

//                // Keep track of whether we're generating an image
//                if ($event['type'] === 'response.image_generation_call.generating') {
//                    $isGeneratingImage = true;
//                } else if ($event['type'] === 'response.image_generation_call.completed') {
//                    $isGeneratingImage = false;
//                }
//
//                // flush PHP buffers
//                if (ob_get_length()) {
//                    @ob_flush();
//                }
//                @flush();
            }
        }

//        // If we have any remaining data in the buffer, process it
        if (strlen(trim($chunkBuffer)) > 0) {
            yield $chunkBuffer;
        }

//        return $completedEvent;
    }
}
