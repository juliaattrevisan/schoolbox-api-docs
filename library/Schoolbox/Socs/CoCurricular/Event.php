<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

/**
 * Normalized SOCS co-curricular event data.
 */
class Event
{
    public function __construct(
        public readonly string $id,
        public readonly string $clubId,
        public readonly string $title,
        public readonly string $location,
        public readonly \Alaress_Date_Format $start,
        public readonly \Alaress_Date_Format $finish,
        public readonly bool $allDay,
        public readonly string $recurringId,
        /** @var string[] */
        public readonly array $staffIds,
    ) {
    }
}
