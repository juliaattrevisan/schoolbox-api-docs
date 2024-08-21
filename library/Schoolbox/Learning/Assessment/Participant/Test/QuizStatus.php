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
class QuizStatus extends TestCase
{
    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isResponseRequired')]
    public function test_isResponseRequired(Participant $participant, $isResponseRequired)
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
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isFeedbackRequired')]
    public function test_selfAssessable_isFeedbackRequired(Participant $participant, $isFeedbackRequired)
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
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_isReviewComplete')]
    public function test_selfAssessable_isReviewComplete(Participant $participant, $isReviewComplete)
    {
        $expected = $isReviewComplete;
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param Participant $participant
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_participants')]
    public function test_mutual_exclusive_states(Participant $participant)
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


    public static function provider_isResponseRequired()
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

    public static function provider_isFeedbackRequired()
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

    public static function provider_isReviewComplete()
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
    public static function provider_participants()
    {
        $quiz = DemoData::getQuiz(1);

        $data = [];
        foreach ($quiz->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        return $data;
    }
}
