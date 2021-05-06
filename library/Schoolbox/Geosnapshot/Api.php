<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Schoolbox\Geosnapshot\Payload\EventPhoto;
use Schoolbox\Geosnapshot\Payload\StudentPhoto;
use Schoolbox\User;


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
     * Last payload we generated, so we can inspect it for diagnostic purposes
     *
     * @var array
     */
    private array $lastPayload;


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
        $headers = [
            'X-API-KEY' => $this->apiKey,
        ];

        return new Client([
            'headers' => $headers,
            'curl' => \Proxy\Server::getInstance()->buildCurlOptions(),
            'timeout' => self::GEOSNAPSHOT_TIMEOUT,
            'http_errors' => false,
        ]);
    }


    /**
     * Find all the photos matching a $viewee's portrait picture
     *
     * Manufacture a payload with the necessary parts and send it out there
     *
     * @param User $viewee
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveStudentPhotos(User $viewee): array
    {
        return $this->retrieve((new StudentPhoto())->build($viewee));
    }


    /**
     * Find all the events matching a given $eventName
     *
     * Manufacture a payload with the necessary parts and send it out there
     *
     * @param string $eventName
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveEventPhotos(string $eventName): array
    {
        return $this->retrieve((new EventPhoto())->build($eventName));
    }


    /**
     * Send a payload to the endpoint
     *
     * @param array $payload
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function retrieve(array $payload): array
    {
        /* Remember it if we need to inspect it for some reason
         */
        $this->lastPayload = $payload;

        /* Portrait contents need to be POSTed, not GETted, despite us not mutating any data
         * in the third party system. We have to send it in json format, otherwise it returns
         * a default set of unfiltered photos and stuff
         *
         * https://docs.guzzlephp.org/en/latest/request-options.html#json
         */
        $response = $this->build()->request('POST', self::GEOSNAPSHOT_ENDPOINT, [
            RequestOptions::JSON => $payload
        ]);

        /* Responds with this tuple: {"url":"https://geosnapshot.com/e/schoolbox-office-re-opening-from-iso-to-posh"}
         */
        if ((int)$response->getStatusCode() === 200) {
            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        }

        throw new \DomainException('Geosnapshot: Invalid response from Geosnapshot; could not decode json response');
    }


    /**
     * @return array
     */
    public function getLastPayload(): array
    {
        return $this->lastPayload;
    }
}