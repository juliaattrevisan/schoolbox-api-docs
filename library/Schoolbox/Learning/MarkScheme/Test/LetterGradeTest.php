<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevel;
use InvalidArgumentException;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\MarkScheme\LetterGrade::class)]
class LetterGradeTest extends TestCase
{
    public function testUnknownCaption()
    {
        $this->expectException(InvalidArgumentException::class);
        $humanReadable = 'Some Unknown Captions';
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $markScheme->getNormalisedMark($humanReadable);
    }


    /**
     * @param float  $normalised
     * @param string $expected
     * @throws InvalidArgumentException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormalisedToHumanReadable')]
    public function testGetHumanReadable($normalised, $expected)
    {
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string $humanReadable
     * @param float  $expected
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHumanReadableToNormalised')]
    public function testGetNormalised($humanReadable, $expected)
    {
        $gradeGroup = $this->getDummyLetterGradeGroup();
        $markScheme = new LetterGrade($gradeGroup);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string|null $formInput
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkData')]
    public function testConvertFormInputToHumanReadableMark(
        ?string $formInput,
        ?string $expected
    ): void {
        $markScheme = new LetterGrade($this->getDummyLetterGradeGroup());
        $this->assertSame($expected, $markScheme->convertFormInputToHumanReadableMark($formInput));
    }


    /**
     *
     * @param string|null $formInput
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkInvalidData')]
    public function testConvertFormInputToHumanReadableMarkInvalid(?string $formInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        $markScheme = new LetterGrade($this->getDummyLetterGradeGroup());
        $markScheme->convertFormInputToHumanReadableMark($formInput);
    }


    /**
     * @param string|null $humanReadableMark
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRetrieveHumanReadableMarkAsFormInputData')]
    public function testConvertHumanReadableMarkToFormInput(
        ?string $humanReadableMark,
        ?string $expected
    ): void {
        $markScheme = new LetterGrade($this->getDummyLetterGradeGroup());
        $this->assertSame($expected, $markScheme->convertHumanReadableMarkToFormInput($humanReadableMark));
    }


    // ==================== Data Providers ====================

    /**
     * @return array
     */
    public static function providerNormalisedToHumanReadable(): array
    {
        return [
            [-5,     'Needs Attention'],
            [0,      'Needs Attention'],
            [39,     'Needs Attention'],
            [39.99,  'Needs Attention'],
            [45,     'Acceptable'],
            [60,     'Good'],
            [89.99,  'Very Good'],
            [89.99999999999, 'Excellent'],  // testing for floating point accuracy beyond our reach
            [90,     'Excellent'],
            [100,    'Excellent'],
            [100.000,'Excellent'],
            [100.1,  'Excellent'],
        ];
    }


    /**
     * @return array
     */
    public static function providerHumanReadableToNormalised(): array
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
     * Data provider for `testConvertFormInputToHumanReadableMark`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'valid form input 1' => ['Needs Attention', 'Needs Attention'];
        yield 'valid form input 2' => ['Acceptable', 'Acceptable'];
        yield 'valid form input 3' => ['Good', 'Good'];
        yield 'valid form input 4' => ['Very Good', 'Very Good'];
        yield 'valid form input 5' => ['Excellent', 'Excellent'];
    }


    /**
     * Data provider for `testRetrieveHumanReadableValueAsFormInput`
     *
     * @return iterable
     */
    public static function getRetrieveHumanReadableMarkAsFormInputData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'valid form input 1' => ['Needs Attention', 'Needs Attention'];
        yield 'valid form input 2' => ['Acceptable', 'Acceptable'];
        yield 'valid form input 3' => ['Good', 'Good'];
        yield 'valid form input 4' => ['Very Good', 'Very Good'];
        yield 'valid form input 5' => ['Excellent', 'Excellent'];
    }


    /**
     * Data provider for `testConvertFormInputToHumanReadableMarkInvalid`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkInvalidData(): iterable
    {
        yield 'invalid valid form input 1' => ['Invalid'];
        yield 'invalid valid form input 2' => [''];
    }


    /**
     * @return GradeGroup
     */
    private function getDummyLetterGradeGroup(): GradeGroup
    {
        $gradeGroup = new GradeGroup();
        $gradeGroup->setId(3);
        $gradeGroup->setName('Competency');
        $gradeGroup->setGradeLevels(new GenericCollection(GradeLevel::class));

        $levelDatums = [
            ['13',  '0', '20', 'Needs Attention'],
            ['14', '40', '53', 'Acceptable'],
            ['15', '60', '68', 'Good'],
            ['16', '75', '88', 'Very Good'],
            ['17', '90', '95', 'Excellent'],
        ];
        foreach ($levelDatums as $levelDatum) {
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
