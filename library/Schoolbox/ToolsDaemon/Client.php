<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * A client to Schoolbox's tools daemon.
 */
class Client
{
    public function __construct(
        private ClientInterface $httpClient,
        private UriFactoryInterface $uriFactory,
        private string $host,
        private int $port,
    ) {
    }

    public function run(string $tool, array $options, ?string $instanceId = null, ?array $environment = null): void
    {
        $this->httpClient->request(
            'GET',
            $this->buildBaseUrl()->withPath('/run'),
            [
                RequestOptions::JSON => [
                    'tool'        => $tool,
                    'options'     => $options,
                    'instanceId'  => $instanceId,
                    'environment' => $environment,
                ],
            ]
        );
    }

    public function getJobStatus(string $jobId): string
    {
        $response = $this->httpClient->request(
            'GET',
            $this->buildBaseUrl()->withPath("jobs/$jobId"),
        );
        return $response->getBody()->getContents();
    }

    public function getJobOutput(string $jobId): array
    {
        $response = $this->httpClient->request(
            'GET',
            $this->buildBaseUrl()->withPath("jobs/$jobId/status"),
        );
        return json_decode($response->getBody()->getContents(), true);
    }

    private function buildBaseUrl(): UriInterface
    {
        return $this->uriFactory->createUri()
            ->withHost($this->host)
            ->withPort($this->port);
    }
}
