<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SubmissionFileMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Participant;

/**
 * A mapper for a student's submission to a due work.
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Submission
 * @method Submission findById($id)
 * @method Submission hydrate(array $row, $alias = '', $callback = null)
 */
class SubmissionMapper extends BaseMapper
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


    /**
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('student_submission');
        $this->hasModel(Submission::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('submitted_by', UserMapper::class);
        $this->joins('owner', UserMapper::class);
        $this->joins('box_id', SubmissionBoxMapper::class, 'id', null, null, 'submissionBox');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('submitted_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joinsReverseMulti('student_submission_id', SubmissionFileMapper::class, 'files', 'files');

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'persistFiles']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteSubmissionFiles']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeParticipationIfNecessary']);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreSubmissionFiles']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'insertParticipation']);
    }


    /**
     * @param Submission      $object
     * @param Submission|null $previous
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws Participant\Plan\InvalidState
     */
    public function canParticipate(Submission $object, ?Submission $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the system is still valid
            return true;
        }
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        return $this->interactionChecker->assertCanReceiveInteractions($object->getOwner(), $assessment);
    }


    /**
     * @param Submission      $object
     * @param Submission|null $previous
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws Participant\Plan\InvalidState
     */
    public function insertParticipation(Submission $object, ?Submission $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the participation record already exists
            return true;
        }
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getOwner(), $assessment);
    }


    /**
     * Remove the student's participant row if the student should no longer be a participant in $assessment.
     * @see ReturnedWorkMapper->removeParticipationIfNecessary() (similar function).
     *
     * @param Submission $object
     * @return bool - Return true on success - whether or not a participation was removed.
     *                This is the expected behaviour for callbacks registered with Schoolbox\Mapper.
     */
    public function removeParticipationIfNecessary(Submission $object): bool
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
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString()
    {
        // much join.
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('box_id', 'b')},
                {$this->getJoinColumnString('owner', 'o')},
                {$this->getJoinColumnString('submitted_by', 'sb')}
            FROM {$this->getDatabase()->getQuoter()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('box_id', '', 'b', true)}
            INNER JOIN {$this->getJoinSql('owner', '', 'o', true)}
            INNER JOIN {$this->getJoinSql('submitted_by', '', 'sb', true)}

        ";
    }


    /**
     * Include all files that are part of this submission.
     *
     * @param PDOStatement $statement
     * @return Collection
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Collection|Submission[] $resultSet */
        $resultSet = $this->createCollection();
        $ids       = [];

        // Default hydration
        foreach ($statement as $row) {
            $result = $this->hydrate($row);
            $ids[]  = $result->getId();

            // I should never need to know the student's specifics here,
            // because this object should always be part of a participant's
            // response or feedback.

            // Try to streamline duework+assessment creation too
            /** @var SubmissionBox $dueWork */
            $dueWork = $this->createJoinModel('box_id', $row['b.id']);
            $dueWork->setAssessmentModel($this->createJoinModel('box_id.assessmentModel', $row['b.assessment_id']));

            $resultSet->add(
                $result
                    ->setSubmissionBox($dueWork)
            );

            $result->setSubmittedBy($this->hydrateJoin($row, 'submitted_by', 'sb'));
            $result->setOwner($this->hydrateJoin($row, 'owner', 'o'));
        }

        // Fetch and hydrate files
        /** @var SubmissionFileMapper $submissionFileMapper */
        $submissionFileMapper = $this->getJoinMapper('files');
        $files                = $submissionFileMapper
            ->findByColumn('student_submission_id', $ids)
            ->groupByCallback(function ($f) {
                /** @var SingleFile\Model $f */
                return $f->getSubmission()->getId();
            });
        foreach ($resultSet as $result) {
            $result->setFiles($files[$result->getId()] ?? $submissionFileMapper->createCollection());
        }
        return $resultSet;
    }


    /**
     * Creates a new instance of class with name passed in $this->hasModel().
     *
     * @param int $idValue
     * @return Submission
     * @throws BaseMapper\DefinitionException
     * @throws MapperException
     */
    public function createModel($idValue = null)
    {
        return parent::createModel($idValue)->setSubmissionBox($this->getJoinMapper('box_id')->createModel());
    }


    /**
     * Post-save callback.
     *
     * Persists all files that have been attached to this submission.
     * This handles added, removed, and updated files.
     *
     * @param Submission      $object
     * @param Submission|null $previous (present because this function is used as a post-save callback).
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException|MapperException
     */
    public function persistFiles(Submission $object, ?Submission $previous = null)
    {
        $submittedFiles = $object->getFiles();

        if ($submittedFiles === null) {
            // Join not hydrated, nothing to do.
            return;
        }

        $joinMapper = $this->getJoinMapper('files');
        $existingFiles = $joinMapper->findByColumn('student_submission_id', $object->getId());

        // Note updated files must be handled separately.  Otherwise, the same file could
        // appear in both $addedFiles and $removedFiles below, and problems would result.
        // Example:
        //  * assume one existing file: {id: 1, hash: 'abcd'}.
        //  * assume one submitted file: {id: 1, hash: 'efgh'}.
        //  * if removedFiles = existingFiles->diffBy(submittedFiles, 'hash'),
        //    then the existing file will be identified as removed.
        //  * if addedFiles = submittedFiles->diffBy(existingFiles, 'hash'),
        //    then the submitted file will be identified as added.
        //  * But these are the same file! (they have the same id).
        //  * (A situation like this occurs when this function is called from the 'submission' case in function
        //     DueWork/Response/Controller->postKamiDownloadCallback().  To reproduce, make a submission to a due work
        //     with 'interactive worksheet' enabled.)
        //  ---
        //  Then problems would result as follows:
        //  We would have two objects that refer to the same file, one marked for removal and marked one for addition.
        //  Confusion would ensue with post-save and post-soft-delete callbacks, and we would be left with linked
        //  database rows incorrectly remaining soft-deleted.
        //  ---
        //  To avoid the situation described above, we treat updated files separately.

        // Updated files are all the submitted files whose ids match existing files.
        $updatedFiles = $submittedFiles->intersect(
            $existingFiles,
            function ($a, $b) {
                return $a->getId() === $b->getId();
            }
        );

        // Since we are dealing with the updated files separately, remove them from the existing and submitted lists.
        $remainingExistingFiles = $existingFiles->diffBy($updatedFiles, 'id');
        $remainingSubmittedFiles = $submittedFiles->diffBy($updatedFiles, 'id');

        // Now that updated files have been removed, identifying removed and added files is straightforward.
        $removedFiles = $remainingExistingFiles->diffBy($remainingSubmittedFiles, 'hash');
        $addedFiles = $remainingSubmittedFiles->diffBy($remainingExistingFiles, 'hash');

        // Soft-delete removed files.
        $joinMapper->softDelete($removedFiles);

        // Save added and updated files.
        $addedAndUpdatedFiles = $addedFiles->union($updatedFiles);
        foreach ($addedAndUpdatedFiles as $file) {
            $file->setSubmission($object);
        }
        $joinMapper->save($addedAndUpdatedFiles);

        // Set IDs on files that are not new
        foreach ($submittedFiles as $file) {
            if (!$file->getId()) {
                foreach ($existingFiles as $existingFile) {
                    if ($file->getStorage()->getHash() == $existingFile->getStorage()->getHash()) {
                        $file->setId($existingFile->getId());
                        break;
                    }
                }
            }
        }
    }


    /**
     * Soft deletes all Files for the specified Submission
     *
     * @param Submission $submission
     * @throws BaseMapper\DefinitionException
     */
    public function softDeleteSubmissionFiles(Submission $submission)
    {
        /** @var SingleFile\Mapper $submissionFileMapper */
        $submissionFileMapper = $this->getJoinMapper('files');
        $files = $submissionFileMapper->findByColumn('student_submission_id', $submission->getId(), ['deleted' => true]);
        $submissionFileMapper->delete($files);
    }


    /**
     * Restore all Files for the specified Submission
     *
     * @param Submission $submission
     * @return bool
     */
    public function restoreSubmissionFiles(Submission $submission): bool
    {
        /** @var SingleFile\Mapper $submissionFileMapper */
        $submissionFileMapper = $this->getJoinMapper('files');
        $files                = $submissionFileMapper->findByColumn('student_submission_id', $submission->getId(), ['deleted' => true]);
        $submissionFileMapper->restoreSoftDeleted($files);
        return true;
    }


    /**
     * Retrieves draft submissions for this user and box, ordered by most recent first.
     *
     * @param int $user_id
     * @param int $box_id
     * @param int $limit
     * @return Submission[]|Collection
     * @throws MapperException
     */
    public function getDrafts($user_id, $box_id, $limit = null)
    {
        return $this->findByColumnList([
            'box_id' => $box_id,
            'owner'  => $user_id,
            'draft'  => true
        ], [
            'limit'  => $limit,
            'order'  => [ 'submitted' => 'desc' ],
        ]);
    }


    /**
     * @return BaseMapper|SubmissionBoxMapper
     * @throws BaseMapper\DefinitionException
     */
    private function getSubmissionBoxMapper(): SubmissionBoxMapper
    {
        return $this->getJoinMapper('box_id');
    }
}
