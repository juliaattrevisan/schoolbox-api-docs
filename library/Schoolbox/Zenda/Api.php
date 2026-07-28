<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Zenda;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class Api
{
    /**
     * The Zenda url which gives us a redirect url eventually
     */
    public const string ENDPOINT_REDIRECT = '/api/external/token/v2';


    /**
     * Default timeout is 30 seconds before giving up
     */
    private const int DEFAULT_TIMEOUT = 30;


    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const int NAP_TIMEOUT = 3;


    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const int NUM_RETRY = 3;


    /**
     * @var Client
     */
    private Client $client;


    /**
     * These config vars might expand as we develop
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $host
     */
    public function __construct(private string $clientId, private string $clientSecret, private string $host)
    {
        /* An authentication token is a special encoding of a concatted set of config vars
         */
        $accessToken = (new AccessToken())->generate(
            $this->clientId,
            $this->clientSecret
        );

        $this->client = new Client([
            'headers' => [
                'Authorization' => "Basic {$accessToken}",
                'Content-Type' => 'application/json'
            ],
            'timeout' => self::DEFAULT_TIMEOUT,
            /* Turn this on for now to capture as many error conditions as we can
             * We'll be coding in to the blind to start with
             */
            'http_errors' => true,
            /* See https://docs.guzzlephp.org/en/stable/quickstart.html for usage
             * There's a little thing about relative urls here
             */
            /* TODO Find out from zenda where their endpoint actually exists
             *  We sadly don't know atm; double-down on this if/when we know more.
             */
            'base_uri' => trim($this->host),
        ]);
    }


    /**
     * Ask zenda for a redirect url
     *
     * @param string $endpoint
     * @param array  $params
     * @return array|null
     */
    public function retrieve(string $endpoint, array $params): ?array
    {
        /* Try for each identifier: email first.
         * For each of the possibilities designated by that identifier: Email, OccupEmail, etc.
         * Any one of those could succeed (potentially...)
         *
         * Then repeat the process for phones.
         * For each of the possibilities designated by that identifier: work phone, home phone, etc.
         * Any one of those could succeed (potentially...)
         */
        foreach ($params as $type => $payloads) {
            foreach ($payloads as $payload) {
                /* Mine out an error message if you try to access a thing that doesn't exist
                 */
                $fails = 0;

                /* Try multiple times in case sis dies for a random reason
                 */
                while ($fails < self::NUM_RETRY) {
                    /* Diagnostic to see what it's sending
                     */
//                    new \Alaress_Probe(sprintf(
//                        'type:%s payload:%s fails:%s',
//                        $type,
//                        json_encode($payload),
//                        $fails
//                    ));

                    /* Any exception thrown by guzzle might as well be a reason to nap and retry
                     */
                    try {
                        $response = $this->client->post($endpoint, [
                            RequestOptions::JSON => $payload
                        ]);
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
                            /* Return the first successful doodad we found from Zenda
                             */
                            return json_decode(
                                $response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR
                            );
                        } catch (\JsonException $e) {
                            /* Couldn't decode json, or server refused something, or something else died
                             * noop; just increase the $fails counter
                             */
                        }
                    }

                    $fails++;
                }
            }
        }

        /* You might have retried a LOT of times if you get here.
         *
         * Couldn't decode json, or server refused something, or something else died
         * Guzzle repeatedly failed too many times and kept throwing exceptions when $response
         * was being requested, so I guess you ended up here
         */
        throw new \DomainException('Could not access server, or bad json payload returned.', 0, $e ?? null);
    }
}
