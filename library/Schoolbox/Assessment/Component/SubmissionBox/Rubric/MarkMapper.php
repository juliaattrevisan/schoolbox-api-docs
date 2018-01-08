<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use PDO;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\User;

/**
 * Class MarkMapper
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 * @method Mark hydrate(array $row, $alias = '', $callback = null)
 * @method Mark createModel($idValue = null)
 * @method GenericCollection|Mark[] findByColumnList($columnList, $options = array())
 */
class MarkMapper extends Mapper
{

    /**
     * Setup method. Implementing classes to define their SQL map here.
     *
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('rubric_mark');
        $this->hasModel(Mark::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);

        $this->joins('return_id', ReturnedWorkMapper::class, 'id', self::TYPE_MODEL, null, 'submissionReturn', 'submission_return');
        $this->joins('descriptor_id', DescriptorMapper::class, 'id', null, null, null, 'descriptor');

        // This is NOT a soft-delete column. It specifically relates to when a teacher alters a rubric's structure,
        // hence marks already given are invalid because they take their value from the rubric descriptor.
        $this->uses('valid', self::TYPE_BOOLEAN);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT  {$this->getColumnString()},
                    {$this->getJoinColumnString('submission_return', 'r')},
                    {$this->getJoinColumnString('descriptor', 'd')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('submission_return', '', 'r')}
            LEFT JOIN {$this->getJoinSql('descriptor', '', 'd')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Mark[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var ReturnedWorkMapper $submissionReturnMapper */
        $submissionReturnMapper = $this->getJoinMapper('submission_return');
        $marks = $this->createCollection();
        $descriptors = $this->getJoinMapper('descriptor')->createCollection();

        $descriptorKey = "d.{$this->getJoinMapper('descriptor')->getIdentifierColumn()}";

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            $descriptorId = $r[$descriptorKey];
            if(!$descriptors->offsetExists($descriptorId)){
                $descriptors->setOffset($descriptorId, $this->hydrateJoin($r, 'descriptor', 'd'));
            }
            /** @var Descriptor $descriptor */
            $descriptor = $descriptors->getOffset($descriptorId);

            $mark = $this->hydrate($r);
            $mark->setSubmissionReturn($submissionReturnMapper->hydrate($r, 'r'));
            $mark->setDescriptor($descriptor);
            $marks->add($mark);
        }

        return $marks;
    }

    /**
     * This will HARD-DELETE all previously given marks with the feedback.
     *
     * @param int  $returnId
     * @return int
     */
    public function deleteFeedbackMarks($returnId)
    {
        $query = "
            DELETE FROM rubric_mark 
                WHERE return_id = :return_id;
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
            FROM              rubric_mark rm
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
