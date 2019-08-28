<?php
/**
 * ReaderTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Profile\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\Import\Profile\Reader;

class ReaderTest extends TestCase
{

    /**
     * hasHeaderRow is true
     *
     * rewind() skips past the header row to the first row of actual data
     *
     * @throws \RuntimeException
     */
    public function testValidHasHeaderRow()
    {
        $reader = new Reader(__DIR__ . '/provider.csv', true);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['1', 'alpha', 'bravo', 'charlie', 'delta'],
            ['2', 'red\'s', 'green', 'blue', 'green'],
            ['3', '', 'missing', '', ''],
            ['4', '', '', '', ''],
            ['5', 'whoa', 'what', 'happened', 'here'],
            ['6', 'heh', 'niceuuu', '', ''],
            ['7', 'hello', '', '', '']
        ];

        $this->assertEquals($expected, $outcome);
    }


    /**
     * hasHeaderRow is false
     *
     * All rows in the csv is considered data
     *
     * @throws \RuntimeException
     */
    public function testValidNoHeaderRow()
    {
        $reader = new Reader(__DIR__ . '/provider.csv', false);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['external id', 'firstname', 'lastname', 'address', 'phone'],
            ['1', 'alpha', 'bravo', 'charlie', 'delta'],
            ['2', 'red\'s', 'green', 'blue', 'green'],
            ['3', '', 'missing', '', ''],
            ['4', '', '', '', ''],
            ['5', 'whoa', 'what', 'happened', 'here'],
            ['6', 'heh', 'niceuuu', '', ''],
            ['7', 'hello', '', '', '']
        ];

        $this->assertEquals($expected, $outcome);
    }


    /**
     * hasHeaderRow is false
     * expected columns are declared
     *
     * ExpectedColumns mandates that the first row is used as a header against which
     * to match values, even if hasHeaderRow is false.
     *
     * @throws \RuntimeException
     */
    public function testValidHeaderRowExpectedColumns()
    {
        $expectedColumns = ['FirstName', 'LastName', 'Phone', 'Address', 'External ID'];

        $reader = (new Reader(__DIR__ . '/provider.csv', false))
            ->setExpectedColumns($expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            [
                'FirstName' => 'firstname',
                'LastName' => 'lastname',
                'Phone' => 'phone',
                'Address' => 'address',
                'External ID' => 'external id'
            ],
            [
                'FirstName' => 'alpha',
                'LastName' => 'bravo',
                'Phone' => 'delta',
                'Address' => 'charlie',
                'External ID' => '1'
            ],
            [
                'FirstName' => 'red\'s',
                'LastName' => 'green',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '2'
            ],
            [
                'FirstName' => '',
                'LastName' => 'missing',
                'Phone' => '',
                'Address' => '',
                'External ID' => '3'
            ],
            [
                'FirstName' => '',
                'LastName' => '',
                'Phone' => '',
                'Address' => '',
                'External ID' => '4'
            ],
            [
                'FirstName' => 'whoa',
                'LastName' => 'what',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '5'
            ],
            [
                'FirstName' => 'heh',
                'LastName' => 'niceuuu',
                'Phone' => '',
                'Address' => '',
                'External ID' => '6'
            ],
            [
                'FirstName' => 'hello',
                'LastName' => '',
                'Phone' => '',
                'Address' => '',
                'External ID' => '7'
            ],
        ];

        $this->assertEquals($expected, $outcome);
    }

    /**
     * This is an example of when filldown is true
     * Column matching doesn't matter
     *
     * Values from a previous row should flow through to the next row
     * where the next row's elements are empty or null
     *
     * @throws \RuntimeException
     */
    public function testValidPersistentUnspecifiedColumns()
    {
        $reader = new Reader(__DIR__ . '/provider.csv', true, true);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['1', 'alpha', 'bravo', 'charlie', 'delta'],
            ['2', 'red\'s', 'green', 'blue', 'green'],
            ['3', 'red\'s', 'missing', 'blue', 'green'],
            ['4', 'red\'s', 'missing', 'blue', 'green'],
            ['5', 'whoa', 'what', 'happened', 'here'],
            ['6', 'heh', 'niceuuu', 'happened', 'here'],
            ['7', 'hello', 'niceuuu', 'happened', 'here']
        ];

        $this->assertEquals($expected, $outcome);
    }


    /**
     * This is an example of when filldown is false
     * Column matching doesn't matter
     *
     * @throws \RuntimeException
     */
    public function testValidTransientUnspecifiedColumns()
    {
        $reader = new Reader(__DIR__ . '/provider.csv', true);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['1', 'alpha', 'bravo', 'charlie', 'delta'],
            ['2', 'red\'s', 'green', 'blue', 'green'],
            ['3', '', 'missing', '', ''],
            ['4', '', '', '', ''],
            ['5', 'whoa', 'what', 'happened', 'here'],
            ['6', 'heh', 'niceuuu', '', ''],
            ['7', 'hello', '', '', '']
        ];

        $this->assertEquals($expected, $outcome);
    }


    /**
     * This is an example of when filldown is true
     * A specific column name ordering and case exists
     *
     * Values from a previous row should flow through to the next row
     * where the next row's elements are empty or null
     *
     * @throws \RuntimeException
     */
    public function testValidPersistentSpecifiedColumns()
    {
        $expectedColumns = ['FirstName', 'LastName', 'Phone', 'Address', 'External ID'];

        $reader = (new Reader(__DIR__ . '/provider.csv', true, true))
            ->setExpectedColumns($expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            [
                'LastName' => 'bravo',
                'FirstName' => 'alpha',
                'Phone' => 'delta',
                'Address' => 'charlie',
                'External ID' => '1'
            ],
            [
                'LastName' => 'green',
                'FirstName' => 'red\'s',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '2'
            ],
            [
                'LastName' => 'missing',
                'FirstName' => 'red\'s',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '3'
            ],
            [
                'LastName' => 'missing',
                'FirstName' => 'red\'s',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '4'
            ],
            [
                'LastName' => 'what',
                'FirstName' => 'whoa',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '5'
            ],
            [
                'LastName' => 'niceuuu',
                'FirstName' => 'heh',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '6'
            ],
            [
                'LastName' => 'niceuuu',
                'FirstName' => 'hello',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '7'
            ],
        ];

        $this->assertEquals($expected, $outcome);
    }

    /**
     * This is an example of when filldown is false
     * A specific column name ordering and case exists
     *
     * @throws \RuntimeException
     */
    public function testValidTransientSpecifiedColumns()
    {
        $expectedColumns = ['FirstName', 'LastName', 'Phone', 'Address', 'External ID'];

        $reader = (new Reader(__DIR__ . '/provider.csv', true, false))
            ->setExpectedColumns($expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            [
                'LastName' => 'bravo',
                'FirstName' => 'alpha',
                'Phone' => 'delta',
                'Address' => 'charlie',
                'External ID' => '1'
            ],
            [
                'LastName' => 'green',
                'FirstName' => 'red\'s',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '2'
            ],
            [
                'LastName' => 'missing',
                'FirstName' => '',
                'Phone' => '',
                'Address' => '',
                'External ID' => '3'
            ],
            [
                'LastName' => '',
                'FirstName' => '',
                'Phone' => '',
                'Address' => '',
                'External ID' => '4'
            ],
            [
                'LastName' => 'what',
                'FirstName' => 'whoa',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '5'
            ],
            [
                'LastName' => 'niceuuu',
                'FirstName' => 'heh',
                'Phone' => '',
                'Address' => '',
                'External ID' => '6'
            ],
            [
                'LastName' => '',
                'FirstName' => 'hello',
                'Phone' => '',
                'Address' => '',
                'External ID' => '7'
            ],
        ];

        $this->assertEquals($expected, $outcome);
    }


    /**
     * What happens when your expectedColumns is a subset of csv columns
     * ie. users provided more data than we expected
     *
     * @throws \RuntimeException
     */
    public function testValidColumnNames1()
    {
        $expectedColumns = ['External ID'];

        $reader = (new Reader(__DIR__ . '/provider.csv', true, false))
            ->setExpectedColumns($expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['External ID' => '1'],
            ['External ID' => '2'],
            ['External ID' => '3'],
            ['External ID' => '4'],
            ['External ID' => '5'],
            ['External ID' => '6'],
            ['External ID' => '7'],
        ];

        $this->assertEquals($expected, $outcome);
    }

    /**
     * What happens when you give it a whole bunch of columns that aren't
     * in the source csv itself
     *
     * @throws \RuntimeException
     */
    public function testValidColumnNames2()
    {
        $expectedColumns = ['catness evergreen'];

        $reader = (new Reader(__DIR__ . '/provider.csv', true, false))
            ->setExpectedColumns($expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['catness evergreen' => null],
            ['catness evergreen' => null],
            ['catness evergreen' => null],
            ['catness evergreen' => null],
            ['catness evergreen' => null],
            ['catness evergreen' => null],
            ['catness evergreen' => null],
        ];

        $this->assertEquals($expected, $outcome);
    }
}
