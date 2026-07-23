<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

/**
 * Complete normalized co-curricular feed data required by one safe import.
 */
final class DataSet
{
    /**
     * @param array<string,Club>     $clubsById
     * @param array<string,Event>    $eventsById
     * @param array<string,string[]> $pupilIdsByClubId
     */
    public function __construct(
        public readonly array $clubsById,
        public readonly array $eventsById,
        public readonly array $pupilIdsByClubId,
    ) {
    }
}
