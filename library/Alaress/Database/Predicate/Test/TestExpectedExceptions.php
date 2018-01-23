<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Database\Predicate\Test;

use Alaress\Database\Predicate\Builder;
use TypeError;
use UnderflowException;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Predicate\ISqlFunc;
use Alaress\Database\Predicate\SqlFunc\Coalesce;
use PHPUnit;
use PHPUnit\Framework\TestCase;
use Alaress\Database\Quoter\Mysql;
use Traversable;
use Alaress_Date_Format;
use Alaress_Date_NullDate;

/**
 * Test that the Predicate Builder's public methods throw the exceptions they claim to.
 */
class TestExpectedExceptions extends TestCase
{

    /** @var Mysql\Quoter */
    private $mySqlQuoter;


    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->mySqlQuoter = new Mysql\Quoter();
    }


    /**
     * @dataProvider provider_invalidColumnNamesAndValues
     * @param string $invalidColumnName
     * @param mixed  $value
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_addColumnList(string $invalidColumnName, $value)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->addColumnList([$invalidColumnName => $value]);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_isNull($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isNull($invalidOperand);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notNull($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notNull($invalidOperand);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_isTrue($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isTrue($invalidOperand);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_isFalse($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isFalse($invalidOperand);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc  $invalidColumn
     * @param string|float|int $value
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_equals_invalidColumn($invalidColumn, $value)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals($invalidColumn, $value);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc  $validOperand
     * @param string|float|int $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_equals_invalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc  $invalidOperand
     * @param string|float|int $value
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notEquals_invalidColumn($invalidOperand, $value)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notEquals($invalidOperand, $value);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc  $validOperand
     * @param string|float|int $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notEquals_invalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notEquals($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValueTraversable
     * @param string|ISqlFunc   $invalidOperand
     * @param array|Traversable $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_in_invalidColumn($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValueTraversable
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_in_invalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in($validOperand, $invalidValue);
    }


    /**
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_in_eEmpty()
    {
        $this->expectException(UnderflowException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in('column', []);
    }


    /**
     * @dataProvider provider_invalidColumn_validValueTraversable
     * @param string|ISqlFunc   $invalidOperand
     * @param array|Traversable $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_notIn_invalidColumn($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notIn($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValueTraversable
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function test_notIn_invalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notIn($validOperand, $invalidValue);
    }


    /**
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testNotInEmpty()
    {
        $this->expectException(UnderflowException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notIn('column', []);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_like_invalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->like($invalidOperand, 'string');
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_likeAny_invalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->likeAny($invalidOperand, ['string', 'string2']);
    }


    /**
     * @dataProvider provider_invalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notLike_invalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLike($invalidOperand, 'string');
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_lessThan_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_lessThan_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_lessThanOrEqualTo_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThanOrEqualTo($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_lessThanOrEqualTo_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThanOrEqualTo($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_greaterThan_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_greaterThan_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_greaterThanOrEqualTo_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThanOrEqualTo($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_greaterThanOrEqualTo_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThanOrEqualTo($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notLessThan_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLessThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notLessThan_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLessThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notGreaterThan_invalidColumn_validValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notGreaterThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider provider_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_notGreaterThan_validColumn_invalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notGreaterThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider provider_between_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validFrom
     * @param mixed           $validTo
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_between_invalidColumn_validValue($invalidOperand, $validFrom, $validTo)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->between($invalidOperand, $validFrom, $validTo);
    }


    /**
     * @dataProvider provider_between_validColumn_invalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidFrom
     * @param mixed           $invalidTo
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_between_validColumn_invalidValue($validOperand, $invalidFrom, $invalidTo)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->between($validOperand, $invalidFrom, $invalidTo);
    }


    /**
     * @dataProvider provider_betweenDates_invalidColumn_validValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validFrom
     * @param mixed           $validTo
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function test_betweenDates_invalidColumn_validValue($invalidOperand, $validFrom, $validTo)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->betweenDates($invalidOperand, $validFrom, $validTo);
    }


    // Data Providers

    public function provider_betweenDates_invalidColumn_validValue()
    {
        $data   = [];
        $format = Alaress_Date_Format::DATABASE_DATE_FORMAT;
        $date1  = new Alaress_Date_Format('2010-01-01', $format);
        $date2  = new Alaress_Date_Format('2020-01-01', $format);
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName, null, $date1];
            $data[] = [$invalidColumnName, $date1, null];
            $data[] = [$invalidColumnName, $date1, $date2];
        }
        foreach ($this->getSqlFuncWithInvalidColumnNames() as $invalidSqlFunc) {
            $data[] = [$invalidSqlFunc, null, $date1];
            $data[] = [$invalidSqlFunc, $date1, null];
            $data[] = [$invalidSqlFunc, $date1, $date2];
        }
        return $data;
    }


    public function provider_between_invalidColumn_validValue()
    {
        $data = [];
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName, null, 20];
            $data[] = [$invalidColumnName, 10, null];
            $data[] = [$invalidColumnName, 10, 20];
            $data[] = [$invalidColumnName, null, '2020-01-01'];
            $data[] = [$invalidColumnName, '2010-01-01', null];
            $data[] = [$invalidColumnName, '2010-01-01', '2020-01-01'];
        }
        foreach ($this->getSqlFuncWithInvalidColumnNames() as $invalidSqlFunc) {
            $data[] = [$invalidSqlFunc, null, 20];
            $data[] = [$invalidSqlFunc, 10, null];
            $data[] = [$invalidSqlFunc, 10, 20];
            $data[] = [$invalidSqlFunc, null, '2020-01-01'];
            $data[] = [$invalidSqlFunc, '2010-01-01', null];
            $data[] = [$invalidSqlFunc, '2010-01-01', '2020-01-01'];
        }
        return $data;
    }


    public function provider_between_validColumn_invalidValue()
    {
        $data = [];
        foreach ($this->getValidColumnNames() as $validColumnName) {
            $data[] = [$validColumnName, [], null];
            $data[] = [$validColumnName, null, []];
            $data[] = [$validColumnName, [], []];
            $data[] = [$validColumnName, '2010-01-01', []];
        }
        foreach ($this->getSqlFuncWithValidColumnNames() as $validSqlFunc) {
            $data[] = [$validSqlFunc, [], null];
            $data[] = [$validSqlFunc, null, []];
            $data[] = [$validSqlFunc, [], []];
            $data[] = [$validSqlFunc, '2010-01-01', []];
        }
        return $data;
    }


    public function provider_invalidColumn()
    {
        $data = [];
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName];
        }
        foreach ($this->getSqlFuncWithInvalidColumnNames() as $invalidSqlFunc) {
            $data[] = [$invalidSqlFunc];
        }
        return $data;
    }


    public function provider_invalidColumn_validValue()
    {
        $data = [];
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName, 10];
            $data[] = [$invalidColumnName, 10.2];
            $data[] = [$invalidColumnName, 'a string'];
            $data[] = [$invalidColumnName, '2010-01-01'];
        }
        foreach ($this->getSqlFuncWithInvalidColumnNames() as $invalidSqlFunc) {
            $data[] = [$invalidSqlFunc, 10];
            $data[] = [$invalidSqlFunc, 10.2];
            $data[] = [$invalidSqlFunc, 'a string'];
            $data[] = [$invalidSqlFunc, '2010-01-01'];
        }
        return $data;
    }


    public function provider_validColumn_invalidValueTraversable()
    {
        $data = [];
        foreach ($this->getValidColumnNames() as $validColumnName) {
            $data[] = [$validColumnName, null];
            $data[] = [$validColumnName, true];
            $data[] = [$validColumnName, false];
            $data[] = [$validColumnName, 10];
            $data[] = [$validColumnName, 'a string'];
            $data[] = [$validColumnName, '2010-01-01'];
        }
        foreach ($this->getSqlFuncWithValidColumnNames() as $validSqlFunc) {
            $data[] = [$validSqlFunc, null];
            $data[] = [$validSqlFunc, true];
            $data[] = [$validSqlFunc, false];
            $data[] = [$validSqlFunc, 10];
            $data[] = [$validSqlFunc, 'a string'];
            $data[] = [$validSqlFunc, '2010-01-01'];
        }
        return $data;
    }


    public function provider_invalidColumn_validValueTraversable()
    {
        $data = [];
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName, [10]];
            $data[] = [$invalidColumnName, [10, 20]];
            $data[] = [$invalidColumnName, ['2010-01-01']];
        }
        foreach ($this->getSqlFuncWithInvalidColumnNames() as $invalidSqlFunc) {
            $data[] = [$invalidSqlFunc, [10]];
            $data[] = [$invalidSqlFunc, [10, 20]];
            $data[] = [$invalidSqlFunc, ['2010-01-01']];
        }
        return $data;
    }


    public function provider_validColumn_invalidValue()
    {
        $data = [];
        foreach ($this->getValidColumnNames() as $validColumn) {
            $data[] = [$validColumn, null];
            $data[] = [$validColumn, new Alaress_Date_NullDate()];
            $data[] = [$validColumn, []];
            $data[] = [$validColumn, [10, 20]];
        }
        foreach ($this->getSqlFuncWithValidColumnNames() as $validSqlFunc) {
            $data[] = [$validSqlFunc, null];
            $data[] = [$validSqlFunc, new Alaress_Date_NullDate()];
            $data[] = [$validSqlFunc, []];
            $data[] = [$validSqlFunc, [10, 20]];
        }
        return $data;
    }


    public function provider_invalidColumnNamesAndValues()
    {
        $data = [];
        foreach ($this->getInvalidColumnNames() as $invalidColumnName) {
            $data[] = [$invalidColumnName, 10];
            $data[] = [$invalidColumnName, [10, 20]];
        }
        return $data;
    }

    // Helper methods


    /**
     * Returns a set of invalid column names
     *
     * @return string[]
     */
    private function getInvalidColumnNames(): array
    {
        return [
            'invalid.column.name',
            'really.invalid.column.name',
        ];
    }


    /**
     * Returns a set of valid column names
     *
     * @return string[]
     */
    private function getValidColumnNames(): array
    {
        return [
            'column',
            'table.column',
        ];
    }


    /**
     * Returns a set of SQL functions with invalid [table.]column names
     *
     * @return ISqlFunc[]
     */
    private function getSqlFuncWithInvalidColumnNames(): array
    {
        return [
            new Coalesce(['invalid.column.name']),
            new Coalesce(['invalid.column.name', 'table.column']),
            new Coalesce(['table.column', 'invalid.table.column']),
        ];
    }


    /**
     * Returns a set of SQL functions with valid [table.]column names
     *
     * @return ISqlFunc[]
     */
    private function getSqlFuncWithValidColumnNames(): array
    {
        return [
            new Coalesce(['column']),
            new Coalesce(['table.column']),
            new Coalesce(['column', 'table.column']),
            new Coalesce(['table.column', 'table.column']),
        ];
    }
}
