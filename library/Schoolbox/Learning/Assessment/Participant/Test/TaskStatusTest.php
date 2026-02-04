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
class TaskStatusTest extends TestCase
{
    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSelfAssessableIsResponseRequired')]
    public function testSelfAssessableIsResponseRequired(Participant $participant, $isResponseRequired)
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSelfAssessableIsFeedbackRequired')]
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSelfAssessableIsReviewComplete')]
    public function testSelfAssessableIsReviewComplete(Participant $participant, $isReviewComplete)
    {
        $expected = $isReviewComplete;
        $actual = $participant->isReviewComplete();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotSelfAssessableIsResponseRequired')]
    public function testNotSelfAssessableIsResponseRequired(Participant $participant, $isResponseRequired)
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotSelfAssessableIsFeedbackRequired')]
    public function testNotSelfAssessableIsFeedbackRequired(Participant $participant, $isFeedbackRequired)
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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNotSelfAssessableIsReviewComplete')]
    public function testNotSelfAssessableIsReviewComplete(Participant $participant, $isReviewComplete)
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

    public static function providerSelfAssessableIsResponseRequired()
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

    public static function providerSelfAssessableIsFeedbackRequired()
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

    public static function providerSelfAssessableIsReviewComplete()
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

    public static function providerNotSelfAssessableIsResponseRequired()
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

    public static function providerNotSelfAssessableIsFeedbackRequired()
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

    public static function providerNotSelfAssessableIsReviewComplete()
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
    public static function providerParticipants()
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
