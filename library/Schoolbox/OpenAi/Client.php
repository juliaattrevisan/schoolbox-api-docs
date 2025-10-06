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

/**
 * A standard client for an AI provider.
 */
class Client implements IClient
{
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
    public function requestStream(string $method, array $payload): iterable
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
            // buffer reads from body, expecting chunks
        }
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
}
