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

    public function getTestIsSatisfiedByData() : iterable
    {
        /* Mark types */
        $markSchemePercentage = new Percentage();
        $markSchemeTotalMark = new TotalMarkFractional();
        $markSchemeLetterGrade = new LetterGrade((new GradeGroup())->setGradeLevels(new GenericCollection(GradeLevel\Model::class)));

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
        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkNull, true];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkNull, true];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkNull, true];

        /* Total Mark type */
        // The mark needs to respect the cap max; 0 is permitted
        yield [$capabilityMax1, $markSchemeTotalMark, $capabilityMark1, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMark1, true];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMark13, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMark14, false];
        yield [$capabilityMax3, $markSchemeTotalMark, $capabilityMark10, true];
        yield [$capabilityMax4, $markSchemeTotalMark, $capabilityMark9, true];
        yield [$capabilityMax4, $markSchemeTotalMark, $capabilityMark3, true];
        yield [$capabilityMax5, $markSchemeTotalMark, $capabilityMark12, false];
        yield [$capabilityMax6, $markSchemeTotalMark, $capabilityMark4, false];

        /* Percentage based marks */
        // Percentage mark type always has max of 100, so cap max shouldn't make any difference
        yield [$capabilityMax1, $markSchemePercentage, $capabilityMark1, true];
        yield [$capabilityMax2, $markSchemePercentage, $capabilityMark2, false];
        yield [$capabilityMax3, $markSchemePercentage, $capabilityMark3, true];
        yield [$capabilityMax4, $markSchemePercentage, $capabilityMark4, true];
        yield [$capabilityMax5, $markSchemePercentage, $capabilityMark5, false];
        yield [$capabilityMax6, $markSchemePercentage, $capabilityMark6, true];
        yield [$capabilityMax1, $markSchemePercentage, $capabilityMark7, true];
        yield [$capabilityMax2, $markSchemePercentage, $capabilityMark8, true];
        yield [$capabilityMax3, $markSchemePercentage, $capabilityMark9, true];
        yield [$capabilityMax4, $markSchemePercentage, $capabilityMark10, true];
        yield [$capabilityMax5, $markSchemePercentage, $capabilityMark11, true];
        yield [$capabilityMax6, $markSchemePercentage, $capabilityMark12, false];

        // This should also be the case for letter grade mark type
        yield [$capabilityMax1, $markSchemeLetterGrade, $capabilityMark1, true];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMark2, false];
        yield [$capabilityMax3, $markSchemeLetterGrade, $capabilityMark3, true];
        yield [$capabilityMax4, $markSchemeLetterGrade, $capabilityMark4, true];
        yield [$capabilityMax5, $markSchemeLetterGrade, $capabilityMark5, false];
        yield [$capabilityMax6, $markSchemeLetterGrade, $capabilityMark6, true];
        yield [$capabilityMax1, $markSchemeLetterGrade, $capabilityMark7, true];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMark8, true];
        yield [$capabilityMax3, $markSchemeLetterGrade, $capabilityMark9, true];
        yield [$capabilityMax4, $markSchemeLetterGrade, $capabilityMark10, true];
        yield [$capabilityMax5, $markSchemeLetterGrade, $capabilityMark11, true];
        yield [$capabilityMax6, $markSchemeLetterGrade, $capabilityMark12, false];

        /* Always invalid marks */
        // Nonsensical values are always nonsensical and should fail, regardless of mark type.
        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid1, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid1, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid1, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid2, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid2, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid2, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid3, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid3, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid3, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid4, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid4, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid4, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid5, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid5, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid5, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid6, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid6, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid6, false];

        yield [$capabilityMax2, $markSchemePercentage, $capabilityMarkInvalid7, false];
        yield [$capabilityMax2, $markSchemeTotalMark, $capabilityMarkInvalid7, false];
        yield [$capabilityMax2, $markSchemeLetterGrade, $capabilityMarkInvalid7, false];
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
