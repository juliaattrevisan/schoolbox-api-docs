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
     * persistent.csv
     * This is an example of when filldown is true
     *
     * Values from a previous row should flow through to the next row
     * where the next row's elements are empty or null
     *
     * @throws \RuntimeException
     */
    public function testValidPersistentUnspecifiedColumns()
    {
        $reader = new Reader(__DIR__ . '/persistent.csv', true);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            ['external id', 'firstname', 'lastname', 'address', 'phone'],
            ['1', 'alpha', 'bravo', 'charlie', 'delta'],
            ['2', 'red', 'green', 'blue', 'green'],
            ['3', 'red', 'missing', 'blue', 'green'],
            ['4', 'red', 'missing', 'blue', 'green'],
            ['5', 'whoa', 'what', 'happened', 'here'],
            ['6', 'heh', 'niceuuu', 'happened', 'here'],
            ['7', 'hello', 'niceuuu', 'happened', 'here']
        ];

        $this->assertEquals($expected, $outcome);
    }

    /**
     * persistent.csv
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
        $expectedColumns = ['Firstname', 'Lastname', 'Phone', 'Address', 'External ID'];

        $reader = new Reader(__DIR__ . '/persistent.csv', true, $expectedColumns);

        $outcome = [];

        foreach ($reader as $row) {
            $outcome[] = $row;
        }

        $expected = [
            [
                'Lastname' => 'lastname',
                'Firstname' => 'firstname',
                'Phone' => 'phone',
                'Address' => 'address',
                'External ID' => 'external id'
            ],
            [
                'Lastname' => 'bravo',
                'Firstname' => 'alpha',
                'Phone' => 'delta',
                'Address' => 'charlie',
                'External ID' => '1'
            ],
            [
                'Lastname' => 'green',
                'Firstname' => 'red',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '2'
            ],
            [
                'Lastname' => 'missing',
                'Firstname' => 'red',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '3'
            ],
            [
                'Lastname' => 'missing',
                'Firstname' => 'red',
                'Phone' => 'green',
                'Address' => 'blue',
                'External ID' => '4'
            ],
            [
                'Lastname' => 'what',
                'Firstname' => 'whoa',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '5'
            ],
            [
                'Lastname' => 'niceuuu',
                'Firstname' => 'heh',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '6'
            ],
            [
                'Lastname' => 'niceuuu',
                'Firstname' => 'hello',
                'Phone' => 'here',
                'Address' => 'happened',
                'External ID' => '7'
            ],
        ];

        $this->assertEquals($expected, $outcome);
    }
}
