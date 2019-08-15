<?php
/**
 * Mapper for a student's completion of a task.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Task;

use PDOStatement;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Participant;

/**
 * @method Completion createModel($idValue = null)
 */
class CompletionMapper extends Mapper {

    /**
     * @var Participant\Plan\InteractionIntegrityChecker
     */
    private $interactionChecker;


    public function __construct()
    {
        parent::__construct();
        $this->interactionChecker = new Participant\Plan\InteractionIntegrityChecker(
            Participant\Plan\Mapper::getInstance(),
            Participant\Mapper::getInstance()
        );
    }

    protected function mapping()
    {
        $this->hasTable('task_completion');
        $this->hasModel(Completion::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('task_id', TaskMapper::class, 'id', null, null, 'task', 'task');
        $this->joins('student_id', UserMapper::class, 'id', null, null, 'student', 'student');
        $this->joins('marking_user_id', UserMapper::class, 'id', null, null, 'markingUser', 'marking_user');
        $this->uses('completion_date', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('completion_status', self::TYPE_INTEGER);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'preSave']);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
    }


    /**
     * @param Completion      $object
     * @param Completion|null $previous
     * @return bool
     * @throws Mapper\DefinitionException
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     */
    public function canParticipate(Completion $object, Completion $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the system is still valid
            return true;
        }
        $assessment = $this->getTaskMapper()->findById($object->getTask()->getId())->getAssessmentModel();
        return $this->interactionChecker->assertCanReceiveInteractions($object->getStudent(), $assessment);
    }


    /**
     * @return Mapper|TaskMapper
     * @throws Mapper\DefinitionException
     */
    private function getTaskMapper()
    {
        return $this->getJoinMapper('task');
    }


    /**
     * @param Completion      $object
     * @param Completion|null $previous
     * @return bool
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function insertParticipation(Completion $object, Completion $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the participation record already exists
            return true;
        }
        $assessment = $this->getTaskMapper()->findById($object->getTask()->getId())->getAssessmentModel();
        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getStudent(), $assessment);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('task', 't')},
                {$this->getJoinColumnString('marking_user', 'm')},
                {$this->getJoinColumnString('student', 's')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            INNER JOIN {$this->getJoinSql('task', '', 't', true)}
            INNER JOIN {$this->getJoinSql('marking_user', '', 'm', true)}
            INNER JOIN {$this->getJoinSql('student', '', 's', true)}

        ";
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     * @throws Mapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Completion $completion */
            $completion = $this->hydrate($row);

            // I should never need to know the student here,
            // because this object should always be part of a participant's
            // response or feedback.

            // Try to streamline task+assessment creation too
            /** @var Task $task */
            $task = $this->createJoinModel('task', $row['t.id']);
            $task->setAssessmentModel($this->createJoinModel('task.assessment_id', $row['t.assessment_id']));

            $completion
                ->setTask($task)
                ->setMarkingUser($this->hydrateJoin($row, 'marking_user', 'm'))
                ->setStudent($this->hydrateJoin($row, 'student', 's'))

            ;
            $c->add($completion);
        }
        return $c;
    }

    /**
     * Unsets this task instance as having been completed by the student.
     *
     * Only tasks set to self assessment may be marked as completed by the student
     *
     * @param int $task_id
     * @param int $student_id
     *
     * @return array
     */
    public function unmarkAsCompleted($task_id, $student_id)
    {
        return $this->mark($task_id, $student_id, Completion::STATUS_INCOMPLETE);
    }

    /**
     * Sets this task instance as having been completed by the student.
     *
     * Only tasks set to self assessment may be marked as completed by the student
     *
     * @param int $task_id
     * @param int $student_id
     *
     * @return array
     */
    public function markAsCompleted($task_id, $student_id)
    {
        return $this->mark($task_id, $student_id, Completion::STATUS_SELF_ASSESSED_COMPLETE);
    }

    /**
     * Internal method used by unmarkAsCompleted and markAsCompleted.
     *
     * @param int $task_id
     * @param int $student_id
     * @param int $status
     *
     * @return array
     */
    protected function mark($task_id, $student_id, $status)
    {
        // What's the most recent completion record?
        // Don't create a new one if it's the same status as the one I want to create.
        try {
            $existing = $this->findOneByColumnList(
                array(
                    'task_id'    => $task_id,
                    'student_id' => $student_id,
                ),
                ['order' => ['completion_date' => 'desc']]
            );
            if ($existing->getCompletionStatus() == $status) {
                return array();
            }
        } catch (Mapper\Exception $e) {
            // no existing completion. Continue
        }

        $model = (new Completion())
            ->setTask((new Task())->setId($task_id))
            ->setStudent((new User)->setId($student_id))
            ->setCompletionDate(new \Alaress_Date_Format(new \DateTime('now')))
            ->setCompletionStatus($status)
        ;
        return $this->save($model);
    }

    /**
     * Retrieves most recent completion for $student_id on $task_instance_id.
     *
     * @param int $task_id
     * @param int $student_id
     *
     * @return Completion
     * @throws Mapper\Exception if no completion was found.
     */
    public function findLatestForTaskAndStudent($task_id, $student_id) {
        $list = $this->findLatestForList(array($task_id), $student_id);
        if ($list->count() === 0) {
            throw new Mapper\Exception\NotFound();
        }
        return $list->first();
    }

    /**
     * Retrieves the most recent completions for each task in
     * $task_id_list.
     *
     * @param int[] $task_id_list
     * @param int   $student_id
     *
     * @return Collection
     */
    public function findLatestForList($task_id_list, $student_id = null) {
        if (empty($task_id_list)) {
            return $this->createCollection();
        }
        $query = "
            SELECT {$this->getColumnString('ti')}
            FROM `{$this->getTable()}` `ti`
            INNER JOIN (
                    SELECT
                        `ti_inner`.`task_id`,
                        `ti_inner`.`student_id`,
                        MAX(`ti_inner`.`completion_date`) as `completion_date`
                    FROM `{$this->getTable()}` `ti_inner`
                    GROUP BY `ti_inner`.`task_id`, `ti_inner`.`student_id`
                ) `x`
                ON  `ti`.`task_id` = `x`.`task_id`
                AND `ti`.`student_id`       = `x`.`student_id`
                AND `ti`.`completion_date`  = `x`.`completion_date`
        ";
        $where_parts = array(
            'ti.task_id IN '.$this->getArrayPlaceholders($task_id_list, 'ti')
        );
        $params = $this->getArrayParameters($task_id_list, 'ti');
        if (!empty($student_id)) {
            $where_parts[]     = 'ti.student_id = :student';
            $params['student'] = $student_id;
        }
        $where_parts[]            = 'ti.completion_status != :exclude_status';
        $params['exclude_status'] = Completion::STATUS_INCOMPLETE;

        $statement = $this->getDatabase()->prepare($query . ' WHERE ' . implode(' AND ', $where_parts));
        $statement->execute($params);

        return $this->hydrateList($statement, 'ti');
    }

    /**
     * Pre-save method for saving an task completion object - saves the
     * marking user if this is a new object.
     *
     * @param Completion $object
     */
    public function preSave(Completion $object) {
        if (!$object->getId() && $_SESSION['user'] instanceof User) {
            $object->setMarkingUser($_SESSION['user']);
        }
    }
}
