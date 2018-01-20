<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate;

use Alaress\Database\Quoter\IQuoter;

interface ISqlFunc
{

    /**
     * Return a parameter prefix to insert in query.
     *
     * @return string
     */
    public function getParam(): string;


    /**
     * Returns the function with any table.columns escaped and ready for insertion
     * into a WHERE clause
     *
     * @param IQuoter $quoter
     * @param string  $defaultTable If a column does not have a table prefix, prefix with this table
     * @return string
     * @throws InvalidColumnException If unable to parse a column name
     */
    public function getEscapedOperand(IQuoter $quoter, string $defaultTable): string;
}
