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
     * @dataProvider provider_isResponseRequired
     * @param Participant $participant
     * @param bool        $isResponseRequired
     */
    public function test_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '" . ($expected ? 'true' : 'false') . "', Actual: '" . ($actual ? 'true' : 'false') . "'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_isFeedbackRequired
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
     * @dataProvider provider_isReviewComplete
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

    public function provider_isResponseRequired()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            [$dueWork->getParticipant(DemoData::getDemoStudent('A')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('B')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('C')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('D')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('E')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('F')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('G')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('H')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('I')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('J')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('K')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('L')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('M')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('N')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('O')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('P')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('R')), false],

            // Self-assessment
            [$dueWork->getParticipant(DemoData::getDemoStudent('S')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('T')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('U')), true],
            // Veronica has submitted a self-assessment, but no response; this can't normally occur,
            // as self-assessment won't exist without response. Count this as requiring response.
            [$dueWork->getParticipant(DemoData::getDemoStudent('V')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('W')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('X')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), false],
        ];
    }

    public function provider_isFeedbackRequired()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            [$dueWork->getParticipant(DemoData::getDemoStudent('A')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('B')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('C')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('D')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('E')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('F')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('G')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('H')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('I')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('J')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('K')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('L')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('M')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('N')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('O')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('P')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('R')), true],

            // Self-assessment
            [$dueWork->getParticipant(DemoData::getDemoStudent('S')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('T')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('U')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('V')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('W')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('X')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), true],
        ];
    }

    public function provider_isReviewComplete()
    {
        $dueWork = DemoData::getDueWork(1);

        return [
            [$dueWork->getParticipant(DemoData::getDemoStudent('A')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('B')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('C')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('D')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('E')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('F')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('G')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('H')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('I')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('J')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('K')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('L')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('M')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('N')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('O')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('P')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Q')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('R')), false],

            // Self-assessment
            [$dueWork->getParticipant(DemoData::getDemoStudent('S')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('T')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('U')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('V')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('W')), false],
            [$dueWork->getParticipant(DemoData::getDemoStudent('X')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Y')), true],
            [$dueWork->getParticipant(DemoData::getDemoStudent('Z')), false],
        ];
    }


    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $dueWork = DemoData::getDueWork(1);

        $data = [];
        foreach ($dueWork->getAllParticipants() as $participant) {
            $data[] = [$participant];
        }
        return $data;
    }
}
