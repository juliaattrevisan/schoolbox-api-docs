<?php

declare(strict_types=1);

namespace Schoolbox\Socs;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Lightweight HTTP client for SOCS APIs with URL usage tracking.
 */
class HttpClient
{
    private Client $client;

    private string $baseUrl;

    /** @var array<string,int> */
    private array $urlUsage = [];

    public function __construct(string $host)
    {
        $this->baseUrl = rtrim($host, '/');
        $this->client = new Client([
            'timeout' => 300,
            'http_errors' => false,
        ]);
    }

    /**
     * @param array<string,string> $query
     */
    public function get(string $path, array $query, LoggerInterface $logger): ?ResponseInterface
    {
        $url = $this->buildBaseUrl($path);
        $this->noteUrlUsed($url, $query);

        try {
            return $this->client->request('GET', $url, [
                'query' => $query,
            ]);
        } catch (GuzzleException $e) {
            $logger->warning(sprintf('Failed to contact SOCS endpoint %s: %s', $url, $e->getMessage()));
            return null;
        }
    }

    public function logUrlUsage(LoggerInterface $logger): void
    {
        foreach ($this->urlUsage as $url => $count) {
            $logger->info(sprintf('SOCS URL used %d time(s): %s', $count, $url));
        }
    }

    /**
     * @param array<string,string> $query
     */
    private function noteUrlUsed(string $baseUrl, array $query): void
    {
        if ($query === []) {
            $url = $baseUrl;
        } else {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $url = $baseUrl . $separator . http_build_query($query);
        }

        if (!isset($this->urlUsage[$url])) {
            $this->urlUsage[$url] = 0;
        }

        $this->urlUsage[$url]++;
    }

    private function buildBaseUrl(string $path): string
    {
        if ($path === '') {
            return $this->baseUrl;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }
}
