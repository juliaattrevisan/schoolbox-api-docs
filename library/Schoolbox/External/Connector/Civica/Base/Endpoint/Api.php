<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use SensitiveParameter;
use Schoolbox\External\Connector\Civica\Base\Endpoint\AccessToken\Generator as AccessTokenGenerator;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint
 */
class Api implements IApi
{
    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const NUM_RETRY = 3;



    /**
     * Decide how big each page size should be
     * Civica's default response is 1000
     */
    private const PAGE_SIZE = 1000;


    /**
     * Maximum number of pages to obtain from an endpoint
     * to safeguard against infinite loops
     */
    private const MAX_PAYLOADS = 100;

    /**
     * @var ApiUsage
     */
    private ApiUsage $apiUsage;


    /**
     * @var Client
     */
    private Client $client;

    /**
     * A Veracross Access Token generator, in case you needed to
     * make a new one somewhere along the way
     *
     * @var AccessTokenGenerator
     */
    private AccessTokenGenerator $accessTokenJurisdictionGenerator;
    private AccessTokenGenerator $accessTokenSchoolGenerator;


    /**
     * The base uri that consists of host and school identifier
     * Paginated data can appear separately in ApiUsage
     *
     * @var string
     */
    private string $baseUri = '';


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    public function __construct(string $host, #[SensitiveParameter] string $apiKey)
    {
        $this->client = $this->initClient($host, $apiKey, self::DEFAULT_TIMEOUT);

        /* Record some stats about api usage
         * Turn this into an Observer pattern if you want to record more details
         */
        $this->apiUsage = new ApiUsage($this->baseUri);
    }


    /**
     * Set up guzzle client
     *
     * @param string $host
     * @param string $dsn
     * @param int    $timeout
     * @return Client
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(string $host, #[SensitiveParameter] string $dsn, int $timeout): Client
    {
        $credentials = (new Codec\DSN())->decode($dsn);

        /* Enforce slash in the endpoint prefix
         *
         * Remember this, because we need to remove it in ApiUsage
         * for it to report unique endpoints properly
         *
         * other than the license/status endpoint, all other endpoints require a schoolID, so dump that in now.
         *
         * Ensure this baseUri ends in just one /
         */
        $this->baseUri = sprintf(
            '%s/api/%s/',
            rtrim($host, '/'),
            $credentials['schoolid'],
        );


        /* Access Token Generator
         */
        $this->accessTokenJurisdictionGenerator = new AccessTokenGenerator(
            $credentials['idp'],
            $credentials['username'],
            $credentials['password']
        );
//        $this->accessTokenSchoolGenerator = new AccessTokenGenerator(
//            $credentials['idp'],
//            $credentials['usernameschool'],
//            $credentials['passwordschool']
//        );
        $this->accessTokenSchoolGenerator = $this->accessTokenJurisdictionGenerator;

        $accessToken = ($this->accessTokenJurisdictionGenerator->generate())->getToken();

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json'
        ];

