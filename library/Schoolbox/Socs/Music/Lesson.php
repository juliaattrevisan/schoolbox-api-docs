<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Music;

use DateTimeImmutable;

/**
 * Parsed SOCS music lesson data before it is converted into a Schoolbox calendar event.
 */
class Lesson
{
    public function __construct(
        public readonly string $id,
        public readonly DateTimeImmutable $start,
        public readonly DateTimeImmutable $end,
        public readonly string $title,
        public readonly string $instrument,
        public readonly string $location,
        public readonly string $staffExternalId,
        public readonly string $studentExternalId,
    ) {
    }
}
