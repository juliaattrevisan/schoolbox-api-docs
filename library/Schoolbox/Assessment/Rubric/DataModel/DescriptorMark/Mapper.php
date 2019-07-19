<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\DescriptorMark;

use PDO;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Mapper as CapabilityMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as DescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Model as DescriptorModel;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Class DescriptorMarkMapper
 *
 * @package Schoolbox\Assessment\Rubric\DataModel
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = array())
 * @method GenericCollection|Model[] findByColumn($columnName, $columnValue, $options = [])
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
        $this->hasTable('rubric_descriptor_mark');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->uses('value', self::TYPE_DECIMAL);
        $this->joins(
            'rubric_group_mark_id',
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
                {$this->getJoinColumnString('group_mark', 'g')},
                {$this->getJoinColumnString('group_mark.submission_return', 'r')},
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
            LEFT JOIN {$this->getJoinSql('group_mark', '', 'g')}
            LEFT JOIN {$this->getJoinSql('group_mark.submission_return', 'g', 'r')}
            LEFT JOIN {$this->getJoinSql('descriptor', '', 'd')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var \Schoolbox\Assessment\Rubric\DataModel\CapabilityMark\Mapper $capabilityMarkMapper */
        $capabilityMarkMapper = $this->getJoinMapper('capability_mark');
        /** @var ReturnedWorkMapper $submissionReturnMapper */
        $submissionReturnMapper = $this->getJoinMapper('capability_mark.submission_return');
        $marks = $this->createCollection();
        $descriptors = $this->getJoinMapper('descriptor')->createCollection();

        $descriptorKey = "d.{$this->getJoinMapper('descriptor')->getIdentifierColumn()}";

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $descriptorId = $r[$descriptorKey];
            if (!$descriptors->offsetExists($descriptorId)) {
                // Descriptor can be null?
                $descriptor = $this->hydrateJoin($r, 'descriptor', 'd');
                if ($descriptor) {
                    $descriptors->setOffset($descriptorId, $descriptor);
                }
            }
            /** @var DescriptorModel $descriptor */
            $descriptor = $descriptors->getOffset($descriptorId);

            $capabilityMark = $capabilityMarkMapper->hydrate($r, 'g');
            $capabilityMark->setSubmissionReturn($submissionReturnMapper->hydrate($r, 'r'));

            $mark = $this->hydrate($r);
            $mark->setCapabilityMark($capabilityMark);
            $mark->setDescriptor($descriptor);
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
        $query = "
            DELETE     rdm
            FROM       rubric_descriptor_mark rdm
            INNER JOIN rubric_group_mark rgm on rdm.rubric_group_mark_id = rgm.id
            WHERE      rgm.return_id = :return_id;
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'return_id' => $returnId,
        ]);

        return $statement->rowCount();
    }

    /**
     * Get a list of students who have some form of rubric marked for an assessment
     *
     * @param int $assessment_id
     * @return array
     */
    public function findMarkedByAssessment($assessment_id)
    {
        $query = "
            SELECT            sr.owner AS student_id, COUNT(rm.id)
            FROM              rubric_descriptor_mark rm
            INNER JOIN        rubric_descriptor rd ON rd.id = rm.descriptor_id
            INNER JOIN        rubric_level rl ON rl.id = rd.level_id
            INNER JOIN        submission_box sb ON sb.id = rl.box_id
            INNER JOIN        assessment a ON a.id = sb.assessment_id
            LEFT JOIN         submission_return sr ON sr.box_id = sb.id
            WHERE             valid = 1 AND a.id = :assessmentId AND sr.deleted_at IS NULL
            GROUP BY          sr.owner
        ";
        $stmt = $this->database->prepare($query);
        $stmt->execute([
            'assessmentId' => $assessment_id
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
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

}
