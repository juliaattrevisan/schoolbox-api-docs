<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * A standard client for an AI provider.
 */
class Client implements IClient
{
    private const DEFAULT_CHUNK_SIZE = 128;

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
            $payload,
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
        $request = new Request(
            $method,
            $this->endpoint,
            $this->getAuthorization() + ['Content-Type' => 'application/json'],
            $payload,
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
        if (str_contains($response->getHeaderLine('Content-Type'), 'text/event-stream')) {
            foreach ($this->readStream($response->getBody(), $chunkSize) as $stream) {
                yield $stream;
            }
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
     * @param StreamInterface $stream
     * @param int             $chunkSize
     * @return iterable
     */
    private function readStream(StreamInterface $stream, int $chunkSize): iterable
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
                $chunk       = substr($chunkBuffer, 0, $pos);
                $chunkBuffer = substr($chunkBuffer, $pos + 2);

                // Yield the chunk value
                yield $chunk;

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
