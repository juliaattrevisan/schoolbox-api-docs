<?php
/**
 * Mapper for the SubmissionBox model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component;

use PDO;
use Schoolbox;
use Schoolbox\Assessment\ActivityFactory;
use Schoolbox\Assessment\ActivityCollection;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;
use Eportfolio\ShowcaseMapper;
use Schoolbox\User;
use External_Markbook;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;

/**
 * Class SubmissionBoxMapper
 *
 * @package Schoolbox\Assessment\Component
 * @method SubmissionBox[]|GenericCollection findByColumn($columnName, $columnValue, $options = array())
 * @method SubmissionBox[]|GenericCollection createCollection($contents = [])
 * @method SubmissionBox hydrate(array $row, $alias = '', $callback = null)
 * @method Collection|SubmissionBox[] findByColumnList($columnList, $options = [])
 * @method SubmissionBox findOneByColumn($columnName, $columnValue, $order = [], $options = [])
 */
class SubmissionBoxMapper extends Schoolbox\Mapper
{

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
        $this->uses('rubric_group_marks', self::TYPE_BOOLEAN, null, 'markedPerRubricGroup');
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->uses('skip_unsubmitted', self::TYPE_BOOLEAN);
        $this->uses('self_reviewability', self::TYPE_STRING);

        $this->joins('category', Folder\Mapper::class); // TODO deprecated, retained for legacy compatibility only
        $this->joins('owner', Schoolbox\UserMapper::class);
        $this->joins('mark_type', SubmissionBox\MarkTypeMapper::class, 'mark_type_id');
        $this->joins('submission_grade_group_id', SubmissionBox\GradeLevel\Group\Mapper::class, 'id', null, null, 'gradeGroup', 'grade_group');
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessmentModel', 'assessmentModel');

        $this->joinsVia('submission_box_file', Storage\Mapper::class, 'hash', 'storage_id', 'box_id', 'id', null, null, 'attachments');

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


        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'syncToExternalMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'deleteFromMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteFromMarkbook'));


