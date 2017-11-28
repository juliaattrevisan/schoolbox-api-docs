<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as Feedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Response;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as Attempt;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as Review;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Summator\ISummator;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;
use Schoolbox\User;

/**
 * Demo data helpers for tests.
 */
class DemoData
{
    /**
     * A cache of User objects, indexed by the first letter of their name, capitalised, eg 'A' for 'Andy', 'B' for Barry
     *
     * @var User[]|GenericCollection<User>
     */
    private static $studentCache;

    /**
     * A cache of User objects, indexed by the first letter of their name, capitalised, eg 'T' for 'Trevor'
     *
     * @var User[]|GenericCollection<User>
     */
    private static $teacherCache;

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
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-14))
            ->setOpenAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7))
            ->setCloseAt(Alaress_Date_Format::now()->addDays(+14))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());


        // User A - no interaction
        $participantA = new Participant(self::getDemoStudent('A'), $dueWork);
        $dueWork->getAllParticipants()->add($participantA);


        // User B - draft response
        $participantB = new Participant(self::getDemoStudent('B'), $dueWork);
        $dueWork->getAllParticipants()->add($participantB);

        $responseB = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantB->getAllResponses()->add($responseB);


        // User C - submitted response
        $participantC = new Participant(self::getDemoStudent('C'), $dueWork);
        $dueWork->getAllParticipants()->add($participantC);

        $responseC = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);


        // User D - submitted response then draft response
        $participantD = new Participant(self::getDemoStudent('D'), $dueWork);
        $dueWork->getAllParticipants()->add($participantD);

        $responseD1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD1);

        $responseD2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantD->getAllResponses()->add($responseD2);


        // User E - draft feedback
        $participantE = new Participant(self::getDemoStudent('E'), $dueWork);
        $dueWork->getAllParticipants()->add($participantE);

        $feedbackE = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantE->getAllFeedbacks()->add($feedbackE);
        $dueWork->getFeedbacks()->add($feedbackE);


        // User F - published feedback
        $participantF = new Participant(self::getDemoStudent('F'), $dueWork);
        $dueWork->getAllParticipants()->add($participantF);

        $feedbackF = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantF->getAllFeedbacks()->add($feedbackF);
        $dueWork->getFeedbacks()->add($feedbackF);


        // User G - published feedback then draft feedback
        $participantG = new Participant(self::getDemoStudent('G'), $dueWork);
        $dueWork->getAllParticipants()->add($participantG);

        $feedbackG1 = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantG->getAllFeedbacks()->add($feedbackG1);
        $dueWork->getFeedbacks()->add($feedbackG1);

        $feedbackG2 = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackG2);
        $dueWork->getFeedbacks()->add($feedbackG2);


        // User H - submitted response with draft floating feedback
        $participantH = new Participant(self::getDemoStudent('H'), $dueWork);
        $dueWork->getAllParticipants()->add($participantH);

        $responseH = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantH->getAllResponses()->add($responseH);

        $feedbackH = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackH);
        $dueWork->getFeedbacks()->add($feedbackH);


        // User I - submitted response with later published floating feedback
        $participantI = new Participant(self::getDemoStudent('I'), $dueWork);
        $dueWork->getAllParticipants()->add($participantI);

        $responseI = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantI->getAllResponses()->add($responseI);

        $feedbackI = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantI->getAllFeedbacks()->add($feedbackI);
        $dueWork->getFeedbacks()->add($feedbackI);


        // User J - submitted response with later draft linked feedback
        $participantJ = new Participant(self::getDemoStudent('J'), $dueWork);
        $dueWork->getAllParticipants()->add($participantJ);

        $responseJ = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantJ->getAllResponses()->add($responseJ);

        $feedbackJ = (new Feedback())
            ->setResponse($responseJ)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantJ->getAllFeedbacks()->add($feedbackJ);
        $dueWork->getFeedbacks()->add($feedbackJ);
        $responseJ->getAllFeedback()->add($feedbackJ);


        // User K - submitted response with published linked feedback
        $participantK = new Participant(self::getDemoStudent('K'), $dueWork);
        $dueWork->getAllParticipants()->add($participantK);

        $responseK = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantK->getAllResponses()->add($responseK);

        $feedbackK = (new Feedback())
            ->setResponse($responseK)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantK->getAllFeedbacks()->add($feedbackK);
        $dueWork->getFeedbacks()->add($feedbackK);
        $responseK->getAllFeedback()->add($feedbackK);


        // User L - submitted response with published linked feedback, then draft response
        $participantL = new Participant(self::getDemoStudent('L'), $dueWork);
        $dueWork->getAllParticipants()->add($participantL);

        $responseL1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantL->getAllResponses()->add($responseL1);

        $feedbackL1 = (new Feedback())
            ->setResponse($responseL1)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantL->getAllFeedbacks()->add($feedbackL1);
        $dueWork->getFeedbacks()->add($feedbackL1);
        $responseL1->getAllFeedback()->add($feedbackL1);

        $responseL2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantL->getAllResponses()->add($responseL2);


        // User M - submitted response with published floating feedback, then later draft response
        $participantM = new Participant(self::getDemoStudent('M'), $dueWork);
        $dueWork->getAllParticipants()->add($participantM);

        $responseM1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantM->getAllResponses()->add($responseM1);

        $feedbackM1 = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantM->getAllFeedbacks()->add($feedbackM1);
        $dueWork->getFeedbacks()->add($feedbackM1);

        $responseM2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantM->getAllResponses()->add($responseM2);


        // User N - submitted response with published floating feedback, then later submitted response
        $participantN = new Participant(self::getDemoStudent('N'), $dueWork);
        $dueWork->getAllParticipants()->add($participantN);

        $responseN1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantN->getAllResponses()->add($responseN1);

        $feedbackN1 = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantN->getAllFeedbacks()->add($feedbackN1);
        $dueWork->getFeedbacks()->add($feedbackN1);

        $responseN2 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+2))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantN->getAllResponses()->add($responseN2);

        // User O - draft floating feedback, then later draft response
        $participantO = new Participant(self::getDemoStudent('O'), $dueWork);
        $dueWork->getAllParticipants()->add($participantO);

        $feedbackO = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantO->getAllFeedbacks()->add($feedbackO);
        $dueWork->getFeedbacks()->add($feedbackO);

        $responseO = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantO->getAllResponses()->add($responseO);


        // User P - draft floating feedback, then later submitted response
        $participantP = new Participant(self::getDemoStudent('P'), $dueWork);
        $dueWork->getAllParticipants()->add($participantP);

        $feedbackP = (new Feedback())
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantP->getAllFeedbacks()->add($feedbackP);
        $dueWork->getFeedbacks()->add($feedbackP);

        $responseP = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantP->getAllResponses()->add($responseP);


        // User Q - published floating feedback, then later draft response
        $participantQ = new Participant(self::getDemoStudent('Q'), $dueWork);
        $dueWork->getAllParticipants()->add($participantQ);

        $feedbackQ = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantQ->getAllFeedbacks()->add($feedbackQ);
        $dueWork->getFeedbacks()->add($feedbackQ);

        $responseQ = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantQ->getAllResponses()->add($responseQ);


        // User R - published floating feedback, then later submitted response
        $participantR = new Participant(self::getDemoStudent('R'), $dueWork);
        $dueWork->getAllParticipants()->add($participantR);

        $feedbackR = (new Feedback())
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantR->getAllFeedbacks()->add($feedbackR);
        $dueWork->getFeedbacks()->add($feedbackR);

        $responseR = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantR->getAllResponses()->add($responseR);

        return $dueWork;
    }

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


        // User C - attempt finished but not reviewed
        $participantC = new Participant(self::getDemoStudent('C'), $quiz);
        $quiz->getAllParticipants()->add($participantC);

        $responseC = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);


        // User D - attempt reviewed
        $participantD = new Participant(self::getDemoStudent('D'), $quiz);
        $quiz->getAllParticipants()->add($participantD);

        $responseD = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD);

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

        $feedbackE1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseE1);
        $responseE1->getAllFeedback()->add($feedbackE1);
        $participantE->getAllFeedbacks()->add($feedbackE1);
        $quiz->getFeedbacks()->add($feedbackE1);

        $responseE2 = (new Attempt())
            ->setSubmittedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE2);


        // User F - attempt reviewed, then attempt finished but not reviewed
        $participantF = new Participant(self::getDemoStudent('F'), $quiz);
        $quiz->getAllParticipants()->add($participantF);

        $responseF1 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantF->getAllResponses()->add($responseF1);

        $feedbackF1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseF1);
        $responseF1->getAllFeedback()->add($feedbackF1);
        $participantF->getAllFeedbacks()->add($feedbackF1);
        $quiz->getFeedbacks()->add($feedbackF1);

        $responseF2 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantF->getAllResponses()->add($responseF2);

        return $quiz;
    }

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
        $task = (new Task())
            ->setId($id)
            ->setTitle('A self-assessable task')
            ->setIsSelfAssessable(true)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7));


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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseF1);
        $participantF->getAllResponses()->add($responseF1);

        $responseF2 = (new TaskResponse())
            ->setId(62)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantF->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseG1);
        $participantG->getAllResponses()->add($responseG1);

        $responseG2 = (new TaskResponse())
            ->setId(72)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantG->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
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
        $task = (new Task())
            ->setId($id)
            ->setTitle('A task that is NOT self-assessable.')
            ->setIsSelfAssessable(false)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7));


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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
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
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
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
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $task->getResponses()->add($responseE2);
        $participantE->getAllResponses()->add($responseE2);

        $feedbackE2 = (new TaskFeedback())
            ->setResponse($responseE2);
        $participantE->getAllFeedbacks()->add($feedbackE2);

        return $task;
    }

    /**
     * Generates a Project with the following sub-assessments:
     *
     * * self-assessable task
     * * non-self-assessable task
     * * quiz
     * * dueWork
     *
     * * User A
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - responseRequired
     * * * DueWork - responseRequired
     * * User B
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - responseRequired
     * * * DueWork - responseRequired
     * * User C
     * * * Task1   - feedbackRequired
     * * * Task2   - reviewComplete
     * * * Quiz    - feedbackRequired
     * * * DueWork - feedbackRequired
     * * User D
     * * * Task1   - responseRequired
     * * * Task2   - responseRequired
     * * * Quiz    - reviewComplete
     * * * DueWork - feedbackRequired
     * * User E
     * * * Task1   - reviewComplete
     * * * Task2   - reviewComplete
     * * * Quiz    - reviewComplete
     * * * DueWork - responseRequired
     * * User F
     * * * Task1   - responseRequired
     * * * Quiz    - feedbackRequired
     * * * DueWork - reviewComplete
     * * User G
     * * * Task1   - reviewComplete
     * * * DueWork - reviewComplete
     * * User H
     * * * Task1   - responseRequired
     * * * DueWork - feedbackRequired
     * * User I
     * * * Task1   - feedbackRequired
     * * * DueWork - reviewComplete
     * * User J
     * * * DueWork - feedbackRequired
     * * User K
     * * * DueWork - reviewComplete
     * * User L
     * * * DueWork - reviewComplete
     * * User M
     * * * DueWork - reviewComplete
     * * User N
     * * * DueWork - feedbackRequired
     * * User O
     * * * DueWork - responseRequired
     * * User P
     * * * DueWork - feedbackRequired
     * * User Q
     * * * DueWork - reviewComplete
     * * User R
     * * * DueWork - feedbackRequired
     *
     * @param ISummator $summator
     * @return Project
     */
    public static function getProject(ISummator $summator = null)
    {
        $project = (new Project($summator))
            ->setId(1);

        $project->getAssessables()
                ->add(self::getSelfAssessableTask(2))
                ->add(self::getNonSelfAssessableTask(3))
                ->add(self::getQuiz(4))
                ->add(self::getDueWork(5));

        // Load the project participants
        foreach ($project->getAssessables() as $subAssessable) {
            foreach ($subAssessable->getAllParticipants() as $subParticipant) {
                // Does project contain this user yet?
                if (!$project->isParticipant($subParticipant->getUser())) {
                    $project->getAllParticipants()->add((new Participant($subParticipant->getUser(), $project)));
                }
            }
        }

        return $project;
    }


    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     */
    public static function getDemoStudent($userChar)
    {
        if (is_null(self::$studentCache)) {
            self::$studentCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$studentCache[$userChar]) && self::$studentCache[$userChar] instanceof User) {
            return self::$studentCache[$userChar];
        }

        $user = (new User())->setId($userChar);
        switch ($userChar) {
            case 'A':
                $user->setFirstname('Andrew (Student)');
                break;
            case 'B':
                $user->setFirstname('Barry (Student)');
                break;
            case 'C':
                $user->setFirstname('Charlie (Student)');
                break;
            case 'D':
                $user->setFirstname('David (Student)');
                break;
            case 'E':
                $user->setFirstname('Ethan (Student)');
                break;
            case 'F':
                $user->setFirstname('Fred (Student)');
                break;
            case 'G':
                $user->setFirstname('Gary (Student)');
                break;
            case 'H':
                $user->setFirstname('Harry (Student)');
                break;
            case 'I':
                $user->setFirstname('Irwin (Student)');
                break;
            case 'J':
                $user->setFirstname('Jake (Student)');
                break;
            case 'K':
                $user->setFirstname('Kate (Student)');
                break;
            case 'L':
                $user->setFirstname('Liam (Student)');
                break;
            case 'M':
                $user->setFirstname('Mary (Student)');
                break;
            case 'N':
                $user->setFirstname('Nate (Student)');
                break;
            case 'O':
                $user->setFirstname('Olly (Student)');
                break;
            case 'P':
                $user->setFirstname('Pete (Student)');
                break;
            case 'Q':
                $user->setFirstname('Quinn (Student)');
                break;
            case 'R':
                $user->setFirstname('Rory (Student)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userChar: $userChar");
        }
        self::$studentCache[$userChar] = $user;
        return $user;
    }

    /**
     * @param string $userChar - a single char representing their first name, eg 'T', etc
     * @return User
     */
    public static function getDemoTeacher($userChar)
    {
        if (is_null(self::$teacherCache)) {
            self::$teacherCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$teacherCache[$userChar]) && self::$teacherCache[$userChar] instanceof User) {
            return self::$teacherCache[$userChar];
        }

        $user = (new User())->setId($userChar);
        switch ($userChar) {
            case 'T':
                $user->setFirstname('Trevor (Teacher)');
                break;
            default:
                throw new InvalidArgumentException("Unknown userChar: $userChar");
        }
        self::$teacherCache[$userChar] = $user;
        return $user;
    }

}
