<?php

/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use InvalidArgumentException;
use PDOException;
use PDOStatement;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RangeException;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Participant;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook\Adapter as MarkbookAdapter;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * Class ReturnedWorkMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox
 * @method ReturnedWork findById($id)
 * @method ReturnedWork hydrate(array $row, $alias = '', $callback = null)
 * @method ReturnedWork createModel($idValue = null)
 * @method GenericCollection|ReturnedWork[] createCollection($contents = [])
 */
class ReturnedWorkMapper extends Schoolbox\Mapper
{
    /**
     * @var Participant\Plan\InteractionIntegrityChecker
     */
    private $interactionChecker;


    public function __construct()
    {
        parent::__construct();
        $this->interactionChecker = new Participant\Plan\InteractionIntegrityChecker(
            Participant\Plan\Mapper::getInstance(),
            Participant\Mapper::getInstance()
        );
    }


    protected function mapping()
    {
        $this->hasTable('submission_return');
        $this->hasModel(ReturnedWork::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox', 'submission_box');
        $this->joins('owner', UserMapper::class, 'id', null, null, 'owner', 'owner');
        $this->joins('marker', UserMapper::class, 'id', null, null, 'marker', 'marker');
        $this->joins('student_submission_id', StudentSubmissionMapper::class, 'id', null, null, 'submission', 'submission');
        $this->uses('comment', self::TYPE_STRING);
        $this->uses('mark', self::TYPE_STRING, null, 'humanReadableMark');
        $this->uses('instructor_mark', self::TYPE_STRING, null, 'humanReadableInstructorMark');
        $this->uses('norm_mark', self::TYPE_DECIMAL, null, 'normalizedMark');
        $this->uses('resubmission_required', self::TYPE_BOOLEAN);
        $this->uses('published_by', self::TYPE_INTEGER);
        $this->uses('published_at', self::TYPE_DATETIME);
        $this->uses('review_route', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        // Unmapped join onto individual files
        $this->hasJoin(
            'files',
            [
                'mapper'    => ReturnedWork\SingleFile\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'submission_return_id',
            ]
        );

        // SoftDelete/Restore weak entities
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteReturnedWorkFiles']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeParticipationIfNecessary']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'persistFiles']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateNormMark']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'createExtension']);

        // Markbook sync
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'syncToExternalMarkbook']);
//        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'syncToExternalMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'syncToExternalMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreReturnedWorkFiles']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'insertParticipation']);
    }


    /**
     * @param ReturnedWork $object
     * @return bool
     */
    public function validateNormMark(ReturnedWork $object): bool
    {
        if ($object->getNormalizedMark() < IMarkScheme::NORMALISED_MARK_MIN || $object->getNormalizedMark() > IMarkScheme::NORMALISED_MARK_MAX) {
            throw new RangeException(
                // For consistency, 'normalised grade' should be used instead of 'normalised mark' in the UI.
                'Normalised grade must lie between ' . IMarkScheme::NORMALISED_MARK_MIN .
                ' and ' . IMarkScheme::NORMALISED_MARK_MAX . ' inclusive: ' . $object->getNormalizedMark()
            );
        }
        return true;
    }


    /**
     * @param ReturnedWork      $object
     * @param ReturnedWork|null $previous
     * @return bool
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     * @throws InvalidArgumentException If assessment not sufficiently hydrated
     * @throws Schoolbox\Mapper\Exception
     * @throws Schoolbox\Mapper\Exception\Deleted
     */
    public function canParticipate(ReturnedWork $object, ?ReturnedWork $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the system is still valid
            return true;
        }
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        return $this->interactionChecker->assertCanReceiveInteractions($object->getOwner(), $assessment);
    }


    /**
     * @param ReturnedWork      $object
     * @param ReturnedWork|null $previous
     * @return bool
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException If assessment not sufficiently hydrated
     */
    public function insertParticipation(ReturnedWork $object, ?ReturnedWork $previous = null): bool
    {
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        if (!$previous) {
            $this->interactionChecker->insertParticipationOnFirstInteraction($object->getOwner(), $assessment);
        }
        return true;
    }


    /**
     * Remove the student's participant row if the student should no longer be a participant in $assessment.
     * @see SubmissionMapper->removeParticipationIfNecessary() (similar function).
     *
     * @param ReturnedWork $object
     * @return bool - Return true on success - whether or not a participation was removed.
     *                This is the expected behaviour for callbacks registered with Schoolbox\Mapper.
     */
    public function removeParticipationIfNecessary(ReturnedWork $object): bool
    {
        $student = $object->getOwner();
        $submissionBox = $object->getSubmissionBox();
        $submissionBoxMapper = $this->getSubmissionBoxMapper();

        // Get response and feedback counts for this student on this assessment.
        // In this case:
        //  * the response is stored in the student_submission table
        //  * the feedback is stored in the submission_return table.
        $submissionCountByBoxId = $submissionBoxMapper->findSubmissionCountsForSubmissionBoxes(
            [$submissionBox->getId()],
            $student->getId()
        );
        $feedbackCountByBoxId = $submissionBoxMapper->findReturnedWorkCountsForSubmissionBoxes(
            [$submissionBox->getId()],
            $student->getId()
        );
        $submissionCountForThisBox = $submissionCountByBoxId[$submissionBox->getId()] ?? 0;
        $feedbackCountForThisBox = $feedbackCountByBoxId[$submissionBox->getId()] ?? 0;

        // If no (non-deleted) response or feedback exists, the student's participation can be removed.
        if ($submissionCountForThisBox == 0 && $feedbackCountForThisBox == 0) {
            // The SubmissionBox obtained by $object->getSubmissionBox() will not be hydrated (but it will have an id).
            // Get a hydrated SubmissionBox so $submissionBox->getAssessmentModel() will return an object with an id.
            try {
                $submissionBox = $submissionBoxMapper->findById($object->getSubmissionBox()->getId());
            } catch (MapperException) {
                // Can't get a $submissionBox - assume the student should still be a participant.
                return true;
            }

            $this->interactionChecker->removeParticipationFromDynamicPlan(
                $student,
                $submissionBox->getAssessmentModel()
            );
        }

        return true;
    }


    /**
     * If a resubmission is requested and create an extension for the user
     *
     * @param ReturnedWork      $object
     * @param ReturnedWork|null $previous
     * @return bool
     */
    public function createExtension(ReturnedWork $object, ?ReturnedWork $previous = null): bool
    {
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        if ($object->isPublished() && $object->getResubmissionRequired()) {
            $expireDateOffset = (string)$this->getConfigLoader()->get(ConfigMapper::NAME_LMS_RESUBMIT_WINDOW);
            $this->interactionChecker->createPlanForResubmission($object->getOwner(), $assessment, $expireDateOffset);
        }
        return true;
    }


    /**
     * Soft deletes all Files for the specified Return
     *
     * @param ReturnedWork $return
     * @throws Schoolbox\Mapper\Exception
     */
    public function softDeleteReturnedWorkFiles(ReturnedWork $return)
    {
        /** @var ReturnedWork\SingleFile\Mapper $returnedWorkFileMapper */
        $returnedWorkFileMapper = $this->getJoinMapper('files');
        $files                  = $returnedWorkFileMapper->findByColumn('submission_return_id', $return->getId(), ['deleted' => true]);
        $returnedWorkFileMapper->delete($files);
    }


    /**
     * Restore all Files for the specified Return
     *
     * @param ReturnedWork $return
     * @return bool
     */
    public function restoreReturnedWorkFiles(ReturnedWork $return): bool
    {
        /** @var ReturnedWork\SingleFile\Mapper $returnedWorkFileMapper */
        $returnedWorkFileMapper = $this->getJoinMapper('files');
        $files                  = $returnedWorkFileMapper->findByColumn('submission_return_id', $return->getId(), ['deleted' => true]);
        $returnedWorkFileMapper->restoreSoftDeleted($files);
        return true;
    }


    /**
     * Include submission box (and its assessment ID)
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('marker', 'm')},
                {$this->getJoinColumnString('owner', 'o')},
                {$this->getJoinColumnString('submission_box', 'b')},
                {$this->getJoinColumnString('submission', 's')}
        ";
    }


    /**
     * It seems we're going down this road...
     * To prevent cataclysmic backward-incompatibility, in the core, the FROM is absorbed into the SELECT... for now...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM `{$this->table}`
            INNER JOIN {$this->getJoinSql('marker', '', 'm', true)}
            INNER JOIN {$this->getJoinSql('owner', '', 'o', true)}
            INNER JOIN {$this->getJoinSql('submission_box', '', 'b', true)}
            LEFT JOIN {$this->getJoinSql('submission', '', 's', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection|ReturnedWork[]
     * @throws Schoolbox\Mapper\Exception
     */
    protected function hydrateSelect(\PDOStatement $statement)
    {
        $collection = $this->createCollection();
        $ids        = [];

        foreach ($statement as $row) {
            $return = $this->hydrate($row);
            $ids[]  = $return->getId();
            $return->setSubmission($this->hydrateJoin($row, 'submission', 's'));
            $return->setMarker($this->hydrateJoin($row, 'marker', 'm'));
            $return->setOwner($this->hydrateJoin($row, 'owner', 'o'));

            // Try to streamline duework+assessment creation
            /** @var SubmissionBox $dueWork */
            $dueWork = $this->createJoinModel('submission_box', $row['b.id']);
            $dueWork->setAssessmentModel($this->createJoinModel('submission_box.assessmentModel', $row['b.assessment_id']));
            $return->setSubmissionBox($dueWork);

            $collection->add($return);
        }

        // Fetch and hydrate files
        $files = $this->getJoinMapper('files')
                      ->findByColumn('submission_return_id', $ids)
                      ->groupByCallback(function ($f) {
                          /** @var ReturnedWork\SingleFile\Model $f */
                          return $f->getReturn()->getId();
                      });
        foreach ($collection as $result) {
            if (isset($files[$result->getId()])) {
                $result->setFiles($files[$result->getId()]);
            }
        }

        return $collection;
    }


    /**
     * Post-save callback for returned work models.
     *
     * Performs the following actions:
     * * Saves the data to the remote data connector
     *
     * @param ReturnedWork $object
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function syncToExternalMarkbook(ReturnedWork $object): void
    {
        if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            return;
        }
        if ($object->isPublished() && !$object->isSelfAssess()) {
            /** @var MarkbookAdapter $markbookAdapter */
            $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
            $markbookAdapter->syncParticipation($object->getSubmissionBox(), $object->getOwner(), $object->getId());
        }
    }


    /**
     * Post-save callback.
     *
     * Saves all attached files in this returned work.
     *
     * @param ReturnedWork      $object
     * @param ReturnedWork|null $previous
     * @throws Schoolbox\Mapper\Exception
     */
    public function persistFiles(ReturnedWork $object, ?ReturnedWork $previous = null)
    {
        $joinMapper = $this->getJoinMapper('files');

        // Delete files that were attached but aren't now
        $existingFiles = $joinMapper->findByColumn('submission_return_id', $object->getId());
        $removedFiles  = $existingFiles->diffBy($object->getFiles(), 'hash');
        $addedFiles    = $object->getFiles()->diffBy($existingFiles, 'hash');
        $joinMapper->hardDelete($removedFiles);

        /* Save only files that are new
         */
        foreach ($addedFiles as $file) {
            $file->setReturn($object);
        }
        $joinMapper->save($addedFiles);
    }


    /**
     * @return Schoolbox\Mapper|SubmissionBoxMapper
     * @throws Schoolbox\Mapper\DefinitionException
     */
    private function getSubmissionBoxMapper(): SubmissionBoxMapper
    {
        return $this->getJoinMapper('submission_box');
    }
}
