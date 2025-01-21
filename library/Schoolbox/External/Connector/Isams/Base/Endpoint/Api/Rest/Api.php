<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Rest;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\Application\Exception\ApiException;
use Schoolbox\Application\Exception\RateLimitException;
use Schoolbox\Url\Uri;
use SensitiveParameter;

/**
 * Class Api
 *
 * @package Schoolbox\External\Connector\IsamsApi\Base\Endpoint
 */
class Api
{
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
    private const NUM_RETRY = 5;


    /**
     * Jitter litter that gets appended to an exponential backoff
     * to kind of randomise when a request can be retried, to prevent
     * multiple requests inevitably occurring again at the same time
     * to cause another rate limit
     */
    private const JITTER_LOWER = 0;
    private const JITTER_UPPER = 3;

    private HttpClient $httpClient;
    private Uri $host;

    private array $jwks = [];

    /**
     * @param string $apiHost
     * @param string $clientId
     * @param string $clientSecret
     */
    public function __construct(
        private string $apiHost,
        private string $clientId,
        #[SensitiveParameter] private string $clientSecret,
    ) {
        $this->httpClient = new HttpClient();
        $this->host = new Uri($this->apiHost);

        /* Incur a first-use cost upon construction. Normally we wouldn't be doing that here,
         * instead incurring the cost upon first-time usage of $this->>getRequest() in order
         * to reduce the workload of an instance of this class. I suppose it was justified by
         * this class having no purpose other than to make api requests - except that you will,
         * in some circumstances, have obtained a token without needing to do an api request.
         */
        $this->getToken();
    }

    /**
     * Retrieve Bearer Token from the OIDC
     *
     * @return string
     * @throws ClientExceptionInterface if something went wrong fetching JWKS
     * @throws InvalidArgumentException if key set or OIDC discovery couldn't be
     *                                  written to or read from cache
     * @throws \JsonException
     * @phpstan-ignore-next-line - InvalidArgumentException IS throwable
     */
    private function getToken(): string
    {
        if (!empty($this->jwks) && $this->jwks['expires'] > time()) {
            return $this->jwks['access_token'];
        }
        // first, need to exchange client id/secret for an access token
        $this->jwks = $this->request(
            '/auth/connect/token',
            'POST',
            [
                RequestOptions::FORM_PARAMS => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                    'scope' => 'restapi',
                ],
            ],
            false,
        );

        $this->jwks['expires'] = time() + $this->jwks['expires_in'];

        return $this->jwks['access_token'];
    }

    /**
     * actually hit the API and do some basic error checking and return data sanitization
     *
     * @param string $path
     * @param string $method
     * @param array $options
     * @param bool $requireToken
     * @param bool $raw - return raw data, not json decoded
     * @return mixed
     * @throws GuzzleException
     */
    public function request(string $path, string $method, array $options = [], bool $requireToken = true, bool $raw = false): mixed
    {
        // if we have a bearer token, then use it.
        if ($requireToken) {
            $options = array_merge(
                $options,
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => 'Bearer ' . $this->getToken(),
                    ]
                ]
            );
        }

        /* Mine out an error message if you try to access a thing that doesn't exist
         */
        $fails = 0;

        /* Try multiple times in case sis dies for a random reason
         */
        while ($fails < self::NUM_RETRY) {
            try {
                $response = $this->httpClient
                    ->request($method, $this->host->withPath($path), $options);

                /* Rate Limit exceeded; take a nap for a while before trying again
                 */
                if ($response->getStatusCode() === 429) {
                    throw new RateLimitException();
                }

                /* I guess we succeeded, which should be the case most of the time,
                 * but we should check for other errors in the caller function to decide
                 * what happens next and how those errors might be logged/presented to users
                 */
                if (\in_array($response->getStatusCode(), [200, 201, 202], true)) {
                    if ($raw) {
                        return $response->getBody()->getContents();
                    }
                    return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                }


                /* Arbitrary default case for now
                 * Something occurred that we haven't accounted for just now
                 *
                 * Can develop this further as per suds tass, to remember payloads etc.
                 * for diagnostics
                 */
                throw new ApiException(sprintf(
                    "iSAMS failed to retrieve(): %s %s - %s %s",
                    $method,
                    $path,
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                ), $response->getStatusCode());
            } catch (RateLimitException $e) {
                $jitter = 0;

                /* If we've failed previously, jitter litter some extra seconds
                 * to randomise a bit more so we don't all try to start again
                 * at the same time (always naps 5sec the first time we fail)
                 */
                if ($fails) {
                    $jitter = random_int(self::JITTER_LOWER, self::JITTER_UPPER);
                }

                /* Rate limit reached; retry after napping
                 */
                $fails++;

                /* "some random jitter" is randomly between 0 and 3 sec by default
                 *
                 * Sleep for 3+2 seconds
                 * then 3+4 seconds (2^2) + some random jitter,
                 * then 3+8 seconds (2^3) + some random jitter,
                 * then 3+16 seconds (2^4) + some random jitter,
                 * then 3+32 seconds (2^5) + some random jitter...
                 *
                 * ...then give up after multiple full rate limited cycles
                 */
                $napTime = self::NAP_TIMEOUT + pow(2, $fails) + $jitter;

                /* Back to sleep Jeffrey
                 */
                sleep($napTime);

                continue;
            } catch (GuzzleException $e) {
                /* 404 Not Found for images (raw mode), we should just return null, not throw an exception
                 */
                if ($raw && $e->getCode() === 404) {
                    return null;
                }

                /* Guzzle failed for some reason
                 */
                throw $e;
            }
        }

        /* Couldn't recover
         */
        throw new ApiException(sprintf(
            '%s %s failed after %s retries',
            $method,
            $path,
            self::NUM_RETRY
        ));
    }
}
