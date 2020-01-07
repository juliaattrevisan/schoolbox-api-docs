<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use External_MarkbookInterface;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataModel;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork as DueWorkFeedbackModel;
use Schoolbox\Assessment\Course\Task as TaskDataModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectDataModel;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizFeedbackModel;
use Schoolbox\Learning\Quiz\Model as QuizDataModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use TypeError;

/**
 * Class MarkbookSyncHelper
 */
class MarkbookSyncHelper
{
    /**
     * @var AssessmentDataSource
     */
    private $dataSource;


    /**
     * @var External_MarkbookInterface
     */
    private $externalMarkbook;


    /**
     * Constructor
     *
     * @param AssessmentDataSource       $dataSource
     * @param External_MarkbookInterface $externalMarkbook
     */
    public function __construct(AssessmentDataSource $dataSource, External_MarkbookInterface $externalMarkbook)
    {
        $this->dataSource       = $dataSource;
        $this->externalMarkbook = $externalMarkbook;
    }


    /**
     * Syncs an assessment to the markbook.
     *
     * @param IAssessableDataModel $assessmentModel
     */
    public function syncAssessment(IAssessableDataModel $assessmentModel): void
    {
        try {
            $assessment = $this->findAssessmentFromDataModel($assessmentModel);
        } catch (BaseMapper\Exception\Deleted $e) {
            return;
        }
        if ($this->externalMarkbook->isSyncable($assessment)) {
            $this->externalMarkbook->syncAssessment($assessment);
        }
    }


    /**
     * Deletes a previously-synced assessment from the markbook.
     *
     * @param IAssessableDataModel $assessmentModel
     */
    public function deleteAssessment(IAssessableDataModel $assessmentModel): void
    {
        try {
            $assessment = $this->findAssessmentFromDataModel($assessmentModel);
        } catch (BaseMapper\Exception\Deleted $e) {
            $assessment = $e->getDeletedObject();
        }
        if ($assessment instanceof IAssessable && $this->externalMarkbook->isSyncable($assessment)) {
            $this->externalMarkbook->deleteAssessment($assessment);
        }
    }


//    /**
//     * Syncs a due work participation to the markbook.
//     *
//     * This and `syncQuizParticipation` are separate methods, as there's no
//     * unified interface for feedback data models.
//     *
//     * @param DueWorkFeedbackModel $feedbackModel The due work feedback
//     * @param User                 $student       Sync this student's particpation
//     */
//    public function syncDueWorkParticipation(DueWorkFeedbackModel $feedbackModel, User $student): void
//    {
//        $this->syncParticipation(
//            $this->dataSource->findByDueWorkId($feedbackModel->getSubmissionBox()->getId(), $feedbackModel->getOwner()),
//            $student
//        );
//    }
//
//
//    /**
//     * Syncs a quiz participation to the markbook.
//     *
//     * @param QuizFeedbackModel $feedbackModel The quiz feedback
//     * @param User              $student       Sync this student's particpation
//     */
//    public function syncQuizParticipation(QuizFeedbackModel $feedbackModel, User $student): void
//    {
//        $this->syncParticipation(
//            $this->dataSource->findByQuizId($feedbackModel->getQuiz()->getId(), $feedbackModel->getUser()),
//            $student
//        );
//    }


    /**
     * Syncs participation in an assessment to the markbook.
     *
     * @param IAssessableDataModel $assessmentModel
     * @param User                 $student
     */
    public function syncParticipation(IAssessableDataModel $assessmentModel, User $student): void
    {
        $assessmentId = $assessmentModel->getAssessmentModel()->getId();
        $assessment   = $this->findAssessmentFromDataModel($assessmentModel, $student);

        if ($assessmentId) {
            $assessment = $this->dataSource->findByIdForReview($assessmentId, $student);
        } elseif ($assessmentModel instanceof DueWorkFeedbackModel) {
            $assessment = $this->dataSource->findByDueWorkId($assessmentModel->getSubmissionBox()->getId(), $student);
        } elseif ($feedbackModel instanceof QuizFeedbackModel) {
            $assessment = $this->dataSource->findByQuizId($feedbackModel->getQuiz()->getId(), $student);
        }

        if ($assessment && $this->externalMarkbook->isSyncable($assessment)) {
            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                $feedback = $participant->findMostRecentInstructorGivenFeedback();
                if ($feedback) {
                    $this->externalMarkbook->syncFeedback($feedback);
                }
            }
        }
    }


    /**
     * Helper function for retrieving an assessment domain model, given a data
     * model.
     *
     * @param IAssessableDataModel $assessmentModel
     * @param User|null            $student Find this student's particpation (if provided)
     * @return IAssessable
     * @throws BaseMapper\Exception\Deleted if the assessment has been deleted
     * @throws TypeError                    if `$assessmentModel` isn't recognized
     */
    private function findAssessmentFromDataModel(
        IAssessableDataModel $assessmentModel,
        ?User $student = null
    ): ?IAssessable {
        $assessmentId = $assessmentModel->getAssessmentModel()->getId();
        if ($assessmentId) {
            // Don't fetch participation if no student provided
            return $student
                ? $this->dataSource->findByIdForReview($assessmentId, $student)
                : $this->dataSource->findById($assessmentId);
        }

        // The assessment ID wasn't hydrated:
        // try with the different types of assessment.
        // Again, don't fetch if no student provided
        $field = null;
        if ($assessmentModel instanceof DueWorkDataModel) {
            $field = 'd.id';
//            return $this->dataSource->findByDueWorkId($assessmentModel->getId(), $student);
        }
        if ($assessmentModel instanceof QuizDataModel) {
            $field = 'q.assess_id';
//            return $this->dataSource->findByQuizId($assessmentModel->getId(), $student);
        }
        if ($assessmentModel instanceof TaskDataModel) {
            $field = 't.id';
//            return $this->dataSource->findByTaskId($assessmentModel->getId(), $student);
        }
        if ($assessmentModel instanceof ProjectDataModel) {
            $field = 'p.id';
//            return $this->dataSource->findByTaskId($assessmentModel->getId(), $student);
        }

        if (isset($field)) {
            return $this->dataSource->findOneByColumn(
                $field,
                $assessmentModel->getId(),
                isset($student),
                $student
            );
        }

        return null;
    }
}
