<?php

/**
 *
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class ValidCapabilityMarkTest extends TestCase
{

    public function getTestIsSatisfiedByData(): iterable
    {
        /* Mark types */
        $markSchemePercentage = new Percentage();
        $markSchemeLetterGrade = new LetterGrade(
            (new GradeGroup())
                ->setName('Test')
                ->setGradeLevels(
                    (new GenericCollection(GradeLevel\Model::class))
                        ->add(
                            (new GradeLevel\Model())
                                ->setCaption('D')
                                ->setLowerBound(0)
                                ->setRawGrade(12.5)
                        )
                        ->add(
                            (new GradeLevel\Model())
                                ->setCaption('C')
                                ->setLowerBound(25)
                                ->setRawGrade(37.5)
                        )
                        ->add(
                            (new GradeLevel\Model())
                                ->setCaption('B')
                                ->setLowerBound(50)
                                ->setRawGrade(62.5)
                        )
                        ->add(
                            (new GradeLevel\Model())
                                ->setCaption('A')
                                ->setLowerBound(75)
                                ->setRawGrade(87.5)
                        )
                ));

        /* Cap maximums */
        $capabilityMax1 = 5;
        $capabilityMax2 = 8;
        $capabilityMax3 = 16;
        $capabilityMax4 = 1;
        $capabilityMax5 = 100;
        $capabilityMax6 = 55;

        /* Cap marks */
        $capabilityMarkNull = null;
        $capabilityMark1 = "6";
        $capabilityMark2 = "101";
        $capabilityMark3 = "0";
        $capabilityMark4 = "55.55";
        $capabilityMark5 = "110";
        $capabilityMark6 = "99";
        $capabilityMark7 = "24.6";
        $capabilityMark8 = "87.67";
        $capabilityMark9 = "0.01";
        $capabilityMark10 = "13";
        $capabilityMark11 = "45.3";
        $capabilityMark12 = "100.01";
        $capabilityMark13 = "8.33";
        $capabilityMark14 = "9";

        /* Letter grades */
        $capabilityMarkA = "A";
        $capabilityMarkB = "B";
        $capabilityMarkC = "C";
        $capabilityMarkD = "D";

        /* Always-invalid cap marks */
        $capabilityMarkInvalid1 = "abc";
        $capabilityMarkInvalid2 = "-1";
        $capabilityMarkInvalid3 = "-8";
        $capabilityMarkInvalid4 = "-0.5";
        $capabilityMarkInvalid5 = "f00d";
        $capabilityMarkInvalid6 = "l8";
        $capabilityMarkInvalid7 = "1inux";

        /* Null cap mark */
        // means we are self-reflecting, so should always return true as cap marks are not given in reflection
        yield 'percent scheme, null' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkNull, true];
        yield 'total mark scheme, null' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkNull, true];
        yield 'letter grade scheme, null' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkNull, true];

        /* Total Mark type */
        // The mark needs to respect the cap max; 0 is permitted
        yield 'total mark scheme, 6/5' => [$capabilityMax1, new TotalMarkFractional($capabilityMax1), $capabilityMark1, false];
        yield 'total mark scheme, 6/8' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMark1, true];
        yield 'total mark scheme, 8.33/8' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMark13, false];
        yield 'total mark scheme, 9/8' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMark14, false];
        yield 'total mark scheme, 13/16' => [$capabilityMax3, new TotalMarkFractional($capabilityMax3), $capabilityMark10, true];
        yield 'total mark scheme, 0.01/1' => [$capabilityMax4, new TotalMarkFractional($capabilityMax4), $capabilityMark9, true];
        yield 'total mark scheme, 0/1' => [$capabilityMax4, new TotalMarkFractional($capabilityMax4), $capabilityMark3, true];
        yield 'total mark scheme, 110/100' => [$capabilityMax5, new TotalMarkFractional($capabilityMax5), $capabilityMark12, false];
        yield 'total mark scheme, 55.55/55' => [$capabilityMax6, new TotalMarkFractional($capabilityMax6), $capabilityMark4, false];

        /* Percentage based marks */
        // Percentage mark type always has max of 100, so cap max shouldn't make any difference
        yield 'percent scheme, 6%' => [$capabilityMax1, $markSchemePercentage, $capabilityMark1, true];
        yield 'percent scheme, 101%' => [$capabilityMax2, $markSchemePercentage, $capabilityMark2, false];
        yield 'percent scheme, 0%' => [$capabilityMax3, $markSchemePercentage, $capabilityMark3, true];
        yield 'percent scheme, 55.55%' => [$capabilityMax4, $markSchemePercentage, $capabilityMark4, true];
        yield 'percent scheme, 110%' => [$capabilityMax5, $markSchemePercentage, $capabilityMark5, false];
        yield 'percent scheme, 99%' => [$capabilityMax6, $markSchemePercentage, $capabilityMark6, true];
        yield 'percent scheme, 24.66%' => [$capabilityMax1, $markSchemePercentage, $capabilityMark7, true];
        yield 'percent scheme, 87.67%' => [$capabilityMax2, $markSchemePercentage, $capabilityMark8, true];
        yield 'percent scheme, 0.01%' => [$capabilityMax3, $markSchemePercentage, $capabilityMark9, true];
        yield 'percent scheme, 13%' => [$capabilityMax4, $markSchemePercentage, $capabilityMark10, true];
        yield 'percent scheme, 45.3%' => [$capabilityMax5, $markSchemePercentage, $capabilityMark11, true];
        yield 'percent scheme, 100.01%' => [$capabilityMax6, $markSchemePercentage, $capabilityMark12, false];

        // This should also be the case for letter grade mark type
        yield 'letter grade scheme, A' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMarkA, true];
        yield 'letter grade scheme, B' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMarkB, true];
        yield 'letter grade scheme, C' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMarkC, true];
        yield 'letter grade scheme, D' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMarkD, true];
        yield 'letter grade scheme, 6%' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMark1, false];
        yield 'letter grade scheme, 101%' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMark2, false];
        yield 'letter grade scheme, 0%' => [$capabilityMax3, $markSchemeLetterGrade, $capabilityMark3, false];
        yield 'letter grade scheme, 55.55%' => [$capabilityMax4, $markSchemeLetterGrade, $capabilityMark4, false];
        yield 'letter grade scheme, 110%' => [$capabilityMax5, $markSchemeLetterGrade, $capabilityMark5, false];
        yield 'letter grade scheme, 99%' => [$capabilityMax6, $markSchemeLetterGrade, $capabilityMark6, false];
        yield 'letter grade scheme, 24.66%' => [$capabilityMax1, $markSchemeLetterGrade, $capabilityMark7, false];
        yield 'letter grade scheme, 87.67%' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMark8, false];
        yield 'letter grade scheme, 0.01%' => [$capabilityMax3, $markSchemeLetterGrade, $capabilityMark9, false];
        yield 'letter grade scheme, 13%' => [$capabilityMax4, $markSchemeLetterGrade, $capabilityMark10, false];
        yield 'letter grade scheme, 45.3%' => [$capabilityMax5, $markSchemeLetterGrade, $capabilityMark11, false];
        yield 'letter grade scheme, 100.01%' => [$capabilityMax6, $markSchemeLetterGrade, $capabilityMark12, false];

        /* Always invalid marks */
        // Nonsensical values are always nonsensical and should fail, regardless of mark type.
        yield 'percent scheme, abc' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid1, false];
        yield 'total mark scheme, abc' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid1, false];
        yield 'letter grade scheme, abc' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid1, false];

        yield 'percent scheme, -1' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid2, false];
        yield 'total mark scheme, -1' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid2, false];
        yield 'letter grade scheme, -1' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid2, false];

        yield 'percent scheme, -8' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid3, false];
        yield 'total mark scheme, -8' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid3, false];
        yield 'letter grade scheme, -8' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid3, false];

        yield 'percent scheme, -0.5' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid4, false];
        yield 'total mark scheme, -0.5' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid4, false];
        yield 'letter grade scheme, -0.5' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid4, false];

        yield 'percent scheme, f00d' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid5, false];
        yield 'total mark scheme, f00d' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid5, false];
        yield 'letter grade scheme, f00d' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid5, false];

        yield 'percent scheme, l8' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid6, false];
        yield 'total mark scheme, l8' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid6, false];
        yield 'letter grade scheme, l8' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid6, false];

        yield 'percent scheme, 1inux' => [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid7, false];
        yield 'total mark scheme, 1inux' => [$capabilityMax2, new TotalMarkFractional($capabilityMax2), $capabilityMarkInvalid7, false];
        yield 'letter grade scheme, 1inux' => [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid7, false];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark::isSatisfiedBy
     *
     * @param float $capabilityMax
     * @param IMarkScheme $markScheme
     * @param string|null $capabilityMark
     * @param bool $expected                 Whether this test should assert true or false
    */
    public function testIsSatisfiedBy(float $capabilityMax, IMarkScheme $markScheme, ?string $capabilityMark, bool $expected)
    {
        $spec = new ValidCapabilityMark($capabilityMax, $markScheme);
        $this->assertSame($expected, $spec->isSatisfiedBy($capabilityMark));
    }
}
