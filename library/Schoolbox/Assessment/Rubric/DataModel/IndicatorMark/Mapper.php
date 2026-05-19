<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\IndicatorMark;

use PDO;
use PDOException;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Mapper as CapabilityMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as DescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model as DescriptorModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class IndicatorMarkMapper
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model> createCollection($contents = [])
 * @method GenericCollection<Model> findByColumnList($columnList, $options = array())
 * @method GenericCollection<Model> findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('rubric_indicator_mark');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->uses('value', self::TYPE_DECIMAL);
        $this->joins(
            'rubric_capability_mark_id',
            CapabilityMarkMapper::class,
            'id',
            self::TYPE_MODEL,
            null,
            'capabilityMark',
            'capability_mark'
        );

        $this->joins(
            'descriptor_id',
            DescriptorMapper::class,
            'id',
            null,
            null,
            null,
            'descriptor'
        );

        // This is NOT a soft-delete column. It specifically relates to when a teacher alters a rubric's structure,
        // hence marks already given are invalid because they take their value from the rubric descriptor.
        $this->uses('valid', self::TYPE_BOOLEAN);
    }


    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('capability_mark', 'cm')},
                {$this->getJoinColumnString('capability_mark.rubricInstance', 'ri')},
                {$this->getJoinColumnString('descriptor', 'd')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('capability_mark', '', 'cm')}
            LEFT JOIN {$this->getJoinSql('capability_mark.rubricInstance', 'cm', 'ri')}
            LEFT JOIN {$this->getJoinSql('descriptor', '', 'd')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection<Model>
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var CapabilityMarkMapper $capabilityMarkMapper */
        $capabilityMarkMapper = $this->getJoinMapper('capability_mark');

        /** @var \Schoolbox\Assessment\Rubric\Instance\Mapper $rubricInstanceMapper */
        $rubricInstanceMapper = $this->getJoinMapper('capability_mark.rubricInstance');

        $marks = $this->createCollection();
        $descriptors = $this->getJoinMapper('descriptor')->createCollection();

        $descriptorKey = "d.{$this->getJoinMapper('descriptor')->getIdentifierColumn()}";

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* Set up a capability mark instance along with its rubric instance...instance (ugh)
             */
            $capabilityMark = $capabilityMarkMapper->hydrate($row, 'cm');
            $rubricInstance = $rubricInstanceMapper->hydrate($row, 'ri');

            $capabilityMark->setRubricInstance($rubricInstance);

            /* Hydrate descriptor if it wasn't previously discovered
             */
            $descriptorId = $row[$descriptorKey];

            if (!$descriptors->offsetExists($descriptorId)) {
                // Descriptor can be null?
                /* Potentially occurs in old/broken data somewhere, maybe
                 */
                $descriptor = $this->hydrateJoin($row, 'descriptor', 'd');

                if ($descriptor) {
                    $descriptors->setOffset($descriptorId, $descriptor);
                }
            }

            /** @var DescriptorModel $descriptor */
            $descriptor = $descriptors->getOffset($descriptorId);

            /* Set up an indicator mark instance and assemble all its constituents
             * Note that in some undocumented circumstance, $descriptor might actually be null
             */
            $mark = $this->hydrate($row)
                ->setCapabilityMark($capabilityMark)
                ->setDescriptor($descriptor);

            $marks->add($mark);
        }

        return $marks;
    }

    /**
     * This will HARD-DELETE all previously given marks with the feedback.
     *
     * @param int $returnId
     * @return int
     */
    public function deleteFeedbackMarks($returnId): int
    {
        /* TODO replace with an iteration through all grids associated with a feedback
         *  -- or, supply a particular rubric instance identifier to operate on instead.
         *  I can't really tell how we'll end up (re)using this method atm
         */
        $query = "
            DELETE     rim
            FROM       rubric_indicator_mark rim
            INNER JOIN rubric_capability_mark rcm on rim.rubric_capability_mark_id = rcm.id
            WHERE      rcm.return_id = :return_id;
        ";

        $statement = $this->database->prepare($query);

        $statement->execute([
            'return_id' => $returnId,
        ]);

        return $statement->rowCount();
    }


    /**
     * Returns the ids of students whose most recent instructor-given feedback for the specified assessment has rubric marks.
     *
     * Includes draft feedback. This is to align with the feedback that is rendered on the QuickMark (aka DueWork Status) page.
     *
     * @param int $assessmentId
     * @return bool[]
     * @throws PDOException
     */
    public function findMarkedByAssessment(int $assessmentId): array
    {
        /* Sadly, append more learning activity's treatments to this stack
         */
        $dueWorkRubrics = $this->findMarkedByAssessmentDueWork($assessmentId);
        $reportingRubrics = $this->findMarkedByAssessmentReporting($assessmentId);

        return array_merge($dueWorkRubrics, $reportingRubrics);
    }


    /**
     * Find the set of student ids whose most recent feedback also had rubric marks associated with $assessmentId.
     * This includes feedbacks which are drafts too.
     *
     * This method finds the Due Work feedbacks due to the somewhat-polymorphic schema of rubric_capability_mark and the
     * idiosyncratic nature of the activity's feedback table. With the advent of agnostic rubrics any of the learning
     * activities could return a data set.
     *
     * @param int $assessmentId
     * @return array|null
     */
    private function findMarkedByAssessmentDueWork(int $assessmentId): ?array
    {
        $query = "
            SELECT sr.owner AS student_id
            FROM submission_return sr
            INNER JOIN submission_box sb ON sr.box_id = sb.id
            INNER JOIN (
                -- find most recent feedback (including drafts)
                SELECT 
                    sr.owner, 
                    MAX(sr.updated_at) AS max_updated_at
                FROM submission_return sr
                INNER JOIN submission_box sb on sr.box_id = sb.id
                WHERE
                    sb.assessment_id = :assessment_id_1 AND
                    sr.review_route = :review_route_1 AND
                    sr.deleted_at IS NULL
                GROUP BY sr.owner
            ) t1 ON t1.owner = sr.owner AND t1.max_updated_at = sr.updated_at
            INNER JOIN rubric_capability_mark rcm ON sr.id = rcm.feedback_id AND rcm.feedback_table = :feedbackTable
            WHERE
                sb.assessment_id = :assessment_id_2 AND
                sr.review_route = :review_route_2 AND
                sr.deleted_at IS NULL AND
                rcm.valid = 1
            GROUP BY sr.owner
        ";
        $stmt  = $this->database->prepare($query);

        $stmt->execute([
            'assessment_id_1' => $assessmentId,
            'assessment_id_2' => $assessmentId,
            'review_route_1'  => ReturnedWork::REVIEW_ROUTE_INSTRUCTOR,
            'review_route_2'  => ReturnedWork::REVIEW_ROUTE_INSTRUCTOR,
            'feedbackTable' => ReturnedWorkMapper::getInstance()->getTable()
        ]);

        $rowset = $stmt->fetchAll();
        $rubricMarks = [];

        foreach ($rowset as $row) {
            $rubricMarks[$row['student_id']] = true;
        }

        return $rubricMarks;
    }



    /**
     * Find the set of student ids whose most recent feedback also had rubric marks associated with $assessmentId.
     * This includes feedbacks which are drafts too.
     *
     * This method finds the Reporting feedbacks due to the somewhat-polymorphic schema of rubric_capability_mark and the
     * idiosyncratic nature of the activity's feedback table. With the advent of agnostic rubrics any of the learning
     * activities could return a data set.
     *
     * @param int $assessmentId
     * @return array|null
     */
    private function findMarkedByAssessmentReporting(int $assessmentId): ?array
    {
        $query = "
            SELECT rf.learner_id AS student_id
            FROM reporting_feedback rf
            INNER JOIN reporting r ON rf.reporting_id = r.id
            INNER JOIN (
                -- find most recent feedback (including drafts)
                SELECT 
                    rf.learner_id, 
                    MAX(rf.updated_at) AS max_updated_at
                FROM reporting_feedback rf
                INNER JOIN reporting r ON rf.reporting_id = r.id
                WHERE
                    r.assessment_id = :assessment_id_1 AND
                    rf.deleted_at IS NULL
                GROUP BY rf.learner_id
            ) t1 ON t1.learner_id = rf.learner_id AND t1.max_updated_at = rf.updated_at
            INNER JOIN rubric_capability_mark rcm ON rf.id = rcm.feedback_id AND rcm.feedback_table = :feedbackTable
            WHERE
                r.assessment_id = :assessment_id_2 AND
                rf.deleted_at IS NULL AND
                rcm.valid = 1
            GROUP BY rf.learner_id
        ";
        $stmt  = $this->database->prepare($query);

        $stmt->execute([
            'assessment_id_1' => $assessmentId,
            'assessment_id_2' => $assessmentId,
            'feedbackTable' => \Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper::getInstance()->getTable()
        ]);

        $rowset = $stmt->fetchAll();
        $rubricMarks = [];

        foreach ($rowset as $row) {
            $rubricMarks[$row['student_id']] = true;
        }

        return $rubricMarks;
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null): bool
    {
        return true;
    }
}
