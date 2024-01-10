<?php

/**
 * Sqlsrv driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Mssql;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestPostProcessor extends TestCase
{
    use TestConnectorTrait;

    /**
     * This method should call `$this->markTestSkipped` if the database
     * connector is unavailable.
     */
    abstract public function skipIfDriverNotAvailable();

    /**
     * Connects to MSSQL database given the correct connector.
     *
     * @return bool
     */
    abstract public function connect();

    /**
     * Skip if SQLSrv driver is not available
     */
    public function setUp(): void
    {
        $this->markTestSkipped('Skipping SQLSrv tests, as test database is inaccessible');
//        $this->skipIfDriverNotAvailable();
//        $this->connect();
    }

    /**
     * Test function to trim whitespace from array/value
     *
     * @param array|string|null $input
     * @return array|string|null $input
     */
    public function trimWhitespace($input)
    {
        if (is_array($input)) {
            $output = [];
            foreach ($input as $key => $value) {
                $output[$key] = trim((string)$value);
            }
            return $output;
        } else {
            return trim((string)$input);
        }
    }

    /**
     * Test plain old boring statements
     */
    public function testPostProcessor()
    {
        $this->mssqlTest->addPostProcessor([$this, 'trimWhitespace']);

        // ----------------------------------------------------------------------------------------------------------------
        // Add post-processor to trim whitespace from results
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $this->mssqlTest->executeQuery("
            SELECT '     douglas' AS name UNION
            SELECT '  damian' AS name UNION
            SELECT '  doctor    ' AS name UNION
            SELECT '  steve' AS name UNION
            SELECT ' andy    ' AS name UNION
            SELECT ' nat    ' AS name UNION
            SELECT 'amri    ' AS name UNION
            SELECT 'stu ' AS name UNION
            SELECT 'zfuturedevs' AS name
        ");

        $count = 0;
        $expectedResult = ['douglas', 'damian', 'doctor', 'steve', 'andy', 'nat', 'amri', 'stu', 'zfuturedevs'];

        while ($row = $statement->fetch()) {
            $this->assertSame(strtolower($row['name']), $expectedResult[$count]);
            $count++;
        }

        $this->assertSame(9, $count);
    }
}
