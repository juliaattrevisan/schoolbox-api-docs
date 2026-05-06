<?php

declare(strict_types=1);

namespace Schoolbox\Socs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

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

    private Client $client;

    private string $baseUrl;

    /** @var array<string,int> */
    private array $urlUsage = [];

    /**
     * @param string $host Base URL of the SOCS API, without trailing slash.
     * @param string $schoolId School ID
     * @param string $token Schoolbox API token
     */
    public function __construct(string $host, private string $schoolId = '', #[SensitiveParameter] private string $token = '')
    {
        $this->baseUrl = rtrim($host, '/');
        $this->client = new Client([
            'timeout' => 300,
            'http_errors' => false,
        ]);
    }

    /**
     * Perform a GET request to a SOCS endpoint, adding school ID and token.
     *
     * @param array<string,string> $query Non-secret query parameters; ID and key are added automatically.
     *
     * @return ResponseInterface The HTTP response.
     * @throws GuzzleException
     */
    public function get(string $path, array $query, LoggerInterface $logger): ResponseInterface
    {
        $url = $this->buildBaseUrl($path);
        $this->noteUrlUsed($path, $query);

        $fullQuery = ['ID' => $this->schoolId, 'key' => $this->token] + $query;
        $fails = 0;

        while (true) {
            try {
                return $this->client->request('GET', $url, [
                    'query' => $fullQuery,
                ]);
            } catch (GuzzleException $e) {
                $fails++;

                if ($fails >= self::NUM_RETRY) {
                    throw $e;
                }

                sleep(self::NAP_TIMEOUT);
            }
        }
    }

    /**
     * Log how many times each fully-qualified SOCS URL was requested.
     */
    public function logUrlUsage(LoggerInterface $logger): void
    {
        foreach ($this->urlUsage as $url => $count) {
            $logger->info(sprintf('SOCS URL used %d time(s): %s', $count, $url));
        }
    }

    /**
     * Record a redacted SOCS URL usage entry for later logging.
     *
     * @param array<string,string> $query Non-secret query parameters to include in logs.
     */
    private function noteUrlUsed(string $path, array $query): void
    {
        $baseUrl = $this->buildBaseUrl($path);

        $safeQuery = $query;
        unset($safeQuery['ID'], $safeQuery['key']);

        if ($safeQuery === []) {
            $url = $baseUrl;
        } else {
            $url = $baseUrl . '?' . http_build_query($safeQuery);
        }

        if (!isset($this->urlUsage[$url])) {
            $this->urlUsage[$url] = 0;
        }

        $this->urlUsage[$url]++;
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
