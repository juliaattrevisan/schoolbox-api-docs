<?php
/**
 * This is just enough of a mapper to get the survey component rendering. This is in no way complete, and is only being
 * used to read data out of the table. This should be blown to smithereeens(sp?) when Quiz/Surveys are sucked out of
 * the refactoring airlock.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Survey;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\Model as Assessment;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Survey\Attempt\Mapper as SurveyAttemptMapper;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup column mapping
     */
    protected function mapping()
    {
        $this->hasTable('assess');
        $this->hasModel(Model::class);
        $this->uses('assess_id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER, 'id');
        $this->uses('availability', self::TYPE_STRING);
        $this->uses('title', self::TYPE_STRING);
        $this->uses('max_attempts', self::TYPE_INTEGER);
        $this->joins('category_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->joins('assessment_id', AssessmentMapper::class, 'id', null, null, 'assessment', 'assessment');
        $this->uses('anonymous_result', self::TYPE_BOOLEAN);
        $this->uses('finish', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

        $this->hasJoin(
            'assess_attempts',
            array(
                'mapper' => SurveyAttemptMapper::class,
                'other_key' => 'assess_id',
                'this_key' => 'assess_id',
                'multiple' => true,
            )
        );
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('folder', 'f')},
                        {$this->getJoinColumnString('assessment', 'ass')},
                        {$this->getJoinColumnString('assess_attempts', 'aa')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('folder', '', 'f')}
            LEFT JOIN   {$this->getJoinSql('assess_attempts', '', 'aa')}
            LEFT JOIN   {$this->getJoinSql('assessment', '', 'ass')}
        ";
    }

    /**
     * This removes anything that is not a survey from the results
     *
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var Model[] $collection */
        $collection = $this->createCollection();

        $idCol = $this->getIdentifierColumn();
        /** @var SurveyAttemptMapper $attemptMapper */
        $attemptMapper = $this->getJoinMapper('assess_attempts');

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($row['availability'] !== 'survey') {
                continue;
            }
            if ($collection->offsetExists($row[$idCol])) {
                $model = $collection[$row[$idCol]];
            } else {
                $model = $this->hydrate($row);
                /** @var Folder $folder */
                $folder = $this->hydrateJoin($row, 'folder', 'f');
                $model->setFolder($folder);

                /** @var Assessment $assessment */
                $assessment = $this->hydrateJoin($row, 'assessment', 'ass');
                $model->setAssessment($assessment);
                $model->setAttempts($attemptMapper->createCollection());
            }

            $attempt = $attemptMapper->hydrate($row, 'aa');
            if ($attempt) {
                $attempt->setSurvey($model);
                $model->getAttempts()->offsetSet($attempt->getId(), $attempt);
            }

            $collection[$model->getId()] = $model;
        }

        return $collection;
    }

    /**
     * Retrieve a list of all surveys attempted by the users specified
     *
     * @param array  $userIds Array containing Schoolbox user IDs of people that have attempted quizzes
     * @param string $name    Only search for surveys whose titles contain this
     * @return Collection|Model[]
     */
    public function findAttemptedSurveysForUsers($userIds, $name): Collection
    {
        $pb = $this->getPredicateBuilder();
        $pb->in('aa.user_id', $userIds);
        $pb->like('title', '%'.$this->database->escapeForLike($name).'%');
        $pb->isNull("{$this->getTable()}.deleted_at" );

        $sql = $this->getSelectString() . " WHERE ".implode(' AND ',$pb->getWhere());

        $statement = $this->getDatabase()->prepare($sql);
        $statement->execute($pb->getParams());

        // Hydrate results and return unique surveys

        return $this->hydrateSelect($statement)->getUnique('id');
    }

    /**
     * Retrieve all accessible surveys by search keyword returns results in a format suitable for selectize
     *
     * @param string $search
     * @return array
     */
    public function findByKeyword($search)
    {
        // Searches cannot be less than three characters

        $search = trim($search);

        if (strlen($search) < 3) {
            return [];
        }

        $results = [];
        $folderMapper = FolderMapper::getInstance();
        $predicateBuilder = $this->getPredicateBuilder();

        // If the logged in user isn't a superuser, restrict search to folders they have access to

        if (!$this->getSessionUser()->isSuperuser()) {

            // Create a sub-predicate to restrict based on folder perms

            $folderPredicate = $this->getPredicateBuilder();

            // Get all folders the person has permissions to

            $folderIds = $folderMapper->findForUser($this->getSessionUser(), true)->getColumnValues('id');

            // Find all the surveys on these pages

            foreach ($folderIds as $folderId) {
                $folderPredicate->equals('category_id', $folderId);
            }

            // Stuff them all into an OR/ANY predicate

            $predicateBuilder->any($folderPredicate);
        }

        // And filter the name of the survey

        $predicateBuilder->like('title', "%{$search}%");

        $surveys = $this->findByPredicates($predicateBuilder);

        /** @var Model $survey */
        foreach ($surveys as $survey) {
            $results[] = [
                'id' => $survey->getId(),
                'name' => $survey->getFolder()->getName() . " - " . $survey->getTitle()
            ];
        }

        return $results;
    }

    /**
     * Retrieve the data required for the ePortfolio matrix component
     *
     * @param int $surveyId
     * @param array $userIds
     * @return array
     */
    public function findClassSnapshot($surveyId, array $userIds = []): array
    {
        $pb = $this->getPredicateBuilder();
        $pb->equals('survey.assess_id', $surveyId);
        $pb->isNull('survey.deleted_at');
        $pb->equals('survey.availability', 'survey');

        if(count($userIds) > 0) {
            $pb->in('attempt.user_id', $userIds);
        }


        $sql = "
            SELECT      attempt.user_id,
                        survey.assess_id  AS survey_id,
                        survey.title      AS survey_title,
                        section.title     AS section_title,
                        question.question AS question,
                        answer.answer     AS answers,
                        response.response AS selectedAnswers
            FROM        assess survey
            JOIN        assess_section section ON section.deleted_at IS NULL AND
                        section.assess_id = survey.assess_id
            JOIN        assess_question question ON question.deleted_at IS NULL AND
                        question.section_id = section.section_id
            JOIN        assess_attempt attempt ON attempt.deleted_at IS NULL AND
                        attempt.assess_id = survey.assess_id AND
                        attempt.finish IS NOT NULL AND
                        attempt.attempt_id = (
                            SELECT    attempt_id
                            FROM      assess_attempt last_attempt
                            WHERE     last_attempt.assess_id = survey.assess_id AND last_attempt.user_id = attempt.user_id
                            ORDER BY  last_attempt.attempt_id DESC
                            LIMIT 1
                        )
            JOIN        assess_response response ON response.deleted_at IS NULL AND
                        response.attempt_id = attempt.attempt_id AND
                        response.section_id = section.section_id AND
                        response.question_id = question.question_id
            JOIN        assess_answer answer ON answer.question_id = question.question_id
            WHERE       ".implode(' AND ', $pb->getWhere())."
            ORDER BY    attempt.user_id,
                        section.sequence,
                        question.sequence
        ";
        $statement = $this->database->prepare($sql);
        $statement->execute($pb->getParams());

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve the data required for the ePortfolio matrix component
     *
     * @param int $surveyId
     * @param int $userId
     * @return array
     */
    public function getMatrix($surveyId, $userId)
    {
        $sql = "
            SELECT      survey.assess_id AS survey_id,
                        survey.title AS survey_title,
                        section.title AS section_title,
                        question.question AS question,
                        answer.answer AS answers,
                        response.response AS selectedAnswers
            FROM        assess survey
            JOIN        assess_section section ON section.deleted_at IS NULL AND section.assess_id = survey.assess_id
            JOIN        assess_question question ON question.deleted_at IS NULL AND question.section_id = section.section_id
            JOIN        assess_attempt attempt ON attempt.deleted_at IS NULL AND attempt.assess_id = survey.assess_id AND attempt.user_id = :user_id AND
                        attempt.finish IS NOT NULL AND attempt.attempt_id = (
                            SELECT    attempt_id
                            FROM      assess_attempt last_attempt
                            WHERE     last_attempt.assess_id = survey.assess_id
                            ORDER BY  last_attempt.attempt_id DESC
                            LIMIT 1
                        )
            JOIN        assess_response response ON response.deleted_at IS NULL AND
                        response.attempt_id = attempt.attempt_id AND
                        response.section_id = section.section_id AND
                        response.question_id = question.question_id
            JOIN        assess_answer answer ON answer.question_id = question.question_id
            WHERE       survey.deleted_at IS NULL AND
                        survey.assess_id = :survey_id AND
                        survey.availability = 'survey'
            ORDER BY    section.sequence,
                        question.sequence
        ";

        $statement = $this->database->prepare($sql);

        $statement->execute([
            'survey_id' => $surveyId,
            'user_id' => $userId
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
