<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs;

use Alaress\Http\Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use SensitiveParameter;
use SimpleXMLElement;

/**
 * Lightweight HTTP client for SOCS APIs with URL usage tracking.
 */
class HttpClient
{
    /**
     * Number of seconds to sleep before retrying a failed SOCS request.
     */
    private const int NAP_TIMEOUT = 3;

    /**
     * Number of times to retry a SOCS request before giving up.
     */
    private const int NUM_RETRY = 3;

    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * Cache lifetime for SOCS calendar feed requests.
     */
    private const int CALENDAR_CACHE_TTL = 1200;

    /**
     * Cache key prefix for SOCS calendar feed requests.
     */
    public const string CALENDAR_CACHE_PREFIX = 'socs-calendar-';
    public const string MUSIC_LESSONS_CACHE_PREFIX = 'socs-music-';

    private Client $client;

    private string $baseUrl;

    // known error strings
    private const NOT_AVAILABLE = 'Not available.';

    /**
     * @param string      $host     Base URL of the SOCS API, without trailing slash.
     * @param string      $schoolId School ID
     * @param string      $token    Schoolbox API token
     * @param Client|null $client
     * @param Cache|null  $cache
     * @param string      $cachePrefix redis prefix used for cache keys
     */
    public function __construct(
        string $host,
        private string $schoolId = '',
        #[SensitiveParameter] private string $token = '',
        ?Client $client = null,
        private ?Cache $cache = null,
        private string $cachePrefix = self::CALENDAR_CACHE_PREFIX,
    ) {
        $this->baseUrl = rtrim($host, '/');
        $this->client = $client ?? new Client([
            RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT,
            RequestOptions::HTTP_ERRORS => false,
        ]);
    }

    /**
     * Perform a GET request to a SOCS endpoint, adding school ID and token.
     *
     * @param string               $path
     * @param array<string,string> $query Non-secret query parameters; ID and key are added automatically.
     *
     * @return SimpleXMLElement
     * @throws \JsonException|\RuntimeException
     */
    public function get(string $path, array $query): SimpleXMLElement
    {
        $url = $this->buildBaseUrl($path);

        $fullQuery = ['ID' => $this->schoolId, 'key' => $this->token] + $query;
        $fails = 0;
        $response = null;

        while (true) {
            try {
                $response = $this->client->request('GET', $url, [
                    RequestOptions::QUERY => $fullQuery,
                    RequestOptions::TIMEOUT => self::DEFAULT_TIMEOUT,
                    RequestOptions::HTTP_ERRORS => false,
                ]);

                // NOT 200 status, assume broken
                if ($response->getStatusCode() !== 200) {
                    throw new \RuntimeException(
                        sprintf(
                            'Bad endpoint %s: %s %s after %s retries. Payload: %s',
                            $path,
                            $response->getStatusCode(),
                            $response->getReasonPhrase(),
                            $fails,
                            json_encode($query, JSON_THROW_ON_ERROR)
                        ),
                        $response->getStatusCode(),
                    );
                }

                /* Successful response from server, but it might not send a valid data payload back
                 * so try to decode it. If it can't decode properly, return null
                 */
                $content = $response->getBody()->getContents();

                if ('' === $content) {
                    throw new \RuntimeException(
                        sprintf(
                            'SOCS endpoint %s: returned an empty payload',
                            $path
                        ),
                        $response->getStatusCode(),
                    );
                }

                // known errors
                // incorrect CREDS:
                if (self::NOT_AVAILABLE === $content) {
                    throw new \RuntimeException(sprintf(
                        'SOCS endpoint returned known error string; aborting. %s',
                        self::NOT_AVAILABLE
                    ));
                }

                $xml = @simplexml_load_string($content);
                if (false === $xml) {
                    throw new \RuntimeException(sprintf(
                        "Unable to parse SOCS XML payload; aborting. First 200 chars: '%s'",
                        substr($content, 0, 200)
                    ));
                }

                return $xml;
            } catch (GuzzleException $e) {
                $fails++;

                if ($fails >= self::NUM_RETRY) {
                    throw new \RuntimeException(
                        sprintf(
                            'Bad endpoint %s: request failed after %s retries. Payload: %s. Error: %s',
                            $path,
                            self::NUM_RETRY,
                            json_encode($query, JSON_THROW_ON_ERROR),
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }
            }
            sleep(self::NAP_TIMEOUT);
        }
    }

    /**
     * Perform a cached GET request to a SOCS endpoint.
     *
     * @param string               $path
     * @param array<string,string> $query Non-secret query parameters; ID and key are added automatically.
     *
     * @return SimpleXMLElement
     * @throws \JsonException|\RuntimeException
     */
    public function getCached(string $path, array $query): SimpleXMLElement
    {
        if (!$this->cache instanceof Cache) {
            return $this->get($path, $query);
        }

        $url = $this->buildBaseUrl($path);
        $fullQuery = ['ID' => $this->schoolId, 'key' => $this->token] + $query;
        $content = $this->cache->request(
            $url . '?' . http_build_query($fullQuery),
            $this->cachePrefix,
            [],
            self::CALENDAR_CACHE_TTL,
        );

        if ('' === $content) {
            throw new \RuntimeException(sprintf(
                'SOCS endpoint %s: returned an empty payload',
                $path
            ));
        }

        if (self::NOT_AVAILABLE === $content) {
            throw new \RuntimeException(sprintf(
                'SOCS endpoint returned known error string; aborting. %s',
                self::NOT_AVAILABLE
            ));
        }

        $xml = @simplexml_load_string($content);
        if (false === $xml) {
            throw new \RuntimeException(sprintf(
                "Unable to parse SOCS XML payload; aborting. First 200 chars: '%s'",
                substr($content, 0, 200)
            ));
        }

        return $xml;
    }

    /**
     * Build an absolute URL for a SOCS endpoint path relative to the configured host.
     */
    private function buildBaseUrl(string $path): string
    {
        if ($path === '') {
            return $this->baseUrl;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
