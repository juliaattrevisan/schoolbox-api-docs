<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\UriInterface;

/**
 * A client to Schoolbox's tools daemon.
 */
class Client implements IClient
{
    /**
     * Constructor
     *
     * @param ClientInterface $httpClient The HTTP client to send requests to.
     * @param UriInterface    $baseUri    The base URI of the client.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private UriInterface $baseUri,
    ) {
    }

    public function run(string $tool, array $options, ?string $instanceId = null, ?array $environment = null): string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUri->withPath('/run'),
                [
                    RequestOptions::JSON => [
                        'tool'        => $tool,
                        'options'     => $options,
                        'instanceId'  => $instanceId,
                        'environment' => $environment,
                    ],
                ]
            );
        } catch (GuzzleException $e) {
            throw new ClientException('Tools daemon returned an error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $responseBody = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Tools daemon response was not parseable: ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (!isset($responseBody['jobId'])) {
            return (string)$responseBody['jobId'];
        }
        throw new ClientException('Tools daemon response did not include a jobId');
    }

    public function getJobStatus(string $jobId): JobStatus
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUri->withPath("jobs/$jobId"),
            );
        } catch (GuzzleException $e) {
            throw new ClientException('Tools daemon returned an error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $responseBody = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Tools daemon response was not parseable: ' . $e->getMessage(), $e->getCode(), $e);
        }

        return new JobStatus(
            $jobId,
            State::tryFrom($responseBody['state'] ?? '') ?? State::UNKNOWN,
            isset($responseBody['result']) ? (string)$responseBody['result'] : null,
            isset($responseBody['error']) ? (string)$responseBody['error'] : null,
        );
    }

    public function getJobOutput(string $jobId): JobOutput
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUri->withPath("jobs/$jobId/status"),
            );
        } catch (GuzzleException $e) {
            throw new ClientException('Tools daemon returned an error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $responseBody = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Tools daemon response was not parseable: ' . $e->getMessage(), $e->getCode(), $e);
        }

        return new JobOutput(
            $jobId,
            isset($responseBody['stdout']) ? (array)$responseBody['stdout'] : [],
            isset($responseBody['stderr']) ? (array)$responseBody['stderr'] : [],
        );
    }
}
