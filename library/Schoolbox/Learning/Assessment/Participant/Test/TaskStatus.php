<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\Participant;

/**
 * Tests that the various is*() state property methods work for Task Participants
 *
 * Also ensures the states are mutually exclusive
 *
 * @see Participant::isResponseRequired()
 * @see Participant::isFeedbackRequired()
 * @see Participant::isReviewComplete()
 * @see Participant::hasSubmittedResponse()
 * @see Participant::hasPublishedFeedback()
 */
class TaskStatus extends TestCase
{
    /**
     * @dataProvider provider_selfAssessable_isResponseRequired
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    public function test_selfAssessable_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_selfAssessable_isFeedbackRequired
     * @param Participant $participant
     * @param bool        $isFeedbackRequired
     */
    public function test_selfAssessable_isFeedbackRequired(Participant $participant, $isFeedbackRequired)
    {
        $expected = $isFeedbackRequired;
        $actual = $participant->isFeedbackRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_selfAssessable_isReviewComplete
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    public function test_selfAssessable_isReviewComplete(Participant $participant, $isReviewComplete)
    {
        $expected = $isReviewComplete;
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_notSelfAssessable_isResponseRequired
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    public function test_NotSelfAssessable_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_notSelfAssessable_isFeedbackRequired
     * @param Participant $participant
     * @param bool        $isFeedbackRequired
     */
    public function test_NotSelfAssessable_isFeedbackRequired(Participant $participant, $isFeedbackRequired)
    {
        $expected = $isFeedbackRequired;
        $actual = $participant->isFeedbackRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_notSelfAssessable_isReviewComplete
     * @param Participant $participant
     * @param bool        $isReviewComplete
     */
    public function test_NotSelfAssessable_isReviewComplete(Participant $participant, $isReviewComplete)
    {
        $expected = $isReviewComplete;
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_participants
     * @param Participant $participant
     */
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

    public function provider_selfAssessable_isResponseRequired()
    {
        $task = DemoData::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), true],
            [$task->getParticipant(DemoData::getDemoStudent('B')), true],
            [$task->getParticipant(DemoData::getDemoStudent('C')), false],
            [$task->getParticipant(DemoData::getDemoStudent('D')), true],
            [$task->getParticipant(DemoData::getDemoStudent('E')), false],
            [$task->getParticipant(DemoData::getDemoStudent('F')), true],
            [$task->getParticipant(DemoData::getDemoStudent('G')), false],
            [$task->getParticipant(DemoData::getDemoStudent('H')), true],
            [$task->getParticipant(DemoData::getDemoStudent('I')), false],
        ];
    }

    public function provider_selfAssessable_isFeedbackRequired()
    {
        $task = DemoData::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), false],
            [$task->getParticipant(DemoData::getDemoStudent('B')), false],
            [$task->getParticipant(DemoData::getDemoStudent('C')), true],
            [$task->getParticipant(DemoData::getDemoStudent('D')), false],
            [$task->getParticipant(DemoData::getDemoStudent('E')), false],
            [$task->getParticipant(DemoData::getDemoStudent('F')), false],
            [$task->getParticipant(DemoData::getDemoStudent('G')), false],
            [$task->getParticipant(DemoData::getDemoStudent('H')), false],
            [$task->getParticipant(DemoData::getDemoStudent('I')), true],
        ];
    }

    public function provider_selfAssessable_isReviewComplete()
    {
        $task = DemoData::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), false],
            [$task->getParticipant(DemoData::getDemoStudent('B')), false],
            [$task->getParticipant(DemoData::getDemoStudent('C')), false],
            [$task->getParticipant(DemoData::getDemoStudent('D')), false],
            [$task->getParticipant(DemoData::getDemoStudent('E')), true],
            [$task->getParticipant(DemoData::getDemoStudent('F')), false],
            [$task->getParticipant(DemoData::getDemoStudent('G')), true],
            [$task->getParticipant(DemoData::getDemoStudent('H')), false],
            [$task->getParticipant(DemoData::getDemoStudent('I')), false],
        ];
    }

    public function provider_notSelfAssessable_isResponseRequired()
    {
        $task = DemoData::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), true],
            [$task->getParticipant(DemoData::getDemoStudent('B')), true],
            [$task->getParticipant(DemoData::getDemoStudent('C')), false],
            [$task->getParticipant(DemoData::getDemoStudent('D')), true],
            [$task->getParticipant(DemoData::getDemoStudent('E')), false],
        ];
    }

    public function provider_notSelfAssessable_isFeedbackRequired()
    {
        $task = DemoData::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), false],
            [$task->getParticipant(DemoData::getDemoStudent('B')), false],
            [$task->getParticipant(DemoData::getDemoStudent('C')), false],
            [$task->getParticipant(DemoData::getDemoStudent('D')), false],
            [$task->getParticipant(DemoData::getDemoStudent('E')), false],
        ];
    }

    public function provider_notSelfAssessable_isReviewComplete()
    {
        $task = DemoData::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(DemoData::getDemoStudent('A')), false],
            [$task->getParticipant(DemoData::getDemoStudent('B')), false],
            [$task->getParticipant(DemoData::getDemoStudent('C')), true],
            [$task->getParticipant(DemoData::getDemoStudent('D')), false],
            [$task->getParticipant(DemoData::getDemoStudent('E')), true],
        ];
    }

    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $task1 = DemoData::getSelfAssessableTask(1);
        $task2 = DemoData::getSelfAssessableTask(2);

        $data = [];
        foreach ($task1->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        foreach ($task2->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        return $data;
    }
}
