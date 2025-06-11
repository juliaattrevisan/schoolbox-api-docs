<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Assessment\Score;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\LTI\Assessment\Mapper as LTIAssessmentMapper;
use Schoolbox\LTI\Assessment\Model as LTIAssessment;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Participant;

/**
 * LTI assessment score mapper.
 *
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @var Participant\Plan\InteractionIntegrityChecker
     */
    private Participant\Plan\InteractionIntegrityChecker $interactionChecker;

    /**
     * @inheritDoc
     */
    public function __construct()
    {
        parent::__construct();
        $this->interactionChecker = new Participant\Plan\InteractionIntegrityChecker(
            Participant\Plan\Mapper::getInstance(),
            Participant\Mapper::getInstance()
        );
    }

    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('lti_assessment_score');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
        $this->joins(
            'lti_assessment_id',
            LTIAssessmentMapper::class,
            'id',
            null,
            null,
            null,
            'lti_assessment',
        );
        $this->joins(
            'student_id',
            UserMapper::class,
            'id',
            null,
            null,
            null,
            'student'
        );
        $this->joins(
            'marking_user_id',
            UserMapper::class,
            'id',
            null,
            null,
            null,
            'marking_user'
        );
        $this->uses('score_given', self::TYPE_DECIMAL);
        $this->uses('score_maximum', self::TYPE_DECIMAL);
        $this->uses('comment', self::TYPE_STRING);
        $this->uses('submitted_at', self::TYPE_DATETIME);
        $this->uses(
            'activity_progress',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            Model::ACTIVITY_PROGRESS_VALID_VALUES,
        );
        $this->uses(
            'grading_progress',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            Model::GRADING_PROGRESS_VALID_VALUES,
        );
        $this->uses('is_response', self::TYPE_BOOLEAN);
        $this->uses('is_feedback', self::TYPE_BOOLEAN);

        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'removeParticipationIfNecessary']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'insertParticipation']);
    }

    /**
     * Include the LTI assessment itself in scores: required so that queries may
     * work against the LTI assessment's assessment ID.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('lti_assessment', 'lti_assessment')},
                {$this->getJoinColumnString('student', 'student')},
                {$this->getJoinColumnString('marking_user', 'marking_user')}
        ";
    }

    /**
     * Include the LTI assessment itself in scores: required so that queries may
     * work against the LTI assessment's assessment ID.
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('lti_assessment', '', 'lti_assessment', true)}
            INNER JOIN  {$this->getJoinSql('student', '', 'student', true)}
            LEFT  JOIN  {$this->getJoinSql('marking_user', '', 'marking_user', true)}
        ";
    }

    /**
     * Include the LTI assessment itself in scores: required so that queries may
     * work against the LTI assessment's assessment ID.
     *
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();
        foreach ($statement as $row) {
            /** @var Model $model */
            $model         = $this->hydrate($row);
            $ltiAssessment = $this->hydrateJoin($row, 'lti_assessment', 'lti_assessment');
            if ($ltiAssessment instanceof LTIAssessment) {
                $model = $model->setLtiAssessment($ltiAssessment);
            }
            $student = $this->hydrateJoin($row, 'student', 'student');
            if ($student instanceof User) {
                $model = $model->setStudent($student);
            }
            $markingUser = $this->hydrateJoin($row, 'marking_user', 'marking_user');
            if ($markingUser instanceof User) {
                $model = $model->setMarkingUser($markingUser);
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * @param Model      $object
     * @param Model|null $previous
     * @return bool
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     */
    public function insertParticipation(Model $object, ?Model $previous = null): bool
    {
        $assessment = $this->getJoinMapper('lti_assessment')->findById($object->getLtiAssessment()->getId())->getAssessmentModel();
        if (!$previous) {
            $this->interactionChecker->insertParticipationOnFirstInteraction($object->getStudent(), $assessment);
        }
        return true;
    }

    /**
     * Remove the student's participant row if $student should no longer be a participant in $assessment.
     *
     * @param Model $object
     * @return bool - Return true on success - whether or not a participation was removed.
     *                This is the expected behaviour for callbacks registered with Schoolbox\Mapper.
     */
    public function removeParticipationIfNecessary(Model $object): bool
    {
        $student = $object->getStudent();
        $ltiAssessment = $object->getLtiAssessment();

        // If no score exists for the student, the student's participation can be removed.
        // Note the response and feedback for LTI assessments are both stored in the lti_assessment_score table.
        if ($this->getScoreCountForStudent($ltiAssessment->getId(), $student->getId()) === 0) {
            $this->interactionChecker->removeParticipationFromDynamicPlan(
                $student,
                $ltiAssessment->getAssessmentModel()
            );
        }

        return true;
    }

    /**
     * Gets number of attempts this student has made on this assessment. Doesn't
     * include deleted assessments.
     *
     * Note that this function expect `$studentId` to be int, but sometimes it might be string or other
     * so make sure that you convert it to int before inputting
     *
     * @param int $ltiAssessmentId LTI assessment to check.
     * @param int $studentId The student to check.
     *
     * @return int
     */
    public function getScoreCountForStudent(int $ltiAssessmentId, int $studentId): int
    {
        $query = "
            SELECT COUNT(*) AS scores
            FROM {$this->getTable()} AS ltias
            WHERE ltias.lti_assessment_id = :lti_assessment_id
            AND ltias.student_id = :student_id
            AND ltias.deleted_at IS NULL
        ";
        $statement = $this->database->prepare($query);
        $statement->bindValue('lti_assessment_id', $ltiAssessmentId, \PDO::PARAM_INT);
        $statement->bindValue('student_id', $studentId, \PDO::PARAM_INT);
        $statement->execute();
        if ($row = $statement->fetch()) {
            return intval($row['scores']);
        }
        return 0;
    }
}
