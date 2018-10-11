<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark;
use PHPUnit\Framework\TestCase;

use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;


class ValidCapabilityMarkTest extends TestCase
{

    public function getTestIsSatisfiedByData() : iterable
    {
        /* Mark types */
        $markSchemePercentage = new Percentage();
        $markSchemeTotalMark = new TotalMarkFractional();
        $markSchemeLetterGrade = new LetterGrade(new GradeGroup());

        /* Cap maximums */
        $capabilityMax1 = 5;
        $capabilityMax2 = 8;
        $capabilityMax3 = 16;
        $capabilityMax4 = 1;
        $capabilityMax5 = 100;

        /* Cap marks */
        $capabilityMarkNull = null;
        $capabilityMarkPercentage1 = 6;
        $capabilityMarkPercentage2 = 101;
        $capabilityMarkPercentage3 = 0;
        $capabilityMarkPercentage4 = 55.55;
        $capabilityMarkPercentage5 = 110;
        $capabilityMarkPercentage6 = 99;

        yield [$capabilityMax1, $markSchemePercentage, $capabilityMarkPercentage1, true];
    }

    /**
     * @dataProvider getTestIsSatisfiedByData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark::isSatisfiedBy
     *
     * @param float $capabilityMax
     * @param IMarkScheme $markScheme
     * @param $capabilityMark
     * @param bool $expected                 Whether this test should assert true or false
    */
    public function testIsSatisfiedBy(float $capabilityMax, IMarkScheme $markScheme, $capabilityMark, bool $expected)
    {
        $spec = new ValidCapabilityMark($capabilityMax, $markScheme);
        $this->assertTrue($spec->isSatisfiedBy($capabilityMark));
    }
}
