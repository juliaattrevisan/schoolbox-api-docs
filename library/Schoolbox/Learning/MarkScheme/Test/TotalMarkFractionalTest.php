<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizFeedback;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use \InvalidArgumentException;

class TotalMarkWholeNumberTest extends TestCase
{

    /**
     * An attempt is now required when retrieving a human-readable mark.
     *
     * @expectedException \DomainException
     */
    public function test_getHumanReadable_WithoutAttempt()
    {
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getHumanReadableMark(40);
    }

    /**
     * An attempt is now required when retrieving a human-readable mark.
     *
     * @expectedException \DomainException
     */
    public function test_getNormalised_WithoutAttempt()
    {
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark(40);
    }

//    /**
//     * @dataProvider provider_setValidTotal
//     * @expectedException RangeException
//     * @param float $totalMark
//     */
//    public function test_getHumanReadable_tooLow($totalMark)
//    {
//        $this->markTestSkipped('Total marks less than the minimum value are now allowed');
//        $normalised = -1.0;
//        $markScheme = new TotalMarkFractional(null);
//        $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
//    }
//
//    /**
//     * @expectedException RangeException
//     */
//    public function test_getHumanReadable_tooHigh()
//    {
//        $this->markTestSkipped('Total marks greater than the maximum value are now allowed');
//        $normalised = 100.0001;
//        $totalMark = 50.0;
//        $markScheme = new TotalMarkFractional(null);
//        $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
//    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Null()
    {
        $humanReadable = null;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Empty()
    {
        $humanReadable = '';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Invalid()
    {
        $humanReadable = 'ten';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional(null);
        $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
    }

    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float $totalMark
     * @param float $expected
     */
    public function test_getNormalised($humanReadable, $totalMark, $expected)
    {
        $markScheme = new TotalMarkFractional(null);
        $actual = $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
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
        $markScheme = new TotalMarkFractional(null);
        $actual = $markScheme->getNormalisedMark($humanReadable, $this->getDummyQuizAttempt($totalMark));
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
        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
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
        $markScheme = new TotalMarkFractional();
        $actual = $markScheme->getHumanReadableMark($normalised, $this->getDummyQuizAttempt($totalMark));
        $message = "Failed asserting equality. Input: $normalised, Expected: $humanReadable, Actual: $actual";
        $this->assertEquals($humanReadable, $actual, $message);
    }


    public function test_getNormalised_Quiz()
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

    public function test_getNormalised_Quiz_OriginalTotalExceedsAttemptTotal()
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

    public function test_getNormalised_Quiz_Total()
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

    public function test_getHumanReadable_Quiz()
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

    public function test_getHumanReadable_Quiz_Fractional()
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
    public function test_getHumanReadable_Quiz_Fractional_OriginalTotalExceedsAttemptTotal()
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

    public function provider_humanReadableToNormalised()
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

    public function provider_normalisedToHumanReadable()
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

    public function provider_normalisedToHumanReadableRecurring()
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
        foreach($responses as $response){
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }

        $attempt->setMarksAvailable($marksAvailable);
        $attempt->setMarksGiven($marksGiven);

        return $quiz;
    }

    /**
     * Generates a quiz attempt for use in getNormalised/getHumanReadable.
     *
     * @param int $totalMark
     * @return QuizAttempt
     */
    private function getDummyQuizAttempt($totalMark)
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
        return (new \Schoolbox\User())->setId(19);
    }
}
