<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Uri;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper;

/**
 * Factory for building OpenAI clients.
 *
 * Handles both the OpenAI API, and the Azure OpenAI-compatible API.
 */
class ClientFactory
{
    private const OPENAI_BASE_URL = 'https://api.openai.com/';

    private const AZURE_BASE_URL = 'https://%s.openai.azure.com/openai/';
    private const AZURE_DEPLOYMENT_BASE_URL = 'https://%s.openai.azure.com/openai/deployments/%s/';

    public function __construct(
        private Mapper $logMapper,
        private HttpClient $httpClient,
    ) {
    }

    public function buildOpenAi(string $apiKey): IClient
    {
        $authHeader = ['Authorization' => 'Bearer ' . $apiKey];
        return new Client(
            $this->httpClient,
            new Uri(self::OPENAI_BASE_URL),
            $authHeader,
            $this->logMapper,
        );
    }

    public function buildAzure(
        string $apiKey,
        string $resourceName,
        ?string $deploymentId,
        ?string $apiVersion,
    ): IClient {
        $endpoint = isset($deploymentId)
            ? sprintf(self::AZURE_DEPLOYMENT_BASE_URL, $resourceName, $deploymentId)
            : sprintf(self::AZURE_BASE_URL, $resourceName);
        $queryParts = isset($apiVersion)
            ? ['api-version' => $apiVersion]
            : [];
        $authHeader = ['api-key' => $apiKey];
        return new Client(
            $this->httpClient,
            new Uri($endpoint)->withQuery(http_build_query($queryParts)),
            $authHeader,
            $this->logMapper,
        );
    }
}
