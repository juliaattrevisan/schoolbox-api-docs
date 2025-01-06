<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\RequestOptions;
use League\Flysystem\FilesystemException;
use Schoolbox\Geosnapshot\Payload\EventPhoto;
use Schoolbox\Geosnapshot\Payload\RedactedPhotoList;
use Schoolbox\Geosnapshot\Payload\Photo;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;
use SensitiveParameter;

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
     * A list of guys whose photos have been redacted, along with some other metadata
     *
     * POST to this endpoint
     */
    private const GEOSNAPSHOT_ENDPOINT_REDACTION = 'https://geosnapshot.com/api/v1/master_participants';


    /**
     * Arbitrarily-decided timeout duration
     */
    private const GEOSNAPSHOT_TIMEOUT = 10;


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
    public function __construct(#[SensitiveParameter] private string $apiKey)
    {
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
            'Accept' => '*/*'
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
     * @param User $viewer
     * @param FilesystemProxy $filesystemProxy
     * @return array
     * @throws FilesystemException
     * @throws GuzzleException
     * @throws \DomainException
     * @throws InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrievePhotos(User $viewee, User $viewer, FilesystemProxy $filesystemProxy): array
    {
        return $this->retrieve(self::GEOSNAPSHOT_ENDPOINT, (new Photo($filesystemProxy))->build($viewee, $viewer));
    }


    /**
     * Find all the events matching a given $eventName
     *
     * Manufacture a payload with the necessary parts and send it out there
     *
     * @param string $eventName
     * @param User $viewer
     * @return array
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveEventPhotos(string $eventName, User $viewer): array
    {
        return $this->retrieve(self::GEOSNAPSHOT_ENDPOINT, (new EventPhoto())->build($eventName, $viewer));
    }


    /**
     * Tell Geosnapshot about guys whose photos have been redacted
     *
     * @param string $fqdn
     * @param array  $externalRedactionList
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function transmitRedactionPhotos(string $fqdn, array $externalRedactionList): array
    {
        $payload = (new RedactedPhotoList($fqdn))->build($externalRedactionList);

        return $this->retrieve(self::GEOSNAPSHOT_ENDPOINT_REDACTION, $payload);
    }


    /**
     * Send a payload to the endpoint
     *
     * @param string $endpoint
     * @param array  $payload
     * @return array
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function retrieve(string $endpoint, array $payload): array
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
        $response = $this->build()->request('POST', $endpoint, [
            RequestOptions::JSON => $payload
        ]);

        /* Responds with this tuple: {"url":"https://geosnapshot.com/e/schoolbox-office-re-opening-from-iso-to-posh"}
         */
        if ($response->getStatusCode() === 200) {
            /* Geosnapshot endpoints sometimes just echo back an empty string
             * which is un-json_decodable - just return empty array I guess
             */
            $content = $response->getBody()->getContents();

            if ('' === $content) {
                return [];
            }

            /* Try to decode whatever json payload was given back
             */
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        throw new \DomainException(sprintf(
            'Geosnapshot: Invalid response from Geosnapshot - could not decode json. %s %s',
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));
    }


    /**
     * @return array
     */
    public function getLastPayload(): array
    {
        return $this->lastPayload;
    }
}
