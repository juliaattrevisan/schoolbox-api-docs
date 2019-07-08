<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Participant;

use PDO;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Participant\Plan;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model createModel($id = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{

    public function mapping(): void
    {
        $this->hasTable('participant');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('participant_plan_id', Plan\Mapper::class, 'id', null, null, 'plan', 'plan');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('excluded_at', self::TYPE_DATETIME);
    }


    /**
     * Returns the participant model for the specified assessment and user, NULL if not exists
     *
     * @param int $assessmentId
     * @param int $userId
     * @return Model|null
     * @throws BaseMapper\Exception
     */
    public function findForAssessmentAndUser(int $assessmentId, int $userId): ?Model
    {
        return $this->findByColumnList([
            'pp.assessment_id' => $assessmentId,
            'user_id'          => $userId,
        ])->first();
    }


    /**
     * By default, hydrate the participants' plans too.
     *
     * @return string
     * @throws BaseMapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('plan', 'pp')}
            FROM   {$this->getTable()}
            JOIN   {$this->getJoinSql('plan', '', 'pp')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Model[]|GenericCollection
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /** @var Plan\Mapper $planMapper */
        $planMapper = $this->getJoinMapper('plan');

        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $participant = $this->hydrate($row);
            $participant->setPlan($planMapper->hydrate($row, 'pp'));
            $collection[$participant->getId()] = $participant;
        }

        return $collection;
    }

    /**
     * Any notifications related to assessment participations must be deferred
     * until all participations are saved.
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
