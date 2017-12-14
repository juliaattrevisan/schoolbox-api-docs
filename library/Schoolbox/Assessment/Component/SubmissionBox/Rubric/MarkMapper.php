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
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;

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

        $this->joins('return_id', SubmissionReturnMapper::class, 'id', self::TYPE_MODEL, null, 'feedback', 'submission_return');
        $this->joins('student_id', UserMapper::class, 'id', null, null, null, 'user');
        $this->joins('descriptor_id', DescriptorMapper::class, 'id', null, null, null, 'descriptor');

        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('published_at', self::TYPE_DATETIME);

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
                    {$this->getJoinColumnString('user', 'u')},
                    {$this->getJoinColumnString('descriptor', 'd')}
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('submission_return', '', 'r')}
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->getJoinSql('descriptor', '', 'd')}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection|Mark[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $marks = $this->createCollection();
        $feedback = $this->getJoinMapper('submission_return')->createModel();
        $students = $this->getJoinMapper('user')->createCollection();
        $descriptors = $this->getJoinMapper('descriptor')->createCollection();

        $studentKey = "u.{$this->getJoinMapper('user')->getIdentifierColumn()}";
        $descriptorKey = "d.{$this->getJoinMapper('descriptor')->getIdentifierColumn()}";

        while ($r = $statement->fetch(PDO::FETCH_ASSOC)) {

            /** @var IFeedback $feedback */

            $userId = $r[$studentKey];
            if(!$students->offsetExists($userId)){
                $students->setOffset($userId, $this->hydrateJoin($r, 'user', 'u'));
            }
            /** @var User $student */
            $student = $students->getOffset($userId);

            $descriptorId = $r[$descriptorKey];
            if(!$descriptors->offsetExists($descriptorId)){
                $descriptors->setOffset($descriptorId, $this->hydrateJoin($r, 'descriptor', 'd'));
            }
            /** @var Descriptor $descriptor */
            $descriptor = $descriptors->getOffset($descriptorId);

            $mark = $this->hydrate($r);
            $mark->setFeedback($feedback);
            $mark->setStudent($student);
            $mark->setDescriptor($descriptor);
            $marks->add($mark);
        }

        return $marks;
    }

    /**
     * This will HARD-DELETE all draft marks for the specified user and assessable.
     *
     * @param User $user
     * @param IAssessable $dueWork
     * @return int
     */
    public function deleteDraftMarks(User $user, IAssessable $dueWork)
    {
        $query = "
            DELETE rm FROM rubric_mark rm 
                JOIN rubric_descriptor rd ON rm.descriptor_id = rd.id
                JOIN rubric_level   rl ON rl.id = rd.level_id
                JOIN submission_box sb ON sb.id = rl.box_id
                WHERE rm.student_id = :student_id
                AND sb.assessment_id = :assessment_id
                AND rm.published_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'student_id'    => $user->getId(),
            'assessment_id' => $dueWork->getId(),
        ]);

        return $statement->rowCount();
    }

    /**
     * Publishes any draft marks for the specified User and DueWork
     *
     * @param User $user
     * @param IAssessable $dueWork
     * @return int How many marks were published.
     */
    public function publishDraftMarks(User $user, IAssessable $dueWork)
    {
        $now = \Alaress_Date_Format::now();

        $query = "
                UPDATE rubric_mark rm
                JOIN rubric_descriptor rd ON rm.descriptor_id = rd.id
                JOIN rubric_level   rl ON rl.id = rd.level_id
                JOIN submission_box sb ON sb.id = rl.box_id
                SET
                  rm.published_at = :now
                WHERE rm.student_id = :student_id
                AND sb.assessment_id = :assessment_id
                AND rm.published_at IS NULL
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
            'now'           => $now->getDatabase(),
            'student_id'    => $user->getId(),
            'assessment_id' => $dueWork->getId(),
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
            SELECT            rubric_mark.student_id,
                              COUNT(*)
            FROM              rubric_mark
            INNER JOIN        rubric_descriptor ON
                              rubric_descriptor.id = rubric_mark.descriptor_id
            INNER JOIN        rubric_level ON
                              rubric_level.id = rubric_descriptor.level_id
            INNER JOIN        submission_box ON 
                              submission_box.id = rubric_level.box_id
            INNER JOIN        assessment ON 
                              assessment.id = submission_box.assessment_id
            WHERE             valid = 1
                              AND assessment.id = :assessmentId
            GROUP BY          rubric_mark.student_id
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
