<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Paperly;

use SensitiveParameter;
use DomainException;
use GuzzleHttp\Client;
use RuntimeException;
use JsonException;
use Schoolbox\User;

class Api
{
    private const PAPERLY_API_VIEW_IFRAME = '/api/v1/schoolbox/dashboard-widget?externalId=%u&role=%s';
    private const MAX_RETRY = 5;
    private const NAP_TIMEOUT = 3;
    private Client $client;
    private User $paperlyUser;

    /**
     * Constructor.
     *
     * @param   null|string  $apiHost
     * @param   null|string  $apiKey
     * @param   User    $user
     * @throws  DomainException
     */
    public function __construct(
        private ?string $apiHost,
        #[SensitiveParameter] private ?string $apiKey,
        private User $user
    ) {
        /*
         * If Paperly not set, throws out warning
         */
        if ('' === (string)$this->apiKey || '' === (string)$this->apiHost) {
            throw new DomainException('Paperly is not configured; please check settings');
        }
        $this->paperlyUser = $this->user;
        $this->client = $this->initClient($this->apiHost, $this->apiKey);
    }
    /**
     * Set up Guzzle client
     * @param   string $apiHost
     * @param   string $apiKey
     * @return  Client
     * @throws  \GuzzleHttp\Exception\InvalidArgumentException
     */
    private function initClient(
        string $apiHost,
        #[\SensitiveParameter] string $apiKey
    ) {

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
        ];

        return new Client([
                              'headers' => $headers,
                              'base_uri' => $apiHost,
                              'http_errors' => false,
                          ]);
    }
    /**
     * Retrieve data from the API endpoint
     *
     * @return  null|array
     * @throws  DomainException
     * @throws  RuntimeException if the retrieval of the endpoint failed
     * @throws  \JsonException
     */
    public function retrievePaperlyIframeView(): ?array
    {

        $response = null;
        $failed = 0;

        $paperlyAPIEndpoint = sprintf(
            self::PAPERLY_API_VIEW_IFRAME,
            $this->paperlyUser->getId(),
            $this->paperlyUser->getRole()->isStaff() ? 'teacher' : $this->paperlyUser->getRole()->getType()
        );

        while ($failed < self::MAX_RETRY) {
            try {
                $response = $this->client->request('GET', $paperlyAPIEndpoint);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $failed++;

                sleep(self::NAP_TIMEOUT);

                continue;
            }

            //In case the rate limit exceeded, kill operation and wait to try again
            if ($response->getStatusCode() === 429) {
                throw new RateLimitException(
                    'Rate limit exceeded on Paperly endpoint. Please try again later.',
                );
            }

            if ($response->getStatusCode() === 200) {
                try {
                    return json_decode(
                        $response->getBody()->getContents(),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (JsonException $e) {
                    //In case the return is a bad JSON payload try to call the API again
                }
            }
            $failed++;
        }

        throw new RuntimeException(
            sprintf(
                'Bad endpoint %s: %s %s after %s retries.',
                $paperlyAPIEndpoint,
                $response->getStatusCode(),
                $response->getReasonPhrase(),
                self::MAX_RETRY
            )
        );
    }
}
