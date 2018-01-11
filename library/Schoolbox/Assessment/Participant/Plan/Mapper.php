<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Participant;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use PDO;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model createModel($id = null)
 */
class Mapper extends BaseMapper
{

    public function mapping(): void
    {
        $this->hasTable('participant_plan');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('assessment_id', Assessment\Mapper::class, 'id', null, null, 'assessment', 'assessment');
        $this->joinsReverseMulti('participant_plan_id', Participant\Mapper::class, 'participants', 'participants');
        $this->uses('is_dynamic', self::TYPE_BOOLEAN);
        $this->uses('name', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('publish_date', self::TYPE_DATETIME);
        $this->uses('open_date', self::TYPE_DATETIME);
        $this->uses('due_date', self::TYPE_DATETIME);
        $this->uses('close_date', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * By default, just get the plan without any joins hydrated.
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()}
            FROM   {$this->getTable()}
        ";
    }


    /**
     * Find plans that match the predicate, and load their participants if they have any.
     *
     * @param Builder $pb
     * @return Model[]|GenericCollection
     * @throws BaseMapper\DefinitionException
     */
    public function findPlansAndParticipants(Builder $pb = null): GenericCollection
    {
        $query = "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('participants', 'p')}
            FROM   {$this->getTable()}
            LEFT JOIN  {$this->getJoinSql('participants', '', 'p')}";

        $args = [];
        if ($pb && $pb->count()) {
            $query .= ' WHERE ' . implode(' AND ', $pb->getWhere());
            $args  = $pb->getParams();
        }

        return $this->hydrateSelect($this->database->query($query, $args));
    }


    /**
     * Finds plans with participants for the specified assessments
     *
     * @param array $assessmentIds
     * @return Model[]|GenericCollection
     * @throws BaseMapper\DefinitionException If unable to locate a specified mapper join
     */
    public function findPlansAndParticipantsForAssessments(array $assessmentIds): GenericCollection
    {
        if (!\count($assessmentIds)) {
            return $this->createCollection();
        }
        $column = "{$this->getTable()}.assessment_id";
        return $this->findPlansAndParticipants($this->getPredicateBuilder()->equals($column, $assessmentIds));
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var Participant\Mapper $participantMapper */
        $participantMapper = $this->getJoinMapper('participants');
        $plans             = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $plan = $this->hydrate($row);

            // Did the query join onto participants?
            if (array_key_exists('p.id', $row)) {
                $participant = $participantMapper->hydrate($row, 'p');
                if ($participant) {
                    // Indexed by user.id to optimise participant traversals
                    $plan->getParticipants()[$participant->getUser()->getId()] = $participant;
                }
            }
            $plans[$plan->getId()] = $plan;
        }
        return $plans;
    }
}
