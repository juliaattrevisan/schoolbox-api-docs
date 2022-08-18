<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\User;

/**
 * Key converter for pastoral type/subtype filter.
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
    }

    /**
     * @inheritdoc
     */
    public function possibleItemCount(): int
    {
        return 0;
    }
}
