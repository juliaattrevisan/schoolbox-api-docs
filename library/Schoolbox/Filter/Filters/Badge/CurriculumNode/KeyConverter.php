<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Key converter implementation for the curriculum node filter.
 */
class KeyConverter extends AbstractKeyConverter
{
    /**
     * @inheritdoc
     */
    protected function getItem(string $key, User $user, string $page): int
    {
        return (int)$key;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey(string $key, User $user, string $page): void
    {
    }


    /**
     * @inheritdoc
     */
    public function possibleItemCount(): int
    {
        return 0;
    }
}
