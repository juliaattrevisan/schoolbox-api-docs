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
     * @param array<string,string> $query Non-secret query parameters; ID and key are added automatically.
     */
    public function get(string $path, array $query, LoggerInterface $logger): ?ResponseInterface
    {
        $url = $this->buildBaseUrl($path);
        $this->noteUrlUsed($path, $query);

        $fullQuery = ['ID' => $this->schoolId, 'key' => $this->token] + $query;

        try {
            return $this->client->request('GET', $url, [
                'query' => $fullQuery,
            ]);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS endpoint %s: %s', $url, $e->getMessage()));
            return null;
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
