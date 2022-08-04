<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\InstanceRegistry;

use Exception;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\IConfigLoader;

/**
 * Class ApiClient
 *
 * Helper class for making API calls to the Instance Registry API.
 */
class ApiClient
{
    /**
     * endpoint where we can find version information about the current latest
     * available version of schoolbox
     */
    private const API_LATEST_RELEASE_URL = '/api/releases/schoolbox/latest';

    /**
     * @var int duration in seconds to cache responses
     */
    private int $cachedTime;

    /**
     * Schoolbox configuration
     */
    private IConfigLoader $config;

    /**
     * @var string
     */
    private string $apiUrl;

    /**
     * ApiClient constructor.
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config     = $config;
        $this->apiUrl     = $this->config->get('instance_registry_url');
        $this->cachedTime = (int)$this->config->get('instance_registry_api_caching');
    }

    /**
     * grab the latest full release from the instance registry
     *
     * @return string
     * @throws DomainException
     */
    public function getLatestRelease(): string
    {
        try {
            $requestUrl    = self::API_LATEST_RELEASE_URL;
            $response      = $this->makeRequest($requestUrl);
            if (!isset($response['name'])) {
                throw new DomainException('Instance Registry API latest release missing');
            }
            $latestRelease = $response['name'];
        } catch (Exception $e) {
            throw new DomainException('Instance Registry API latest release missing');
        }

        return $latestRelease;
    }

    /**
     * fetch information from the instance registry
     *
     * @param string $requestUrl
     * @return array
     * @throws DomainException
     */
    private function makeRequest(string $requestUrl): array
    {
        // Cache the request
        try {
            $cache = new Cache($this->cachedTime);
            $cache->init("{$this->apiUrl}{$requestUrl}");
            $response = $cache->getStreamContents();
        } catch (InvalidArgumentException $e) {
            throw new DomainException('Invalid Instance Registry API endpoint');
        }

        // Attempt to json decode the response body
        try {
            $decodedResponse = json_decode($response, true);
        } catch (RuntimeException $e) {
            throw new DomainException('Unable to read Instance Registry API response', 0, $e);
        }

        /* If the api wasn't correctly configured, $responseData returns an array
         * containing an error message
         */
        if (isset($decodedResponse['error'])) {
            throw new DomainException("Instance Registry API error: {$decodedResponse['error']['info']}");
        }

        // If this is an array, we can assume we got some data back
        if (is_array($decodedResponse)) {
            return $decodedResponse;
        }

        // If it isn't, it either failed to decode or we hit an incorrect server endpoint, but that server endpoint returned a 2xx, likely
        // with a HTML payload. This happens for example if you hit a login wall, which redirected you to a login page.
        throw new DomainException("Unable to decode Instance Registry API response: {$decodedResponse}");
    }
}
