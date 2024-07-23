<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Async\Notification\Cloud;

use DateTimeInterface;
use RuntimeException;
use Throwable;

/**
 * Exception raised when a rate limit happens when sending a notification.
 */
class RateLimitException extends RuntimeException
{
    /**
     * Constructor
     *
     * @param string                 $message
     * @param int                    $code
     * @param Throwable|null         $previous
     * @param DateTimeInterface|null $retryAfter If provided: a time after which this rate limit should be lifted.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private ?DateTimeInterface $retryAfter = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getRetryAfter(): ?DateTimeInterface
    {
        return $this->retryAfter;
    }
}
