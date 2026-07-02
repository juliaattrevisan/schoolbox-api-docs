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
     * The name of the HTTP header within which the API key is sent.
     */
    public const API_KEY_HEADER = 'x-api-key';

    /**
     * Constructor
     *
     * @param ClientInterface $httpClient The HTTP client to send requests to.
     * @param UriInterface    $baseUri    The base URI of the client.
     * @param string|null     $apiKey     If provided: the API key to use.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private UriInterface $baseUri,
        #[SensitiveParameter] private ?string $apiKey,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function run(JobRequest $request): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                $this->baseUri->withPath('/run'),
                $this->buildBaseOptions() + [RequestOptions::JSON => $request->toPayload()],
            );
        } catch (GuzzleException $e) {
            throw new ClientException('Tools daemon returned an error: ' . $e->getMessage(), $e->getCode(), $e);
        }

        try {
            $responseBody = json_decode($response->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ClientException('Tools daemon response was not parseable: ' . $e->getMessage(), $e->getCode(), $e);
        }

        if (isset($responseBody['jobId'])) {
            return (string)$responseBody['jobId'];
        }
        throw new ClientException('Tools daemon response did not include a jobId');
    }

    /**
     * @inheritdoc
     */
    public function getJobStatus(string $jobId): JobStatus
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUri->withPath("/jobs/$jobId"),
                $this->buildBaseOptions(),
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

    /**
     * @inheritdoc
     */
    public function getJobOutput(string $jobId): JobOutput
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->baseUri->withPath("/jobs/$jobId/output"),
                $this->buildBaseOptions(),
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

    /**
     * Build base options for the request.
     *
     * This will include the API key in its header, if it's set.
     *
     * @return array
     */
    private function buildBaseOptions(): array
    {
        $options = [];
        if (isset($this->apiKey)) {
            $options[RequestOptions::HEADERS][self::API_KEY_HEADER] = $this->apiKey;
        }
        return $options;
    }
}
