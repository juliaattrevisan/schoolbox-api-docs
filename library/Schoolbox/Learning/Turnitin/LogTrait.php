<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use Psr\Log\LoggerInterface;

/**
 * Some logging behaviours common across File, Report, Group processors
 */
trait LogTrait
{

    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Log to Logger if one was provided (used in cli tools)
     *
     * @param string $message
     */
    private function logDebug(string $message): void
    {
        if (null !== $this->log) {
            $this->log->debug($message);
        }
    }


    /**
     * Specifically log a warning to the cli
     *
     * @param string $message
     */
    private function logWarning(string $message): void
    {
        if (null !== $this->log) {
            $this->log->warning($message);
        }
    }
}
