<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use GuzzleHttp\Client;


/**
 * Class Api
 *
 * Establish connectivity to the Geosnapshot place
 *
 * @package Schoolbox\Geosnapshot
 */
class Api
{

    /**
     * POST to this endpoint
     */
    private const GEOSNAPSHOT_ENDPOINT = 'https://geosnapshot.com/api/v1/embeds/org_photos';


    /**
     * Arbitrarily-decided timeout duration
     */
    private const GEOSNAPSHOT_TIMEOUT = 10;


    /**
     * @var string
     */
    private string $apiKey;


    /**
     * Api constructor.
     *
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }


    /**
     * Set up that client
     *
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function build(): Client
    {
        $headers = array_combine(
            ['X-API-KEY'],
            [$this->apiKey]
        );

        return new Client([
            'headers' => $headers,
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'timeout' => self::GEOSNAPSHOT_TIMEOUT,
            'http_errors' => false,
        ]);
    }


    /**
     * @param array $payload
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveStudentPhotos(array $payload): array
    {
        $response = $this->build()->request('POST', self::GEOSNAPSHOT_ENDPOINT, $payload);

        /* Responds with this tuple: {"url":"https://geosnapshot.com/e/schoolbox-office-re-opening-from-iso-to-posh"}
         */
        if ((int)$response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        throw new \DomainException('Geosnapshot: Invalid response from Geosnapshot; could not decode json response');
    }
}