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
            $this->buildStream($test1),
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


        $test2 = <<<TEST2
"markPerCapability: Off","useMarkRange: On",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST2;
        yield 'mark range on' => [
            $this->buildStream($test2),
            5,
            ['', '', '', '', '',],
            [
                [
                    'name'     => 'Test Capability 1',
                    'maxValue' => null,
                ],
            ],
            true,
            false,
        ];


        $test3= <<<TEST2
"markPerCapability: On","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST2;
        yield 'mark-per-cap on' => [
            $this->buildStream($test3),
            5,
            ['', '', '', '', '',],
            [
                [
                    'name'     => 'Test Capability 1',
                    'maxValue' => 9,
                ],
            ],
            false,
            true,
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


    /**
     * Helper for building a stream out of a string.
     *
     * @param string $content
     * @return resource
     */
    private function buildStream(string $content)
    {
        $stream = fopen('php://memory','rb+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }
}
