<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs\CoCurricular;

/**
 * Normalized SOCS co-curricular club data.
 */
class Club
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $category,
    ) {
    }

    /**
     * Return the shared visible name used for the club's Fixtures records.
     */
    public function getDisplayName(): string
    {
        return sprintf('%s - %s', $this->name, $this->category);
    }
}
