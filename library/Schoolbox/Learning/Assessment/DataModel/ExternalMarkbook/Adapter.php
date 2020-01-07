<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook;

use External_MarkbookInterface;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataModel;
use Schoolbox\Assessment\Course\Task as TaskDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectDataModel;
use Schoolbox\Learning\Quiz\Model as QuizDataModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use TypeError;

/**
 * An adapter class for syncing `IAssessableDataModel` instances to the external
 * markbook
 * (instead of the `IAssessable` objects `External_MarkbookInterface` expects).
 */
class Adapter
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
     * @see External_MarkbookInterface::syncAssessment()
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
        if ($assessment instanceof IAssessable && $this->externalMarkbook->isSyncable($assessment)) {
            $this->externalMarkbook->syncAssessment($assessment);
        }
    }


    /**
     * Deletes a previously-synced assessment from the markbook.
     *
     * @see External_MarkbookInterface::deleteAssessment()
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


    /**
     * Syncs participation in an assessment to the markbook.
     *
     * @see External_MarkbookInterface::syncFeedback()
     *
     * @param IAssessableDataModel $assessmentModel
     * @param User                 $student
     */
    public function syncParticipation(IAssessableDataModel $assessmentModel, User $student): void
    {
        try {
            $assessment = $this->findAssessmentFromDataModel($assessmentModel);
        } catch (BaseMapper\Exception\Deleted $e) {
            return;
        }

        if ($assessment instanceof IAssessable && $this->externalMarkbook->isSyncable($assessment)) {
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
     * Helper function for retrieving an assessment domain model, given a data model.
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
        // Simplest case: assessment ID is hydrated
        $assessmentId = $assessmentModel->getAssessmentModel()->getId();
        if ($assessmentId) {
            // Don't fetch participation if no student provided
            return $student
                ? $this->dataSource->findByIdForReview($assessmentId, $student)
                : $this->dataSource->findById($assessmentId);
        }

        // The assessment ID wasn't hydrated:
        // try with the different types of assessment.
        // Again, don't fetch participation if no student provided
        $field = null;
        if ($assessmentModel instanceof DueWorkDataModel) {
            $field = 'd.id';
        } elseif ($assessmentModel instanceof QuizDataModel) {
            $field = 'q.assess_id';
        } elseif ($assessmentModel instanceof TaskDataModel) {
            $field = 't.id';
        } elseif ($assessmentModel instanceof ProjectDataModel) {
            $field = 'p.id';
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
