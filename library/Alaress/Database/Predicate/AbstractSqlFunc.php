<?php
/**
 * Author: andy.ryan@alaress.com.au
 * Created: 20/01/18 9:39 PM
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate;

use Alaress\Database\Quoter\IQuoter;

/**
 * Common implementation of SQL Functions
 */
abstract class AbstractSqlFunc implements ISqlFunc
{

    /**
     * Escapes a column name for insertion into query
     *
     * @param IQuoter $quoter
     * @param string  $defaultTable
     * @param string  $column
     * @return string
     * @throws InvalidColumnException
     */
    protected function escapeColumn(IQuoter $quoter, string $defaultTable, string $column): string
    {
        [$table, $field] = $this->parseColumnName($defaultTable, $column);

        return $quoter->quoteIdentifier($table) . '.' . $quoter->quoteIdentifier($field);
    }


    /**
     * @param string $defaultTable
     * @param string $columnName
     * @return string[]
     * @throws InvalidColumnException If unable to parse a table and field from the column name
     */
    protected function parseColumnName(string $defaultTable, string $columnName): array
    {
        $xpl = explode('.', $columnName) ?: [];

        if(1 === \count($xpl)) {
            return [$defaultTable, $xpl[0]];
        }
        if(2 === \count($xpl)) {
            return [$xpl[0], $xpl[1]];
        }
        throw new InvalidColumnException("Unable to parse [table].column name: $columnName");
    }
}
