<?php

declare(strict_types=1);

namespace Schoolbox\Socs;

use Psr\Log\LoggerInterface;

/**
 * Final SOCS strategy that logs per-URL usage from the HTTP client.
 */
class ApiStatistics implements IStrategy
{
    public function __construct(private LoggerInterface $log, private HttpClient $httpClient)
    {
    }

    public function import(): void
    {
        $this->httpClient->logUrlUsage($this->log);
    }
}
