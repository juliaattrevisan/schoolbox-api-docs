<?php

/**
 * Mysql driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test\Sqlsrv;

use Alaress\Database\Factory;
use Alaress\Database\Test\TestConnectorTrait;
use Database;
use PHPUnit_Framework_TestCase;

class TestPostProcessor extends PHPUnit_Framework_TestCase
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

        $synergetic68 = $factory->createSqlsrv();
        $synergetic68->connect(
            '172.31.15.10',
            'Synergetic_AUVIC_ALARESS_PRD',
            'sa',
            'Synergetic#1'
        );

        $synergetic68->addPostProcessor([$this, 'trimWhitespace']);

        // ----------------------------------------------------------------------------------------------------------------
        // Add post-processor to trim whitespace from results
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $synergetic68->executeQuery("
            SELECT '     douglas' AS name UNION 
            SELECT '  damian' AS name UNION 
            SELECT '  doctor    ' AS name UNION 
            SELECT '  steve' AS name UNION 
            SELECT ' andy    ' AS name UNION 
            SELECT 'amri    ' AS name UNION 
            SELECT 'stu ' AS name 
        ");

        $count = 0;
        $expectedResult = ['douglas', 'damian', 'doctor', 'steve', 'andy', 'amri', 'stu'];

        while ($row = $statement->fetch()) {
            $this->assertSame(strtolower($row['name']), $expectedResult[$count]);
            $count++;
        }
    }
}