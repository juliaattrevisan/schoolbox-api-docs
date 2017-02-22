<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */


use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\User;

require_once 'ProjectStatus.php';

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
class TaskStatus extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_selfAssessable_isResponseRequired
     * @param Participant $participant
     * @param bool $isResponseRequired
     */
    public function test_selfAssessable_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_notSelfAssessable_isResponseRequired
     * @param Participant $participant
     * @param bool $isResponseRequired
     */
    public function test_NotSelfAssessable_isResponseRequired(Participant $participant, $isResponseRequired)
    {
        $expected = $isResponseRequired;
        $actual = $participant->isResponseRequired();

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

        $message = "User: {$participant->getUser()->getFirstname()}, Expected: '". ($expected ? 'true' : 'false') ."', Actual: '". ($actual ? 'true' : 'false') ."'";
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

    public function provider_selfAssessable_isResponseRequired()
    {
        $task = self::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), true],
            [$task->getParticipant(self::getDemoStudent('B')), true],
            [$task->getParticipant(self::getDemoStudent('C')), false],
            [$task->getParticipant(self::getDemoStudent('D')), true],
            [$task->getParticipant(self::getDemoStudent('E')), false],
            [$task->getParticipant(self::getDemoStudent('F')), true],
            [$task->getParticipant(self::getDemoStudent('G')), false],
            [$task->getParticipant(self::getDemoStudent('H')), true],
            [$task->getParticipant(self::getDemoStudent('I')), false],
        ];
    }

    public function provider_selfAssessable_isFeedbackRequired()
    {
        $task = self::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), false],
            [$task->getParticipant(self::getDemoStudent('B')), false],
            [$task->getParticipant(self::getDemoStudent('C')), true],
            [$task->getParticipant(self::getDemoStudent('D')), false],
            [$task->getParticipant(self::getDemoStudent('E')), false],
            [$task->getParticipant(self::getDemoStudent('F')), false],
            [$task->getParticipant(self::getDemoStudent('G')), false],
            [$task->getParticipant(self::getDemoStudent('H')), false],
            [$task->getParticipant(self::getDemoStudent('I')), true],
        ];
    }

    public function provider_selfAssessable_isReviewComplete()
    {
        $task = self::getSelfAssessableTask(1);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), false],
            [$task->getParticipant(self::getDemoStudent('B')), false],
            [$task->getParticipant(self::getDemoStudent('C')), false],
            [$task->getParticipant(self::getDemoStudent('D')), false],
            [$task->getParticipant(self::getDemoStudent('E')), true],
            [$task->getParticipant(self::getDemoStudent('F')), false],
            [$task->getParticipant(self::getDemoStudent('G')), true],
            [$task->getParticipant(self::getDemoStudent('H')), false],
            [$task->getParticipant(self::getDemoStudent('I')), false],
        ];
    }

    public function provider_notSelfAssessable_isResponseRequired()
    {
        $task = self::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), true],
            [$task->getParticipant(self::getDemoStudent('B')), true],
            [$task->getParticipant(self::getDemoStudent('C')), false],
            [$task->getParticipant(self::getDemoStudent('D')), true],
            [$task->getParticipant(self::getDemoStudent('E')), false],
        ];
    }

    public function provider_notSelfAssessable_isFeedbackRequired()
    {
        $task = self::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), false],
            [$task->getParticipant(self::getDemoStudent('B')), false],
            [$task->getParticipant(self::getDemoStudent('C')), false],
            [$task->getParticipant(self::getDemoStudent('D')), false],
            [$task->getParticipant(self::getDemoStudent('E')), false],
        ];
    }

    public function provider_notSelfAssessable_isReviewComplete()
    {
        $task = self::getNonSelfAssessableTask(2);

        return [
            [$task->getParticipant(self::getDemoStudent('A')), false],
            [$task->getParticipant(self::getDemoStudent('B')), false],
            [$task->getParticipant(self::getDemoStudent('C')), true],
            [$task->getParticipant(self::getDemoStudent('D')), false],
            [$task->getParticipant(self::getDemoStudent('E')), true],
        ];
    }

    /**
     * @return Participant[][]
     */
    public function provider_participants()
    {
        $task1 = self::getSelfAssessableTask(1);
        $task2 = self::getSelfAssessableTask(2);

        $data = [];
        foreach($task1->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        foreach($task2->getAllParticipants() as $participant){
            $data[] = [$participant];
        }
        return $data;
    }

    // ================= Demo Data Helpers ==============

    /**
     * Generates a self-assessable Task with the following participation:
     *
     * * User A - no response
     * * User B - self-assessed as incomplete
     * * User C - self-assessed as complete
     * * User D - teacher-assessed as incomplete
     * * User E - teacher-assessed as complete
     * * User F - self-assessed as complete, then teacher-assessed as incomplete
     * * User G - self-assessed as incomplete, then teacher-assessed as complete
     * * User H - teacher-assessed as complete, then teacher-assessed as incomplete
     * * User I - teacher-assessed as incomplete, then student-assessed as complete
     *
     * @param int $id - The ID to assign to the task
     * @return Task
     */
    public static function getSelfAssessableTask($id)
    {
        $task =  (new Task())
            ->setId($id)
            ->setTitle('A self-assessable task')
            ->setIsSelfAssessable(true)
            ->setPublishAt((clone Alaress_Date_Format::now())->addDays(-7))
            ->setDueAt((clone Alaress_Date_Format::now())->addDays(+7));


        // Student A - no response
        $participantA = new Participant(self::getDemoStudent('A'), $task);
        $task->getAllParticipants()->add($participantA);


        // User B - self-assessed as incomplete
        $participantB = new Participant(self::getDemoStudent('B'), $task);
        $task->getAllParticipants()->add($participantB);

        $responseB = (new TaskResponse())
            ->setId(21)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantB->getUser())
            ->setSubmittedBy($participantB->getUser())
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseB);
        $participantB->getAllResponses()->add($responseB);


        // User C - self-assessed as complete
        $participantC = new Participant(self::getDemoStudent('C'), $task);
        $task->getAllParticipants()->add($participantC);

        $responseC = (new TaskResponse())
            ->setId(31)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy($participantC->getUser())
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseC);
        $participantC->getAllResponses()->add($responseC);


        // User D - teacher-assessed as incomplete
        $participantD = new Participant(self::getDemoStudent('D'), $task);
        $task->getAllParticipants()->add($participantD);

        $responseD = (new TaskResponse())
            ->setId(41)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseD);
        $participantD->getAllResponses()->add($responseD);

        $feedbackD = (new TaskFeedback())
            ->setResponse($responseD);
        $participantD->getAllFeedbacks()->add($feedbackD);


        // User E - teacher-assessed as complete
        $participantE = new Participant(self::getDemoStudent('E'), $task);
        $task->getAllParticipants()->add($participantE);

        $responseE = (new TaskResponse())
            ->setId(51)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseE);
        $participantE->getAllResponses()->add($responseE);

        $feedbackE = (new TaskFeedback())
            ->setResponse($responseE);
        $participantE->getAllFeedbacks()->add($feedbackE);


        // User F - self-assessed as complete, then teacher-assessed as incomplete
        $participantF = new Participant(self::getDemoStudent('F'), $task);
        $task->getAllParticipants()->add($participantF);

        $responseF1 = (new TaskResponse())
            ->setId(61)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantF->getUser())
            ->setSubmittedBy($participantF->getUser())
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseF1);
        $participantF->getAllResponses()->add($responseF1);

        $responseF2 = (new TaskResponse())
            ->setId(62)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantF->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseF2);
        $participantF->getAllResponses()->add($responseF2);

        $feedbackF2 = (new TaskFeedback())
            ->setResponse($responseF2);
        $participantF->getAllFeedbacks()->add($feedbackF2);


        // User G - self-assessed as incomplete, then teacher-assessed as complete
        $participantG = new Participant(self::getDemoStudent('G'), $task);
        $task->getAllParticipants()->add($participantG);

        $responseG1 = (new TaskResponse())
            ->setId(71)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantG->getUser())
            ->setSubmittedBy($participantG->getUser())
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseG1);
        $participantG->getAllResponses()->add($responseG1);

        $responseG2 = (new TaskResponse())
            ->setId(72)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantG->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseG2);
        $participantG->getAllResponses()->add($responseG2);

        $feedbackG2 = (new TaskFeedback())
            ->setResponse($responseG2);
        $participantG->getAllFeedbacks()->add($feedbackG2);

        // User H - teacher-assessed as complete, then teacher-assessed as incomplete
        $participantH = new Participant(self::getDemoStudent('H'), $task);
        $task->getAllParticipants()->add($participantH);

        $responseH1 = (new TaskResponse())
            ->setId(81)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantH->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseH1);
        $participantH->getAllResponses()->add($responseH1);

        $feedbackH1 = (new TaskFeedback())
            ->setResponse($responseH1);
        $participantH->getAllFeedbacks()->add($feedbackH1);

        $responseH2 = (new TaskResponse())
            ->setId(82)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantH->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseH2);
        $participantH->getAllResponses()->add($responseH2);

        $feedbackH2 = (new TaskFeedback())
            ->setResponse($responseH2);
        $participantH->getAllFeedbacks()->add($feedbackH2);


        // User I - teacher-assessed as incomplete, then student-assessed as complete
        $participantI = new Participant(self::getDemoStudent('I'), $task);
        $task->getAllParticipants()->add($participantI);

        $responseI1 = (new TaskResponse())
            ->setId(91)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantI->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseI1);
        $participantI->getAllResponses()->add($responseI1);

        $feedbackI1 = (new TaskFeedback())
            ->setResponse($responseI1);
        $participantI->getAllFeedbacks()->add($feedbackI1);

        $responseI2 = (new TaskResponse())
            ->setId(9)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantI->getUser())
            ->setSubmittedBy($participantI->getUser())
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseI2);
        $participantI->getAllResponses()->add($responseI2);


        return $task;
    }

    /**
     * Generates a non-self-assessable Task with the following participation:
     *
     * * User A - no interaction
     * * User B - teacher-assessed as incomplete
     * * User C - teacher-assessed as complete
     * * User D - teacher-assessed as complete, then teacher-assessed as incomplete
     * * User E - teacher-assessed as incomplete, then teacher-assessed as complete
     *
     * @param int $id - The ID to assign to the task
     * @return Task
     */
    public static function getNonSelfAssessableTask($id)
    {
        $task =  (new Task())
            ->setId($id)
            ->setTitle('A task that is NOT self-assessable.')
            ->setIsSelfAssessable(false)
            ->setPublishAt((clone Alaress_Date_Format::now())->addDays(-7))
            ->setDueAt((clone Alaress_Date_Format::now())->addDays(+7));


        // Student A - no response
        $participantA = new Participant(self::getDemoStudent('A'), $task);
        $task->getAllParticipants()->add($participantA);


        // User B - teacher-assessed as incomplete
        $participantB = new Participant(self::getDemoStudent('B'), $task);
        $task->getAllParticipants()->add($participantB);

        $responseB = (new TaskResponse())
            ->setId(21)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantB->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseB);
        $participantB->getAllResponses()->add($responseB);

        $feedbackB = (new TaskFeedback())
            ->setResponse($responseB);
        $participantB->getAllFeedbacks()->add($feedbackB);

        // User C - teacher-assessed as complete
        $participantC = new Participant(self::getDemoStudent('C'), $task);
        $task->getAllParticipants()->add($participantC);

        $responseC = (new TaskResponse())
            ->setId(31)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseC);
        $participantC->getAllResponses()->add($responseC);

        $feedbackC = (new TaskFeedback())
            ->setResponse($responseC);
        $participantC->getAllFeedbacks()->add($feedbackC);


        // User D - teacher-assessed as complete, then teacher-assessed as incomplete
        $participantD = new Participant(self::getDemoStudent('D'), $task);
        $task->getAllParticipants()->add($participantD);

        $responseD1 = (new TaskResponse())
            ->setId(41)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantD->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseD1);
        $participantD->getAllResponses()->add($responseD1);

        $feedbackD1 = (new TaskFeedback())
            ->setResponse($responseD1);
        $participantD->getAllFeedbacks()->add($feedbackD1);

        $responseD2 = (new TaskResponse())
            ->setId(42)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantD->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseD2);
        $participantD->getAllResponses()->add($responseD2);

        $feedbackD2 = (new TaskFeedback())
            ->setResponse($responseD2);
        $participantD->getAllFeedbacks()->add($feedbackD2);

        // User E - teacher-assessed as incomplete, then teacher-assessed as complete
        $participantE = new Participant(self::getDemoStudent('E'), $task);
        $task->getAllParticipants()->add($participantE);

        $responseE1 = (new TaskResponse())
            ->setId(51)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(clone Alaress_Date_Format::now())
            ->setUpdatedAt(clone Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseE1);
        $participantE->getAllResponses()->add($responseE1);

        $feedbackE1 = (new TaskFeedback())
            ->setResponse($responseE1);
        $participantE->getAllFeedbacks()->add($feedbackE1);

        $responseE2 = (new TaskResponse())
            ->setId(52)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setUpdatedAt((clone Alaress_Date_Format::now())->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseE2);
        $participantE->getAllResponses()->add($responseE2);

        $feedbackE2 = (new TaskFeedback())
            ->setResponse($responseE2);
        $participantE->getAllFeedbacks()->add($feedbackE2);

        return $task;
    }

    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     */
    private static function getDemoStudent($userChar)
    {
        return ProjectStatus::getDemoStudent($userChar);
    }

    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     */
    private static function getDemoTeacher($userChar)
    {
        return ProjectStatus::getDemoTeacher($userChar);
    }
}