        return new Client([
            'headers' => $headers,
            'timeout' => $timeout,
            'http_errors' => false,

            /* See https://docs.guzzlephp.org/en/stable/quickstart.html for usage
             * There's a little thing about relative urls here
             */
            'base_uri' => $this->baseUri,
        ]);
    }

    /**
     * Yield one page of the return payload per iteration,
     * spawning new payloads while Civica has data for it
     *
     * You'll incur an API request cost when payload size equals page size,
     * and the next request is empty. This is by Civica design, sadly,
     * and you'll have paid for an empty payload. This is because when the
     * payload == page size, you can't tell if there's more or if that's
     * exactly the amount there was.
     *
     * @param string $method
     * @param string $endpoint
     * @param bool   $jurisdiction are we getting data from a school endpoint or Jurisdiction
     * @param array  $params
     * @param int    $pageSize
     * @return \Generator
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieve(string $method, string $endpoint, bool $jurisdiction = false, array $params = [], int $pageSize = self::PAGE_SIZE): \Generator
    {
        $pageNum = 1;
        $payload = null;
        $n = 0;

        /* Iterate all pages from Civica.
         *
         * 1. Check base case, to prevent infinite loopage.
         * 2. If you're requesting your first payload
         * 3. The payload size == page size
         */
        while (($n < self::MAX_PAYLOADS) && (!$payload || \count($payload['values']) === self::PAGE_SIZE)) {
            $params = array_merge($params, [
                'pageNumber' => $pageNum,
                'pageSize'  => $pageSize
            ]);
            $payload = $this->retrieveOne($method, $endpoint, $jurisdiction, $params);

            $pageNum++;
            $n++;

            yield $payload;
        }
    }


    /**
     * @inheritDoc
     *
     * @param string $method
     * @param string $endpoint
     * @param bool $jurisdiction are we getting data from a school endpoint or Jurisdiction
     * @param array  $params
     * @return array
     * @throws \RuntimeException
     * @throws \DomainException
     * @throws \JsonException
     */
    public function retrieveOne(string $method, string $endpoint, bool $jurisdiction = false, array $params = []): array
    {
        $response = null;
        if ($jurisdiction) {
            $endpoint = sprintf("%s/Jurisdiction", $endpoint);
            $accessToken = ($this->accessTokenJurisdictionGenerator->generate())->getToken();
        } else {
            $accessToken = ($this->accessTokenSchoolGenerator->generate())->getToken();
        }

        /* Record endpoint usage
         */
        $this->apiUsage->record($endpoint);

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json'
        ];

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            /* Any exception thrown by guzzle might as well be a reason to nap and retry
             */
            try {
                $options = ['headers' => $headers];

                if (!empty($params)) {
                    $options[RequestOptions::QUERY] = $params;

                    /* Debugging:
                     * Find out what guzzle calculated as the endpoint + query string params
                     */
//                    $options['on_stats'] = function (TransferStats $stats) use (&$url) {
//                        $url = $stats->getEffectiveUri();
//                    };
                }

                $response = $this->client->request($method, $endpoint, $options);

                /* Debugging:
                 * Find out what guzzle calculated as the endpoint + query string params
                 */
//                print_r($url);
            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                $fails++;

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            /* Successful response from server, but it might not send a valid data payload back
             * so try to decode it. If it can't decode properly, return null
             */
            if ($response->getStatusCode() === 200) {
                try {
                    $content = $response->getBody()->getContents();

                    if ('' === $content) {
                        return [];
                    }

                    return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    /* Couldn't decode json, or server refused something, or something else died
                     * noop; just increase the $fails counter
                     */
                }
            } elseif (404 === $response->getStatusCode()) {
                try {
                    /*
                     * when iterating through pages and hit one with no data, you will get a 404 with some helpful body data
                     */
                    $content = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                    if ('Endpoint was found but returned no results.' === $content['Error']) {
                        return [];
                    }
                } catch (\JsonException $e) {
                    /* Couldn't decode json, or server refused something, or something else died
                     * noop; just increase the $fails counter
                     */
                }
            }

            $fails++;
        }

        /* We tried multiple times and failed multiple times
         * This might as well have died
         */
        if ($response) {
            throw new \RuntimeException(
                sprintf(
                    'Bad endpoint %s %s: %s %s after %s retries. Body: %s. Payload: %s',
                    $method,
                    $endpoint,
                    $response->getStatusCode(),
                    $response->getReasonPhrase(),
                    self::NUM_RETRY,
                    $response->getBody()->getContents(),
                    json_encode($params, JSON_THROW_ON_ERROR)
                ),
                $response->getStatusCode(),
                $e ?? null
            );
        }

        /* Couldn't decode json, or server refused something, or something else died
         * Guzzle repeatedly failed too many times and kept throwing exceptions when $response
         * was being requested, so I guess you ended up here
         */
        throw new \DomainException('Could not access server, or bad json payload returned.', 0, $e ?? null);
    }


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage
    {
        return $this->apiUsage;
    }
}
