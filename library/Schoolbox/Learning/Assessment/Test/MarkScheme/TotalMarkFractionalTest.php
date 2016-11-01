<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use \InvalidArgumentException;
use \RangeException;

class TotalMarkFractionalTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException RangeException
     */
    public function test_NegativeTotal()
    {
        $input = -1.0;
        new TotalMarkFractional($input);
    }

    /**
     * @dataProvider provider_invalidTotalMarks
     * @expectedException InvalidArgumentException
     * @param mixed $totalMark
     */
    public function test_TotalWrongType($totalMark)
    {
        new TotalMarkFractional($totalMark);
    }

    /**
     * @dataProvider provider_setValidTotal
     * @expectedException RangeException
     * @param float $totalMark
     */
    public function test_getHumanReadable_tooLow($totalMark)
    {
        $normalised = -1.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException RangeException
     */
    public function test_getHumanReadable_tooHigh()
    {
        $normalised = 100.0001;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Null()
    {
        $humanReadable = null;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Empty()
    {
        $humanReadable = '';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Invalid()
    {
        $humanReadable = 'ten';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float $totalMark
     * @param float $expected
     */
    public function test_getNormalised($humanReadable, $totalMark, $expected)
    {
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_humanReadableToRecurringNormalised
     * @param string $humanReadable
     * @param float $totalMark
     * @param float $expected
     */
    public function test_getRecurringNormalised($humanReadable, $totalMark, $expected)
    {
        $delta = 0.00001;
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);
    }

    /**
     * @dataProvider provider_normalisedToHumanReadable
     * @param float  $normalised
     * @param float  $totalMark
     * @param string $humanReadable
     */
    public function test_getHumanReadable($normalised, $totalMark, $humanReadable)
    {
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $humanReadable, Actual: $actual";
        $this->assertEquals($humanReadable, $actual, $message);
    }

    /**
     * @dataProvider provider_normalisedToHumanReadableRecurring
     * @param float  $normalised
     * @param float  $totalMark
     * @param string $humanReadable
     */
    public function test_getHumanReadableFromRecurring($normalised, $totalMark, $humanReadable)
    {
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $humanReadable, Actual: $actual";
        $this->assertEquals($humanReadable, $actual, $message);
    }


    public function test_getNormalised_Quiz()
    {
        // Quiz has total marks of 3.0
        $quiz = $this->getDummyQuiz();
        $quizTotalMarks = $quiz->getMaxMark();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getResponses()->first();
        $humanReadable = 1;

        // 1 / 2.0 * 100.0
        $expected = 50.0;

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getNormalisedMark($humanReadable, $quizAttempt);

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * A quiz's max mark no longer affects the human-readable or normalized mark,
     * nor does it determine whether the mark is valid.
     */
    public function test_getNormalised_Quiz_OriginalTotalExceedsAttemptTotal()
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMaxMark(1);
        $quizTotalMarks = $quiz->getMaxMark();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getResponses()->first();
        $humanReadable = 1;

        // 1 / 2.0 * 100.0
        $expected = 50.0;

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getNormalisedMark($humanReadable, $quizAttempt);

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * This is distinct from test above in that it does NOT pass in the Quiz
     * response. So although the attempt total available marks = 2.0, if the attempt is not present,
     * the MarkScheme uses the quiz total marks = 3.0 instead.
     */
    public function test_getNormalised_Quiz_Total()
    {
        // Quiz has total marks of 3.0
        $quiz = $this->getDummyQuiz();
        $quizTotalMarks = $quiz->getMaxMark();

        $humanReadable = 1;

        // 1 / 3.0 * 100.0
        $expected = 33.333333;
        $delta = 0.00001;

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getNormalisedMark($humanReadable);

        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);
    }

    public function test_getHumanReadable_Quiz()
    {
        $quiz = $this->getDummyQuiz();

        $quizTotalMarks = $quiz->getMaxMark();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getResponses()->first();
        $normalised = 50.0;
        $expected = '1 / 2';

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    public function test_getHumanReadable_Quiz_Fractional()
    {
        $quiz = $this->getDummyQuiz();

        $quizTotalMarks = $quiz->getMaxMark();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getResponses()->first();
        $normalised = 87.6;
        $expected = '1.75 / 2';

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * A quiz's max mark no longer affects the human-readable or normalized mark,
     * nor does it determine whether the mark is valid.
     */
    public function test_getHumanReadable_Quiz_Fractional_OriginalTotalExceedsAttemptTotal()
    {
        $quiz = $this->getDummyQuiz();
        $quiz->setMaxMark(1);

        $quizTotalMarks = $quiz->getMaxMark();

        // Attempt has total marks of 2.0
        $quizAttempt = $quiz->getResponses()->first();
        $normalised = 87.6;
        $expected = '1.75 / 2';

        $markScheme = new TotalMarkFractional($quizTotalMarks);
        $actual = $markScheme->getHumanReadableMark($normalised, $quizAttempt);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    // ==================== Data Providers ====================

    public function provider_invalidTotalMarks()
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

    public function provider_humanReadableToRecurringNormalised()
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

    public function provider_setValidTotal()
    {
        $originalTotalMark = 50;
        return [
            [0,         $originalTotalMark],
            [0.0,       $originalTotalMark],
            [10.0,      $originalTotalMark],
            [49.999999, $originalTotalMark],
            [50,        $originalTotalMark],
            [50.0,      $originalTotalMark],
            [50.000000, $originalTotalMark],
        ];
    }

    public function provider_humanReadableToNormalised()
    {
        $totalMark = 100;
        return [
            ['0',          $totalMark, 0.0],
            ['0.0',        $totalMark, 0.0],
            [' 0',         $totalMark, 0.0],
            [' 0 ',        $totalMark, 0.0],
            ['1.5',        $totalMark, 1.5],
            ['50',         $totalMark, 50.0],
            ['66.6666666', $totalMark, 66.6666666],
            ['99.99',      $totalMark, 99.99],
            ['100',        $totalMark, 100.0],
        ];
    }

    public function provider_normalisedToHumanReadable()
    {
        $totalMark = 100;
        return [
            [0.0,      $totalMark,   '0 / 100'],
            [1.0,      $totalMark,   '1 / 100'],
            [10.00001, $totalMark,  '10 / 100'],
            [99.9,     $totalMark, '100 / 100'],
            [100,      $totalMark, '100 / 100'],
        ];
    }

    public function provider_normalisedToHumanReadableRecurring()
    {
        $totalMark = 3;
        return [
            [0.0,      $totalMark,   '0 / 3'],
            [33.3333,  $totalMark,   '1 / 3'],
            [66.66666, $totalMark,   '2 / 3'],
            [99.9,     $totalMark,   '3 / 3'],
            [50,       $totalMark, '1.5 / 3'],
        ];
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
        $question3 = [
            'question_id' => 3,
            'section_id'  => 1,
            'sequence'    => 3,
            'type_id'     => 1,
            'marks'       => 1.0,
            'question'    => '<p><span>What is Jerome\'s (Vincent\'s) life expectancy?</span></p>',
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

        // Link them all together
        $quiz->getResponses()->add($attempt);
        $quiz->getFeedbacks()->add($feedback);
        $feedback->setResponse($attempt);
        $feedback->setAssessable($quiz);
        $attempt->getAllFeedback()->add($feedback);
        $attempt->setAssessable($quiz);

        // Inject data
        $quiz->setMaxMark($question1['marks'] + $question2['marks'] + $question3['marks']);

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
        foreach($responses as $response){
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);

        return $quiz;
    }
}