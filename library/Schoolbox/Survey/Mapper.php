<?php
/**
 * This is just enough of a mapper to get the survey component rendering. This is in no way complete, and is only being
 * used to read data out of the table. This should be blown to smithereeens(sp?) when Quiz/Surveys are sucked out of
 * the refactoring airlock.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Survey;

use PDO;
use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Survey\Attempt\Mapper as SurveyAttemptMapper;
use Schoolbox\Survey\Mapper as SurveyMapper;
use Schoolbox\Survey\Attempt\Model as SurveyAttempt;

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
        $this->uses('anonymous_result', self::TYPE_BOOLEAN);
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');

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

    protected function getSelectString()
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('folder', 'f')},
                        {$this->getJoinColumnString('assess_attempts', 'aa')}
            FROM        {$this->getTable()}
            INNER JOIN  {$this->getJoinSql('folder', '', 'f')}
            LEFT JOIN   {$this->getJoinSql('assess_attempts', '', 'aa')}
        ";
    }

    /**
     * This removes anything that is not a survey from the results
     *
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $results = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            if ($row['availability'] == 'survey') {
                $results[] = $row;

                /** @var Model $model */
                $model = $this->hydrate($row, '');

                /** @var Folder $folder */
                $folder = $this->hydrateJoin($row, 'folder', 'f');
                $model->setFolder($folder);


                /** @var Collection $attempts */
                $attempts = $this->getJoinMapper('assess_attempts')->findByColumn('assess_id', $model->getId());
                $model->setAttempts($attempts);
            }
        }

        return $this->hydrateList($results);
    }

    /**
     * Retrieve the data required for the ePortfolio matrix component
     *
     * @param int $surveyId
     * @return array
     */
    public function getClassSnapshot($surveyId)
    {
        $sql = "
            SELECT
              attempt.user_id,
              survey.assess_id  AS survey_id,
              survey.title      AS survey_title,
              section.title     AS section_title,
              question.question AS question,
              answer.answer     AS answers,
              response.response AS selectedAnswers
            FROM assess survey
              JOIN assess_section section ON
                                            section.deleted_at IS NULL AND
                                            section.assess_id = survey.assess_id
              JOIN assess_question question ON
                                              question.deleted_at IS NULL AND
                                              question.section_id = section.section_id
              JOIN assess_attempt attempt ON
                                            attempt.deleted_at IS NULL AND
                                            attempt.assess_id = survey.assess_id AND
                                            attempt.attempt_id = (
                                              SELECT attempt_id
                                              FROM assess_attempt last_attempt
                                              WHERE last_attempt.assess_id = survey.assess_id AND
                                                last_attempt.user_id = attempt.user_id
            
                                              ORDER BY last_attempt.attempt_id DESC
                                              LIMIT 1
                                            )
              JOIN assess_response response ON
                                              response.deleted_at IS NULL AND
                                              response.attempt_id = attempt.attempt_id AND
                                              response.section_id = section.section_id AND
                                              response.question_id = question.question_id
              JOIN assess_answer answer ON answer.question_id = question.question_id
            WHERE survey.deleted_at IS NULL AND
                  survey.assess_id = :survey_id AND
                  survey.availability = 'survey'
            ORDER BY attempt.user_id,
              section.sequence,
              question.sequence            
        ";

        $statement = $this->database->prepare($sql);

        $statement->execute(['survey_id' => $surveyId]);

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
        $sql =
            "
            SELECT      survey.assess_id AS survey_id,
                        survey.title AS survey_title,
                        section.title AS section_title,
                        question.question AS question,
                        answer.answer AS answers,
                        response.response AS selectedAnswers
            FROM        assess survey
            JOIN        assess_section section ON 
                            section.deleted_at IS NULL AND 
                            section.assess_id = survey.assess_id
            JOIN        assess_question question ON
                            question.deleted_at IS NULL AND
                            question.section_id = section.section_id
            JOIN        assess_attempt attempt ON
                            attempt.deleted_at IS NULL AND
                            attempt.assess_id = survey.assess_id AND
                            attempt.user_id = :user_id AND
                            attempt.finish IS NOT NULL AND
                            attempt.attempt_id = (
                                SELECT    attempt_id
                                FROM      assess_attempt last_attempt
                                WHERE     last_attempt.assess_id = survey.assess_id
                                ORDER BY  last_attempt.attempt_id DESC
                                LIMIT 1
                            )
            JOIN        assess_response response ON
                            response.deleted_at IS NULL AND
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

    /**
     * Retrieve all accessible surveys by search keyword
     *
     * @param $search
     * @return mixed
     */
    public function findByKeyword($search)
    {
        $search = trim($search);

        if (strlen($search) < 3) {
            return [];
        }

        $results = [];

        $folderMapper = FolderMapper::getInstance();
        $surveyMapper = SurveyMapper::getInstance();

        $predicateBuilder = $this->getPredicateBuilder();

        // If the logged in user isn't a superuser, restrict search to folders they have access to

        if (!$this->getSessionUser()->isSuperuser()) {

            $folderIds = $folderMapper->findForUser($this->getSessionUser(), true)->getColumnValues('id');

            // Find all the surveys on these pages

            foreach ($folderIds as $folderId) {
                $predicateBuilder->equals('category_id', $folderId);
            }
        }

        // And filter the name of the survey

        $predicateBuilder->like('title', "%{$search}%");

        $surveys = $surveyMapper->findByPredicates($predicateBuilder);

        /** @var Model $survey */
        foreach ($surveys as $survey) {
            $results[] = [
                'id' => $survey->getId(),
                'name' => $survey->getFolder()->getName() . " - " . $survey->getTitle()
            ];
        }

        return $results;
    }
}
