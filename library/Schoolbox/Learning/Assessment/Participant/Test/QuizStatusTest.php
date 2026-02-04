<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Participant;

/**
 * Tests that the various is*() state property methods work for Quiz Participants
 *
 * Also ensures the states are mutually exclusive
 *
 * @see Participant::isResponseRequired()
 * @see Participant::isFeedbackRequired()
 * @see Participant::isReviewComplete()
 * @see Participant::hasSubmittedResponse()
 * @see Participant::hasPublishedFeedback()
 */
class QuizStatusTest extends TestCase
{
    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsResponseRequired')]
    public function testIsResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param Participant $participant
     * @param bool        $isFeedbackRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsFeedbackRequired')]
    public function testSelfAssessableIsFeedbackRequired(Participant $participant, $isFeedbackRequired)
    {
        $expected = $isFeedbackRequired;
        $actual = $participant->isFeedbackRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsReviewComplete')]
    public function testSelfAssessableIsReviewComplete(Participant $participant, $isReviewComplete)
    {
        $expected = $isReviewComplete;
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param Participant $participant
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerParticipants')]
    public function testMutualExclusiveStates(Participant $participant)
    {
        $countStates = 0;
        if ($participant->isResponseRequired()) {
            $countStates++;
        }
        if ($participant->isFeedbackRequired()) {
            $countStates++;
        }
        if ($participant->isReviewComplete()) {
            $countStates++;
        }

        $message = "User: {$participant->getUser()->getFirstname()}, Expected count: 1, Actual count: $countStates.";
        $this->assertEquals(1, $countStates, $message);
    }

    // ================= Data Providers =================


    public static function providerIsResponseRequired()
    {
        $quiz = DemoData::getQuiz(1);

        return [
            [$quiz->getParticipant(DemoData::getDemoStudent('A')), true],
            [$quiz->getParticipant(DemoData::getDemoStudent('B')), true],
            [$quiz->getParticipant(DemoData::getDemoStudent('C')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('D')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('E')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('F')), false],
        ];
    }

    public static function providerIsFeedbackRequired()
    {
        $quiz = DemoData::getQuiz(1);

        return [
            [$quiz->getParticipant(DemoData::getDemoStudent('A')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('B')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('C')), true],
            [$quiz->getParticipant(DemoData::getDemoStudent('D')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('E')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('F')), true],
        ];
    }

    public static function providerIsReviewComplete()
    {
        $quiz = DemoData::getQuiz(1);

        return [
            [$quiz->getParticipant(DemoData::getDemoStudent('A')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('B')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('C')), false],
            [$quiz->getParticipant(DemoData::getDemoStudent('D')), true],
            [$quiz->getParticipant(DemoData::getDemoStudent('E')), true],
            [$quiz->getParticipant(DemoData::getDemoStudent('F')), false],
        ];
    }

    /**
     * @return Participant[][]
     */
    public static function providerParticipants()
    {
        $quiz = DemoData::getQuiz(1);

        $data = [];
        foreach ($quiz->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        return $data;
    }
}
