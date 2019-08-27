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
}
