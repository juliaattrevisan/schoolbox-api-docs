<?php

/**
 * Mysql driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Mysql;

use Alaress\Database\Factory;
use Alaress\Database\Test\TestConnectorTrait;
use Database;
use PHPUnit\Framework\TestCase;

class TestPostProcessor extends TestCase
{
    use TestConnectorTrait;

    /**
     * Test function to trim whitespace from array/value
     *
     * @param array|string $input
     * @return array|string $input
     */
    public function trimWhitespace($input)
    {
        if (is_array($input)) {
            $output = [];
            foreach ($input as $key => $value) {
                $output[$key] = trim($value);
            }
            return $output;
        } else {
            return trim($input);
        }
    }

    /**
     * Test plain old boring statements
     */
    public function testPostProcessor()
    {
        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $factory = new Factory();

        $schoolbox = $factory->createMysql();
        $schoolbox->connect(
            '192.168.36.10',
            'schoolbox',
            'schoolboxvagrant',
            'abc123'
        );

        $schoolbox->addPostProcessor([$this, 'trimWhitespace']);

        // ----------------------------------------------------------------------------------------------------------------
        // Add post-processor to trim whitespace from results
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $schoolbox->executeQuery("
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
