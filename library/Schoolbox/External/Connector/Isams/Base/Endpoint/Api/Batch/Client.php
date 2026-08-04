<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Batch;

use Alaress_Curl_Url;
use JsonException;
use RuntimeException;
use Schoolbox\Url\Uri;
use SensitiveParameter;

/**
 * Sends authenticated requests to the iSAMS Batch API.
 */
class Client
{
    /**
     * Cached OAuth access token.
     */
    private ?string $accessToken = null;


    /**
     * Unix timestamp at which the cached token expires.
     */
    private int $accessTokenExpiresAt = 0;


    /**
     * Create an iSAMS Batch API client.
     *
     * @param string $host iSAMS school host.
     * @param string $apiKey Batch API key and OAuth client ID.
     * @param string $clientSecret Batch OAuth client secret.
     */
    public function __construct(
        private string $host,
        #[SensitiveParameter] private string $apiKey,
        #[SensitiveParameter] private string $clientSecret
    ) {
    }


    /**
     * Retrieve a Batch API resource.
     *
     * @param string $path Batch endpoint path.
     * @return string Response body.
     * @throws RuntimeException When authentication or retrieval fails.
     * @throws \DomainException When the endpoint URL is invalid.
     * @throws \InvalidArgumentException When the endpoint URL is invalid.
     */
    public function get(string $path): string
    {
        return $this->request($path, false);
    }


    /**
     * Check a Batch API resource without downloading its response body.
     *
     * @param string $path Batch endpoint path.
     * @return void
     * @throws RuntimeException When authentication or retrieval fails.
     * @throws \DomainException When the endpoint URL is invalid.
     * @throws \InvalidArgumentException When the endpoint URL is invalid.
     */
    public function check(string $path): void
    {
        $this->request($path, true);
    }


    /**
     * Send a Batch API request with OAuth retry handling.
     *
     * @param string $path Batch endpoint path.
     * @param bool $head Whether to omit the response body.
     * @return string Response body.
     * @throws RuntimeException When authentication or retrieval fails.
     * @throws \DomainException When the endpoint URL is invalid.
     * @throws \InvalidArgumentException When the endpoint URL is invalid.
     */
    private function request(string $path, bool $head): string
    {
        $usesOAuth = $this->clientSecret !== '';
        $maximumAttempts = $usesOAuth ? 2 : 1;

        for ($attempt = 0; $attempt < $maximumAttempts; $attempt++) {
            $options = $head ? [CURLOPT_NOBODY => true] : [];

            if ($usesOAuth) {
                $options[CURLOPT_HTTPHEADER] = [
                    'Authorization: Bearer ' . $this->getAccessToken(),
                ];
            }

            $curl = $this->createCurl((string)$this->createBatchUrl($path));

            if ($options !== []) {
                $curl->addOptions($options);
            }

            $curl->exec();

            if ($curl->getResponseCode() === 401 && $usesOAuth && $attempt === 0) {
                $this->accessToken = null;
                $this->accessTokenExpiresAt = 0;

                continue;
            }

            $this->assertBatchResponseSucceeded($curl);

            return $curl->getResult();
        }

        throw new RuntimeException('iSAMS Batch API authorization failed after refreshing the OAuth token.');
    }


    /**
     * Build a Batch endpoint URL containing the existing API key.
     *
     * @param string $path Batch endpoint path.
     * @return Uri Batch endpoint URL.
     * @throws \DomainException When the endpoint URL is invalid.
     * @throws \InvalidArgumentException When the endpoint URL is invalid.
     */
    private function createBatchUrl(string $path): Uri
    {
        return (new Uri())
            ->withScheme('https')
            ->withHost(rtrim($this->host, "/ \n\r\t\v\x00"))
            ->withPath('/' . ltrim($path, '/'))
            ->appendQueryParameters(['apikey' => $this->apiKey]);
    }


