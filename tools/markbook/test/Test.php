<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Tass\Test;

use Alaress\Database\Test\TestConnectorTrait;
use External_Tass_Class;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Schoolbox\Config\ConfigMapper;

class ClassCodeTest extends TestCase
{
    use TestConnectorTrait;

    // https://schoolbox.atlassian.net/browse/SA-8761 ..
// https://schoolbox.atlassian.net/browse/SA-8649 ... self assessment

    /**
     * Load a known dataset into the DB
     */
    public function setUp()
    {
        $this->connectSchoolbox();
//        $this->schoolbox->beginTransaction();
//        $this->schoolbox->executeQuery("UPDATE `folder` SET deleted_at = '1970-01-01 00:00:00'");
//        $this->schoolbox->executeQuery('TRUNCATE `folder_code`');
//
//        self::iterate($this->schoolbox, 0, self::$tree);
    }

    /**
     * @dataProvider providerForTestClassCode
     * @param string $year
     * @param string $code
     * @param string $class
     * @throws \ReflectionException
     */
    public function testClassCode(string $year, string $code, string $class)
    {
        // Enable mark book sync
        passthru(__DIR__ .  '/../enableMarkbookSync.php --perform', $returnVar);
        $this->assertEquals(1, $this->getConfigMapper()->get('external_markbook'));

        //
        passthru(__DIR__ .  '/../sync.php --perform -vvv', $returnVar);



        // Disable mark book sync
        passthru(__DIR__ .  '/../disableMarkbookSync.php --perform', $returnVar);
        $this->assertEquals(0, $this->getConfigMapper()->get('external_markbook'));

        $parseClassCode = (new ReflectionClass(External_Tass_Class::class))->getMethod('parseClassCode');
        $parseClassCode->setAccessible(true);

        list($actualYear, $actualCode, $actualClass) = $parseClassCode->invoke(null, implode('-', [$year, $code, $class]));

        $this->assertEquals($actualYear, $year);
        $this->assertEquals($actualCode, $code);
        $this->assertEquals($actualClass, $class);
    }


    /**
     * Data provider for `testClassCode`.
     *
     * @return array
     */
    public function providerForTestClassCode()
    {
        return [
            ['year' => '12', 'code' => '6125', 'class' => 'TC'],
            ['year' => '-2', 'code' => '6125', 'class' => 'TC'],
            ['year' => '', 'code' => '6125', 'class' => 'TC'],
            ['year' => '12', 'code' => 'ABCDEF', 'class' => 'Term 2'],
            ['year' => '-2', 'code' => 'ABCDEF', 'class' => 'Term 2'],
            ['year' => '', 'code' => 'ABCDEF', 'class' => 'Term 2'],
        ];
    }

    /**
     * @return ConfigMapper
     */
    private function getConfigMapper()
    {
        return \Schoolbox\Config\ConfigMapper::getInstance();

    }

}
