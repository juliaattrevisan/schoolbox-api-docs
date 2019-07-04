<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory;

/**
 * Test for the CSV importer factory.
 *
 * @covers \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory
 */
class CsvImporterFactoryTest extends TestCase
{
    /**
     * @return iterable
     */
    public function getTestBuildData(): iterable
    {
        $test1 = <<<TEST1
,,,,,,
cap2,ind1,,,,,
,,0,2,4,6,8
,ind2,,,,,
,,0,2,4,6,8
cap1,ind3,,,,,
,,0,2,4,6,8
,ind4,,,,,
,,0,2,4,6,8
TEST1;
        yield 'empty descriptors' => [
            fopen('data://text/plain,' . $test1,'rb'),
            5,
            ['', '', '', '', '',],
            [
                [
                    'name'     => 'cap2',
                    'maxValue' => null,
                ],
                [
                    'name'     => 'cap1',
                    'maxValue' => null,
                ],
            ],
            false,
            false,
        ];
    }


    /**
     * @dataProvider getTestBuildData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory::build
     *
     * @param resource $fp
     * @param int      $maxLevels
     * @param array    $expectedLevels
     * @param array    $expectedCapabilities
     * @param bool     $expectedUseMarkRange
     * @param bool     $expectedMarkPerCapability
     */
    public function testBuild(
        $fp,
        int $maxLevels,
        array $expectedLevels,
        array $expectedCapabilities,
        bool $expectedUseMarkRange,
        bool $expectedMarkPerCapability
    ): void {
        $factory  = new CsvImporterFactory();
        $importer = $factory->build($fp, $maxLevels);
        $this->assertSame($expectedLevels, $importer->getLevels());
        $this->assertSame($expectedCapabilities, $importer->getCapabilities());
        $this->assertSame($expectedUseMarkRange, $importer->isUseMarkRange());
        $this->assertSame($expectedMarkPerCapability, $importer->isMarkPerCapability());
    }
}
