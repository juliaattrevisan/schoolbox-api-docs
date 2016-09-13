<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use \InvalidArgumentException;
use \RangeException;
use \DomainException;

class MarkSchemeTest extends \PHPUnit_Framework_TestCase
{
    // MarkScheme = 'None'

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_None_getHumanReadable_Null()
    {
        $input = null;
        $markScheme = new None();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_None_getHumanReadable_Empty()
    {
        $input = '';
        $markScheme = new None();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_None_getHumanReadable_TooLow()
    {
        $input = -20.0;
        $markScheme = new None();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_None_getHumanReadable_TooHigh()
    {
        $input = 120.0;
        $markScheme = new None();
        $markScheme->getHumanReadableMark($input);
    }


    public function test_None_getHumanReadable_Valid()
    {
        $inputs = [
            0,
            0.0,
            50,
            99.99,
            100,
            100.000,
        ];
        $expected = null;
        $markScheme = new None();

        foreach($inputs as $input){
            $actual = $markScheme->getHumanReadableMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function test_None_GetNormalised_Null()
    {
        $input = null;
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($input);
        $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    public function test_None_GetNormalised_Empty()
    {
        $input = '';
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($input);
        $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }





    public function test_None_GetNormalised_Valid()
    {
        $inputs = [
            null,
            '',
            'a big dinosaur',
            20.5,
        ];
        $expected = null;
        $markScheme = new None();
        foreach($inputs as $input){
            $actual = $markScheme->getNormalisedMark($input);
            $this->assertEquals($expected, $actual);
        }
    }






    // MarkScheme = 'Completion'

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetHumanReadable_Null()
    {
        $input = null;
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_Completion_GetHumanReadable_TooLow()
    {
        $input = -1;
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetHumanReadable_InvalidType()
    {
        $input = 'zero';
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetHumanReadable_Invalid()
    {
        $input = '50.0';
        $markScheme = new Completion();
        $markScheme->getHumanReadableMark($input);
    }


    public function test_Completion_GetHumanReadable_Valid()
    {
        $inputs = [
            [Completion::NORMALISED_INCOMPLETE, Completion::HUMAN_READABLE_INCOMPLETE],
            [Completion::NORMALISED_COMPLETE,   Completion::HUMAN_READABLE_COMPLETE],
        ];

        $markScheme = new Completion();

        foreach($inputs as $pair){
            $input = $pair[0];
            $expected = $pair[1];

            $actual = $markScheme->getHumanReadableMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetNormalised_Null()
    {
        $input = null;
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetNormalised_Empty()
    {
        $input = '';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetNormalised_Whitespace()
    {
        $input = '     ';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Completion_GetNormalised_Invalid()
    {
        $input = 'some_unknown_human_readable_value';
        $markScheme = new Completion();
        $markScheme->getNormalisedMark($input);
    }


    public function test_Completion_GetNormalised_Valid()
    {
        $inputs = [
            Completion::HUMAN_READABLE_INCOMPLETE => IMarkScheme::NORMALISED_MARK_MIN,
            Completion::HUMAN_READABLE_COMPLETE   => IMarkScheme::NORMALISED_MARK_MAX,
        ];

        $markScheme = new Completion();

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }



    // MarkScheme = Percentage

    public function test_Percentage_getHumanReadable_Valid()
    {
        $inputs = [
            [0,           '0 %'],
            [0.0,         '0 %'],
            [0.000000000, '0 %'],
            [0.000000001, '0 %'],
            [0.5,         '1 %'],
            [0.9,         '1 %'],
            [1,           '1 %'],
            [1.0,         '1 %'],
            [1.000000001, '1 %'],
            [10,          '10 %'],
            [10.00000000, '10 %'],
            [99,          '99 %'],
            [99.49999999, '99 %'],
            [99.5,        '100 %'],
            [100,         '100 %'],
            [100.0,       '100 %'],
        ];
        $markScheme = new Percentage();

        foreach($inputs as $pair){
            $input = $pair[0];
            $expected = $pair[1];

            $actual = $markScheme->getHumanReadableMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    /**
     * @expectedException RangeException
     */
    public function test_Percentage_getHumanReadable_TooLow()
    {
        $input = -0.0001;
        $markScheme = new Percentage();
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_Percentage_getHumanReadable_TooHigh()
    {
        $input = 100.0000001;
        $markScheme = new Percentage();
        $markScheme->getHumanReadableMark($input);
    }

    public function test_Percentage_getNormalised_Valid()
    {
        $inputs = [
            '  0 %'      =>   0.0,
            '  0.0 %'    =>   0.0,
            '  0.01 %'   =>   0.01,
            '  0.0001 %' =>   0.0001,
            '  0.5 %'    =>   0.5,
            '  1 %'      =>   1.0,
            '  1.0 %'    =>   1.0,
            ' 50 %'      =>  50.0,
            ' 99.99 %'   =>  99.99,
            '100 %'      => 100.0,
            '100.0 %'    => 100.0,
        ];
        $markScheme = new Percentage();

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    /**
     * @expectedException RangeException
     */
    public function test_Percentage_getNormalised_TooLow()
    {
        $input = '-0.0001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($input);
    }


    /**
     * @expectedException RangeException
     */
    public function test_Percentage_getNormalised_TooHigh()
    {
        $input = '100.0000001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($input);
    }


    // Mark Scheme = LetterGrade

    public function test_LetterGrade_getHumanReadable()
    {
        $inputs = [
            [0,      'Needs Attention'],
            [39,     'Needs Attention'],
            [39.999, 'Needs Attention'],
            [45,     'Acceptable'],
            [60,     'Good'],
            [89.999, 'Very Good'],
            [90,     'Excellent'],
            [100,    'Excellent'],
            [100.000,'Excellent'],
        ];
        $gradeGroup = $this->getDummyLetterGradeGroup();

        $markScheme = new LetterGrade($gradeGroup);

        foreach($inputs as $pair){
            $input = $pair[0];
            $expected = $pair[1];

            $actual = $markScheme->getHumanReadableMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function test_LetterGrade_getNormalised()
    {
        $inputs = [
            'Needs Attention' => 20.0,
            'Acceptable'      => 53.0,
            'Good'            => 68.0,
            'Very Good'       => 88.0,
            'Excellent'       => 95.0,
        ];
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    /**
     * @expectedException DomainException
     */
    public function test_LetterGrade_Unknown_Caption()
    {
        $input = 'Some Unknown Captions';
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_LetterGrade_Normalised_Negative()
    {
        $input = -5;
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_LetterGrade_Normalised_TooHigh()
    {
        $input = 100.1;
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $markScheme->getHumanReadableMark($input);
    }

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



    // Mark Scheme = TotalMarkWholeNumber

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_TotalMarkWholeNumber_Fractional_Denominator()
    {
        $input = 10.5;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkWholeNumber_Negative_Denominator()
    {
        $input = -10;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkWholeNumber_Zero_Denominator()
    {
        $input = 0;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function test_TotalMarkWholeNumber_setDenominator()
    {
        $input = 50;
        $markScheme = new TotalMarkWholeNumber(50);
        $markScheme->setTotalMark($input);
    }

    public function test_TotalMarkWholeNumber_getHumanReadable()
    {
        $inputs = [
            [0,        '0 / 100'],
            [0.1,      '0 / 100'],
            [0.2,   '0.25 / 100'],
            [0.3,   '0.25 / 100'],
            [01,       '1 / 100'],
            [1.0,      '1 / 100'],
            [99.4,  '99.5 / 100'],
            [99.7, '99.75 / 100'],
            [99.9,   '100 / 100'],
            [100,    '100 / 100'],
            [100.0,  '100 / 100'],
        ];
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);

        foreach($inputs as $pair){
            $input = $pair[0];
            $expected = $pair[1];

            $actual = $markScheme->getHumanReadableMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkWholeNumber_GetHumanReadable_NegativeNumerator()
    {
        $input = -10;
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $markScheme->getHumanReadableMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkWholeNumber_NumeratorTooHigh()
    {
        $input = 100.25;
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $markScheme->getHumanReadableMark($input);
    }

    public function test_TotalMarkWholeNumber_getNormalised()
    {
        $inputs = [
            '0'   => 0.0,
            ' 0'  => 0.0,
            '0.0' => 0.0,
            '0 '  => 0.0,
            '1'   => 1.0,
            '45'  => 45.0,
            '100' => 100.0,
        ];
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function test_TotalMarkWholeNumber_getNormalised_Recurring()
    {
        $inputs = [
            '0'     => 0.0,
            '1'     => 33.333333333,
            '3'     => 100.0,
        ];
        $totalMark = 3;
        $delta = 0.00001;
        $markScheme = new TotalMarkWholeNumber($totalMark);

        foreach($inputs as $input => $expected){

            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertLessThan($delta, abs($expected - $actual), $message);
        }
    }


    // Mark Scheme = TotalMarkFractional

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_NegativeTotal()
    {
        $input = -1.0;
        new TotalMarkFractional($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_SetTotalNegative()
    {
        $input = -50.0;

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->setTotalMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_SetTotalExceedOriginal()
    {
        $input = 60.0;

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->setTotalMark($input);
    }

    public function test_TotalMarkFractional_SetTotalValid()
    {
        $inputs = [
            0,
            0.0,
            10.0,
            49.999999,
            50,
            50.0,
            50.000000,
        ];

        $originalTotalMark = 50.0;
        $markScheme = new TotalMarkFractional($originalTotalMark);

        foreach($inputs as $newTotalMark){
            $markScheme->setTotalMark($newTotalMark);
        }
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_getHumanReadable_tooLow()
    {
        $inputs = -1.0;

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($inputs);
    }

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_getHumanReadable_tooHigh()
    {
        $inputs = 100.0001;

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($inputs);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_TotalMarkFractional_getNormalised_Null()
    {
        $input = null;

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_TotalMarkFractional_getNormalised_Empty()
    {
        $input = '';

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($input);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_TotalMarkFractional_getNormalised_Invalid()
    {
        $input = 'ten';

        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($input);
    }


    public function test_TotalMarkFractional_getNormalised()
    {
        $inputs = [
            '0'          => 0.0,
            '0.0'        => 0.0,
            ' 0'         => 0.0,
            ' 0 '        => 0.0,
            '1.5'        => 1.5,
            '50'         => 50.0,
            '66.6666666' => 66.6666666,
            '99.99'      => 99.99,
            '100'        => 100.0,
        ];

        $totalMark = 100.0;
        $markScheme = new TotalMarkFractional($totalMark);

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertEquals($expected, $actual, $message);
        }
    }

    public function test_TotalMarkFractional_getNormalised_Recursive()
    {
        $inputs = [
            '0'   =>   0.0,
            '1'   =>  33.33333333,
            '2'   =>  66.66666666,
            '3'   => 100.0,
            '0.1' =>   3.33333333,

        ];

        $totalMark = 3;
        $delta = 0.00001;
        $markScheme = new TotalMarkFractional($totalMark);

        foreach($inputs as $input => $expected){
            $actual = $markScheme->getNormalisedMark($input);
            $message = "Failed asserting equality. Input: $input, Expected: $expected, Actual: $actual";
            $this->assertLessThan($delta, abs($expected - $actual), $message);
        }
    }

}