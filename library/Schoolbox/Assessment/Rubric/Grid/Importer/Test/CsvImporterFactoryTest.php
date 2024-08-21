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
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporterFactory::class)]
class CsvImporterFactoryTest extends TestCase
{
    /**
     * @return iterable
     */
    public function getTestBuildData(): iterable
    {
        $test1 = <<<TEST
,,,,,,
cap2,ind1,,,,,
,,0,2,4,6,8
,ind2,,,,,
,,0,2,4,6,8
cap1,ind3,,,,,
,,0,2,4,6,8
,ind4,,,,,
,,0,2,4,6,8
TEST;
        $emptyDescriptorTestLevels       = ['', '', '', '', '',];
        $emptyDescriptorTestCapabilities = [
            [
                'name'       => 'cap2',
                'maxValue'   => null,
                'indicators' => [
                    [
                        'name'        => 'ind1',
                        'descriptors' => [
                            ['name' => '', 'value' => (float)0,],
                            ['name' => '', 'value' => (float)2,],
                            ['name' => '', 'value' => (float)4,],
                            ['name' => '', 'value' => (float)6,],
                            ['name' => '', 'value' => (float)8,],
                        ],
                    ],
                    [
                        'name'        => 'ind2',
                        'descriptors' => [
                            ['name' => '', 'value' => (float)0,],
                            ['name' => '', 'value' => (float)2,],
                            ['name' => '', 'value' => (float)4,],
                            ['name' => '', 'value' => (float)6,],
                            ['name' => '', 'value' => (float)8,],
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
                            ['name' => '', 'value' => (float)0,],
                            ['name' => '', 'value' => (float)2,],
                            ['name' => '', 'value' => (float)4,],
                            ['name' => '', 'value' => (float)6,],
                            ['name' => '', 'value' => (float)8,],
                        ],
                    ],
                    [
                        'name'        => 'ind4',
                        'descriptors' => [
                            ['name' => '', 'value' => (float)0,],
                            ['name' => '', 'value' => (float)2,],
                            ['name' => '', 'value' => (float)4,],
                            ['name' => '', 'value' => (float)6,],
                            ['name' => '', 'value' => (float)8,],
                        ],
                    ],
                ],
            ],
        ];
        yield 'empty descriptors' => [
            $this->buildStream($test1),
            5,
            new DueWorkModel(),
            $emptyDescriptorTestLevels,
            $emptyDescriptorTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null
        ];


        $test2 = <<<TEST
"capabilityMarkType: none","useMarkRange: On",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        $standardTestLevelCount   = 5;
        $standardTestLevels       = ['', '', '', '', '',];
        $standardTestCapabilities = [
            [
                'name'       => 'Test Capability 1',
                'maxValue'   => 9,
                'indicators' => [
                    [
                        'name'        => 'Indicator 1',
                        'descriptors' => [
                            ['name' => 'a', 'value' => (float)15,],
                            ['name' => 'b', 'value' => (float)10,],
                            ['name' => 'c', 'value' => (float)5,],
                            ['name' => 'd', 'value' => (float)1,],
                            ['name' => 'e', 'value' => (float)0,],
                        ],
                    ],
                    [
                        'name'        => 'Indicator 2',
                        'descriptors' => [
                            ['name' => 'a', 'value' => (float)15,],
                            ['name' => 'b', 'value' => (float)10,],
                            ['name' => 'c', 'value' => (float)5,],
                            ['name' => 'd', 'value' => (float)1,],
                            ['name' => 'e', 'value' => (float)0,],
                        ],
                    ],
                ],
            ],
        ];


        // For this test only: capability mark value is null
        $markPerCapOffTestCapabilities                = $standardTestCapabilities;
        $markPerCapOffTestCapabilities[0]['maxValue'] = null;
        yield 'mark-per-cap off, mark range on' => [
            $this->buildStream($test2),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $markPerCapOffTestCapabilities,
            true,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];


        $test3 = <<<TEST
"capabilityMarkType: percent","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        yield 'mark-per-cap percent' => [
            $this->buildStream($test3),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_PERCENT],
            null,
        ];


        $test4 = <<<TEST
"capabilityMarkType: letter:1","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        yield 'mark-per-cap letter:1' => [
            $this->buildStream($test4),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_LETTER],
            1,
        ];


        $test5 = <<<TEST
"capabilityMarkType: letter : 1","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        yield 'mark-per-cap letter:1 with spaces' => [
            $this->buildStream($test5),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_LETTER],
            1,
        ];


        $test6 = <<<TEST
"capabilityMarkType: letter:","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        yield 'mark-per-cap letter with empty grade group' => [
            $this->buildStream($test6),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_LETTER],
            null,
        ];


        $test7 = <<<TEST
"capabilityMarkType: letter","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;
        yield 'mark-per-cap letter with null grade group' => [
            $this->buildStream($test7),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_LETTER],
            null,
        ];



        // legacy markPerCapability setting on
        $legacyTestMarkPerCapabilityOn = <<<TEST
"markPerCapability: on","useMarkRange: Off",,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;

        yield 'mark-per-cap legacy on: null mark type => assume percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_PERCENT],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type none => cap mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_NONE))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_TOTAL],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_PERCENT))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_PERCENT],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_TOTAL))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_TOTAL],
            null,
        ];

        yield 'mark-per-cap legacy on: mark type letter + grade group' => [
            $this->buildStream($legacyTestMarkPerCapabilityOn),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_LETTER))
                ->setGradeGroup((new GradeGroup())->setId(1))
            ,
            $standardTestLevels,
            $standardTestCapabilities,
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
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type none' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_NONE))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type percent' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_PERCENT))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type total' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_TOTAL))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];

        yield 'mark-per-cap legacy off: mark type letter + grade group' => [
            $this->buildStream($legacyTestMarkPerCapabilityOff),
            $standardTestLevelCount,
            (new DueWorkModel())
                ->setMarkType((new MarkType())->setSlug(MarkType::SLUG_LETTER))
                ->setGradeGroup((new GradeGroup())->setId(1)),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];


        // Edge case: there's less levels than descriptors
        // Expand list of levels out to the max number of descriptors
        $noLevelsTest = <<<TEST
,,,
"Test Capability 1","Indicator 1",a,b,c,d,e
9,,15,10,5,1,0
,"Indicator 2",a,b,c,d,e
,,15,10,5,1,0
TEST;

        yield 'no levels provided' => [
            $this->buildStream($noLevelsTest),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];


        // If there's more levels than the level count:
        $tooManyLevelsTest = <<<TEST
,,,,,,,,,,,
"Test Capability 1","Indicator 1",a,b,c,d,e,f,g,h,i,j
9,,15,10,5,1,0,0,0,0,0,0
,"Indicator 2",a,b,c,d,e,f,g,h,i,j
,,15,10,5,1,0,0,0,0,0,0
TEST;

        yield 'too many levels provided' => [
            $this->buildStream($tooManyLevelsTest),
            $standardTestLevelCount,
            new DueWorkModel(),
            $standardTestLevels,
            $standardTestCapabilities,
            false,
            MockCsvImporterFactory::MAPPING[MarkType::SLUG_NONE],
            null,
        ];
    }


    /**
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
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestBuildData')]
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
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $content);
        rewind($stream);
        return $stream;
    }
}
