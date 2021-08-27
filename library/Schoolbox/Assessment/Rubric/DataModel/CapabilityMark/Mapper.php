<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityMark;

use PDO;
use PDOStatement;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as CapabilityMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for marks for a rubric capability.
 *
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('rubric_capability_mark');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('human_readable_value', self::TYPE_STRING);
        $this->uses('value', self::TYPE_DECIMAL);
        $this->joins(
            'return_id',
            ReturnedWorkMapper::class,
            'id',
            self::TYPE_MODEL,
            null,
            'submissionReturn',
            'submission_return'
        );
        $this->joins(
            'rubric_capability_id',
            CapabilityMapper::class,
            'id',
            null,
            null,
            'capability',
            'capability'
        );

        // This is NOT a soft-delete column, but effectively has the same
        // behavior as one, as when it's set (by the teacher altering a rubric
        // grid's structure) the marks given for that rubric no longer show.
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
                {$this->getJoinColumnString('submission_return', 'r')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('submission_return', '', 'r')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Model[]|GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var ReturnedWorkMapper $submissionReturnMapper */
        $submissionReturnMapper = $this->getJoinMapper('submission_return');
        $marks                  = $this->createCollection();

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {
            $mark = $this->hydrate($r);
            $mark->setSubmissionReturn($submissionReturnMapper->hydrate($r, 'r'));
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
        $query = "DELETE FROM rubric_capability_mark WHERE return_id = :return_id";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'return_id' => $returnId,
        ]);

        return $statement->rowCount();
    }


    /**
     * This mapper does not raise notifications.
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
