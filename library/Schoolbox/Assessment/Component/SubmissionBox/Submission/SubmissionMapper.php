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

        $this->registerCallback(self::CALLBACK_POSTSAVE, [ $this, 'persistFiles' ]);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [ $this, 'softDeleteSubmissionFiles']);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
    }


    /**
     * @param Submission      $object
     * @param Submission|null $previous
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws Participant\Plan\InvalidState
     */
    public function canParticipate(Submission $object, Submission $previous = null): bool
    {
        if($previous){
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
    public function insertParticipation(Submission $object, Submission $previous = null): bool
    {
        if($previous){
            // If an update, assume the participation record already exists
            return true;
        }
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getOwner(), $assessment);
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
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
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
     *
     * @param Submission $object
     * @param Submission $previous
     * @throws BaseMapper\DefinitionException
     * @throws \PDOException
     */
    public function persistFiles(Submission $object, Submission $previous = null)
    {
        if ($object->getFiles() === null) {
            // Join not hydrated, nothing to do.
            return;
        }
        $joinMapper = $this->getJoinMapper('files');

        // Delete files that were attached but aren't now
        $existingFiles = $joinMapper->findByColumn('student_submission_id', $object->getId());
        $removedFiles  = $existingFiles->diffBy($object->getFiles(), 'hash');
        $addedFiles    = $object->getFiles()->diffBy($existingFiles, 'hash');
        $joinMapper->hardDelete($removedFiles);

        /* Save only files that are new
         */
        foreach ($addedFiles as $file) {
            $file->setSubmission($object);
        }
        $joinMapper->save($addedFiles);

        // Set IDs on files that are not new
        foreach ($object->getFiles() as $file) {
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
     * Retrieves the number of submissions that have been made on each box in a
     * list.
     *
     * @param array $box_ids
     * @param int   $user_id
     *
     * @return array The submission box ID as the key, the count as the value.
     */
    public function findCountsForSubmissionBoxes($box_ids, $user_id) {
        // Retrieve count of submission IDs from a list of IDs for a user
        if (empty($box_ids)) {
            return array();
        }
        $query = "
            SELECT ss.box_id, COUNT(DISTINCT submitted_at) as submission_count
            FROM {$this->getTable()} ss
            WHERE ss.owner = :user_id
            AND ss.box_id IN {$this->getArrayPlaceholders($box_ids, 'sb')}
            AND ss.submitted_at IS NOT NULL
            GROUP BY ss.box_id
        ";
        $statement = $this->database->prepare($query);
        $params = $this->getArrayParameters($box_ids, 'sb');
        $params['user_id'] = $user_id;
        $statement->execute($params);

        $return = array();
        foreach ($statement as $row) {
            $return[$row['box_id']] = $row['submission_count'];
        }
        return $return;
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
