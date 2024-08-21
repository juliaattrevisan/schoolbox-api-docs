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
 * Tests that the various is*() state property methods work for DueWork Participants
 *
 * Also ensures the states are mutually exclusive
 *
 * @see Participant::isResponseRequired()
 * @see Participant::isFeedbackRequired()
 * @see Participant::isReviewComplete()
 * @see Participant::hasSubmittedResponse()
 * @see Participant::hasPublishedFeedback()
 */
class DueWorkStatus extends TestCase
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
     * @param bool        $isResubmissionRequired
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerIsResubmissionRequired')]
    public function testIsResubmissionRequired(Participant $participant, bool $isResubmissionRequired): void
    {
        $expected = $isResubmissionRequired;
        $actual   = $participant->isResubmissionRequired();
        $message  = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
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

        $message = "User: {$participant->getUser()->getUsername()}, Expected count: 1, Actual count: $countStates.";
        $this->assertEquals(1, $countStates, $message);
    }

    // ================= Data Providers =================

    public static function provider_isResponseRequired()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            "no interaction" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('A')), true],
            "draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('B')), true],
            "submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('C')), false],
            "submitted response then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('D')), false],
            "draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('E')), true],
            "published feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('F')), false],
            "published feedback then draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('G')), false],
            "submitted response with draft floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('H')), false],
            "submitted response with later published floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('I')), false],
            "submitted response with later draft linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('J')), false],
            "submitted response with published linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('K')), false],
            "submitted response with published linked feedback, then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('L')), false],
            "submitted response with published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('M')), false],
            "submitted response with published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('N')), false],
            "draft floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('O')), true],
            "draft floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('P')), false],
            "published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), false],
            "published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('R')), false],
            "unpublished self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('S')), true],
            "published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('T')), false],
            "unpublished self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('U')), true],
            "published self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('V')), true],
            "published self-assessed, draft instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('W')), false],
            "published self-assessed, published instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('X')), false],
            "published self-assessed and instructor-given feedback, draft self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), false],
            "published self-assessed and instructor-given feedback, published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), false],
        ];
    }


    public static function providerIsResubmissionRequired()
    {
        $dueWork = DemoData::getDueWork(1)->setAcceptsNone();

        return [
            "no interaction" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('A')), false],
            "draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('B')), false],
            "submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('C')), false],
            "submitted response then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('D')), false],
            "draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('E')), false],
            "published feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('F')), false],
            "published feedback then draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('G')), false],
            "submitted response with draft floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('H')), false],
            "submitted response with later published floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('I')), false],
            "submitted response with later draft linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('J')), false],
            "submitted response with published linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('K')), false],
            "submitted response with published linked feedback, then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('L')), false],
            "submitted response with published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('M')), false],
            "submitted response with published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('N')), false],
            "draft floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('O')), false],
            "draft floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('P')), false],
            "published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), false],
            "published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('R')), false],
            "unpublished self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('S')), false],
            "published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('T')), false],
            "unpublished self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('U')), false],
            "published self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('V')), false],
            "published self-assessed, draft instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('W')), false],
            "published self-assessed, published instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('X')), false],
            "published self-assessed and instructor-given feedback, draft self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), false],
            "published self-assessed and instructor-given feedback, published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), false],
            "no response, unpublished feedback requires resubmit" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('0')), false],
            "no response, published feedback requires resubmit" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('1')), true],
            "no response, first feedback requires resubmit, second doesn't" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('2')), false],
            "no response, first feedback doesn't require resubmit, second does" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('3')), true],
            "unpublished response, only feedback requires resubmit" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('4')), true],
            "published response then a feedback requiring resubmit" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('5')), true],
            "a feedback requiring resubmit, then an unpublished response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('6')), true],
            "a feedback requiring resubmit, then a published response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('7')), false],
        ];
    }

    public static function provider_isFeedbackRequired()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            "no interaction" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('A')), false],
            "draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('B')), false],
            "submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('C')), true],
            "submitted response then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('D')), true],
            "draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('E')), false],
            "published feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('F')), false],
            "published feedback then draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('G')), false],
            "submitted response with draft floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('H')), true],
            "submitted response with later published floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('I')), false],
            "submitted response with later draft linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('J')), true],
            "submitted response with published linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('K')), false],
            "submitted response with published linked feedback, then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('L')), false],
            "submitted response with published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('M')), false],
            "submitted response with published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('N')), true],
            "draft floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('O')), false],
            "draft floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('P')), true],
            "published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), false],
            "published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('R')), true],
            "unpublished self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('S')), false],
            "published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('T')), true],
            "unpublished self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('U')), false],
            "published self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('V')), false],
            "published self-assessed, draft instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('W')), true],
            "published self-assessed, published instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('X')), false],
            "published self-assessed and instructor-given feedback, draft self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), false],
            "published self-assessed and instructor-given feedback, published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), true],
        ];
    }

    public static function provider_isReviewComplete()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            "no interaction" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('A')), false],
            "draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('B')), false],
            "submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('C')), false],
            "submitted response then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('D')), false],
            "draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('E')), false],
            "published feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('F')), true],
            "published feedback then draft feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('G')), true],
            "submitted response with draft floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('H')), false],
            "submitted response with later published floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('I')), true],
            "submitted response with later draft linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('J')), false],
            "submitted response with published linked feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('K')), true],
            "submitted response with published linked feedback, then draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('L')), true],
            "submitted response with published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('M')), true],
            "submitted response with published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('N')), false],
            "draft floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('O')), false],
            "draft floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('P')), false],
            "published floating feedback, then later draft response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), true],
            "published floating feedback, then later submitted response" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('R')), false],
            "unpublished self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('S')), false],
            "published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('T')), false],
            "unpublished self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('U')), false],
            "published self-assessed floating feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('V')), false],
            "published self-assessed, draft instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('W')), false],
            "published self-assessed, published instructor-given feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('X')), true],
            "published self-assessed and instructor-given feedback, draft self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), true],
            "published self-assessed and instructor-given feedback, published self-assessed feedback" =>
                [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), false],
        ];
    }


    /**
     * @return Participant[][]
     */
    public static function provider_participants()
    {
        $dueWork = DemoData::getDueWork(1);

        $data = [];
        foreach ($dueWork->getAllParticipants() as $participant) {
            $data[$participant->getUser()->getFirstname()] = [$participant];
        }
        return $data;
    }
}
