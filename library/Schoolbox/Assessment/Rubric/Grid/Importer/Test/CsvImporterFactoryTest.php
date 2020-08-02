<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\Grid\Importer\MockCsvImporterFactory;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkModel;

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
            new DueWorkModel(),
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
            null,
            null
        ];


        $test2 = <<<TEST2
"capabilityMarkType: none","useMarkRange: On",,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST2;
        yield 'mark range on' => [
            $this->buildStream($test2),
            5,
            new DueWorkModel(),
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
            6,
            3,
        ];


        $test3= <<<TEST2
"capabilityMarkType: percent","useMarkRange: Off",,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST2;
        yield 'mark-per-cap percent' => [
            $this->buildStream($test3),
            5,
            new DueWorkModel(),
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
            1,
            2,
        ];


        // legacy markPerCapability setting on
        $legacyTestMarkPerCapabilityOn = <<<TEST
"markPerCapability: on","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        $legacyTestLevelCount           = 5;
        $legacyTestExpectedLevels       = ['', '', '', '', '',];
        $legacyTestExpectedCapabilities = [
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
        ];

        yield 'mark-per-cap legacy on: null mark type => assume percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $legacyTestLevelCount,
            new DueWorkModel(),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_PERCENT],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type none => cap mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_NONE))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_TOTAL],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_PERCENT))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_PERCENT],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_TOTAL))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_TOTAL],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type letter + grade group' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_LETTER))
                ->setGradeGroup((new GradeGroup())->setId(1))
            ,
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_LETTER],
            1,
        ];


        // legacy markPerCapability setting off, test with various due work mark types
        $legacyTestMarkPerCapabilityOff = <<<TEST
"markPerCapability: Off","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;

        yield 'mark-per-cap legacy off: null mark type => assume none' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $legacyTestLevelCount,
            new DueWorkModel(),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type none' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_NONE))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_PERCENT))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_TOTAL))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type letter + grade group' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $legacyTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_LETTER))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $legacyTestExpectedLevels,
            $legacyTestExpectedCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];
    }


    /**
     * @dataProvider getTestBuildData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory::build
     *
     * @param resource     $fp
     * @param int          $maxLevels
     * @param DueWorkModel $dueWork
     * @param array        $expectedLevels
     * @param array        $expectedCapabilities
     * @param bool         $expectedUseMarkRange
     * @param int|null     $expectedCapabilityMarkType
     * @param int|null     $expectedCapabilityGradeGroup
     */
    public function testBuild(
        $fp,
        int $maxLevels,
        DueWorkModel $dueWork,
        array $expectedLevels,
        array $expectedCapabilities,
        bool $expectedUseMarkRange,
        ?int $expectedCapabilityMarkType,
        ?int $expectedCapabilityGradeGroup
    ): void {
        $factory  = new MockCsvImporterFactory();
        $importer = $factory->build($fp, $maxLevels, $dueWork);
        $this->assertSame($expectedLevels, $importer->getLevels());
        $this->assertSame($expectedCapabilities, $importer->retrieveCapabilitiesWithIndicators());
        $this->assertSame($expectedUseMarkRange, $importer->isUseMarkRange());
        $this->assertSame($expectedCapabilityMarkType, $importer->getCapabilityMarkType());
        $this->assertSame($expectedCapabilityGradeGroup, $importer->getCapabilityGradeGroup());
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
