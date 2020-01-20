<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Participant\Plan;

use Alaress\Database\Predicate\Builder;
use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Participant;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use PDO;
use Schoolbox\UserMapper;
use UnexpectedValueException;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model createModel($id = null)
 */
class Mapper extends BaseMapper
{
    public const COL_ASSESSMENT_ID = 'assessment_id';

    public function mapping(): void
    {
        $this->hasTable('participant_plan');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(self::COL_ASSESSMENT_ID, Assessment\Mapper::class, 'id', null, null, 'assessment', 'assessment');
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

        $this->registerCallback(self::CALLBACK_PRESOFTDELETE, [$this, 'verifyNoParticipants']);
    }


    /**
     * Pre-soft delete hook to ensure we don't delete a plan that still has participants.
     *
     * This is because:
     * * participants are not soft-deletable, and
     * * a user has 0:1 (never N) participant records per assessment
     *
     * If we allow participant records to be linked to soft-deleted plans, the state is ambiguous and complicated.
     * Much simpler to either either:
     * * hard-delete the participant records first if no interactions, or
     * * link the participant record to a different plan (likely the dynamic one)
     * Both of these are the responsibility of the caller.
     *
     * @param Model $plan
     * @return bool
     * @throws UnexpectedValueException
     */
    protected function verifyNoParticipants(Model $plan): bool
    {
        $participants = Participant\Mapper::getInstance()->findByColumn('participant_plan_id', $plan->getId());
        if ($participants->count()) {
            throw new UnexpectedValueException("Cannot delete plan ({$plan->getId()}), it still has linked {$participants->count()} participant records");
        }
        return true;
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
     * @throws BaseMapper\Exception
     */
    public function findPlansAndParticipants(Builder $pb = null): GenericCollection
    {
        $query = "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('participants', 'p')},
                   {$this->getJoinColumnString('participants.user', 'u')}
            FROM   {$this->getTable()}
            LEFT JOIN  {$this->getJoinSql('participants', '', 'p')}
            LEFT JOIN  {$this->getJoinSql('participants.user', 'p', 'u')}";

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
     * @throws BaseMapper\Exception If unable to locate a specified mapper join
     */
    public function findPlansAndParticipantsForAssessments(array $assessmentIds): GenericCollection
    {
        if (!\count($assessmentIds)) {
            return $this->createCollection();
        }
        $column = "{$this->getTable()}.assessment_id";
        return $this->findPlansAndParticipants($this->getPredicateBuilder()->in($column, $assessmentIds));
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        /**
         * @var Participant\Mapper $participantMapper
         * @var UserMapper $userMapper
         */
        $participantMapper = $this->getJoinMapper('participants');
        $userMapper        = $participantMapper->getJoinMapper('user');
        $plans             = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($plans[$row['id']]) {
                $plan = $plans[$row['id']];
            } else {
                $plan = $this->hydrate($row);
                // Are we hydrating 'participant' join?
                if (array_key_exists('p.id', $row)) {
                    $plan->setParticipants($participantMapper->createCollection());
                }
                $plans[$plan->getId()] = $plan;
            }

            // Did the query join onto participants and users?
            $participant = null;
            $user = null;
            if (\array_key_exists('p.id', $row) && !\is_null($row['p.id'])) {
                $participant = $participantMapper->hydrate($row, 'p');
            }
            // Try hydrating user, only if participant data is set and not excluded
            if ($participant && \array_key_exists('u.id', $row) && !\is_null($row['u.id'])) {
                $user = $userMapper->hydrate($row, 'u');
            }
            if ($participant && $user) {
                // Indexed by user.id to optimise participant traversals
                $participant->setUser($user);
                $plan->getParticipants()[$participant->getUser()->getId()] = $participant;
            }
        }
        return $plans;
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
