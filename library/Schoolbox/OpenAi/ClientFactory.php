<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use Config\Modules\OpenAI;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Uri;
use UnexpectedValueException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Editor\OpenAI\Log\Mapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for building OpenAI clients.
 *
 * Handles both the OpenAI API, and the Azure OpenAI-compatible API.
 */
class ClientFactory
{
    /**
     * Base URL for OpenAI's API.
     *
     * Note that the version is part of the path.
     */
    private const OPENAI_BASE_URL = 'https://api.openai.com/v1/';

    /**
     * Base URL for Azure's OpenAI-compatible API, when not using a defined
     * deployment.
     *
     * Azure has the concept of resources and deployments:
     * * resources are created per-account, and allow configuration of how that
     *   Azure AI instance may be accessed
     * * deployments are created per-resource, and include the model which is
     *   actually called
     */
    private const AZURE_BASE_URL = 'https://%s.openai.azure.com/openai/';

    /**
     * Base URL for Azure's OpenAI-compatible API, when using a defined
     * deployment.
     */
    private const AZURE_DEPLOYMENT_BASE_URL = 'https://%s.openai.azure.com/openai/deployments/%s/';

    /**
     * Constructor
     *
     * @param IConfigLoader $config     Configuration: used to determine how to create an AI client.
     * @param Mapper        $logMapper  Log: used for logging calls to AI providers.
     * @param HttpClient    $httpClient HTTP client: used for sending calls to AI providers.
     */
    public function __construct(
        private IConfigLoader $config,
        private Mapper $logMapper,
        private HttpClient $httpClient,
    ) {
    }

    /**
     * Build a client for a given feature.
     *
     * @param string&Helper::FEATURE_* $feature The feature to build for.
     * @return IClient
     * @throws UnexpectedValueException if the provider is not implemented, or
     *                                  it is but doesn't implement the feature.
     */
    public function buildForFeature(string $feature): IClient
    {
        switch ($feature) {
            case Helper::FEATURE_IMAGE:
                $apiKey   = $this->config->get(ConfigMapper::NAME_OPENAI_IMAGE_KEY);
                $provider = $this->config->get(ConfigMapper::NAME_OPENAI_IMAGE_ENDPOINT);
                break;
            default:
                $apiKey   = $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_KEY);
                $provider = $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_ENDPOINT);
                break;
        }

        $providerEnum = Provider::tryFrom($provider) ?? Provider::Off;

        // OpenAI: build something standard
        if ($providerEnum === Provider::OpenAi) {
            return $this->buildOpenAi($apiKey);
        }

        // Azure: depends on feature
        if ($providerEnum === Provider::Azure) {
            switch ($feature) {
                case Helper::FEATURE_CHAT:
                    // Chat does use deployment ID
                    return $this->buildAzure(
                        $apiKey,
                        $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_RESOURCE_NAME),
                        $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_DEPLOYMENT_ID),
                        '2023-12-01-preview',
                    );
                case Helper::FEATURE_IMAGE:
                    throw new UnexpectedValueException('Image feature for Azure is not implemented');
                default:
                    return $this->buildAzure(
                        $apiKey,
                        $this->config->get(ConfigMapper::NAME_OPENAI_TEXT_RESOURCE_NAME),
                        null,
                        '2025-03-01-preview',
                    );
            }
        }

        throw new UnexpectedValueException(sprintf('AI provider "%s" is not implemented', $provider));
    }


    /**
     * Build a standard OpenAI client.
     *
     * @param string $apiKey
     * @return IClient
     */
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

    /**
     * Build an OpenAI-compatible Azure client.
     *
     * @param string      $apiKey
     * @param string      $resourceName
     * @param string|null $deploymentId
     * @param string|null $apiVersion
     * @return IClient
     */
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
