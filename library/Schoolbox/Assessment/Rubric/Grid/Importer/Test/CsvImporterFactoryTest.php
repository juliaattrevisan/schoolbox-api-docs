<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer\Test;

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
                    'name'       => 'cap2',
                    'maxValue'   => null,
                    'indicators' => [
                        [
                            'name'        => 'ind1',
                            'descriptors' => [
                                ['name' => '', 'value' => 0,],
                                ['name' => '', 'value' => 2,],
                                ['name' => '', 'value' => 4,],
                                ['name' => '', 'value' => 6,],
                                ['name' => '', 'value' => 8,],
                            ],
                        ],
                        [
                            'name'        => 'ind2',
                            'descriptors' => [
                                ['name' => '', 'value' => 0,],
                                ['name' => '', 'value' => 2,],
                                ['name' => '', 'value' => 4,],
                                ['name' => '', 'value' => 6,],
                                ['name' => '', 'value' => 8,],
                            ],
                        ],
                    ],
                ],
                [
                    'name'       => 'cap1',
                    'maxValue'   => null,
                    'indicators' => [
                        [
                            'name'        => 'ind3',
                            'descriptors' => [
                                ['name' => '', 'value' => 0,],
                                ['name' => '', 'value' => 2,],
                                ['name' => '', 'value' => 4,],
                                ['name' => '', 'value' => 6,],
                                ['name' => '', 'value' => 8,],
                            ],
                        ],
                        [
                            'name'        => 'ind4',
                            'descriptors' => [
                                ['name' => '', 'value' => 0,],
                                ['name' => '', 'value' => 2,],
                                ['name' => '', 'value' => 4,],
                                ['name' => '', 'value' => 6,],
                                ['name' => '', 'value' => 8,],
                            ],
                        ],
                    ],
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
                    'name'       => 'Test Capability 1',
                    'maxValue'   => null,
                    'indicators' => [
                        [
                            'name'        => 'Indicator 1',
                            'descriptors' => [
                                ['name' => 'a', 'value' => 15,],
                                ['name' => 'b', 'value' => 10,],
                                ['name' => 'c', 'value' => 5,],
                                ['name' => 'd', 'value' => 1,],
                                ['name' => 'e', 'value' => 0,],
                            ],
                        ],
                        [
                            'name'        => 'Indicator 2',
                            'descriptors' => [
                                ['name' => 'a', 'value' => 15,],
                                ['name' => 'b', 'value' => 10,],
                                ['name' => 'c', 'value' => 5,],
                                ['name' => 'd', 'value' => 1,],
                                ['name' => 'e', 'value' => 0,],
                            ],
                        ],
                    ],
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
                    'name'       => 'Test Capability 1',
                    'maxValue'   => 9,
                    'indicators' => [
                        [
                            'name'        => 'Indicator 1',
                            'descriptors' => [
                                ['name' => 'a', 'value' => 15,],
                                ['name' => 'b', 'value' => 10,],
                                ['name' => 'c', 'value' => 5,],
                                ['name' => 'd', 'value' => 1,],
                                ['name' => 'e', 'value' => 0,],
                            ],
                        ],
                        [
                            'name'        => 'Indicator 2',
                            'descriptors' => [
                                ['name' => 'a', 'value' => 15,],
                                ['name' => 'b', 'value' => 10,],
                                ['name' => 'c', 'value' => 5,],
                                ['name' => 'd', 'value' => 1,],
                                ['name' => 'e', 'value' => 0,],
                            ],
                        ],
                    ],
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
        $this->assertSame($expectedCapabilities, $importer->retrieveCapabilitiesWithIndicators());
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