        // SoftDelete/Restore weak entities
        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'softDeleteRubricLevels']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteAssessment']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteSubmissions'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteReturns'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteShowcase'));
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
        /** @var SubmissionBox $model */
        return parent::createModel($idValue)->setAssessmentModel($this->getJoinMapper('assessmentModel')->createModel());
    }


    /**
     * Soft-delete callback to delete any rubric levels on this submission box
     *
     * @param SubmissionBox $dueWork
     * @return bool
     */
    public function softDeleteRubricLevels(SubmissionBox $dueWork)
    {
        Rubric\LevelMapper::getInstance()->deleteByColumn('box_id', $dueWork->getId());
        return true;
    }


    /**
     * Soft delete the box's assessment model
     *
     * @param SubmissionBox $dueWork
     * @return bool
     * @throws Schoolbox\Mapper\DefinitionException
     */
    protected function softDeleteAssessment(SubmissionBox $dueWork): bool
    {
        /** @var AssessmentMapper $assessmentMapper */
        $assessmentMapper = $this->getJoinMapper('assessmentModel');
        return $assessmentMapper->softDelete($dueWork->getAssessmentModel());
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

        // TODO This should *really* be silenceNotifications() and unSilenceNotifications()
        $submissionMapper->setSessionData([]);
        $submissionMapper->delete($submissions);
        $submissionMapper->setSessionData($this->getSessionData());
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
    public function findYears() {
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
        /** @var Storage\Mapper $attach_mapper */
        $attach_mapper   = $this->getJoinMapper('submission_box_file');
        $marktype_mapper = $this->getJoinMapper('mark_type');

        // This is how you get ants!
        $query = "
			SELECT
				{$this->getColumnString('sb')},
				{$marktype_mapper->getColumnString('mt')},
				{$this->getJoinColumnString('category', 'cat')},
				{$attach_mapper->getColumnString('at')},
				{$attach_mapper->getJoinColumnString('allowedFileExtension', 'mime')}
			FROM {$this->getTable()} sb
			LEFT JOIN {$this->getJoinSql('mark_type', 'sb', 'mt')}
			LEFT JOIN {$this->getJoinSql('category',  'sb', 'cat')}
			LEFT JOIN {$this->getJoinSql('submission_box_file', 'sb', 'at')}
			LEFT JOIN {$attach_mapper->getJoinSql('allowedFileExtension', 'at', 'mime', true)}
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
                $submission_box->setAssessmentModel($this->getJoinMapper('assessmentModel')->findById($row['sb.assessment_id'])); // I despise the necessity of this
            }
            // Hydrate curriculum nodes

            if ($row['at.hash']) {
                $storage = $attach_mapper->hydrate($row, 'at');
                $storage->setMimetype($attach_mapper->hydrateJoin($row, 'allowedFileExtension', 'mime'));

                $submission_box->addAttachment($storage);
            }

        }
            
        if (!($submission_box instanceof SubmissionBox)) {
            throw new Schoolbox\Mapper\Exception('Instance of object not found');
        }
        $this->throwIfDeleted($submission_box);
        return $submission_box;
    }


    /**
     * Post-save method for submission boxes - syncs details to SIS markbook
     *
     * @param SubmissionBox $object
     * @return bool
     */
    public function syncToExternalMarkbook(SubmissionBox $object): bool
    {
        // Don't attempt to sync assessment specs to external markbook (they cannot have interactions)
        if ($this->isAssessmentSpecification($object)) {
            return false;
        }

        // This saves the assessment to the remote system
        $externalMarkbook = new External_Markbook();
        $activity         = (new ActivityFactory())->buildFromSubmissionBox($object, 'upcoming');
        if (!$externalMarkbook->hasAssessment($activity)) {
            return $externalMarkbook->createAssessment($activity);
        }
        return $externalMarkbook->updateAssessment($activity);
    }

    /**
     * @param SubmissionBox $box
     * @return bool
     */
    private function isAssessmentSpecification(SubmissionBox $box)
    {
        /** @var Schoolbox\Learning\Assessment\DataModel\Model $assessment */
        $assessment = $this->getJoinMapper('assessmentModel')->findById($box->getAssessmentId());
        return $assessment->getAssessmentContext()->isSpecificationContext();
    }



    /**
     * Removes this box from the markbook.
     *
     * This is used as a post-delete hook.
     *
     * @param SubmissionBox $object
     */
    public function deleteFromMarkbook(SubmissionBox $object)
    {
        $externalMarkbook = new \External_Markbook();
        $activityFactory  = new \Schoolbox\Assessment\ActivityFactory();
        $externalMarkbook->deleteAssessment(
            $activityFactory->buildFromSubmissionBox($object, 'upcoming')
        );
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
    * Gets all submission boxes that are due within the given date span.
    *
    * @param array $category_list  A list of category IDs to filter on.
    * @param int   $from_timestamp A Unix timestamp to search from
    * @param int   $to_timestamp   A Unix timestamp to search until
    *
    * @return ActivityCollection
    */
    public function getDueActivities($category_list, $from_timestamp, $to_timestamp)
    {
        $factory    = new ActivityFactory();
        $collection = new ActivityCollection();
        if (empty($category_list)) {
            return $collection;
        }

        $cat_mapper  = $this->getJoinMapper('category');
        $user_mapper = $this->getJoinMapper('owner');
        $query = "
            SELECT
                {$this->getColumnString('sb')},
                {$cat_mapper->getColumnString('c')},
                {$user_mapper->getColumnString('u')}
            FROM {$this->getTable()} sb
            INNER JOIN {$this->getJoinSql('assessmentModel', 'sb', 'a')}
            LEFT JOIN {$this->getJoinSql('category', 'sb', 'c')}
            LEFT JOIN {$this->getJoinSql('owner',    'sb', 'u')}
            WHERE sb.category IN {$this->getArrayPlaceholders($category_list, 'category')}
            AND sb.date BETWEEN :from_date AND :to_date
            AND a.deleted_at IS NULL
            ORDER BY sb.date ASC
        ";

        //
        // Build parameters
        $from_date = new \Alaress_Date_Format($from_timestamp, 'U');
        $to_date   = new \Alaress_Date_Format($to_timestamp, 'U');
        $params    = $this->getArrayParameters($category_list, 'category');
        $params['from_date'] = $from_date->getDatabaseDate();
        $params['to_date']   = $to_date->getDatabaseDate();
        $statement = $this->database->prepare($query);
        $statement->execute($params);

        //
        // Hydrate as activity list, then add category codes
        foreach ($statement as $row) {
            $box = $this->hydrate($row, 'sb');
            $box->setOwner($user_mapper->hydrate($row, 'u'));
            $box->setCategory($cat_mapper->hydrate($row, 'c'));
            $collection->add($factory->buildFromSubmissionBox($box, 'overdue'));
        }
        /** @var Folder\Code\Mapper $code_mapper */
        $code_mapper = $cat_mapper->getJoinMapper('codes');
        $code_mapper->applyCodesToCategories($collection,
            function ($item) {
                /** @var SubmissionBox $item */
                return $item->getCategory();
            }
        );
        return $collection;
    }

    /**
    * Calculates and returns the statistics of the given submission box
    *
    * @param $submission_box
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
    * @param $submission_box
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
        $categories = array();
        foreach ($catMap->findForUser($user, true) as $cat) {
            $categories[] = $cat->getId();
        }
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
     */
    public function softDelete($object): bool
    {
        $collection = $this->getModelAsCollection($object);
        $notDeleted = $collection->filter(function (SubmissionBox $model) {
            return !$model->getAssessmentModel()->isDeleted();
        });
        if (!\count($notDeleted)) {
            return false;
        }

        // Trigger the pre-trigger
        foreach ($collection as $model) {
            if (!$this->triggerCallbacks(self::CALLBACK_PRESOFTDELETE, $model)) {
                return false;
            }
        }

        // Trigger the post-trigger
        foreach ($collection as $model) {
            $this->triggerCallbacks(self::CALLBACK_POSTSOFTDELETE, $model);
        }

        // Send the notification action
        foreach ($collection as $model) {
            $this->raiseNotification($model, self::METHOD_DELETE);
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
