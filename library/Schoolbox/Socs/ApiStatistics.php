<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

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

    /**
     * Log the number of times each SOCS URL was called during this run.
     */
    public function import(): void
    {
        $this->httpClient->logUrlUsage($this->log);
    }
}
