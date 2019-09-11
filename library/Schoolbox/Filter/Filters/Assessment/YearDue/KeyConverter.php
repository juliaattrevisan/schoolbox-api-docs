<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\YearDue;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractKeyConverter;

/**
 * Converts between YearDue concrete keys and YearDue strings
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * @inheritdoc
     */
    protected function getItem(string $key, \Schoolbox\User $user, string $page)
    {
        $this->assertValidKey($key, $user, $page);
        return $key;
    }


    /**
     * @return Collection|string[]
     */
    public function getAllPossibleItems(): Collection
    {
        return new Collection();
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If key is not numeric
     */
    protected function assertValidKey(string $key, \Schoolbox\User $user, string $page): void
    {
        if (!is_numeric($key)) {
            throw new \InvalidArgumentException('YearDue Key is invalid: ' . $key);
        }
    }
}
