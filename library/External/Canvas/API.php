<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Schoolbox\External;

class API
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $apiSecret;

    /** @var string */
    private $apiHost;

    /**
     * @param string $apiKey
     * @param string $apiSecret
     */
    public function __construct(string $apiKey, string $apiSecret, string $apiHost)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiHost = $apiHost;
    }

    /**
     * Executes a Canvas API call and returns the response body
     *
     * @param string $command
     * @param array  $queryParams
     * @return string
     * @throws External\Exception
     */
    public function request(string $command, array $queryParams = []): string
    {
        $url = 'https://' . $this->apiHost . $command;

        $client = new Client();
        $options = [
            'Authorization' => "Bearer {$this->apiKey}"
        ];

        try {
            $response = $client->request('GET', $url, [RequestOptions::HEADERS => $options, RequestOptions::QUERY => $queryParams]);
        } catch (GuzzleException $e) {
            throw new External\Exception("Unable to get $url: {$e->getMessage()}");
        }
        return $response->getBody()->getContents();
    }
}
