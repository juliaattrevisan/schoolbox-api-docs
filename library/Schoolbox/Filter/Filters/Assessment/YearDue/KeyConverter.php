<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

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
    public function getAllPossibleItems()
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
