<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
    public function setUp(): void
    {
        parent::setUp();
        $this->mySqlQuoter = new Mysql\Quoter();
    }


    /**
     * @dataProvider providerInvalidColumnNamesAndValues
     * @param string $invalidColumnName
     * @param mixed  $value
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testAddColumnList(string $invalidColumnName, $value): void
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->addColumnList([$invalidColumnName => $value]);
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testIsNull($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isNull($invalidOperand);
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotNull($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notNull($invalidOperand);
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testIsTrue($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isTrue($invalidOperand);
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testIsFalse($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->isFalse($invalidOperand);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc  $invalidColumn
     * @param string|float|int $value
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testEqualsInvalidColumn($invalidColumn, $value)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals($invalidColumn, $value);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc  $validOperand
     * @param string|float|int $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testEqualsInvalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->equals($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc  $invalidOperand
     * @param string|float|int $value
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotEqualsInvalidColumn($invalidOperand, $value)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notEquals($invalidOperand, $value);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc  $validOperand
     * @param string|float|int $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotEqualsInvalidArg($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notEquals($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValueTraversable
     * @param string|ISqlFunc   $invalidOperand
     * @param array|Traversable $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testInInvalidColumn($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValueTraversable
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testInInvalidArg($validOperand, $invalidValue)
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
    public function testInEmpty()
    {
        $this->expectException(UnderflowException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->in('column', []);
    }


    /**
     * @dataProvider providerInvalidColumnValidValueTraversable
     * @param string|ISqlFunc   $invalidOperand
     * @param array|Traversable $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testNotInInvalidColumn($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notIn($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValueTraversable
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     * @throws UnderflowException
     */
    public function testNotInInvalidArg($validOperand, $invalidValue)
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
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLikeInvalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->like($invalidOperand, 'string');
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLikeAnyInvalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->likeAny($invalidOperand, ['string', 'string2']);
    }


    /**
     * @dataProvider providerInvalidColumn
     * @param string|ISqlFunc $invalidOperand
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotLikeInvalidColumn($invalidOperand)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLike($invalidOperand, 'string');
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLessThanInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLessThanValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLessThanOrEqualToInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThanOrEqualTo($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testLessThanOrEqualToValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->lessThanOrEqualTo($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testGreaterThanInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testGreaterThanValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testGreaterThanOrEqualToInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThanOrEqualTo($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testGreaterThanOrEqualToValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->greaterThanOrEqualTo($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotLessThanInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLessThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotLessThanValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notLessThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotGreaterThanInvalidColumnValidValue($invalidOperand, $validValue)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notGreaterThan($invalidOperand, $validValue);
    }


    /**
     * @dataProvider providerValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidValue
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testNotGreaterThanValidColumnInvalidValue($validOperand, $invalidValue)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->notGreaterThan($validOperand, $invalidValue);
    }


    /**
     * @dataProvider providerBetweenInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validFrom
     * @param mixed           $validTo
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testBetweenInvalidColumnValidValue($invalidOperand, $validFrom, $validTo)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->between($invalidOperand, $validFrom, $validTo);
    }


    /**
     * @dataProvider providerBetweenValidColumnInvalidValue
     * @param string|ISqlFunc $validOperand
     * @param mixed           $invalidFrom
     * @param mixed           $invalidTo
     * @throws TypeError
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testBetweenValidColumnInvalidValue($validOperand, $invalidFrom, $invalidTo)
    {
        $this->expectException(TypeError::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->between($validOperand, $invalidFrom, $invalidTo);
    }


    /**
     * @dataProvider providerBetweenDatesInvalidColumnValidValue
     * @param string|ISqlFunc $invalidOperand
     * @param mixed           $validFrom
     * @param mixed           $validTo
     * @throws InvalidColumnException
     * @throws PHPUnit\Framework\Exception
     */
    public function testBetweenDatesInvalidColumnValidValue($invalidOperand, $validFrom, $validTo)
    {
        $this->expectException(InvalidColumnException::class);
        $pb = new Builder($this->mySqlQuoter, 'table');
        $pb->betweenDates($invalidOperand, $validFrom, $validTo);
    }


    // Data Providers

    public function providerBetweenDatesInvalidColumnValidValue()
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


    public function providerBetweenInvalidColumnValidValue()
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


    public function providerBetweenValidColumnInvalidValue()
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


    public function providerInvalidColumn()
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


    public function providerInvalidColumnValidValue()
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


    public function providerValidColumnInvalidValueTraversable()
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


    public function providerInvalidColumnValidValueTraversable()
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


    public function providerValidColumnInvalidValue()
    {
        $data = [];
        foreach ($this->getValidColumnNames() as $validColumn) {
            $data[] = [$validColumn, null];
            $data[] = [$validColumn, []];
            $data[] = [$validColumn, [10, 20]];
        }
        foreach ($this->getSqlFuncWithValidColumnNames() as $validSqlFunc) {
            $data[] = [$validSqlFunc, null];
            $data[] = [$validSqlFunc, []];
            $data[] = [$validSqlFunc, [10, 20]];
        }
        return $data;
    }


    public function providerInvalidColumnNamesAndValues()
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
