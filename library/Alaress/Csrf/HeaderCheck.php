<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Csrf;

class HeaderCheck
{
    /**
     * Server vars ($_SERVER)
     *
     * @var array
     */
    private $serverVars;

    /**
     * Hard-coded hostname
     *
     * @var string|null
     */
    private $host;

    public function __construct(array $serverVars, ?string $host = null)
    {
        $this->serverVars = $serverVars;
        $this->host       = $host;
    }

    public function check() : bool
    {
        // Valid sources: origin or referer header
        $sources = array_filter([
            $this->serverVars['HTTP_ORIGIN'] ?? null,
            $this->serverVars['HTTP_REFERER'] ?? null,
        ]);

        // Valid targets: can be configured; otherwise host or x-forwarded-host
        $targets = array_filter([
            $this->host,
            $_SERVER['HTTP_HOST'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_HOST'] ?? null,
        ]);

        foreach ($sources as $source) {
            foreach ($targets as $target) {

            }
        }
        return false;
    }
}
