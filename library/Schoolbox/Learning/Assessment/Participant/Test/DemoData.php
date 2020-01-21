<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Participant\Test;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Assessment\Test\ParticipantLinker;
use UnexpectedValueException;
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
     * * User S - unpublished self-assessed feedback
     * * User T - published self-assessed feedback
     * * User U - unpublished self-assessed floating feedback
     * * User V - published self-assessed floating feedback
     * * User W - published self-assessed, draft instructor-given feedback
     * * User X - published self-assessed, published instructor-given feedback
     * * User Z - published self-assessed and instructor-given feedback, published self-assessed feedback
     *
     * @param int $id - The ID to assign to the assessment
     * @return DueWork
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     */
    public static function getDueWork($id): DueWork
    {
        $dueWork = (new DueWork())
            ->setId($id)
            ->setAcceptOnline(true)
            ->setAcceptsMultiple()
            ->setSelfReviewability(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-14))
            ->setOpenAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7))
            ->setCloseAt(Alaress_Date_Format::now()->addDays(+14))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());

        $linker      = new ParticipantLinker();
        $dueWorkPlan = $linker->createAndLinkPlan($dueWork);

        // User A - no interaction
        $linker->createAndLinkParticipant(self::getDemoStudent('A'), $dueWorkPlan);


        // User B - draft response
        $participantB = $linker->createAndLinkParticipant(self::getDemoStudent('B'), $dueWorkPlan);

        $responseB = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantB->getAllResponses()->add($responseB);


        // User C - submitted response
        $participantC = $linker->createAndLinkParticipant(self::getDemoStudent('C'), $dueWorkPlan);

        $responseC = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);


        // User D - submitted response then draft response
        $participantD = $linker->createAndLinkParticipant(self::getDemoStudent('D'), $dueWorkPlan);

        $responseD1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD1);

        $responseD2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantD->getAllResponses()->add($responseD2);


        // User E - draft feedback
        $participantE = $linker->createAndLinkParticipant(self::getDemoStudent('E'), $dueWorkPlan);

        $feedbackE = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantE->getAllFeedbacks()->add($feedbackE);


        // User F - published feedback
        $participantF = $linker->createAndLinkParticipant(self::getDemoStudent('F'), $dueWorkPlan);

        $feedbackF = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantF->getAllFeedbacks()->add($feedbackF);


        // User G - published feedback then draft feedback
        $participantG = $linker->createAndLinkParticipant(self::getDemoStudent('G'), $dueWorkPlan);

        $feedbackG1 = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantG->getAllFeedbacks()->add($feedbackG1);

        $feedbackG2 = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackG2);


        // User H - submitted response with draft floating feedback
        $participantH = $linker->createAndLinkParticipant(self::getDemoStudent('H'), $dueWorkPlan);

        $responseH = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantH->getAllResponses()->add($responseH);

        $feedbackH = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantG->getAllFeedbacks()->add($feedbackH);


        // User I - submitted response with later published floating feedback
        $participantI = $linker->createAndLinkParticipant(self::getDemoStudent('I'), $dueWorkPlan);

        $responseI = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantI->getAllResponses()->add($responseI);

        $feedbackI = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantI->getAllFeedbacks()->add($feedbackI);


        // User J - submitted response with later draft linked feedback
        $participantJ = $linker->createAndLinkParticipant(self::getDemoStudent('J'), $dueWorkPlan);

        $responseJ = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantJ->getAllResponses()->add($responseJ);

        $feedbackJ = (new Feedback())
            ->setResponse($responseJ)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantJ->getAllFeedbacks()->add($feedbackJ);
        $responseJ->getAllFeedback()->add($feedbackJ);


        // User K - submitted response with published linked feedback
        $participantK = $linker->createAndLinkParticipant(self::getDemoStudent('K'), $dueWorkPlan);

        $responseK = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantK->getAllResponses()->add($responseK);

        $feedbackK = (new Feedback())
            ->setResponse($responseK)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantK->getAllFeedbacks()->add($feedbackK);
        $responseK->getAllFeedback()->add($feedbackK);


        // User L - submitted response with published linked feedback, then draft response
        $participantL = $linker->createAndLinkParticipant(self::getDemoStudent('L'), $dueWorkPlan);

        $responseL1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantL->getAllResponses()->add($responseL1);

        $feedbackL1 = (new Feedback())
            ->setResponse($responseL1)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantL->getAllFeedbacks()->add($feedbackL1);
        $responseL1->getAllFeedback()->add($feedbackL1);

        $responseL2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantL->getAllResponses()->add($responseL2);


        // User M - submitted response with published floating feedback, then later draft response
        $participantM = $linker->createAndLinkParticipant(self::getDemoStudent('M'), $dueWorkPlan);

        $responseM1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantM->getAllResponses()->add($responseM1);

        $feedbackM1 = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantM->getAllFeedbacks()->add($feedbackM1);

        $responseM2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantM->getAllResponses()->add($responseM2);


        // User N - submitted response with published floating feedback, then later submitted response
        $participantN = $linker->createAndLinkParticipant(self::getDemoStudent('N'), $dueWorkPlan);

        $responseN1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantN->getAllResponses()->add($responseN1);

        $feedbackN1 = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantN->getAllFeedbacks()->add($feedbackN1);

        $responseN2 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+2))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+2));
        $participantN->getAllResponses()->add($responseN2);

        // User O - draft floating feedback, then later draft response
        $participantO = $linker->createAndLinkParticipant(self::getDemoStudent('O'), $dueWorkPlan);

        $feedbackO = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantO->getAllFeedbacks()->add($feedbackO);

        $responseO = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantO->getAllResponses()->add($responseO);


        // User P - draft floating feedback, then later submitted response
        $participantP = $linker->createAndLinkParticipant(self::getDemoStudent('P'), $dueWorkPlan);

        $feedbackP = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantP->getAllFeedbacks()->add($feedbackP);

        $responseP = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantP->getAllResponses()->add($responseP);


        // User Q - published floating feedback, then later draft response
        $participantQ = $linker->createAndLinkParticipant(self::getDemoStudent('Q'), $dueWorkPlan);

        $feedbackQ = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantQ->getAllFeedbacks()->add($feedbackQ);

        $responseQ = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantQ->getAllResponses()->add($responseQ);


        // User R - published floating feedback, then later submitted response
        $participantR = $linker->createAndLinkParticipant(self::getDemoStudent('R'), $dueWorkPlan);

        $feedbackR = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantR->getAllFeedbacks()->add($feedbackR);

        $responseR = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1));
        $participantR->getAllResponses()->add($responseR);


        // User S: unpublished self-assessed feedback
        $participantS = $linker->createAndLinkParticipant(self::getDemoStudent('S'), $dueWorkPlan);

        $responseS = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantS->getAllResponses()->add($responseS);

        $feedbackS = (new Feedback())
            ->setResponse($responseS)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantS->getAllFeedbacks()->add($feedbackS);


        // User T: published self-assessed feedback
        $participantT = $linker->createAndLinkParticipant(self::getDemoStudent('T'), $dueWorkPlan);

        $responseT = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantT->getAllResponses()->add($responseT);

        $feedbackT = (new Feedback())
            ->setResponse($responseT)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantT->getAllFeedbacks()->add($feedbackT);


        // User U: unpublished self-assessed floating feedback
        // Business rules state that self-assessed feedback should never be floating, but test anyway.
        $participantU = $linker->createAndLinkParticipant(self::getDemoStudent('U'), $dueWorkPlan);

        $feedbackU = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantU->getAllFeedbacks()->add($feedbackU);


        // User V: published self-assessed floating feedback
        // Business rules state that self-assessed feedback should never be floating, but test anyway.
        $participantV = $linker->createAndLinkParticipant(self::getDemoStudent('V'), $dueWorkPlan);

        $feedbackV = (new Feedback())
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantV->getAllFeedbacks()->add($feedbackV);


        // User W: published self-assessed, draft instructor-given feedback
        $participantW = $linker->createAndLinkParticipant(self::getDemoStudent('W'), $dueWorkPlan);

        $responseW = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantW->getAllResponses()->add($responseW);

        $feedbackW1 = (new Feedback())
            ->setResponse($responseW)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantW->getAllFeedbacks()->add($feedbackW1);

        $feedbackW2 = (new Feedback())
            ->setResponse($responseW)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantW->getAllFeedbacks()->add($feedbackW2);


        // User X: published self-assessed, published instructor-given feedback
        $participantX = $linker->createAndLinkParticipant(self::getDemoStudent('X'), $dueWorkPlan);

        $responseX = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantX->getAllResponses()->add($responseX);

        $feedbackX1 = (new Feedback())
            ->setResponse($responseX)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantX->getAllFeedbacks()->add($feedbackX1);

        $feedbackX2 = (new Feedback())
            ->setResponse($responseX)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantX->getAllFeedbacks()->add($feedbackX2);


        // User Y: published self-assessed and instructor-given feedback, draft self-assessed feedback
        $participantY = $linker->createAndLinkParticipant(self::getDemoStudent('Y'), $dueWorkPlan);

        $responseY1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantY->getAllResponses()->add($responseY1);

        $feedbackY1 = (new Feedback())
            ->setResponse($responseY1)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantY->getAllFeedbacks()->add($feedbackY1);

        $feedbackY2 = (new Feedback())
            ->setResponse($responseY1)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantY->getAllFeedbacks()->add($feedbackY2);

        $responseY2 = (new Response())
            ->setSubmittedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantY->getAllResponses()->add($responseY2);

        $feedbackY3 = (new Feedback())
            ->setResponse($responseY2)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(new Alaress_Date_NullDate())
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantY->getAllFeedbacks()->add($feedbackY3);


        // User Z: published self-assessed and instructor-given feedback, published self-assessed feedback
        $participantZ = $linker->createAndLinkParticipant(self::getDemoStudent('Z'), $dueWorkPlan);

        $responseZ1 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantZ->getAllResponses()->add($responseZ1);

        $feedbackZ1 = (new Feedback())
            ->setResponse($responseZ1)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantZ->getAllFeedbacks()->add($feedbackZ1);

        $feedbackZ2 = (new Feedback())
            ->setResponse($responseZ1)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());
        $participantZ->getAllFeedbacks()->add($feedbackZ2);

        $responseZ2 = (new Response())
            ->setSubmittedAt(Alaress_Date_Format::now()->addDays(1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantZ->getAllResponses()->add($responseZ2);

        $feedbackZ3 = (new Feedback())
            ->setResponse($responseZ2)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_SELF_GRADING)
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(1));
        $participantZ->getAllFeedbacks()->add($feedbackZ3);

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
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     */
    public static function getQuiz($id): Quiz
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

        $linker      = new ParticipantLinker();
        $quizPlan = $linker->createAndLinkPlan($quiz);


        // User A - no interaction
        $linker->createAndLinkParticipant(self::getDemoStudent('A'), $quizPlan);


        // User B - attempt not finished
        $participantB = $linker->createAndLinkParticipant(self::getDemoStudent('B'), $quizPlan);

        $responseB = (new Attempt())
            ->setSubmittedAt(new Alaress_Date_NullDate());
        $participantB->getAllResponses()->add($responseB);


        // User C - attempt finished but not reviewed
        $participantC = $linker->createAndLinkParticipant(self::getDemoStudent('C'), $quizPlan);

        $responseC = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantC->getAllResponses()->add($responseC);


        // User D - attempt reviewed
        $participantD = $linker->createAndLinkParticipant(self::getDemoStudent('D'), $quizPlan);

        $responseD = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantD->getAllResponses()->add($responseD);

        $feedbackD = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseD);
        $responseD->getAllFeedback()->add($feedbackD);
        $participantD->getAllFeedbacks()->add($feedbackD);


        // User E - attempt reviewed, then attempt not finished
        $participantE = $linker->createAndLinkParticipant(self::getDemoStudent('E'), $quizPlan);

        $responseE1 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantE->getAllResponses()->add($responseE1);

        $feedbackE1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseE1);
        $responseE1->getAllFeedback()->add($feedbackE1);
        $participantE->getAllFeedbacks()->add($feedbackE1);

        $responseE2 = (new Attempt())
            ->setSubmittedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE2);


        // User F - attempt reviewed, then attempt finished but not reviewed
        $participantF = $linker->createAndLinkParticipant(self::getDemoStudent('F'), $quizPlan);

        $responseF1 = (new Attempt())
            ->setSubmittedAt(Alaress_Date_Format::now());
        $participantF->getAllResponses()->add($responseF1);

        $feedbackF1 = (new Review())
            ->setPublishedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setResponse($responseF1);
        $responseF1->getAllFeedback()->add($feedbackF1);
        $participantF->getAllFeedbacks()->add($feedbackF1);

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
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     */
    public static function getSelfAssessableTask($id): Task
    {
        $task = (new Task())
            ->setId($id)
            ->setTitle('A self-assessable task')
            ->setIsSelfAssessable(true)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());

        $linker   = new ParticipantLinker();
        $taskPlan = $linker->createAndLinkPlan($task);

        // Student A - no response
        $linker->createAndLinkParticipant(self::getDemoStudent('A'), $taskPlan);


        // User B - self-assessed as incomplete
        $participantB = $linker->createAndLinkParticipant(self::getDemoStudent('B'), $taskPlan);

        $responseB = (new TaskResponse())
            ->setId(21)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantB->getUser())
            ->setSubmittedBy($participantB->getUser())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantB->getAllResponses()->add($responseB);


        // User C - self-assessed as complete
        $participantC = $linker->createAndLinkParticipant(self::getDemoStudent('C'), $taskPlan);

        $responseC = (new TaskResponse())
            ->setId(31)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy($participantC->getUser())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantC->getAllResponses()->add($responseC);


        // User D - teacher-assessed as incomplete
        $participantD = $linker->createAndLinkParticipant(self::getDemoStudent('D'), $taskPlan);

        $responseD = (new TaskResponse())
            ->setId(41)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantD->getAllResponses()->add($responseD);

        $feedbackD = (new TaskFeedback())
            ->setResponse($responseD);
        $participantD->getAllFeedbacks()->add($feedbackD);
        $responseD->getAllFeedback()->add($feedbackD);


        // User E - teacher-assessed as complete
        $participantE = $linker->createAndLinkParticipant(self::getDemoStudent('E'), $taskPlan);

        $responseE = (new TaskResponse())
            ->setId(51)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE);

        $feedbackE = (new TaskFeedback())
            ->setResponse($responseE);
        $participantE->getAllFeedbacks()->add($feedbackE);
        $responseE->getAllFeedback()->add($feedbackE);


        // User F - self-assessed as complete, then teacher-assessed as incomplete
        $participantF = $linker->createAndLinkParticipant(self::getDemoStudent('F'), $taskPlan);

        $responseF1 = (new TaskResponse())
            ->setId(61)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantF->getUser())
            ->setSubmittedBy($participantF->getUser())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
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
        $participantF->getAllResponses()->add($responseF2);

        $feedbackF2 = (new TaskFeedback())
            ->setResponse($responseF2);
        $participantF->getAllFeedbacks()->add($feedbackF2);
        $responseF2->getAllFeedback()->add($feedbackF2);


        // User G - self-assessed as incomplete, then teacher-assessed as complete
        $participantG = $linker->createAndLinkParticipant(self::getDemoStudent('G'), $taskPlan);

        $responseG1 = (new TaskResponse())
            ->setId(71)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantG->getUser())
            ->setSubmittedBy($participantG->getUser())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
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
        $participantG->getAllResponses()->add($responseG2);

        $feedbackG2 = (new TaskFeedback())
            ->setResponse($responseG2);
        $participantG->getAllFeedbacks()->add($feedbackG2);
        $responseG2->getAllFeedback()->add($feedbackG2);

        // User H - teacher-assessed as complete, then teacher-assessed as incomplete
        $participantH = $linker->createAndLinkParticipant(self::getDemoStudent('H'), $taskPlan);

        $responseH1 = (new TaskResponse())
            ->setId(81)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantH->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantH->getAllResponses()->add($responseH1);

        $feedbackH1 = (new TaskFeedback())
            ->setResponse($responseH1);
        $participantH->getAllFeedbacks()->add($feedbackH1);
        $responseH1->getAllFeedback()->add($feedbackH1);

        $responseH2 = (new TaskResponse())
            ->setId(82)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantH->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantH->getAllResponses()->add($responseH2);

        $feedbackH2 = (new TaskFeedback())
            ->setResponse($responseH2);
        $participantH->getAllFeedbacks()->add($feedbackH2);
        $responseH2->getAllFeedback()->add($feedbackH2);


        // User I - teacher-assessed as incomplete, then student-assessed as complete
        $participantI = $linker->createAndLinkParticipant(self::getDemoStudent('I'), $taskPlan);

        $responseI1 = (new TaskResponse())
            ->setId(91)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantI->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantI->getAllResponses()->add($responseI1);

        $feedbackI1 = (new TaskFeedback())
            ->setResponse($responseI1);
        $participantI->getAllFeedbacks()->add($feedbackI1);
        $responseH2->getAllFeedback()->add($feedbackI1);

        $responseI2 = (new TaskResponse())
            ->setId(9)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantI->getUser())
            ->setSubmittedBy($participantI->getUser())
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
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
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     */
    public static function getNonSelfAssessableTask($id)
    {
        $task = (new Task())
            ->setId($id)
            ->setTitle('A task that is NOT self-assessable.')
            ->setIsSelfAssessable(false)
            ->setPublishAt(Alaress_Date_Format::now()->addDays(-7))
            ->setDueAt(Alaress_Date_Format::now()->addDays(+7))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(-21))
            ->setDeletedAt(new Alaress_Date_NullDate());

        $linker   = new ParticipantLinker();
        $taskPlan = $linker->createAndLinkPlan($task);

        // Student A - no response
        $linker->createAndLinkParticipant(self::getDemoStudent('A'), $taskPlan);


        // User B - teacher-assessed as incomplete
        $participantB = $linker->createAndLinkParticipant(self::getDemoStudent('B'), $taskPlan);

        $responseB = (new TaskResponse())
            ->setId(21)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantB->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantB->getAllResponses()->add($responseB);

        $feedbackB = (new TaskFeedback())
            ->setResponse($responseB);
        $participantB->getAllFeedbacks()->add($feedbackB);
        $responseB->getAllFeedback()->add($feedbackB);

        // User C - teacher-assessed as complete
        $participantC = $linker->createAndLinkParticipant(self::getDemoStudent('C'), $taskPlan);

        $responseC = (new TaskResponse())
            ->setId(31)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantC->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantC->getAllResponses()->add($responseC);

        $feedbackC = (new TaskFeedback())
            ->setResponse($responseC);
        $participantC->getAllFeedbacks()->add($feedbackC);
        $responseC->getAllFeedback()->add($feedbackC);


        // User D - teacher-assessed as complete, then teacher-assessed as incomplete
        $participantD = $linker->createAndLinkParticipant(self::getDemoStudent('D'), $taskPlan);

        $responseD1 = (new TaskResponse())
            ->setId(41)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantD->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantD->getAllResponses()->add($responseD1);

        $feedbackD1 = (new TaskFeedback())
            ->setResponse($responseD1);
        $participantD->getAllFeedbacks()->add($feedbackD1);
        $responseD1->getAllFeedback()->add($feedbackD1);

        $responseD2 = (new TaskResponse())
            ->setId(42)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantD->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantD->getAllResponses()->add($responseD2);

        $feedbackD2 = (new TaskFeedback())
            ->setResponse($responseD2);
        $participantD->getAllFeedbacks()->add($feedbackD2);
        $responseD2->getAllFeedback()->add($feedbackD2);

        // User E - teacher-assessed as incomplete, then teacher-assessed as complete
        $participantE = $linker->createAndLinkParticipant(self::getDemoStudent('E'), $taskPlan);

        $responseE1 = (new TaskResponse())
            ->setId(51)
            ->setAssessable($task)
            ->setMarkedComplete(false)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now())
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE1);

        $feedbackE1 = (new TaskFeedback())
            ->setResponse($responseE1);
        $participantE->getAllFeedbacks()->add($feedbackE1);
        $responseE1->getAllFeedback()->add($feedbackE1);

        $responseE2 = (new TaskResponse())
            ->setId(52)
            ->setAssessable($task)
            ->setMarkedComplete(true)
            ->setSubmittedOnBehalfOf($participantE->getUser())
            ->setSubmittedBy(self::getDemoTeacher('T'))
            ->setCreatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setUpdatedAt(Alaress_Date_Format::now()->addDays(+1))
            ->setDeletedAt(new Alaress_Date_NullDate());
        $participantE->getAllResponses()->add($responseE2);

        $feedbackE2 = (new TaskFeedback())
            ->setResponse($responseE2);
        $participantE->getAllFeedbacks()->add($feedbackE2);
        $responseE2->getAllFeedback()->add($feedbackE2);

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
     * @throws InvalidArgumentException If an unrecognised $userChar is attempted
     * @throws UnexpectedValueException If unable to merge collections
     */
    public static function getProject(ISummator $summator = null): Project
    {
        $project = (new Project($summator))
            ->setId(1)
            ->setTitle('aProject');

        $project->getAssessables()
                ->add(self::getSelfAssessableTask(2))
                ->add(self::getNonSelfAssessableTask(3))
                ->add(self::getQuiz(4))
                ->add(self::getDueWork(5));

        $aggregator = new Participant\Aggregator();
        $aggregator->aggregate($project);

        return $project;
    }


    /**
     * @param string $userChar - a single char representing their first name, eg 'A', 'B', etc
     * @return User
     * @throws InvalidArgumentException If an unrecognised $userChar is passed
     */
    public static function getDemoStudent(string $userChar): User
    {
        if (null === self::$studentCache) {
            self::$studentCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$studentCache[$userChar]) && self::$studentCache[$userChar] instanceof User) {
            return self::$studentCache[$userChar];
        }

        $user = (new User())->setId(\ord($userChar));
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
            case 'S':
                $user->setFirstname('Steve (Student)');
                break;
            case 'T':
                $user->setFirstname('Tom (Student)');
                break;
            case 'U':
                $user->setFirstname('Ursula (Student)');
                break;
            case 'V':
                $user->setFirstname('Veronica (Student)');
                break;
            case 'W':
                $user->setFirstname('Wally (Student)');
                break;
            case 'X':
                $user->setFirstname('Xavier (Student)');
                break;
            case 'Y':
                $user->setFirstname('Yvonne (Student)');
                break;
            case 'Z':
                $user->setFirstname('Zoe (Student)');
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
     * @throws InvalidArgumentException If an unrecognised $userChar is passed
     */
    public static function getDemoTeacher(string $userChar): User
    {
        if (null === self::$teacherCache) {
            self::$teacherCache = new GenericCollection(User::class);
        }

        // Use cache if possible
        if (isset(self::$teacherCache[$userChar]) && self::$teacherCache[$userChar] instanceof User) {
            return self::$teacherCache[$userChar];
        }

        $user = (new User())->setId(\ord($userChar));
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
