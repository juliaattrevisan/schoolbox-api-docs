<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

use External_MarkbookInterface;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
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
     * @param IAssessableDataModel $assessmentDataModel
     */
    public function syncAssessment(IAssessableDataModel $assessmentDataModel): void
    {
        try {
            $assessment = $this->dataSource->findById($assessmentDataModel->getAssessmentModel()->getId());
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
     * @param IAssessableDataModel $assessmentDataModel
     */
    public function deleteAssessment(IAssessableDataModel $assessmentDataModel): void
    {
        try {
            $assessment = $this->dataSource->findById($assessmentDataModel->getAssessmentModel()->getId());
        } catch (BaseMapper\Exception\Deleted $e) {
            $assessment = $e->getDeletedObject();
        }
        if ($assessment instanceof IAssessable && $this->externalMarkbook->isSyncable($assessment)) {
            $this->externalMarkbook->deleteAssessment($assessment);
        }
    }


    /**
     * Syncs an assessment's participation to the markbook.
     *
     * @param IAssessable $assessment
     * @param User        $student    Sync this student's particpation
     */
    public function syncParticipation(IAssessable $assessment, User $student): void
    {
        if ($this->externalMarkbook->isSyncable($assessment)) {
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
