<?php

/**
 * CapabilityMarkTypeTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\MarkScheme\Factory;

/**
 * Class CapabilityMarkTypeTest
 *
 * Rubric Capabilities' values can be represented with a different Mark Type than its
 * parent Assessment. This should ensure that Capabilities don't inadvertently regress
 * to using their parent Assessment's Mark Type.
 *
 * @package Schoolbox\Example\Test
 */
class CapabilityMarkTypeTest extends TestCase
{
    /**
     * @param MarkType                    $dueWorkMarkType
     * @param MarkType                    $capabilityMarkType
     * @param GradeLevel\Group\Model|null $capabilityGradeGroup
     * @param array                       $expected
     * @throws \DomainException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidCapabilityMarkType')]
    public function testValidCapabilityMarkType(MarkType $dueWorkMarkType, MarkType $capabilityMarkType, ?GradeLevel\Group\Model $capabilityGradeGroup, array $expected)
    {
        $generator        = new Generator();
        $capabilities     = \Schoolbox\Assessment\Rubric\RowSet\Test\Generator::TEST_CAPABILITIES;
        $levels           = \Schoolbox\Assessment\Rubric\Grid\Test\Generator::TEST_LEVELS;

        /* Capabilities made available for a simulated DueWork instance
         */
        $capabilityUsages = \Schoolbox\Assessment\Rubric\Grid\Test\Generator::TEST_CAPABILITY_USAGES;

        /* These are the Descriptors that we simulate as being selected,
         * corresponding to elements in TEST_CAPABILITY_USAGES that were
         * simulated as enabled for a particular DueWork instance
         */
        $capabilityMarks  = \Schoolbox\Assessment\Rubric\MarkedGrid\Test\Generator::TEST_CAPABILITY_MARKS;

        /* Set up a grid generator with the assessment specified
         */
        $generator->setDueWork((new DueWork\Model())
            ->setMarkType($dueWorkMarkType)
            ->setCapabilityMarkType($capabilityMarkType)
            ->setCapabilityGradeGroup($capabilityGradeGroup));

        /* Reach in and yank out a bunch of human readable marks using our local variant
         */
        $outcome = $this->calculateCapabilityMarks($generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        ));

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }


    /**
     * Data provider for testValidCapabilityMarkType
     *
     * @return array
     */
    public static function providerValidCapabilityMarkType(): array
    {
        $markTypeNone = (new MarkType())->setId(MarkType::TYPE_NONE)->setSlug(MarkType::SLUG_NONE);
        $markTypeTotal = (new MarkType())->setId(MarkType::TYPE_TOTAL)->setSlug(MarkType::SLUG_TOTAL);
        $markTypePercentage = (new MarkType())->setId(MarkType::TYPE_PERCENT)->setSlug(MarkType::SLUG_PERCENT);
        $markTypeLetter = (new MarkType())->setId(MarkType::TYPE_LETTER)->setSlug(MarkType::SLUG_LETTER);

        /* Not using a grade group - send it null
         */
        $gradeGroupNull = null;

        /* Letter Grade construction; simulate the A-E scale we have in vagrant atm
         */
        $gradeGroupStandard = (new GradeLevel\Group\Model())
            ->setGradeLevels(new Collection());

        $gradeGroupStandard->getGradeLevels()
                ->add((new GradeLevel\Model())->setLowerBound(0)->setRawGrade(5)->setCaption('E'))
                ->add((new GradeLevel\Model())->setLowerBound(10)->setRawGrade(15)->setCaption('E+'))
                ->add((new GradeLevel\Model())->setLowerBound(20)->setRawGrade(25)->setCaption('D'))
                ->add((new GradeLevel\Model())->setLowerBound(30)->setRawGrade(35)->setCaption('D+'))
                ->add((new GradeLevel\Model())->setLowerBound(40)->setRawGrade(45)->setCaption('C'))
                ->add((new GradeLevel\Model())->setLowerBound(50)->setRawGrade(55)->setCaption('C+'))
                ->add((new GradeLevel\Model())->setLowerBound(60)->setRawGrade(65)->setCaption('B'))
                ->add((new GradeLevel\Model())->setLowerBound(70)->setRawGrade(75)->setCaption('B+'))
                ->add((new GradeLevel\Model())->setLowerBound(80)->setRawGrade(85)->setCaption('A'))
                ->add((new GradeLevel\Model())->setLowerBound(90)->setRawGrade(95)->setCaption('A+'));

        return [
            'assessment mark type None, capability mark type Total' => [
                $markTypeNone,
                $markTypeTotal,
                $gradeGroupNull,
                [
                    '8 / 16',
                    '6 / 16'
                ]
            ],

            'assessment mark type None, capability mark type Percentage' => [
                $markTypeNone,
                $markTypePercentage,
                $gradeGroupNull,
                [
                    '50 %',
                    '37.5 %'
                ]
            ],

            'assessment mark type None, capability mark type None' => [
                $markTypeNone,
                $markTypeNone,
                $gradeGroupNull,
                [
                    '',
                    ''
                ]
            ],

            'assessment mark type None, capability mark type Letter (Standard)' => [
                $markTypeNone,
                $markTypeLetter,
                $gradeGroupStandard,
                [
                    'C+',
                    'D+'
                ]
            ],

            /* Capability mark types should be independent of their parent assessment's mark type
             * therefore same answers should come out, regardless of assessment mark type
             */
            'assessment mark type Total, capability mark type Total' => [
                $markTypeNone,
                $markTypeTotal,
                $gradeGroupNull,
                [
                    '8 / 16',
                    '6 / 16'
                ]
            ],

            'assessment mark type Total, capability mark type Percentage' => [
                $markTypeNone,
                $markTypePercentage,
                $gradeGroupNull,
                [
                    '50 %',
                    '37.5 %'
                ]
            ],

            'assessment mark type Total, capability mark type None' => [
                $markTypeNone,
                $markTypeNone,
                $gradeGroupNull,
                [
                    '',
                    ''
                ]
            ],

            'assessment mark type Total, capability mark type Letter (Standard)' => [
                $markTypeNone,
                $markTypeLetter,
                $gradeGroupStandard,
                [
                    'C+',
                    'D+'
                ]
            ],

            /* Add more cases/combinations here, as you discover them
             */
        ];
    }


    /**
     * A custom method to inspect the contents of the Marked Grid's capabilities
     * The main app never required this method to function, so it's unit-test-only
     *
     * It got cloned out of another test and modified to suit what we're doing here
     *
     * @param MarkedGrid $markedGrid
     * @return array
     * @throws \DomainException
     * @throws \RangeException
     */
    private function calculateCapabilityMarks(MarkedGrid $markedGrid): array
    {
        $stack = [];
        $markSchemeFactory = new Factory();
        $dueWork = $markedGrid->getGrid()->getAssessment();

        foreach ($markedGrid->getCapabilityMarks() as $index => $capabilityMark) {
            $capabilityUsage = $capabilityMark->getCapabilityUsage();

            /* Figure out the mark scheme for this capability
             */
            $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($dueWork, $capabilityUsage->getMaxValue());

            // Use the actual input value (which may be null) instead of the
            // calculated mark.
            $capabilityValue = $markedGrid->getGrid()->isMarkPerCapability()
                ? $capabilityMark->getValue()
                : $capabilityMark->calculateScaledMark();

            $capabilityMark = (new CapabilityMark($capabilityUsage, $capabilityMarkScheme))
                ->setValue($capabilityValue);
            $stack[] = $capabilityMarkScheme->getHumanReadableMark($capabilityMark->retrieveNormalizedMark());
        }

        return $stack;
    }
}
