<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

use DateTimeImmutable;

/**
 * Normalized SOCS co-curricular event data.
 */
final class Event
{
    public function __construct(
        public readonly string $id,
        public readonly string $clubId,
        public readonly string $title,
        public readonly string $location,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $finish,
        public readonly bool $allDay,
        public readonly string $recurringId,
    ) {
    }
}
