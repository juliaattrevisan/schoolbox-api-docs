<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as Response;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;

require_once 'ProjectStatus.php';

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
class DueWorkStatus extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_isResponseRequired
     * @param Participant $participant
     * @param bool $isResponseRequired
     */
    public function test_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @dataProvider provider_participants
     * @param Participant $participant
     */
    public function test_mutual_exclusive_states(Participant $participant)
    {
        $countStates = 0;
        if($participant->isResponseRequired()){
            $countStates++;
        }
        if($participant->isFeedbackRequired()){
            $countStates++;
        }
        if($participant->isReviewComplete()){
            $countStates++;
        }

        $message = "User: {$participant->getUser()->getFirstname()}, Expected count: 1, Actual count: $countStates.";
        $this->assertEquals(1, $countStates, $message);
    }

    // ================= Data Providers =================

    public function provider_isResponseRequired()
    {
        $dueWork = self::getDueWork(1);

        return [
            [$dueWork->getParticipant(self::getDemoStudent('A')), true],
            [$dueWork->getParticipant(self::getDemoStudent('B')), true],
            [$dueWork->getParticipant(self::getDemoStudent('C')), false],
            [$dueWork->getParticipant(self::getDemoStudent('D')), false],
            [$dueWork->getParticipant(self::getDemoStudent('E')), true],
            [$dueWork->getParticipant(self::getDemoStudent('F')), false],
            [$dueWork->getParticipant(self::getDemoStudent('G')), false],
            [$dueWork->getParticipant(self::getDemoStudent('H')), false],
            [$dueWork->getParticipant(self::getDemoStudent('I')), false],
            [$dueWork->getParticipant(self::getDemoStudent('J')), false],
            [$dueWork->getParticipant(self::getDemoStudent('K')), false],
            [$dueWork->getParticipant(self::getDemoStudent('L')), false],
            [$dueWork->getParticipant(self::getDemoStudent('M')), false],
            [$dueWork->getParticipant(self::getDemoStudent('N')), false],
            [$dueWork->getParticipant(self::getDemoStudent('O')), true],
            [$dueWork->getParticipant(self::getDemoStudent('P')), false],
            [$dueWork->getParticipant(self::getDemoStudent('Q')), false],
            [$dueWork->getParticipant(self::getDemoStudent('R')), false],
        ];
    }

    public function provider_isFeedbackRequired()
    {
        $dueWork = self::getDueWork(1);

        return [
            [$dueWork->getParticipant(self::getDemoStudent('A')), false],
            [$dueWork->getParticipant(self::getDemoStudent('B')), false],
            [$dueWork->getParticipant(self::getDemoStudent('C')), true],
            [$dueWork->getParticipant(self::getDemoStudent('D')), true],
            [$dueWork->getParticipant(self::getDemoStudent('E')), false],
            [$dueWork->getParticipant(self::getDemoStudent('F')), false],
            [$dueWork->getParticipant(self::getDemoStudent('G')), false],
            [$dueWork->getParticipant(self::getDemoStudent('H')), true],
            [$dueWork->getParticipant(self::getDemoStudent('I')), false],
            [$dueWork->getParticipant(self::getDemoStudent('J')), true],
            [$dueWork->getParticipant(self::getDemoStudent('K')), false],
            [$dueWork->getParticipant(self::getDemoStudent('L')), false],
            [$dueWork->getParticipant(self::getDemoStudent('M')), false],
            [$dueWork->getParticipant(self::getDemoStudent('N')), true],
            [$dueWork->getParticipant(self::getDemoStudent('O')), false],
            [$dueWork->getParticipant(self::getDemoStudent('P')), true],
            [$dueWork->getParticipant(self::getDemoStudent('Q')), false],
            [$dueWork->getParticipant(self::getDemoStudent('R')), true],
        ];
    }

    public function provider_isReviewComplete()
    {
        $dueWork = self::getDueWork(1);

        return [
            [$dueWork->getParticipant(self::getDemoStudent('A')), false],
            [$dueWork->getParticipant(self::getDemoStudent('B')), false],
            [$dueWork->getParticipant(self::getDemoStudent('C')), false],
            [$dueWork->getParticipant(self::getDemoStudent('D')), false],
            [$dueWork->getParticipant(self::getDemoStudent('E')), false],
            [$dueWork->getParticipant(self::getDemoStudent('F')), true],
            [$dueWork->getParticipant(self::getDemoStudent('G')), true],
            [$dueWork->getParticipant(self::getDemoStudent('H')), false],
            [$dueWork->getParticipant(self::getDemoStudent('I')), true],
            [$dueWork->getParticipant(self::getDemoStudent('J')), false],
            [$dueWork->getParticipant(self::getDemoStudent('K')), true],
            [$dueWork->getParticipant(self::getDemoStudent('L')), true],
            [$dueWork->getParticipant(self::getDemoStudent('M')), true],
            [$dueWork->getParticipant(self::getDemoStudent('N')), false],
            [$dueWork->getParticipant(self::getDemoStudent('O')), false],
            [$dueWork->getParticipant(self::getDemoStudent('P')), false],
            [$dueWork->getParticipant(self::getDemoStudent('Q')), true],
            [$dueWork->getParticipant(self::getDemoStudent('R')), false],
        ];
    }


    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $dueWork = self::getDueWork(1);

        $data = [];
        foreach($dueWork->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        return $data;
    }

    // ================= Demo Data Helpers ==============

    /**
     * Generates a DueWork with the following participation:
     *
     * * User A - no interaction
     * * User B - draft response
     * * User C - submitted response
     * * User D - submitted response then draft response
     * * User E - draft feedback
     * * User F - published feedback
     * * User G - published feedback then draft feedback
     * * User H - submitted response with draft floating feedback
     * * User I - submitted response with later published floating feedback
     * * User J - submitted response with later draft linked feedback
     * * User K - submitted response with published linked feedback
     * * User L - submitted response with published linked feedback, then draft response
     * * User M - submitted response with later published floating feedback, then later draft response
     * * User N - submitted response with later published floating feedback, then later submitted response
     * * User O - draft floating feedback, then later draft response
     * * User P - draft floating feedback, then later submitted response
     * * User Q - published floating feedback, then later draft response
     * * User R - published floating feedback, then later submitted response
     *
     * @param int $id - The ID to assign to the assessment
     * @return DueWork
     */
    public static function getDueWork($id)
    {
        $dueWork = (new DueWork())
            ->setId($id)
            ->setAcceptOnline(true)
            ->setAcceptsMultiple()
            ->setPublishAt((clone Alaress_Date_Format::now())->addDays(-14))
            ->setOpenAt((clone Alaress_Date_Format::now())->addDays(-7))
            ->setDueAt((clone Alaress_Date_Format::now())->addDays(+7))
            ->setCloseAt((clone Alaress_Date_Format::now())->addDays(+14))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(-21))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());


        // User A - no interaction
        $participantA = new Participant(self::getDemoStudent('A'), $dueWork);
        $dueWork->getAllParticipants()->add($participantA);


        // User B - draft response
        $participantB = new Participant(self::getDemoStudent('B'), $dueWork);
        $dueWork->getAllParticipants()->add($participantB);

        $responseB = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantB->getAllResponses()->add($responseB);
        $dueWork->getResponses()->add($responseB);


        // User C - submitted response
        $participantC = new Participant(self::getDemoStudent('C'), $dueWork);
        $dueWork->getAllParticipants()->add($participantC);

        $responseC = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);
        $dueWork->getResponses()->add($responseC);


        // User D - submitted response then draft response
        $participantD = new Participant(self::getDemoStudent('D'), $dueWork);
        $dueWork->getAllParticipants()->add($participantD);

        $responseD1 = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD1);
        $dueWork->getResponses()->add($responseD1);

        $responseD2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(1));
        $participantD->getAllResponses()->add($responseD2);
        $dueWork->getResponses()->add($responseD2);


        // User E - draft feedback
        $participantE = new Participant(self::getDemoStudent('E'), $dueWork);
        $dueWork->getAllParticipants()->add($participantE);

        $feedbackE = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantE->getAllFeedbacks()->add($feedbackE);
        $dueWork->getFeedbacks()->add($feedbackE);


        // User F - published feedback
        $participantF = new Participant(self::getDemoStudent('F'), $dueWork);
        $dueWork->getAllParticipants()->add($participantF);

        $feedbackF = (new Feedback())
            ->setPublishedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantF->getAllFeedbacks()->add($feedbackF);
        $dueWork->getFeedbacks()->add($feedbackF);


        // User G - published feedback then draft feedback
        $participantG = new Participant(self::getDemoStudent('G'), $dueWork);
        $dueWork->getAllParticipants()->add($participantG);

        $feedbackG1 = (new Feedback())
            ->setPublishedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantG->getAllFeedbacks()->add($feedbackG1);
        $dueWork->getFeedbacks()->add($feedbackG1);

        $feedbackG2 = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackG2);
        $dueWork->getFeedbacks()->add($feedbackG2);


        // User H - submitted response with draft floating feedback
        $participantH = new Participant(self::getDemoStudent('H'), $dueWork);
        $dueWork->getAllParticipants()->add($participantH);

        $responseH = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantH->getAllResponses()->add($responseH);
        $dueWork->getResponses()->add($responseH);

        $feedbackH = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackH);
        $dueWork->getFeedbacks()->add($feedbackH);


        // User I - submitted response with later published floating feedback
        $participantI = new Participant(self::getDemoStudent('I'), $dueWork);
        $dueWork->getAllParticipants()->add($participantI);

        $responseI = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantI->getAllResponses()->add($responseI);
        $dueWork->getResponses()->add($responseI);

        $feedbackI = (new Feedback())
            ->setPublishedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantI->getAllFeedbacks()->add($feedbackI);
        $dueWork->getFeedbacks()->add($feedbackI);


        // User J - submitted response with later draft linked feedback
        $participantJ = new Participant(self::getDemoStudent('J'), $dueWork);
        $dueWork->getAllParticipants()->add($participantJ);

        $responseJ = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantJ->getAllResponses()->add($responseJ);
        $dueWork->getResponses()->add($responseJ);

        $feedbackJ = (new Feedback())
            ->setResponse($responseJ)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantJ->getAllFeedbacks()->add($feedbackJ);
        $dueWork->getFeedbacks()->add($feedbackJ);
        $responseJ->getAllFeedback()->add($feedbackJ);


        // User K - submitted response with published linked feedback
        $participantK = new Participant(self::getDemoStudent('K'), $dueWork);
        $dueWork->getAllParticipants()->add($participantK);

        $responseK = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantK->getAllResponses()->add($responseK);
        $dueWork->getResponses()->add($responseK);

        $feedbackK = (new Feedback())
            ->setResponse($responseK)
            ->setPublishedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantK->getAllFeedbacks()->add($feedbackK);
        $dueWork->getFeedbacks()->add($feedbackK);
        $responseK->getAllFeedback()->add($feedbackK);


        // User L - submitted response with published linked feedback, then draft response
        $participantL = new Participant(self::getDemoStudent('L'), $dueWork);
        $dueWork->getAllParticipants()->add($participantL);

        $responseL1 = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantL->getAllResponses()->add($responseL1);
        $dueWork->getResponses()->add($responseL1);

        $feedbackL1 = (new Feedback())
            ->setResponse($responseL1)
            ->setPublishedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantL->getAllFeedbacks()->add($feedbackL1);
        $dueWork->getFeedbacks()->add($feedbackL1);
        $responseL1->getAllFeedback()->add($feedbackL1);

        $responseL2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+2));
        $participantL->getAllResponses()->add($responseL2);
        $dueWork->getResponses()->add($responseL2);


        // User M - submitted response with published floating feedback, then later draft response
        $participantM = new Participant(self::getDemoStudent('M'), $dueWork);
        $dueWork->getAllParticipants()->add($participantM);

        $responseM1 = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantM->getAllResponses()->add($responseM1);
        $dueWork->getResponses()->add($responseM1);

        $feedbackM1 = (new Feedback())
            ->setPublishedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantM->getAllFeedbacks()->add($feedbackM1);
        $dueWork->getFeedbacks()->add($feedbackM1);

        $responseM2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+2));
        $participantM->getAllResponses()->add($responseM2);
        $dueWork->getResponses()->add($responseM2);


        // User N - submitted response with published floating feedback, then later submitted response
        $participantN = new Participant(self::getDemoStudent('N'), $dueWork);
        $dueWork->getAllParticipants()->add($participantN);

        $responseN1 = (new Response())
            ->setSubmittedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantN->getAllResponses()->add($responseN1);
        $dueWork->getResponses()->add($responseN1);

        $feedbackN1 = (new Feedback())
            ->setPublishedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantN->getAllFeedbacks()->add($feedbackN1);
        $dueWork->getFeedbacks()->add($feedbackN1);

        $responseN2 = (new Response())
            ->setSubmittedAt((clone Alaress_Date_Format::now())->addDays(+2))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+2));
        $participantN->getAllResponses()->add($responseN2);
        $dueWork->getResponses()->add($responseN2);

        // User O - draft floating feedback, then later draft response
        $participantO = new Participant(self::getDemoStudent('O'), $dueWork);
        $dueWork->getAllParticipants()->add($participantO);

        $feedbackO = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantO->getAllFeedbacks()->add($feedbackO);
        $dueWork->getFeedbacks()->add($feedbackO);

        $responseO = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantO->getAllResponses()->add($responseO);
        $dueWork->getResponses()->add($responseO);


        // User P - draft floating feedback, then later submitted response
        $participantP = new Participant(self::getDemoStudent('P'), $dueWork);
        $dueWork->getAllParticipants()->add($participantP);

        $feedbackP = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantP->getAllFeedbacks()->add($feedbackP);
        $dueWork->getFeedbacks()->add($feedbackP);

        $responseP = (new Response())
            ->setSubmittedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantP->getAllResponses()->add($responseP);
        $dueWork->getResponses()->add($responseP);


        // User Q - published floating feedback, then later draft response
        $participantQ = new Participant(self::getDemoStudent('Q'), $dueWork);
        $dueWork->getAllParticipants()->add($participantQ);

        $feedbackQ = (new Feedback())
            ->setPublishedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantQ->getAllFeedbacks()->add($feedbackQ);
        $dueWork->getFeedbacks()->add($feedbackQ);

        $responseQ = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantQ->getAllResponses()->add($responseQ);
        $dueWork->getResponses()->add($responseQ);


        // User R - published floating feedback, then later submitted response
        $participantR = new Participant(self::getDemoStudent('R'), $dueWork);
        $dueWork->getAllParticipants()->add($participantR);

        $feedbackR = (new Feedback())
            ->setPublishedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now());
        $participantR->getAllFeedbacks()->add($feedbackR);
        $dueWork->getFeedbacks()->add($feedbackR);

        $responseR = (new Response())
            ->setSubmittedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1));
        $participantR->getAllResponses()->add($responseR);
        $dueWork->getResponses()->add($responseR);

        return $dueWork;
    }


    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     */
    private static function getDemoStudent($userChar)
    {
        return ProjectStatus::getDemoStudent($userChar);
    }
}
