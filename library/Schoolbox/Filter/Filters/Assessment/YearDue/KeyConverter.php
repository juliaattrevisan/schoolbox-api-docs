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
     * @param string $key
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function getItem($key)
    {
        $this->assertValidKey($key);
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
     * @param string $key A concrete key
     * @throws \InvalidArgumentException If key is not numeric
     */
    protected function assertValidKey($key)
    {
        if (!is_numeric($key)) {
            throw new \InvalidArgumentException('YearDue Key is invalid: ' . $key);
        }
    }
}
