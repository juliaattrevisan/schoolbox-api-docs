<?php
/**
 * Mapper for online assessment attempts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt;

use Alaress_Date_Format;
use PDOStatement;
use Schoolbox\Assessment\Participant;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\ExternalMarkbook\Adapter as MarkbookAdapter;
use Schoolbox\Learning\Quiz\Attempt\Answer\Mapper as QuizAnswerMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * @method Model createModel($idValue = null)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model findById($identifierValue)
 */
class Mapper extends BaseMapper
{

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

    /**
     * SQL definition
     */
    protected function mapping()
    {
        $this->hasTable('assess_attempt');
        $this->hasModel(Model::class);
        $this->uses('attempt_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->joins('assess_id', QuizMapper::class, 'assess_id', null, null, 'quiz', 'quiz');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('start', self::TYPE_DATETIME);
        $this->uses('finish', self::TYPE_DATETIME);
        $this->joins('reviewer_id', UserMapper::class, 'id', null, null, 'reviewer', 'reviewer');
        $this->uses('reviewed_at', self::TYPE_DATETIME, null, 'reviewed');
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE, 'deleted');

        $this->hasJoin('answers', array(
            'mapper'    => QuizAnswerMapper::class,
            'multiple'  => true,
            'this_key'  => 'attempt_id',
            'other_key' => 'attempt_id',
        ));

        $this->registerCallback(self::CALLBACK_POSTSOFTDELETE, [$this, 'softDeleteAnswers']);

        $this->registerCallback(self::CALLBACK_PRESAVE, [$this, 'canParticipate']);
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'insertParticipation']);

        // Markbook sync
        $this->registerCallback(self::CALLBACK_POSTSAVE, [$this, 'syncToExternalMarkbook']);
        $this->registerCallback(self::CALLBACK_POSTRESTORE, [$this, 'syncToExternalMarkbook']);
    }


    /**
     * @param Model      $object
     * @param Model|null $previous
     * @return bool
     * @throws BaseMapper\DefinitionException
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     */
    public function canParticipate(Model $object, Model $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the system is still valid
            return true;
        }
        $assessment = $this->getQuizMapper()->findById($object->getQuiz()->getId())->getAssessmentModel();
        return $this->interactionChecker->assertCanReceiveInteractions($object->getUser(), $assessment);
    }


    /**
     * @param Model      $object
     * @param Model|null $previous
     * @return bool
     * @throws Participant\Plan\InvalidState If permitting the save would cause the system to enter an invalid state
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function insertParticipation(Model $object, Model $previous = null): bool
    {
        if ($previous) {
            // If an update, assume the participation record already exists
            return true;
        }
        $assessment = $this->getQuizMapper()->findById($object->getQuiz()->getId())->getAssessmentModel();
        return $this->interactionChecker->insertParticipationOnFirstInteraction($object->getUser(), $assessment);
    }


    /**
     * Soft deletes all Answers in the specified Attempt
     *
     * @param Model $attempt
     * @return true
     */
    public function softDeleteAnswers(Model $attempt)
    {
        /** @var Answer\Mapper $quizAnswerMapper */
        $quizAnswerMapper = $this->getJoinMapper('answers');
        // Don't re-delete deleted leaf-nodes. Unlike non-leaf nodes, these will retain original deletion date.
        $quizAnswerMapper->deleteByColumn('attempt_id', $attempt->getId());
        return true;
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'r')},
                {$this->getJoinColumnString('user', 'u')},

                {$this->getJoinColumnString('quiz', 'a')}
            FROM {$this->getDatabase()->quoteIdentifier($this->getTable())}
            LEFT JOIN {$this->getJoinSql('reviewer', '', 'r', true)}
            INNER JOIN {$this->getJoinSql('user', '', 'u', true)}
            INNER JOIN {$this->getJoinSql('quiz', '', 'a', true)}
        ";
    }

    /**
     * @param PDOStatement $statement
     * @return Collection
     * @throws BaseMapper\Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $c = $this->createCollection();
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Model $attempt */
            $attempt = $this->hydrate($row);

            // I should never need to know the student's specifics here,
            // because this object should always be part of a participant's
            // response or feedback.

            /** @var User $user */
            $reviewer = $this->hydrateJoin($row, 'reviewer', 'r');
            // TODO, second part of test should not be required. see https://github.com/alaress/schoolbox/pull/11281
            if($reviewer instanceof User && $reviewer->getId()){
                $attempt->setReviewer($reviewer);
            }

            $attempt->setUser($this->hydrateJoin($row, 'user', 'u'));

            // Try to streamline quiz+assessment creation too
            /** @var Quiz $quiz */
            $quiz = $this->createJoinModel('quiz', $row['a.assess_id']);
            $quiz->setAssessmentModel($this->createJoinModel('quiz.am', $row['a.assessment_id']));
            $attempt->setQuiz($quiz);
            $c->add($attempt);
        }
        return $c;
    }

    /**
     * Post-Save callback.
     *
     * Writes mark data back into remote markbook
     *
     * @param Model $object
     */
    protected function syncToExternalMarkbook(Model $object): void
    {
        if ($object->isReviewed()) {
            /** @var MarkbookAdapter $markbookAdapter */
            $markbookAdapter = self::getDi()->get('externalMarkbookAdapter');
            $markbookAdapter->syncParticipation($object->getQuiz(), $object->getUser());
        }
    }


    /**
     * Gets a string of placeholders for an array - usually used for creating a
     * list of placeholders for an IN condition.
     *
     * @param array $input
     * @param string $prefix If not supplied, ? will be used as the placeholder
     *                       for all values.
     *
     * @return string Something like "(:id_0, :id_1, :id_2)".
     */
    public function getArrayPlaceholders($input, $prefix = null)
    {
        $placeholders = array();
        foreach ($input as $i => $param) {
            $placeholders[] = isset($prefix) ? (':' . $prefix . '_' . $i) : '?';
        }
        return '(' . implode(',', $placeholders) . ')';
    }

    /**
     * Retrieves mark, max_mark and first_section for all given attempt IDs.
     *
     * TODO consider removing this sum method when mappers for responses and questions exist
     *
     * @param array $attemptIds
     * @return Collection|array[]
     */
    public function findSupplementaryInformation($attemptIds)
    {
        $result = new Collection();
        if (empty($attemptIds)) {
            return $result;
        }
        $query = "
            SELECT
                aa.attempt_id,
                SUM(ar.mark)  AS mark,
                SUM(aq.marks) AS max_mark,
                (
                    SELECT `as`.section_id
                    FROM assess_section `as`
                    WHERE `as`.assess_id = q.assess_id
                    ORDER BY `as`.sequence ASC
                    LIMIT 1
                ) AS first_section
            FROM assess_attempt aa
            JOIN assess q ON q.assess_id = aa.assess_id
            LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
            LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
            WHERE aa.attempt_id IN {$this->getArrayPlaceholders($attemptIds)}
            GROUP BY aa.assess_id, aa.attempt_id
            ORDER BY aa.start DESC
        ";
        foreach ($this->getDatabase()->query($query, $this->getArrayParameters($attemptIds)) as $row) {
            $result[$row['attempt_id']] = $row;
        }
        return $result;
    }


    public function getAllAttempts($assess_id, $extra_params = array())
    {
        //
        // Build filters for query
        $params = array(
            'assess_id' => $assess_id,
        );

        $query = "
            SELECT
                aa.user_id,
                aa.attempt_id,
                aq.section_id,
                SUM(ar.mark)  AS mark,
                SUM(aq.marks) AS max_mark
            FROM assess_attempt aa
                LEFT JOIN assess_response ar ON aa.attempt_id  = ar.attempt_id
                LEFT JOIN assess_question aq ON ar.question_id = aq.question_id
                RIGHT JOIN assess          a  ON aa.assess_id   = a.assess_id
            WHERE a.assess_id = :assess_id
            AND a.availability = 'assess'
            AND aa.deleted_at IS NULL
            GROUP BY aa.assess_id, aa.attempt_id
            ORDER BY aa.start DESC
        ";
        $stmt = $this->database->prepare($query);
        $stmt->execute($params);

        $attempts = array();

        $sum = 0;
        $count = 0;

        foreach ($stmt as $row) {

            if ($row['user_id']) {
                // only include the most recent attempt for each user (ie. first seen)
                if (!empty($extra_params['most_recent']) && $extra_params['most_recent'] == true) {
                    // if the user is already in the array (ie already seen), just increment the attempt count
                    if (array_key_exists($row['user_id'], $attempts) || $row['max_mark'] == 0) {
                        $attempts[$row['user_id']][0]['attempts']++;
                        continue;
                    }
                }

                // include
                $mark = ($row['mark'] / $row['max_mark']) * 100;
                $attempts[$row['user_id']][] = array(
                    'section_id'   => $row['section_id'],
                    'attempt_id'   => $row['attempt_id'],
                    'mark'         => $mark,
                    'display_mark' => sprintf("%s / %d", round($row['mark'], 2), $row['max_mark']),
                    'comment'      => '',
                    'attempts'     => 1
                );

                // add to averages vars
                ++$count;
                $sum += $mark;
            }
        }
        if (!empty($extra_params['calc_average']) && $extra_params['calc_average'] == true) {
            $attempts['avg'] = $count ? $sum / $count : 0;
        }

        return $attempts;
    }


    /**
     * Gets number of attempts this student has made on this assessment. Doesn't
     * include deleted assessments.
     *
     * @param int $assess_id  The assessment to check.
     * @param int $student_id The student to check.
     *
     * @return int
     */
    public function getAttemptCountForStudent($assess_id, $student_id)
    {
        $query = "
            SELECT COUNT(*) AS attempts
            FROM {$this->getTable()} AS aa
            WHERE aa.assess_id = :assess_id
            AND aa.user_id = :student_id
            AND aa.deleted_at IS NULL
        ";
        $statement = $this->database->prepare($query);
        $statement->bindValue('assess_id', $assess_id, \PDO::PARAM_INT);
        $statement->bindValue('student_id', $student_id, \PDO::PARAM_INT);
        $statement->execute();
        if ($row = $statement->fetch()) {
            return intval($row['attempts']);
        }
        return 0;
    }

    /**
     * Gets the number of quizzes that a student attempted for the given subject between the given dates
     *
     * @param int $studentId
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @param int $folderId
     *
     * @return int
     */
    public function getAttemptCountForStudentInTerm($studentId, $folderId, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        $parts = [
            'aa.user_id = :studentId',
            'a.category_id = :folderId',
            'a.deleted_at IS NULL',
            'aa.deleted_at IS NULL'
        ];
        $params = [
            'studentId' => $studentId,
            'folderId'  => $folderId,
        ];
        if (!$from->isNull()) {
            $params['from'] = $from->getDatabaseDate();
            $parts[] = 'DATE(a.finish) >= :from';
        }
        if (!$to->isNull()) {
            $params['to'] = $to->getDatabaseDate();
            $parts[] = 'DATE(a.finish) <= :to';
        }
        $whereClause = implode(' AND ', $parts);

        $query = "
            SELECT COUNT(*) AS attempts
            FROM {$this->getTable()} AS aa
            JOIN assess AS a ON a.assess_id = aa.assess_id
            WHERE $whereClause
        ";

        $statement = $this->database->prepare($query);
        $statement->execute($params);
        if ($row = $statement->fetch()) {
            return intval($row['attempts']);
        }
        return 0;
    }

    /**
     * Return a collection of subjects where the specified student attempted quizzes during the specified period.
     *
     * @param int $studentId
     * @param Alaress_Date_Format $from
     * @param Alaress_Date_Format $to
     * @return Collection|Folder[]
     */
    public function getAttemptedSubjects($studentId, Alaress_Date_Format $from, Alaress_Date_Format $to)
    {
        $parts = [
            'aa.user_id = :studentId',
        ];
        $params = [
            'studentId' => $studentId,
        ];
        if (!$from->isNull()) {
            $params['from'] = $from->getDatabaseDate();
            $parts[] = 'DATE(a.finish) >= :from';
        }
        if (!$to->isNull()) {
            $params['to'] = $to->getDatabaseDate();
            $parts[] = 'DATE(a.finish) <= :to';
        }
        $whereClause = implode(' AND ', $parts);

        $query = "
                SELECT f.id FROM folder f
                JOIN assess a ON a.category_id = f.id
                JOIN assess_attempt aa ON aa.assess_id = a.assess_id
                WHERE $whereClause";
        $query .= " GROUP BY f.id";

        $statement = $this->database->prepare($query);
        $statement->execute($params);

        $folderIds = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $folderIds[] = $row['id'];
        }

        if (count($folderIds)) {
            return FolderMapper::getInstance()->findByColumn('id', $folderIds);
        }
        return new Collection();
    }

    /**
     * Gets all attempts which are are not yet finished, but have expired.
     *
     * @return Model[]
     */
    public function findUnfinishedExpiredAttempts()
    {
        $query = "
            SELECT
                {$this->getColumnString('aa')},
                {$this->getJoinColumnString('quiz', 'as')},
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->getTable()} aa
            INNER JOIN {$this->getJoinSql('quiz', 'aa', 'as')}
            INNER JOIN {$this->getJoinSql('user', 'aa', 'u')}
            WHERE `as`.`availability` = 'assess'
            AND   `as`.`duration` > 0
            AND   TIMESTAMPADD(SECOND, `as`.`duration`, `aa`.`start`) < :expiry
            AND   `aa`.`finish`     IS NULL
            AND   `aa`.`deleted_at` IS NULL
            ORDER BY TIMESTAMPADD(SECOND, `as`.`duration`, `aa`.`start`) ASC
        ";
        $statement = $this->database->prepare($query);
        $statement->execute([
            'expiry' => \Alaress_Date_Format::now()->getDatabase(),
        ]);
        return $this->hydrateList(
            $statement,
            'aa',
            function ($mapper, $model, $row) {
                /** @var Quiz $quiz */
                $quiz = $this->hydrateJoin($row, 'quiz', 'as');
                /** @var User $user */
                $user = $this->hydrateJoin($row, 'user', 'u');
                /** @var Model $model */
                return $model
                    ->setQuiz($quiz)
                    ->setUser($user);
            }
        );
    }

    /**
     * Mark the attempt as reviewed
     * @return bool|\int[]
     * @param Model $attempt
     * @param User $reviewer
     * @throws BaseMapper\Exception
     */
    public function finalizeReview(Model $attempt, User $reviewer)
    {
        $attempt->setReviewed(Alaress_Date_Format::now());
        $attempt->setReviewer($reviewer);
        return $this->save($attempt);
    }

    /**
     * @return BaseMapper|QuizMapper
     * @throws BaseMapper\DefinitionException
     */
    private function getQuizMapper(): QuizMapper
    {
        return $this->getJoinMapper('quiz');
    }
}
