<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use External_MarkbookInterface;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork as DueWorkFeedbackModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizFeedbackModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;

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
            $assessment = $this->dataSource->findById($assessmentModel->getAssessmentModel()->getId());
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
            $assessment = $this->dataSource->findById($assessmentModel->getAssessmentModel()->getId());
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
     * Syncs a participation to the markbook.
     *
     * @param object $feedbackModel
     * @param User   $student
     */
    public function syncParticipation($feedbackModel, User $student): void
    {
        $assessment = null;
        if ($feedbackModel instanceof DueWorkFeedbackModel) {
            $assessment = $this->dataSource->findByDueWorkId($feedbackModel->getSubmissionBox()->getId(), $student);
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
}
