<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Rubric\DataModel\CapabilityMark;

use PDO;
use PDOStatement;
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
        $this->uses('feedback_id', self::TYPE_INTEGER);
        $this->uses('feedback_table', self::TYPE_STRING);

        $this->joins(
            'rubric_capability_id',
            CapabilityMapper::class,
            'id',
            null,
            null,
            'capability',
            'capability'
        );

        $this->joins(
            'rubric_instance_id',
            \Schoolbox\Assessment\Rubric\Instance\Mapper::class,
            'id',
            null,
            null,
            'rubricInstance',
            'rubricInstance'
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
                {$this->getJoinColumnString('rubricInstance', 'ri')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            INNER JOIN {$this->getJoinSql('rubricInstance', '', 'ri', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Model[]|GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $marks = $this->createCollection();

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /* As far as I can tell, the submission_return instance (and any other feedback instances) aren't actually
             * meaningful beyond the loading/saving of its identifier; they probably weren't even needed at all. Now
             * that we have a somewhat-polymorphic (feedback_id, feedback_table) tuple idea we probably need to do that
             * in business logic anyway or risk a massive mega-left-join onto every possible feedback table.
             *
             * Maybe you'll need to do that when we expand usages beyond due works
             */
            $mark = $this->hydrate($row);

            /** @var \Schoolbox\Assessment\Rubric\Instance\Model $rubricInstance */
            $rubricInstance = $this->hydrateJoin($row, 'rubricInstance', 'ri');

            $mark->setRubricInstance($rubricInstance);

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
