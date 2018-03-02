<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;


use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use \RangeException;
use \InvalidArgumentException;

class LetterGradeTest extends TestCase
{

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Unknown_Caption()
    {
        $humanReadable = 'Some Unknown Captions';
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @dataProvider provider_normalisedToHumanReadable
     * @param float $normalised
     * @param string $expected
     */
    public function test_getHumanReadable($normalised, $expected)
    {
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float $expected
     */
    public function test_getNormalised($humanReadable, $expected)
    {
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    // ==================== Data Providers ====================

    public function provider_normalisedToHumanReadable()
    {
        return [
            [-5,     'Needs Attention'],
            [0,      'Needs Attention'],
            [39,     'Needs Attention'],
            [39.999, 'Needs Attention'],
            [45,     'Acceptable'],
            [60,     'Good'],
            [89.999, 'Very Good'],
            [90,     'Excellent'],
            [100,    'Excellent'],
            [100.000,'Excellent'],
            [100.1,  'Excellent'],
        ];
    }

    public function provider_humanReadableToNormalised()
    {
        return [
            ['Needs Attention', 20.0],
            ['Acceptable',      53.0],
            ['Good',            68.0],
            ['Very Good',       88.0],
            ['Excellent',       95.0],
        ];
    }

    /**
     * @return GradeGroup
     */
    private function getDummyLetterGradeGroup()
    {
        $gradeGroup = new GradeGroup();
        $gradeGroup->setId(3);
        $gradeGroup->setName('Competency');

        $levelDatums = [
            ['13',  '0', '20', 'Needs Attention'],
            ['14', '40', '53', 'Acceptable'],
            ['15', '60', '68', 'Good'],
            ['16', '75', '88', 'Very Good'],
            ['17', '90', '95', 'Excellent'],
        ];
        foreach($levelDatums as $levelDatum){
            $level = new GradeLevel();
            $level->setId($levelDatum[0]);
            $level->setLowerBound($levelDatum[1]);
            $level->setRawGrade($levelDatum[2]);
            $level->setCaption($levelDatum[3]);
            $level->setGroup($gradeGroup);

            $gradeGroup->addGradeLevel($level);
        }

        return $gradeGroup;
    }
}
