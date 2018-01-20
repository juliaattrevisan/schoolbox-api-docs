<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate\SqlFunc;

use Alaress\Database\Predicate\AbstractSqlFunc;
use Alaress\Database\Quoter\IQuoter;
use InvalidArgumentException;

/**
 * SQL COALESCE() function
 */
class Coalesce extends AbstractSqlFunc
{

    /**
     * Unescaped column names, with optional table prefix. eg 'table.column' or 'column'
     *
     * @var string[]
     */
    private $columns;


    /**
     * Coalesce constructor.
     *
     * @param string[] $columnNames
     * @throws InvalidArgumentException If an empty array is supplied
     */
    public function __construct(array $columnNames)
    {
        if(0 === \count($columnNames)) {
            throw new InvalidArgumentException('Coalesce function requires at least 1 column name');
        }
        $this->columns = $columnNames;
    }


    /**
     * @inheritdoc
     */
    public function getParam(): string
    {
        return 'coalesce';
    }


    /**
     * @inheritdoc
     */
    public function getEscapedOperand(IQuoter $quoter, string $defaultTable): string
    {
        $escapedColumns = [];
        foreach($this->columns as $column) {
            $escapedColumns[] = $this->escapeColumn($quoter, $defaultTable, $column);
        }

        return 'COALESCE(' . implode(',', $escapedColumns) . ')';
    }
}
