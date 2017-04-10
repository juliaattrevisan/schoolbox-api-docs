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
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

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
        $this->joins('category_id', FolderMapper::class, 'id', null, null, 'folder', 'folder');
        $this->uses('created_at', self::TYPE_DATETIME);
        $this->uses('deleted_at', self::TYPE_DATETIME);
        $this->hasSoftDeleteDateField('deleted_at');
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
            }
        }

        return $this->hydrateList($results);
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
            JOIN        assess_section section ON section.assess_id = survey.assess_id
            JOIN        assess_question question ON question.section_id = section.section_id
            JOIN        assess_attempt attempt ON
                            attempt.assess_id = survey.assess_id AND
                            attempt.user_id = :user_id AND
                            attempt.attempt_id = (
                                SELECT    attempt_id
                                FROM      assess_attempt last_attempt
                                WHERE     last_attempt.assess_id = survey.assess_id
                                ORDER BY  last_attempt.attempt_id DESC
                                LIMIT 1
                            )
            JOIN        assess_response response ON
                            response.attempt_id = attempt.attempt_id AND
                            response.section_id = section.section_id AND
                            response.question_id = question.question_id
            JOIN        assess_answer answer ON answer.question_id = question.question_id                            
            WHERE       survey.assess_id = :survey_id AND
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