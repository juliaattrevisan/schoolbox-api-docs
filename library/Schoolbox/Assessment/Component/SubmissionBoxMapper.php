<?php

/**
 * Mapper for the SubmissionBox model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component;

use Alaress\Database\Predicate\Builder;
use Eportfolio\ShowcaseMapper;
use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Assessment\Rubric\DataModel\Level\Mapper as RubricLevelMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook\Adapter as MarkbookAdapter;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Class SubmissionBoxMapper
 *
 * @package Schoolbox\Assessment\Component
 * @method SubmissionBox[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 * @method SubmissionBox[]|GenericCollection createCollection($contents = [])
 * @method SubmissionBox hydrate(array $row, $alias = '', $callback = null)
 * @method Collection|SubmissionBox[] findByColumnList($columnList, $options = [])
 * @method GenericCollection|SubmissionBox[] findByPredicates(Builder $pb, array $options = [])
 * @method SubmissionBox findOneByColumn($columnName, $columnValue, array $options = [])
 * @method SubmissionBox findOneByColumnList($columnList, array $options = [])
 */
class SubmissionBoxMapper extends BaseMapper
{
    public const COL_EXTERNAL_ID = 'external_id';

    /**
     * @throws Schoolbox\Mapper\Exception
     */
    protected function mapping()
    {
        $this->hasTable('submission_box');

        $this->hasModel(SubmissionBox::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING); // TODO deprecated, lives on `assessment` table now
        $this->uses('description', self::TYPE_STRING); // TODO deprecated, lives on `assessment` table now
        $this->uses('publish_date', self::TYPE_DATETIME); // TODO deprecated, lives on `assessment` table now
        $this->uses('open_date', self::TYPE_DATETIME); // TODO deprecated, lives on `assessment` table now
        $this->uses('date', self::TYPE_DATETIME, null, 'dueDate'); // TODO deprecated, lives on `assessment` table now
        $this->uses('late_date', self::TYPE_DATETIME); // TODO deprecated, lives on `assessment` table now
        $this->uses('accept_submission', self::TYPE_BOOLEAN, null, 'acceptOnline');
        $this->uses('multi_submission', self::TYPE_BOOLEAN, null, 'acceptMultiple');
        $this->uses('weight', self::TYPE_INTEGER); // TODO deprecated, lives on `assessment` table now
        $this->uses('max_mark', self::TYPE_DECIMAL);
        $this->uses('use_rubric', self::TYPE_BOOLEAN);
        $this->joins('rubric_capability_mark_type_id', SubmissionBox\MarkTypeMapper::class, 'mark_type_id', null, null, 'capabilityMarkType');
        $this->joins('rubric_capability_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'capabilityGradeGroup');
        $this->uses('rubric_mark_range', self::TYPE_BOOLEAN, null, 'markedUsingRange');
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->uses('skip_unsubmitted', self::TYPE_BOOLEAN);
        $this->uses('self_reviewability', self::TYPE_STRING);
        $this->uses('similarity_check_enabled', self::TYPE_BOOLEAN);
        $this->uses(self::COL_EXTERNAL_ID, self::TYPE_STRING);

        $this->joins('category', Folder\Mapper::class); // TODO deprecated, retained for legacy compatibility only
        $this->joins('owner', Schoolbox\UserMapper::class);
        $this->joins('mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id');
        $this->joins('submission_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'gradeGroup', 'grade_group');
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessmentModel', 'assessmentModel');

        // Unmapped join onto individual files
        $this->hasJoin(
            'files',
            [
                'multiple'  => true,
                'mapper'    => SubmissionBox\SingleFile\Mapper::class,
                'this_key'  => 'id',
                'other_key' => 'box_id',
            ]
        );

        // joins that have no column definition
        $this->hasJoin('submission', array(
            'multiple'  => true,
            'mapper'    => SubmissionBox\Submission\SubmissionMapper::class,
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));
        $this->hasJoin('return', array(
            'multiple'  => true,
            'mapper'    => SubmissionBox\ReturnedWorkMapper::class,
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));
        $this->hasJoin('showcase', array(
            'multiple'  => true,
            'mapper'    => ShowcaseMapper::class,
            'this_key'  => 'id',
            'other_key' => 'box_id',
        ));

        // Markbook sync
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'syncToExternalMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteFromMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteFromMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'syncToExternalMarkbook']);

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteRubricLevels']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteSubmissions']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteReturns']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteShowcase']);
    }


    /**
     * @inheritdoc
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('assessmentModel', 'am')},
                   {$this->getJoinColumnString('owner', 'o')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM       {$this->getTable()}
            INNER JOIN {$this->getJoinSql('assessmentModel', '', 'am', true)}
            INNER JOIN {$this->getJoinSql('owner', '', 'o', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws Schoolbox\Mapper\DefinitionException
     * @throws Schoolbox\Mapper\Exception
     */
    public function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $box = $this->hydrate($row)
                        ->setAssessmentModel($this->hydrateJoin($row, 'assessmentModel', 'am'))
                        ->setOwner($this->hydrateJoin($row, 'owner', 'o'))
            ;
            $collection[$box->getId()] = $box;
        }

        return $collection;
    }


    /**
     * Creates a new instance of class with name passed in $this->hasModel().
     *
     * @param int $idValue
     * @throws \Schoolbox\Mapper\Exception
     * @return object|SubmissionBox
     */
    public function createModel($idValue = null)
    {
        return parent::createModel($idValue)->setAssessmentModel($this->getJoinMapper('assessmentModel')->createModel());
    }


    /**
     * Persist due work files.
     *
     * (This could be used as a callback, but I don't know all the points at
     *  which a due work is saved, so for safety's sake it must be called
     *  manually.)
     *
     * @param SubmissionBox $dueWork
     */
    public function saveFiles(SubmissionBox $dueWork): void
    {
        $this->cascadeJoin('files', ['box_id' => $dueWork->getId()], $dueWork->getAttachments());
    }


    /**
     * Soft-delete callback to delete any rubric levels on this submission box
     *
     * @param SubmissionBox $dueWork
     * @return bool
     */
    public function softDeleteRubricLevels(SubmissionBox $dueWork)
    {
        RubricLevelMapper::getInstance()->deleteByColumn('box_id', $dueWork->getId());
        return true;
    }


    /**
     * Soft deletes all Student Submissions for the specified DueWork
     *
     * @param SubmissionBox $dueWork
     */
    public function softDeleteSubmissions(SubmissionBox $dueWork)
    {
        $submissionMapper = $this->getJoinMapper('submission');
        $submissions      = $submissionMapper->findByColumn('box_id', $dueWork->getId(), ['deleted' => true]);

        $submissionMapper->suppressNotification();
        $submissionMapper->delete($submissions);
        $submissionMapper->restoreNotification();
    }

    /**
     * Soft deletes all Teacher Returns for the specified DueWork
     *
     * @param SubmissionBox $dueWork
     */
    public function softDeleteReturns(SubmissionBox $dueWork)
    {
        $returnMapper = $this->getJoinMapper('return');
        $returns = $returnMapper->findByColumn('box_id', $dueWork->getId(), ['deleted' => true]);
        $returnMapper->delete($returns);
    }


    /**
     * Soft deletes all students showcase work for the specified DueWork
     *
     * @param SubmissionBox $dueWork
     */
    public function softDeleteShowcase(SubmissionBox $dueWork)
    {
        $showcaseMapper = $this->getJoinMapper('showcase');
        $showcaseMapper->deleteByColumn('submission_box_id', $dueWork->getId());
    }


    /**
     * Find all the years containing a submission box
     *
     * @return Collection
     */
    public function findYears()
    {
        $query = "
            SELECT DISTINCT YEAR(date) AS year
            FROM {$this->getTable()} sb
            ORDER BY date DESC
        ";

        $statement = $this->database->prepare($query);
        $statement->execute();

        $collection = new Collection();

        foreach ($statement as $row) {
            $collection->add($row['year']);
        }

        return $collection;
    }

    /**
    * @throws Schoolbox\Mapper\Exception if submission box is not found
    * @param  int  $id
    * @return SubmissionBox
    */
    public function findById($id)
    {
        $marktype_mapper = $this->getJoinMapper('mark_type');

        // This is how you get ants!
        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$marktype_mapper->getColumnString('mt')},
				{$this->getJoinColumnString('category', 'cat')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			LEFT JOIN {$this->getJoinSql('category',  'sb', 'cat')}
			WHERE sb.id=?
		";
        $statement = $this->database->prepare($query);
        $statement->execute(array($id));

        $submission_box = false;

        foreach ($statement as $row) {
            if (!($submission_box instanceof SubmissionBox)) {
                $submission_box = $this->hydrate($row, 'sb');
                $submission_box->setMarkType($marktype_mapper->hydrate($row, 'mt'));
                $submission_box->setCategory($this->hydrateJoin($row, 'category', 'cat'));
                try {
                    $submission_box->setAssessmentModel($this->getJoinMapper('assessmentModel')->findById($row['sb.assessment_id'])); // I despise the necessity of this
                } catch (Schoolbox\Mapper\Exception\Deleted $e) {
                    $submission_box->setAssessmentModel($e->getDeletedObject()); // I also am not thrilled :)
                }
                // Fetch and hydrate files
                $submission_box->setAttachments(
                    $this->getJoinMapper('files')->findByColumn('box_id', $id)
                );
            }
        }

        if (!($submission_box instanceof SubmissionBox)) {
            throw new Schoolbox\Mapper\Exception\NotFound();
        }
        $this->throwIfDeleted($submission_box);
        return $submission_box;
    }


    /**
     * Post-save method for submission boxes - syncs details to SIS markbook
     *
     * @param SubmissionBox $object
     */
    public function syncToExternalMarkbook(SubmissionBox $object): void
    {
        if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            return;
        }
        /** @var MarkbookAdapter $markbookAdapter */
        $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
        $markbookAdapter->syncAssessment($object);
    }


    /**
     * Removes this box from the markbook.
     *
     * This is used as a post-delete hook.
     *
     * @param SubmissionBox $object
     */
    public function deleteFromMarkbook(SubmissionBox $object): void
    {
        if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            return;
        }
        /** @var MarkbookAdapter $markbookAdapter */
        $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
        $markbookAdapter->deleteAssessment($object);
    }


    /**
    * Conversion of SubmissionBoxHandler->getAllYearsByUser().
    *
    * @param int $user_id The user ID to retrieve for.
    *
    * @return array
    */
    public function getAllYearsByUser($user_id)
    {
        $query = "
			SELECT DISTINCT DATE_FORMAT(submitted, '%Y') AS submitted_year
			FROM student_submission
			WHERE owner = ?
			ORDER BY submitted DESC";

        $statement = $this->database->prepare($query);
        $statement->execute(array($user_id));

        $years = array();
        foreach ($statement as $row) {
            $years[] = $row['submitted_year'];
        }
        return $years;
    }



    /**
    * Calculates and returns the statistics of the given submission box
    *
    * @param int|SubmissionBox $submission_box
    * @return array
    */
    public function getStatistics($submission_box)
    {
        if ($submission_box instanceof SubmissionBox) {
            $submission_box_id = $submission_box->getId();
        } else {
            $submission_box_id = $submission_box;
        }

        // Fetch only the most recent return for each student.
        $sql = "
			SELECT
				AVG(t0.norm_mark) AS `mean`,
				STDDEV(t0.norm_mark) AS `stdev`,
				MIN(t0.norm_mark) AS `min`,
				MAX(t0.norm_mark) AS `max`
			FROM (
				SELECT * FROM (
					SELECT *
					FROM submission_return sr
					WHERE sr.box_id = :box_id
					ORDER BY sr.returned DESC
				) t1
				GROUP BY t1.owner
				HAVING t1.norm_mark IS NOT NULL
			) t0;
		";

        $stmt = $this->database->prepare($sql);
        $stmt->execute(array('box_id' => $submission_box_id));

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
    * Returns the progress of the given submission box, ie. the number of submissions made and returns given
    *
    * @param int|SubmissionBox $submission_box
    * @return array
    */
    public function getProgress($submission_box)
    {
        if ($submission_box instanceof SubmissionBox) {
            $submission_box_id = $submission_box->getId();
        } else {
            // we need both elements
            $submission_box_id = $submission_box;
            $submission_box = $this->findById($submission_box_id);
        }

        $stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(owner))
			FROM student_submission
			WHERE box_id = :box_id
		");

        $stmt->execute(array('box_id' => $submission_box_id));
        $progress['submission_count'] = $stmt->fetchColumn(0);

        $progress['accepts_submissions'] = $submission_box->getAcceptOnline();

        $stmt = $this->database->prepare("
			SELECT COUNT(DISTINCT(owner))
			FROM submission_return
			WHERE box_id = :box_id
		");

        $stmt->execute(array('box_id' => $submission_box_id));
        $progress['return_count'] = $stmt->fetchColumn(0);

        return $progress;
    }


    /**
     * Port of SubmissionStudent handler's securityCheck() method.
     *
     * @throws Schoolbox\Resource\Exception
     *
     * @param SubmissionBox $box
     * @param User          $user
     *
     * @return bool
     */
    public function checkUploadability(SubmissionBox $box, User $user)
    {
        // Are online submissions accepted?
        if (!$box->getAcceptOnline()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept online submission.',
                Schoolbox\Resource\Exception::CODE_NO_ONLINE_SUBMISSION
            );
        }

        // Does a (non-draft) submission already exist for this student?
        $boxId  = $box->getId();
        $userId = $user->getId();
        $stmt   = $this->database->prepare(
            "SELECT id FROM `student_submission` WHERE box_id=? AND owner=?"
        );
        $stmt->execute(array($boxId, $userId));
        $submitted = ($stmt->rowCount() > 0);
        if ($submitted && !$box->getAcceptMultiple()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept multiple submissions.',
                Schoolbox\Resource\Exception::CODE_NO_MULTIPLE_SUBMISSION
            );
        }

        // Is the submission late (after overdue time)?
        $now = new \DateTime('now');
        if ($box->getLateDate()->getUnixTimeStamp() < $now->getTimestamp()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box no longer accepts submissions.',
                Schoolbox\Resource\Exception::CODE_SUBMISSION_CLOSED
            );
        }

        // Is the student involved in this group?
        // (Need much better checking than this, this abuses session variables)

        /** @var Schoolbox\Resource\Folder\Mapper $catMap */
        $catMap = $this->getJoinMapper('category');
        $catMap->setExternalUser(new \External_User());
        $categories = $catMap->findFolderIdsForUser($user, true);

        if (!in_array($box->getCategory()->getId(), $categories)) {
            // 403 forbidden error
            throw new Schoolbox\Resource\Exception('Access denied', Schoolbox\Resource\Exception::CODE_ACCESS_DENIED);
        }

        // Is the submission early (before open time)?
        if ($box->getOpenDate()->getUnixTimeStamp() > $now->getTimestamp()) {
            throw new Schoolbox\Resource\Exception(
                'This submission box does not accept submissions yet.',
                Schoolbox\Resource\Exception::CODE_SUBMISSION_NOT_YET_OPEN
            );
        }
        return true;
    }


    /**
     * Special case for submission_box which reflects the soft-deleted status of it's assessment
     *
     * @inheritdoc
     */
    public function isSoftDeletable(): bool
    {
        // Even though this table lacks a soft-delete column, it takes is soft-delete status from the assessment table
        return $this->getJoinMapper('assessmentModel')->isSoftDeletable();
    }


    /**
     * Special case for SubmissionBox which takes its deletion status from the assessment table.
     *
     * This table lacks a soft-delete column, but is soft-deletable by virtue of it being composed
     * of a soft-deletable assessment
     *
     * @param object|Collection|SubmissionBox $object
     * @return bool
     * @throws \UnexpectedValueException If a callback fails and we need to halt
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function softDelete($object): bool
    {
        $collection = $this->getModelAsCollection($object);

        // Trigger the pre-trigger
        foreach ($collection as $model) {
            if (!$this->triggerCallbacks(self::CALLBACK_PRESOFTDELETE, $model)) {
                return false;
            }
        }

        // This is neither a PRE or POST action - it is the action.
        /** @var AssessmentMapper $assessmentMapper */
        $assessmentMapper = $this->getJoinMapper('assessmentModel');
        /** @var SubmissionBox $model */
        foreach ($collection as $model) {
            if ($model->getAssessmentModel()->isDeleted()) {
                continue;
            }
            $assessmentMapper->softDelete($model->getAssessmentModel());
        }

        // Trigger the post-trigger
        foreach ($collection as $model) {
            $this->triggerCallbacks(self::CALLBACK_POSTSOFTDELETE, $model);
        }

        return true;
    }


    /**
     * Special case restore for submission boxes:
     * they take their deletion status from the assessment table.
     *
     * This table lacks a soft-delete column, but is soft-deletable by virtue of
     * it being composed of a soft-deletable assessment.
     *
     * @param object|Collection|SubmissionBox $object
     * @return bool
     * @throws \UnexpectedValueException If a callback fails and we need to halt
     * @throws Schoolbox\Mapper\DefinitionException
     */
    public function restoreSoftDeleted($object)
    {
        $collection = $this->getModelAsCollection($object);

        // Trigger the pre-trigger
        foreach ($collection as $model) {
            if (!$this->triggerCallbacks(self::CALLBACK_PRERESTORE, $model)) {
                return false;
            }
        }

        // This is neither a PRE or POST action - it is the action.
        /** @var AssessmentMapper $assessmentMapper */
        $assessmentMapper = $this->getJoinMapper('assessmentModel');
        /** @var SubmissionBox $model */
        foreach ($collection as $model) {
            if (!$model->getAssessmentModel()->isDeleted()) {
                continue;
            }
            $assessmentMapper->restoreSoftDeleted($model->getAssessmentModel());
        }

        // Trigger the post-trigger
        foreach ($collection as $model) {
            $this->triggerCallbacks(self::CALLBACK_POSTRESTORE, $model);
        }

        return true;
    }

    /**
     * Any notifications related to assessments must be deferred until all
     * its plans and constituent participation records are saved.
     *
     * @param object      $object
     * @param string      $verb
     * @param object|null $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }
}
