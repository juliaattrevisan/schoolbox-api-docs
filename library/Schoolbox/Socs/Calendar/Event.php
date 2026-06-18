<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Calendar;

use DateTimeImmutable;

class Event
{
    /**
     * @param string[] $schools
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $title,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly bool $allDay,
        public readonly string $description,
        public readonly string $location,
        public readonly string $category,
        public readonly array $schools,
    ) {
    }
}
