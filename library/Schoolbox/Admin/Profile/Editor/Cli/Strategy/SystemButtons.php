<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor\Cli\Strategy;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;

class SystemButtons
{
    /**
     * Constructor.
     *
     * @param IConnector $db
     * @param LoggerInterface $log
     * @param array $roletypeLookup
     */
    public function __construct(
        private IConnector $db,
        private LoggerInterface $log,
        private array $roletypeLookup
    ) {
    }


    /**
     * Create system buttons as profile buttons
     *
     * @return void
     */
    public function migrate(): void
    {
        /* Get a list of buttons to inspect - users can specify --system,
         * --custom, or both
         */
        $candidates = $this->generateCandidates($this->roletypeLookup);
    }


    private function generateCandidates(): array
    {
    }
}
