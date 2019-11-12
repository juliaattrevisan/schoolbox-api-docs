<?php
/**
 * Mapper for the Submission model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use RangeException;
use Schoolbox;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use PDOStatement;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Participant;

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
        $this->uses('norm_mark', self::TYPE_DECIMAL, null, 'normalizedMark');
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
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteReturnedWorkFiles'));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'syncToExternalMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'persistFiles'));
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'validateNormMark']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'restoreToExternalMarkbook']);
    }

    /**
     * @param ReturnedWork $object
     * @return bool
     */
    public function validateNormMark(ReturnedWork $object): bool
    {
        if ($object->getNormalizedMark() < IMarkScheme::NORMALISED_MARK_MIN || $object->getNormalizedMark() > IMarkScheme::NORMALISED_MARK_MAX) {
            $msg = 'Normalised mark must lie between ' . IMarkScheme::NORMALISED_MARK_MIN .
                ' and ' . IMarkScheme::NORMALISED_MARK_MAX . ' inclusive: ' . $object->getNormalizedMark();
            throw new RangeException($msg);
        }
        return true;
    }

    /**
     * @param ReturnedWork      $object
     * @param ReturnedWork|null $previous
     * @return bool
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     * @throws \InvalidArgumentException If assessment not sufficiently hydrated
     */
    public function canParticipate(ReturnedWork $object, ReturnedWork $previous = null): bool
    {
        if($previous){
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
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException If assessment not sufficiently hydrated
     */
    public function insertParticipation(ReturnedWork $object, ReturnedWork $previous = null): bool
    {
        if($previous){
            // If an update, assume the participation record already exists
            return true;
        }
        $assessment = $this->getSubmissionBoxMapper()->findById($object->getSubmissionBox()->getId())->getAssessmentModel();
        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getOwner(), $assessment);
    }


    /**
     * Soft deletes all Files for the specified Return
     *
     * @param ReturnedWork $return
     */
    public function softDeleteReturnedWorkFiles(ReturnedWork $return)
    {
        /** @var ReturnedWork\SingleFile\Mapper $returnedWorkFileMapper */
        $returnedWorkFileMapper = $this->getJoinMapper('files');
        $files = $returnedWorkFileMapper->findByColumn('submission_return_id', $return->getId(), ['deleted' => true]);
        $returnedWorkFileMapper->delete($files);
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
            $return->setMarker($this->hydrateJoin($row, 'marker','m'));
            $return->setOwner($this->hydrateJoin($row, 'owner','o'));

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
                      ->groupByCallback(function($f) {
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
     * Retrieves the number of returns that have been made on each box in a
     * list.
     *
     * @param array $box_ids
     * @param int $user_id
     *
     * @return array The submission box ID as the key, the count as the value.
     */
    public function findCountsForSubmissionBoxes($box_ids, $user_id)
    {
        // Retrieve count of submission IDs from a list of IDs for a user
        if (empty($box_ids)) {
            return array();
        }
        $query = "
			SELECT sr.box_id, COUNT(*) as submission_count
			FROM {$this->getTable()} sr
			WHERE sr.owner = :user_id
			AND sr.box_id IN {$this->getArrayPlaceholders($box_ids, 'sb')}
			GROUP BY sr.box_id
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
     * Post-save callback for returned work models.
     *
     * Performs the following actions:
     * * Saves the data to the remote data connector
     *
     * @param ReturnedWork $object
     */
    public function syncToExternalMarkbook(ReturnedWork $object): void
    {
        if ($object->isPublished() && !$object->isSelfAssess()) {
            $externalMarkbook = new \External_Markbook();
            $externalMarkbook->setMark($object);
        }
    }


    /**
     * Re-inserts mark in markbook
     *
     * @param ReturnedWork $returnedWork
     * @return bool
     */
    public function restoreToExternalMarkbook(ReturnedWork $returnedWork): bool
    {
        $this->syncToExternalMarkbook($returnedWork);
        return true;
    }


    /**
     * Post-save callback.
     *
     * Saves all attached files in this returned work.
     *
     * @param ReturnedWork $object
     * @param ReturnedWork $previous
     */
    public function persistFiles(ReturnedWork $object, ReturnedWork $previous = null)
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
