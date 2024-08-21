<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\Assessment\Test\ParticipantLinker;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use InvalidArgumentException;
use Schoolbox\User;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\MarkScheme\TotalMarkFractional::class)]
class TotalMarkFractionalTest extends TestCase
{
    /**
     * An attempt is now required when retrieving a human-readable mark.
     *
     *
     */
    public function testGetHumanReadableWithoutAttempt()
    {
        $this->expectException(\DomainException::class);
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getHumanReadableMark(40);
    }

    /**
     * An attempt is now required when retrieving a human-readable mark.
     *
     *
     */
    public function testGetNormalisedWithoutAttempt()
    {
        $this->expectException(\DomainException::class);
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark(40);
    }

    public function testGetNormalisedNull()
    {
        $this->expectException(InvalidArgumentException::class);
        $humanReadable = null;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }

    public function testGetNormalisedEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        $humanReadable = '';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }

    public function testGetNormalisedInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $humanReadable = 'ten';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }


    /**
     * @param string $humanReadable
     * @param float  $totalMark
     * @param float  $expected
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHumanReadableToNormalised')]
    public function testGetNormalised($humanReadable, $totalMark, $expected)
    {
        $markScheme = new TotalMarkFractional(null);
        $actual = $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string $humanReadable
     * @param float  $totalMark
     * @param float  $expected
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHumanReadableToRecurringNormalised')]
    public function testGetRecurringNormalised($humanReadable, $totalMark, $expected)
    {
        $delta = 0.00001;
        $markScheme = new TotalMarkFractional(null);
        $actual = $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);
    }


    /**
     * @param float  $normalised
     * @param float  $totalMark
     * @param string $humanReadable
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormalisedToHumanReadable')]
    public function testGetHumanReadable($normalised, $totalMark, $humanReadable)
    {
        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
        $message = "Failed asserting equality. Input: $normalised, Expected: $humanReadable, Actual: $actual";
        $this->assertEquals($humanReadable, $actual, $message);
    }


    /**
     * @param float  $normalised
     * @param float  $totalMark
     * @param string $humanReadable
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormalisedToHumanReadableRecurring')]
    public function testGetHumanReadableFromRecurring($normalised, $totalMark, $humanReadable)
    {
        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
        $message = "Failed asserting equality. Input: $normalised, Expected: $humanReadable, Actual: $actual";
        $this->assertEquals($humanReadable, $actual, $message);
    }


    /**
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnexpectedValueException
     */
    public function testGetNormalisedQuiz()
    {
        $quiz = $this->getDummyQuiz();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getParticipant($this->getDummyQuizAttempter())->getAllResponses()->first();
        $humanReadable = 1;

        // 1 / 2.0 * 100.0
        $expected = 50.0;

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getNormalisedMark($humanReadable, $quizAttempt);

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnexpectedValueException
     */
    public function testGetNormalisedQuizOriginalTotalExceedsAttemptTotal()
    {
        $quiz = $this->getDummyQuiz();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getParticipant($this->getDummyQuizAttempter())->getAllResponses()->first();
        $humanReadable = 1;

        // 1 / 2.0 * 100.0
        $expected = 50.0;

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getNormalisedMark($humanReadable, $quizAttempt);

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     */
    public function testGetNormalisedQuizTotal()
    {
        $humanReadable = 1;

        // 1 / 3.0 * 100.0
        $expected = 33.333333;
        $delta = 0.00001;

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt(3));

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);
    }


    /**
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnexpectedValueException
     */
    public function testGetHumanReadableQuiz()
    {
        $quiz = $this->getDummyQuiz();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getParticipant($this->getDummyQuizAttempter())->getAllResponses()->first();
        $normalised = 50.0;
        $expected = '1 / 2';

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @throws InvalidArgumentException
     * @throws \DomainException
     * @throws \RangeException
     * @throws \UnexpectedValueException
     */
    public function testGetHumanReadableQuizFractional()
    {
        $quiz = $this->getDummyQuiz();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getParticipant($this->getDummyQuizAttempter())->getAllResponses()->first();
        $normalised = 87.6;
        $expected = '1.75 / 2';

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * A quiz does not have a max mark
     */
    public function testGetHumanReadableQuizFractionalOriginalTotalExceedsAttemptTotal()
    {
        $quiz = $this->getDummyQuiz();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getParticipant($this->getDummyQuizAttempter())->getAllResponses()->first();
        $normalised = 87.6;
        $expected = '1.75 / 2';

        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     *
     * @param TotalMarkFractional $markScheme
     * @param string|null         $formInput
     * @param string|null         $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkData')]
    public function testConvertFormInputToHumanReadableMark(
        TotalMarkFractional $markScheme,
        ?string $formInput,
        ?string $expected
    ): void {
        $this->assertSame($expected, $markScheme->convertFormInputToHumanReadableMark($formInput));
    }



    /**
     * @param TotalMarkFractional $markScheme
     * @param string|null         $formInput
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkInvalidData')]
    public function testConvertFormInputToHumanReadableMarkInvalid(
        TotalMarkFractional $markScheme,
        ?string $formInput
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $markScheme->convertFormInputToHumanReadableMark($formInput);
    }


    /**
     *
     * @param TotalMarkFractional $markScheme
     * @param string|null         $humanReadableMark
     * @param string|null         $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRetrieveHumanReadableMarkAsFormInputData')]
    public function testConvertHumanReadableMarkToFormInput(
        TotalMarkFractional $markScheme,
        ?string $humanReadableMark,
        ?string $expected
    ): void {
        $this->assertSame($expected, $markScheme->convertHumanReadableMarkToFormInput($humanReadableMark));
    }


    // ==================== Data Providers ====================

    /**
     * @return array
     */
    public function providerInvalidTotalMarks(): array
    {
        return [
            [null],
            [''],
            ['10'],
            ['ten'],
            [new \stdClass()],
            [true],
            [false],
            [[100]],
        ];
    }


    /**
     * @return array
     */
    public static function providerHumanReadableToRecurringNormalised(): array
    {
        $totalMark = 3.0;
        return [
            ['0',   $totalMark, 0.0],
            ['1',   $totalMark, 33.33333333],
            ['2',   $totalMark, 66.66666666],
            ['3',   $totalMark, 100.0],
            ['0.1', $totalMark, 3.33333333],
        ];
    }


    /**
     * @return array
     */
    public function providerSetValidTotal(): array
    {
        $originalTotalMark = 50;
        return [
            [-1.1,      $originalTotalMark],
            [-1,        $originalTotalMark],
            [0,         $originalTotalMark],
            [0.0,       $originalTotalMark],
            [10.0,      $originalTotalMark],
            [49.999999, $originalTotalMark],
            [50,        $originalTotalMark],
            [50.0,      $originalTotalMark],
            [50.000000, $originalTotalMark],
            [50.000001, $originalTotalMark],
            [51,        $originalTotalMark],
        ];
    }


    /**
     * @return array
     */
    public static function providerHumanReadableToNormalised(): array
    {
        $totalMark = 100;
        return [
            ['-1.1',       $totalMark, -1.1],
            ['-1',         $totalMark, -1],
            ['0',          $totalMark, 0.0],
            ['0.0',        $totalMark, 0.0],
            [' 0',         $totalMark, 0.0],
            [' 0 ',        $totalMark, 0.0],
            ['1.5',        $totalMark, 1.5],
            ['50',         $totalMark, 50.0],
            ['66.6666666', $totalMark, 66.6666666],
            ['99.99',      $totalMark, 99.99],
            ['100',        $totalMark, 100.0],
            ['100.000001', $totalMark, 100.000001],
            ['101',        $totalMark, 101],
        ];
    }


    /**
     * @return array
     */
    public static function providerNormalisedToHumanReadable(): array
    {
        $totalMark = 100;
        return [
            [-1.0,     $totalMark,    '0 / 100'],
            [0.0,      $totalMark,    '0 / 100'],
            [1.0,      $totalMark,    '1 / 100'],
            [10.00001, $totalMark,   '10 / 100'],
            [99.9,     $totalMark, '99.9 / 100'],
            [100,      $totalMark,  '100 / 100'],
            [101,      $totalMark,  '100 / 100'],
        ];
    }


    /**
     * @return array
     */
    public static function providerNormalisedToHumanReadableRecurring(): array
    {
        $totalMark = 3;
        return [
            [-33.3333, $totalMark,   '0 / 3'],
            [0.0,      $totalMark,   '0 / 3'],
            [33.3333,  $totalMark,   '1 / 3'],
            [66.66666, $totalMark,   '2 / 3'],
            [99.9,     $totalMark,   '3 / 3'],
            [50,       $totalMark, '1.5 / 3'],
            [133.3333, $totalMark,   '3 / 3'],
        ];
    }


    /**
     * Data provider for `testConvertFormInputToHumanReadableMark`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkData(): iterable
    {
        $total = new TotalMarkFractional(12);
        yield 'total mark > 1: null form input' => [$total, null, null];
        yield 'total mark > 1: numeric form input' => [$total, '6', '6 / 12'];
        yield 'total mark > 1: fraction form input, space' => [$total, '6 / 12', '6 / 12'];
        yield 'total mark > 1: fraction form input, no space' => [$total, '6/12', '6 / 12'];
        yield 'total mark > 1: numeric form input, < 0%' => [$total, '-1', '-1 / 12'];
        yield 'total mark > 1: numeric form input, > 100%' => [$total, '13', '13 / 12'];

        $total2 = new TotalMarkFractional(1);
        yield 'total mark 1: null form input' => [$total2, null, null];
        yield 'total mark 1: numeric form input' => [$total2, '.2', '0.2 / 1'];
        yield 'total mark 1: fraction w/ leading zero form input, space' => [$total2, '0.3 / 1', '0.3 / 1'];
        yield 'total mark 1: fraction w/ leading zero form input, no space' => [$total2, '0.3/1', '0.3 / 1'];
        yield 'total mark 1: fraction w/o leading zero form input, space' => [$total2, '.3 / 1', '0.3 / 1'];
        yield 'total mark 1: fraction w/o leading zero form input, no space' => [$total2, '.3/1', '0.3 / 1'];
        yield 'total mark 1: numeric form input, < 0%' => [$total2, '-1', '-1 / 1'];
        yield 'total mark 1: numeric form input, > 100%' => [$total2, '2', '2 / 1'];

        $total3 = new TotalMarkFractional(0.5);
        yield 'total mark < 1: null form input' => [$total3, null, null];
        yield 'total mark < 1: numeric form input' => [$total3, '.2', '0.2 / 0.5'];
        yield 'total mark < 1: fraction w/ leading zero form input, space' => [$total3, '0.3 / 0.5', '0.3 / 0.5'];
        yield 'total mark < 1: fraction w/ leading zero form input, no space' => [$total3, '0.3/0.5', '0.3 / 0.5'];
        yield 'total mark < 1: fraction w/o leading zero form input, space' => [$total3, '.3 / .5', '0.3 / 0.5'];
        yield 'total mark < 1: fraction w/o leading zero form input, no space' => [$total3, '.3/.5', '0.3 / 0.5'];
        yield 'total mark < 1: numeric form input, < 0%' => [$total3, '-1', '-1 / 0.5'];
        yield 'total mark < 1: numeric form input, > 100%' => [$total3, '1', '1 / 0.5'];
    }


    /**
     * Data provider for `testConvertFormInputToHumanReadableMarkInvalid`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkInvalidData(): iterable
    {
        $total = new TotalMarkFractional(12);
        yield 'invalid valid form input 1' => [$total, 'Invalid'];
        yield 'invalid valid form input 2' => [$total, ''];
    }


    /**
     * Data provider for `testRetrieveHumanReadableValueAsFormInput`
     *
     * @return iterable
     */
    public static function getRetrieveHumanReadableMarkAsFormInputData(): iterable
    {
        $total = new TotalMarkFractional(12);
        yield 'null form input' => [$total, null, null];
        yield 'numeric form input' => [$total, '6', '6'];
        yield 'fraction form input, space' => [$total, '6 / 12', '6'];
        yield 'fraction form input, no space' => [$total, '6/12', '6'];
        yield 'fraction form input, < 0' => [$total, '-1/12', '-1'];
        yield 'fraction form input, > 100' => [$total, '13/12', '13'];
    }


    /**
     * Generate a Quiz with attached Attempts, Feedbacks, Questions + Responses
     *
     * Quiz does not yet have a MarkType or MarkScheme attached, that is caller's responsibility
     *
     * The quiz logically has 1 section with 3 questions worth 1 mark each.
     * The quiz has 1 attempt with 2 answers, 1 of which is correct (ie norm mark = 50.0)
     */
    private function getDummyQuiz()
    {
        $question1 = [
            'question_id' => 1,
            'section_id'  => 1,
            'sequence'    => 1,
            'type_id'     => 1,
            'marks'       => 1.0,
            'question'    => '<div class=\"ff4\">What does Jerome (Vincent) place on the comb at his workstation?</div>',
            'config'      => null,
            'deleted_at'  => null,
        ];
        $question2 = [
            'question_id' => 2,
            'section_id'  => 1,
            'sequence'    => 2,
            'type_id'     => 1,
            'marks'       => 1.0,
            'question'    => '<div class=\"ff4\">They used to say that a child conceived in love has a greater chance</div>',
            'config'      => null,
            'deleted_at'  => null,
        ];

        $response1 = [
            'response_id'        => 1,
            'attempt_id'         => 1,
            'section_id'         => 1,
            'question_id'        => 1,
            'response'           => 'toothpaste',
            'comment'            => "No, that's not the correct answer",
            'mark'               => 0.0,
            'automarked_already' => false,
            'updated_at'         => '2012-07-04 13:42:15',
            'deleted_at'         => null,
        ];

        $response2 = [
            'response_id'        => 2,
            'attempt_id'         => 1,
            'section_id'         => 1,
            'question_id'        => 2,
            'response'           => 'Being happy',
            'comment'            => "Correct. Although the correct term is 'Happiness'",
            'mark'               => 1.0,
            'automarked_already' => false,
            'updated_at'         => '2012-07-04 13:42:30',
            'deleted_at'         => null,
        ];

        $quiz = new Quiz();
        $attempt = new QuizAttempt();
        $feedback = new QuizFeedback();

        $linker      = new ParticipantLinker();
        $plan        = $linker->createAndLinkPlan($quiz);
        $participant = $linker->createAndLinkParticipant($this->getDummyQuizAttempter(), $plan);
        $participant->getAllResponses()->add($attempt);
        $participant->getAllFeedbacks()->add($feedback);

        $quiz->getAllParticipants()->add($participant);

        // Link them all together
        $feedback->setResponse($attempt);
        $feedback->setAssessable($quiz);
        $attempt->getAllFeedback()->add($feedback);
        $attempt->setAssessable($quiz);

        $responses = [
            [
                'mark' => $response1['mark'],
                'available' => $question1['marks'],
            ],
            [
                'mark' => $response2['mark'],
                'available' => $question2['marks'],
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);

        /* Presume Total Mark Fractional mark scheme for historical data,
         * causing human readable mark to be cached in the format "$numerator / $denominator"
         */
        $attempt->setHumanReadableMark("{$marksGiven} / {$marksAvailable}")
            ->setNormalisedMark(100 * $marksGiven / $marksAvailable);

        return $quiz;
    }

    /**
     * Generates a quiz attempt for use in getNormalised/getHumanReadable.
     *
     * @param int $totalMark
     * @return QuizAttempt
     */
    private function getDummyQuizAttempt($totalMark): QuizAttempt
    {
        return (new QuizAttempt())->setMarksAvailable($totalMark);
    }


    /**
     * Generate a dummy User instance
     * This person "attempted the quiz"
     *
     * @return mixed
     */
    private function getDummyQuizAttempter()
    {
        return (new User())->setId(19);
    }
}
