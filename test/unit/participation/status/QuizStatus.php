<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as Attempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as Review;
use Schoolbox\User;

require_once 'ProjectStatus.php';

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
class QuizStatus extends PHPUnit_Framework_TestCase
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
        $quiz = self::getQuiz(1);

        return [
            [$quiz->getParticipant(self::getDemoStudent('A')), true],
            [$quiz->getParticipant(self::getDemoStudent('B')), true],
            [$quiz->getParticipant(self::getDemoStudent('C')), false],
            [$quiz->getParticipant(self::getDemoStudent('D')), false],
            [$quiz->getParticipant(self::getDemoStudent('E')), false],
            [$quiz->getParticipant(self::getDemoStudent('F')), false],
        ];
    }

    public function provider_isFeedbackRequired()
    {
        $quiz = self::getQuiz(1);

        return [
            [$quiz->getParticipant(self::getDemoStudent('A')), false],
            [$quiz->getParticipant(self::getDemoStudent('B')), false],
            [$quiz->getParticipant(self::getDemoStudent('C')), true],
            [$quiz->getParticipant(self::getDemoStudent('D')), false],
            [$quiz->getParticipant(self::getDemoStudent('E')), false],
            [$quiz->getParticipant(self::getDemoStudent('F')), true],
        ];
    }

    public function provider_isReviewComplete()
    {
        $quiz = self::getQuiz(1);

        return [
            [$quiz->getParticipant(self::getDemoStudent('A')), false],
            [$quiz->getParticipant(self::getDemoStudent('B')), false],
            [$quiz->getParticipant(self::getDemoStudent('C')), false],
            [$quiz->getParticipant(self::getDemoStudent('D')), true],
            [$quiz->getParticipant(self::getDemoStudent('E')), true],
            [$quiz->getParticipant(self::getDemoStudent('F')), false],
        ];
    }

    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $quiz = self::getQuiz(1);

        $data = [];
        foreach($quiz->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        return $data;
    }


    // ================= Demo Data Helpers ==============


    /**
     * Generates a DueWork with the following participation:
     *
     * * User A - no attempts
     * * User B - attempt not finished
     * * User C - attempt finished but not reviewed
     * * User D - attempt reviewed
     * * User E - attempt reviewed, then attempt not finished
     * * User F - attempt reviewed, then attempt finished but not reviewed
     *
     * @param int $id - The ID to assign to the assessment
     * @return Quiz
     */
    public static function getQuiz($id)
    {
        $quiz = (new Quiz())
            ->setId($id)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-14))
            ->setOpenAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7))
            ->setCloseAt(Alaress_Date_Format::now()->addDays(+14))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());


        // User A - no interaction
        $participantA = new Participant(self::getDemoStudent('A'), $quiz);
        $quiz->getAllParticipants()->add($participantA);


        // User B - attempt not finished
        $participantB = new Participant(self::getDemoStudent('B'), $quiz);
        $quiz->getAllParticipants()->add($participantB);

        $responseB = (new Attempt())
            ->setSubmittedAt(new Alaress_Date_NullDate());
        $participantB->getAllResponses()->add($responseB);
        $quiz->getResponses()->add($responseB);


        // User C - attempt finished but not reviewed
        $participantC = new Participant(self::getDemoStudent('C'), $quiz);
        $quiz->getAllParticipants()->add($participantC);

        $responseC = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);
        $quiz->getResponses()->add($responseC);


        // User D - attempt reviewed
        $participantD = new Participant(self::getDemoStudent('D'), $quiz);
        $quiz->getAllParticipants()->add($participantD);

        $responseD = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD);
        $quiz->getResponses()->add($responseD);

        $feedbackD = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseD);
        $responseD->getAllFeedback()->add($feedbackD);
        $participantD->getAllFeedbacks()->add($feedbackD);
        $quiz->getFeedbacks()->add($feedbackD);


        // User E - attempt reviewed, then attempt not finished
        $participantE = new Participant(self::getDemoStudent('E'), $quiz);
        $quiz->getAllParticipants()->add($participantE);

        $responseE1 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantE->getAllResponses()->add($responseE1);
        $quiz->getResponses()->add($responseE1);

        $feedbackE1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseE1);
        $responseE1->getAllFeedback()->add($feedbackE1);
        $participantE->getAllFeedbacks()->add($feedbackE1);
        $quiz->getFeedbacks()->add($feedbackE1);

        $responseE2 = (new Attempt())
            ->setSubmittedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE2);
        $quiz->getResponses()->add($responseE2);


        // User F - attempt reviewed, then attempt finished but not reviewed
        $participantF = new Participant(self::getDemoStudent('F'), $quiz);
        $quiz->getAllParticipants()->add($participantF);

        $responseF1 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantF->getAllResponses()->add($responseF1);
        $quiz->getResponses()->add($responseF1);

        $feedbackF1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseF1);
        $responseF1->getAllFeedback()->add($feedbackF1);
        $participantF->getAllFeedbacks()->add($feedbackF1);
        $quiz->getFeedbacks()->add($feedbackF1);

        $responseF2 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantF->getAllResponses()->add($responseF2);
        $quiz->getResponses()->add($responseF2);

        return $quiz;
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