    /**
     * Return a cached access token or request a new one.
     *
     * @return string OAuth access token.
     * @throws RuntimeException When token acquisition fails.
     * @throws \DomainException When the token URL is invalid.
     * @throws \InvalidArgumentException When the token URL is invalid.
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken !== null && $this->getCurrentTime() < $this->accessTokenExpiresAt) {
            return $this->accessToken;
        }

        $curl = $this->createCurl((string)$this->createTokenUrl());
        $curl->addOptions([
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'client_id' => $this->apiKey,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
                'scope' => 'apiv1',
            ]),
        ]);
        $curl->exec();

        $this->assertTokenResponseSucceeded($curl);

        try {
            $response = json_decode($curl->getResult(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('iSAMS Batch OAuth returned an invalid token response.');
        }

        $accessToken = $response['access_token'] ?? null;
        $expiresIn = $response['expires_in'] ?? null;
        $tokenType = $response['token_type'] ?? 'Bearer';

        if (
            !is_string($accessToken)
            || $accessToken === ''
            || !is_numeric($expiresIn)
            || (float)$expiresIn <= 0
            || !is_string($tokenType)
            || strcasecmp($tokenType, 'Bearer') !== 0
        ) {
            throw new RuntimeException('iSAMS Batch OAuth returned an invalid token response.');
        }

        $this->accessToken = $accessToken;
        $this->accessTokenExpiresAt = $this->getCurrentTime() + (int)$expiresIn;

        return $this->accessToken;
    }


    /**
     * Build the school-specific OAuth token URL.
     *
     * @return Uri OAuth token URL.
     * @throws \DomainException When the token URL is invalid.
     * @throws \InvalidArgumentException When the token URL is invalid.
     */
    private function createTokenUrl(): Uri
    {
        return (new Uri())
            ->withScheme('https')
            ->withHost(rtrim($this->host, "/ \n\r\t\v\x00"))
            ->withPath('/auth/connect/token');
    }


    /**
     * Ensure a Batch endpoint response was successful.
     *
     * @param Alaress_Curl_Url $curl Executed Batch request.
     * @return void
     * @throws RuntimeException When the response was unsuccessful.
     */
    private function assertBatchResponseSucceeded(Alaress_Curl_Url $curl): void
    {
        if ($curl->getErrors() !== []) {
            throw new RuntimeException('iSAMS Batch API request failed due to a transport error.');
        }

        $responseCode = $curl->getResponseCode();

        if ($responseCode === 401) {
            if ($this->clientSecret === '') {
                throw new RuntimeException(
                    'iSAMS Batch API authorization failed. Configure the Batch OAuth client secret.'
                );
            }

            throw new RuntimeException('iSAMS Batch API authorization failed after refreshing the OAuth token.');
        }

        if ($responseCode < 200 || $responseCode >= 300) {
            throw new RuntimeException(
                sprintf('iSAMS Batch API request failed with response code %d.', $responseCode),
                $responseCode
            );
        }
    }


    /**
     * Ensure an OAuth token response was successful.
     *
     * @param Alaress_Curl_Url $curl Executed token request.
     * @return void
     * @throws RuntimeException When the response was unsuccessful.
     */
    private function assertTokenResponseSucceeded(Alaress_Curl_Url $curl): void
    {
        if ($curl->getErrors() !== []) {
            throw new RuntimeException('iSAMS Batch OAuth token request failed due to a transport error.');
        }

        $responseCode = $curl->getResponseCode();

        if ($responseCode < 200 || $responseCode >= 300) {
            throw new RuntimeException(
                sprintf('iSAMS Batch OAuth token request failed with response code %d.', $responseCode),
                $responseCode
            );
        }
    }


    /**
     * Create the curl request wrapper.
     *
     * @param string $url Request URL.
     * @return Alaress_Curl_Url Curl request wrapper.
     */
    protected function createCurl(string $url): Alaress_Curl_Url
    {
        return new Alaress_Curl_Url($url);
    }


    /**
     * Return the current Unix timestamp.
     *
     * @return int Current Unix timestamp.
     */
    protected function getCurrentTime(): int
    {
        return time();
    }
}
