<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter;

use Alaress\Database\Predicate\InvalidColumnException;

/**
 * Class Base
 */
abstract class Base implements IQuoter
{

    /**
     * @inheritdoc
     */
    public function escapeForLike($string)
    {
        return strtr(
            $string,
            [
                '_' => '\\_',
                '%' => '\\%',
            ]
        );
    }


    /**
     * @inheritdoc
     */
    public function getEscapedOperand(string $defaultTable, string $columnName): string
    {
        [$table, $field] = $this->parseColumnName($defaultTable, $columnName);
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($field);
    }


    /**
     * @param string $defaultTable
     * @param string $columnName
     * @return string[]
     * @throws InvalidColumnException  If unable to parse a sane table and field
     */
    private function parseColumnName(string $defaultTable, string $columnName): array
    {
        $xpl = explode('.', $columnName) ?: [];

        if (1 === \count($xpl)) {
            return [$defaultTable, $xpl[0]];
        }
        if (2 === \count($xpl)) {
            return [$xpl[0], $xpl[1]];
        }
        throw new InvalidColumnException("Unable to parse [table].column name: $columnName");
    }
}
