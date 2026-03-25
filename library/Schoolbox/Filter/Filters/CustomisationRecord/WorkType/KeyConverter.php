<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\WorkType;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Work Type key converter for customisation records
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page)
    {
        return (int)$key;
    }

    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
        // No validation needed for work type keys
    }

    /**
     * @inheritdoc
     */
    public function possibleItemCount(): int
    {
        return 0; // Dynamic count from database
    }
}
