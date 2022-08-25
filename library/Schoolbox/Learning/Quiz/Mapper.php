<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz;

use PDO;
use PDOStatement;
use Schoolbox;
use Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook\Adapter as MarkbookAdapter;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Learning\Quiz\Attempt\Answer\Model as QuizAnswerModel;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestionModel;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Model as QuizSolution;
use Schoolbox\Learning\Quiz\Section\Question\Solution\Mapper as QuizSolutionMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($id = null)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method GenericCollection|Model[] findByColumnList($columnList, $options = [])
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * @const WORK_TYPE_QUIZ
     * @deprecated 17.0 - too fragile. You cannot reliably imply semantics from the ID. It is user configurable.
     */
    const WORK_TYPE_QUIZ = 11;

    /**
     * SQL map definition
     */
    protected function mapping()
    {
        $this->hasTable('assess');
        $this->hasModel(Model::class);
        $this->uses('assess_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessmentModel', 'am');
        $this->joins('category_id', FolderMapper::class, 'id', null, null, 'category', 'category');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('type', WorkTypeMapper::class, 'id', null, null, 'workType', 'work_type');
        $this->uses('title', self::TYPE_STRING);
        $this->uses('description', self::TYPE_STRING);
        $this->uses('completion_message', self::TYPE_STRING);
        $this->uses('start', self::TYPE_DATETIME);
        $this->uses('finish', self::TYPE_DATETIME);
        $this->uses('duration', self::TYPE_INTEGER);
        $this->uses('max_attempts', self::TYPE_INTEGER);
        $this->uses('weight', self::TYPE_INTEGER);
        $this->uses('availability', self::TYPE_STRING);
        $this->uses('anonymous_result', self::TYPE_BOOLEAN);
        $this->uses('mark_visibility', self::TYPE_INTEGER);
        $this->uses('external_id', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP, 'created');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deleted');

        $this->hasJoin('sections', array(
            'mapper'    => QuizSectionMapper::class,
            'multiple'  => true,
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        $this->hasJoin('attempt', array(
            'mapper'    => QuizAttemptMapper::class,
            'multiple'  => true,
            'this_key'  => 'assess_id',
            'other_key' => 'assess_id',
        ));

        // Markbook sync
        $this->registerCallback(self::CALLBACK_POSTSAVE, array($this, 'syncToExternalMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTDELETE, array($this, 'deleteFromMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'deleteFromMarkbook'));
        $this->registerCallback(self::CALLBACK_POSTDELETE, [$this, 'deleteAssessment']);
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'deleteAssessment']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'syncToExternalMarkbook']);

        $this->registerCallback(self::CALLBACK_PRESAVE, array($this, 'setWorkType'));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteSections'));
        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, array($this, 'softDeleteAttempts'));
    }


    /**
     * @inheritdoc
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT {$this->getColumnString()},
                   {$this->getJoinColumnString('am', 'am')},
                   {$this->getJoinColumnString('user', 'u')}
        ";
    }


    /**
     * @inheritdoc
     */
    protected function getFromString(): string
    {
        return "
            FROM       {$this->getTable()}
            INNER JOIN {$this->getJoinSql('am', '', 'am', true)}
            INNER JOIN {$this->getJoinSql('user', '', 'u', true)}
        ";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     * @throws BaseMapper\Exception
     */
    public function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $quiz = $this->hydrate($row)
                         ->setAssessmentModel($this->hydrateJoin($row, 'am', 'am'))
                         ->setUser($this->hydrateJoin($row, 'user', 'u'))
            ;
            $collection[$quiz->getId()] = $quiz;
        }

        return $collection;
    }


    /**
     * Soft deletes all Attempts for the specified Quiz
     *
     * @param Model $quiz
     * @return true
     * @throws BaseMapper\DefinitionException
     */
    public function softDeleteSections(Model $quiz)
    {
        $quizSectionMapper = $this->getJoinMapper('sections');
        // Force cascade on non-leaf nodes to ensure whole tree is deleted.
        // However this will also updated the 'deleted_at' field for already deleted items.
        $quizSectionMapper->deleteByColumn('assess_id', $quiz->getId(), ['deleted' => true]);
        return true;
    }

    /**
     * Soft deletes all Attempts for the specified Quiz
     *
     * @param Model $quiz
     * @return true
     * @throws BaseMapper\DefinitionException
     */
    public function softDeleteAttempts(Model $quiz)
    {
        $quizAttemptMapper = $this->getJoinMapper('attempt');
        // Force cascade on non-leaf nodes to ensure whole tree is deleted.
        // TODO - SA-1277 However this will also updated the 'deleted_at' field for already deleted items.
        $quizAttemptMapper->deleteByColumn('assess_id', $quiz->getId(), ['deleted' => true]);
        return true;
    }


    /**
     * Pre-save callback.
     *
     * If not explicitly set to something else
     * Ensures that work type is always WorkType::TYPE_ONLINE_ASSESSMENT
     *
     * @param  Model $object
     * @return true
     */
    protected function setWorkType(Model $object)
    {
        if (!$object->getWorkType()) {
            $object->setWorkType((new WorkType())->setId(self::WORK_TYPE_QUIZ));
        }
        return true;
    }


    /**
     * Copies Sections, Questions and Solutions from source Quiz to destination Quiz
     *
     * For now, this call will throw an exception if the destination Quiz already has Sections.
     *
     * @param int $sourceAssessmentId
     * @param int $destinationAssessmentId
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException If Destination Quiz already has Sections
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function copyQuizConstituents($sourceAssessmentId, $destinationAssessmentId)
    {
        $sectionMapper = $this->getJoinMapper('sections');
        $questionMapper = QuizQuestionMapper::getInstance();
        $solutionMapper = QuizSolutionMapper::getInstance();

        /** @var Model $sourceQuiz */
        $sourceQuiz = $this->findOneByColumn('assessment_id', $sourceAssessmentId);
        $sourceQuiz->setSections($sectionMapper->findByColumn('assess_id', $sourceQuiz->getId()));

        foreach ($sourceQuiz->getSections() as $section) {
            $questions = $questionMapper->findByColumn('section_id', $section->getId());
            $section->setQuestions($questions);
        }

        /** @var Model $destinationQuiz */
        $destinationQuiz = $this->findOneByColumn('assessment_id', $destinationAssessmentId);

        // Ensure that we don't clobber destination Sections
        $destinationQuiz->setSections($sectionMapper->findByColumn('assess_id', $destinationQuiz->getId()));
        if ($destinationQuiz->getSections()->count()) {
            throw new \InvalidArgumentException("Destination Quiz (ID: {$destinationQuiz->getId()}) already has Sections.");
        }

        // Clone and Save Sections, Questions + Solutions
        foreach ($sourceQuiz->getSections() as $section) {
            $clonedSection = clone $section;
            $clonedSection->setQuiz($destinationQuiz);
            $destinationQuiz->getSections()->add($clonedSection);
            $sectionMapper->save($clonedSection);

            foreach ($clonedSection->getQuestions() as $clonedQuestion) {
                $questionMapper->save($clonedQuestion);
                if ($clonedQuestion->getSolution() instanceof QuizSolution) {
                    $solutionMapper->save($clonedQuestion->getSolution());
                }
            }
        }
        return true;
    }

    /**
     * Deletes all constituents (sections and questions) from the quiz whose
     * assessment ID matches `$assessmentId`.
     *
     * @param int $assessmentId
     * @return void
     * @throws BaseMapper\Exception if the quiz is deleted or doesn't exist
     */
    public function deleteQuizConstituents(int $assessmentId): void
    {
        try {
            $quiz = $this->findOneByColumn('assessment_id', $assessmentId);
        } catch (BaseMapper\Exception\Deleted $e) {
            $quiz = $e->getDeletedObject();
        }
        $this->softDeleteSections($quiz);
    }

    /**
     * Post-save callback.
     *
     * Saves a record in external markbook system.
     *
     * @param Model $object
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function syncToExternalMarkbook(Model $object): void
    {
        if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            return;
        }
        /** @var MarkbookAdapter $markbookAdapter */
        $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
        $markbookAdapter->syncAssessment($object);
    }


    /**
     * Post-delete callback.
     *
     * Removes this assessment from the external markbook.
     *
     * @param Model $object
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function deleteFromMarkbook(Model $object): void
    {
        if (!$this->getConfigLoader()->get(Schoolbox\Config\ConfigMapper::NAME_EXTERNAL_MARKBOOK)) {
            return;
        }
        /** @var MarkbookAdapter $markbookAdapter */
        $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
        $markbookAdapter->deleteAssessment($object);
    }


    /**
     * Post-delete callback.
     *
     * Delete's this quiz's assessment
     *
     * @param Model $object
     * @return true
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     */
    protected function deleteAssessment(Model $object): bool
    {
        $assessment = $object->getAssessmentModel();
        if (!$assessment->isDeleted()) {
            /** @var AssessmentMapper $assessmentMapper */
            $assessmentMapper = $this->getJoinMapper('am');
            $assessmentMapper->delete($assessment);
        }
        return true;
    }


    /**
     * Calculates and returns the statistics for the given online assessment
     *
     * @param int|Model $assess
     * @return array
     * @throws \PDOException
     */
    public function getStatistics($assess)
    {
        if ($assess instanceof Model) {
            $assess_id = $assess->getId();
        } else {
            $assess_id = $assess;
        }

        $sql = '
            SELECT
                AVG(sum_mark) AS `mean`,
                STDDEV(sum_mark) AS `stdev`,
                MIN(sum_mark) AS `min`,
                MAX(sum_mark) AS `max`
            FROM
            (
              SELECT *
              FROM
              (
                  SELECT (SUM(mark) / SUM(aq.marks) * 100) AS sum_mark, ar.attempt_id, t0.user_id, t0.finish
                  FROM assess_response ar
                  INNER JOIN
                  (
                        SELECT attempt_id, user_id, finish
                        FROM assess_attempt aa
                        WHERE assess_id = :assess_id
                        AND aa.reviewed_at IS NOT NULL
                        AND aa.deleted_at IS NULL
                  ) t0
                  ON ar.attempt_id = t0.attempt_id
                  LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
                  WHERE ar.deleted_at IS NULL
                  GROUP BY t0.attempt_id
                  ORDER BY finish DESC
              ) t1
              GROUP BY user_id
            ) t2;
        ';

        $stmt = $this->database->prepare($sql);
        $stmt->execute(array('assess_id' => $assess_id));

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * @param int $identifierValue
     * @return Model
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws BaseMapper\Exception\Deleted
     * @throws BaseMapper\Exception\NotFound
     * @throws \PDOException
     */
    public function findById($identifierValue)
    {
        $stmt = $this->getDatabase()->prepare("
            SELECT
                {$this->getColumnString('a')},
                {$this->getJoinColumnString('category', 'c')},
                {$this->getJoinColumnString('work_type', 't')},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('am', 'am')}
            FROM
                `{$this->getTable()}` `a`
                LEFT JOIN {$this->getJoinSql('category', 'a', 'c')}
                LEFT JOIN {$this->getJoinSql('work_type', 'a', 't')}
                LEFT JOIN {$this->getJoinSql('user', 'a', 'u', true)}
                LEFT JOIN {$this->getJoinSql('am', 'a', 'am')}
            WHERE
                `a`.assess_id = :id
        ");

        $stmt->execute(array('id' => $identifierValue));

        $quiz = null;
        $curriculumNodes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!($quiz instanceof Model)) {
                $quiz = $this->hydrate($row, 'a');
                $quiz->setCategory($this->hydrateJoin($row, 'category', 'c'));
                $quiz->setWorkType($this->hydrateJoin($row, 'work_type', 't'));
                $quiz->setUser($this->hydrateJoin($row, 'user', 'u'));
                $quiz->setAssessmentModel($this->hydrateJoin($row, 'am', 'am'));
            }
        }

        if (!($quiz instanceof Model)) {
            throw new BaseMapper\Exception\NotFound();
        }

        $this->throwIfDeleted($quiz);

        $quiz->setCurriculumNodes(new Collection(array_values($curriculumNodes)));

        return $quiz;
    }


    /**
     * Closes any attempts in $quiz that have expired.
     *
     * @param Model                $quiz
     * @param \Alaress_Date_Format $now
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function closeExpiredAttempts(Model $quiz, \Alaress_Date_Format $now): void
    {
        if ($quiz->isDurationUnlimited()) {
            return;
        }
        $duration = $quiz->getDuration();
        foreach ($quiz->getAttempts() as $attempt) {
            if (!$attempt->isFinished()) {
                // Has it passed its duration?
                $expiry = (clone $attempt->getStart())->addSeconds($duration);
                if ($expiry->lt($now)) {
                    $attempt->setFinish($expiry);
                    $this->getJoinMapper('attempt')->save($attempt);
                }
            }
        }
    }


    /**
     * Loads sections and their questions into this quiz.
     *
     * @param Model $quiz
     * @return Model
     * @throws BaseMapper\DefinitionException
     * @throws \DomainException
     */
    public function loadSectionsAndQuestions(Model $quiz): Model
    {
        // Load sections
        $sectionMapper = $this->getJoinMapper('sections');
        $quiz->setSections(
            $sectionMapper->findByColumn(
                'assess_id',
                $quiz->getId(),
                ['order' => ['sequence' => 'asc']]
            )
        );

        // Load questions into sections
        // TODO question mapper should be part of section mapper
        $questionMapper   = QuizQuestionMapper::getInstance();
        $sectionQuestions = $questionMapper
            ->findByColumn(
                'section_id',
                $quiz->getSections()->getPropertyValues('id')
            )
            ->groupByCallback(function (QuizQuestionModel $q) {
                return $q->getSection()->getId();
            });
        foreach ($quiz->getSections() as $section) {
            if (isset($sectionQuestions[$section->getId()])) {
                $section->setQuestions($sectionQuestions[$section->getId()]);
            } else {
                $section->setQuestions($questionMapper->createCollection());
            }
        }

        return $quiz;
    }


    /**
     * Loads attempts and answers into this quiz.
     *
     * @param Model     $quiz
     * @param null|User $student If set, only load attempts made by this student.
     * @return Model
     * @throws BaseMapper\DefinitionException
     */
    public function loadAttemptsAndAnswers(Model $quiz, ?User $student = null): Model
    {
        // Load attempts
        $attemptMapper = $this->getJoinMapper('attempt');
        $attemptFilter = ['assess_id' => $quiz->getId(),];
        $attemptOrder  = ['order' => ['start' => 'desc']];
        if ($student) {
            $attemptFilter['user_id'] = $student->getId();
        }
        $quiz->setAttempts($attemptMapper->findByColumnList($attemptFilter, $attemptOrder));

        // Load answers (which include questions) into attempts
        // TODO answer mapper should be part of attempt mapper
        $answerMapper   = QuizAnswerMapper::getInstance();
        $attemptAnswers = $answerMapper
            ->findByColumn(
                'attempt_id',
                $quiz->getAttempts()->getPropertyValues('id')
            )
            ->groupByCallback(function (QuizAnswerModel $a) {
                return $a->getAttempt()->getId();
            });
        foreach ($quiz->getAttempts() as $attempt) {
            if (isset($attemptAnswers[$attempt->getId()])) {
                $attempt->setAnswers($attemptAnswers[$attempt->getId()]);
            } else {
                $attempt->setAnswers($answerMapper->createCollection());
            }
        }

        return $quiz;
    }


    /**
     * Any notifications related to assessments must be deferred until all
     * its plans and constituent participation records are saved.
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
