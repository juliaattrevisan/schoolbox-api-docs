<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Uri;
use JsonException;
use Schoolbox\Admin\OpenAI;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\OpenAi\Session\Log\Mapper;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
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
     * @param IConfigLoader       $config         Configuration: used to determine how to create an AI client.
     * @param Mapper              $logMapper      Log: used for logging calls to AI providers.
     * @param HttpClient          $httpClient     HTTP client: used for sending calls to AI providers.
     * @param SessionMapper       $sessionMapper  Session: used for ensuring the parent ai_session row exists.
     */
    public function __construct(
        private IConfigLoader $config,
        private Mapper $logMapper,
        private HttpClient $httpClient,
        private SessionMapper $sessionMapper,
    ) {
    }

    /**
     * Build a client for a given feature.
     *
     * @param Feature $feature The feature to build for.
     * @return IClient
     * @throws Exception\Runtime if something went wrong with building the
     *                           provider, e.g.:
     *                           * it is not implemented
     *                           * it is but doesn't implement the feature
     */
    public function buildForFeature(Feature $feature): IClient
    {
        // Each feature maps to a config which holds the API key and provider
        $configKey = OpenAI\Config::ALL_MODULES[$feature->value] ?? null;
        if ($configKey === null) {
            throw new Exception\FeatureNotImplemented(sprintf('AI feature "%s" is not implemented', $feature->value));
        }
        try {
            $aiConfig = new OpenAI\Config()->decode((string)$this->config->get($configKey));
        } catch (JsonException $e) {
            throw new Exception\FeatureNotImplemented(sprintf('AI feature "%s" configuration cannot be parsed', $feature->value), 0, $e);
        }
        $apiKey   = $aiConfig[OpenAi\Config::SECTION_GENERAL][OpenAI\Config::FIELD_AIKEY]['value'] ?? '';
        $provider = $aiConfig[OpenAI\Config::FIELD_PROVIDER]['value'] ?? '';

        $providerEnum = Provider::tryFrom($provider) ?? Provider::Off;

        // Off: exit with a notice
        if ($providerEnum === Provider::Off) {
            throw new Exception\AgentNotEnabled('AI queries are not enabled for this feature.');
        }

        // OpenAI: build standard client
        if ($providerEnum === Provider::OpenAi) {
            return $this->buildOpenAi($apiKey);
        }

        // Azure: build client depending on feature
        if ($providerEnum === Provider::Azure) {
            switch ($feature) {
                case Feature::CHAT:
                    // Chat does use deployment ID
                    return $this->buildAzure(
                        $apiKey,
                        $aiConfig[OpenAi\Config::SECTION_AZURE][OpenAI\Config::FIELD_RESOURCE_NAME]['value'] ?? '',
                        $aiConfig[OpenAi\Config::SECTION_AZURE][OpenAI\Config::FIELD_DEPLOYMENT_ID]['value'] ?? '',
                        '2023-12-01-preview',
                    );
                case Feature::IMAGE:
                    throw new Exception\FeatureNotSupportedByProvider('Image feature for Azure is not implemented');
                default:
                    // Default does not use deployment ID
                    return $this->buildAzure(
                        $apiKey,
                        $aiConfig[OpenAi\Config::SECTION_AZURE][OpenAI\Config::FIELD_RESOURCE_NAME]['value'] ?? '',
                        null,
                        '2025-03-01-preview',
                    );
            }
        }

        throw new Exception\ProviderNotImplemented(sprintf('AI provider "%s" is not implemented', $provider));
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
            $this->sessionMapper,
        );
    }


    /**
     * Build an OpenAI client for use with the Moderations API
     * https://platform.openai.com/docs/api-reference/moderations
     *
     * This is independent as schools can't control the moderation key,
     * it is set by us for all schools and can be used across different AI features.
     *
     * @return IClient
     */
    public function buildOpenAiModeration(): IClient
    {
        return $this->buildOpenAi($this->config->get(ConfigMapper::NAME_AI_MODERATION_KEY));
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
            $this->sessionMapper,
        );
    }
}
