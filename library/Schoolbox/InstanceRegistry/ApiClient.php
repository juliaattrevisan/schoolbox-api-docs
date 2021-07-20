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

/**
 * Class ApiClient
 *
 * Helper class for making API calls to the Instance Registry API.
 */
class ApiClient
{
    /**
     * @var string
     */
    private string $apiUrl;

    /**
     * ApiClient constructor.
     *
     * @param string $apiUrl
     */
    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
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
            $requestUrl    = "/api/releases/schoolbox/latest";
            $response      = $this->makeRequest($requestUrl);
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
            $cache = new Cache();
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
        throw new DomainException("Unable to decode Weather API response: {$decodedResponse}");
    }
}
