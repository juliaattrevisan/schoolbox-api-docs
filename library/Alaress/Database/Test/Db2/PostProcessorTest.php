<?php

/**
 * IBM DB2 driver statement tests
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test\Db2;

use Alaress\Database\Factory;
use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('ibm_db2')]
class PostProcessorTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Skip DB2 tests for now.
     */
    public function setUp(): void
    {
        $this->markTestSkipped('Skipping DB2 tests, as test database is inaccessible');
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
    #[RequiresPhpExtension('ibm_db2')]
    public function testPostProcessor()
    {
        $factory = new Factory();

        $db2Test = $factory->createDb2();
        $db2Test->connect(
            'ec2-54-153-145-255.ap-southeast-2.compute.amazonaws.com:50000',
            'SBOXTEST',
            'sbox',
            'tnp879'
        );

        $db2Test->addPostProcessor([$this, 'trimWhitespace']);

        // ----------------------------------------------------------------------------------------------------------------
        // Add post-processor to trim whitespace from results
        // ----------------------------------------------------------------------------------------------------------------

        $statement = $db2Test->executeQuery("
            VALUES '     douglas' UNION
            VALUES '  damian' UNION
            VALUES '  doctor    ' UNION
            VALUES '  steve' UNION
            VALUES ' andy    ' UNION
            VALUES ' nat    ' UNION
            VALUES 'amri    ' UNION
            VALUES 'stu ' UNION
            VALUES 'zfuturedevs'
        ");

        $count = 0;
        $expectedResult = ['douglas', 'damian', 'doctor', 'steve', 'andy', 'nat', 'amri', 'stu', 'zfuturedevs'];

        while ($row = $statement->fetch()) {
            $this->assertSame(strtolower($row[1]), $expectedResult[$count]);
            $count++;
        }

        $this->assertSame(9, $count);
    }
}
