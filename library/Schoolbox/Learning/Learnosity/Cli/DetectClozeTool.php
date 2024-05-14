<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

namespace Schoolbox\Learning\Learnosity\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Learnosity\Api;

/**
 * Detect legacy quizzes containing cloze exercises.
 * These are known to have imported incorrectly at the start of 2024.
 */
class DetectClozeTool extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * Corresponds to the Fillgap question type in luQuestionType
     */
    private const CLOZE_TYPE_ID = 3;


    /**
     * @var Api
     */
    private Api $api;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Find out how many legacy quizzes contain cloze exercise questions (fillgap)
because we mis-imported them at the start of 2024 during the mega migration.
These will have used the wrong regex to parse answers.

Usage: detectcloze.php [-vvv]


HELP;
            exit;
        }

        /* Log the title first lol
         */
        $this->getLogger()->info('');
        $this->getLogger()->info('Learnosity Detect Cloze.');
        $this->getLogger()->info('');

        /* Find all the quizzes that contain a cloze exercise
         */
        $query = "
            SELECT
                assess.assess_id,
                assess.title,
                assess_section.section_id,
                assess_question.question_id,
                assess_question.question,
                assess_answer.answer
            FROM assess
            INNER JOIN assess_section ON assess.assess_id = assess_section.assess_id
            INNER JOIN assess_question on assess_question.section_id = assess_section.section_id
            INNER JOIN assess_answer ON assess_answer.question_id = assess_question.question_id
            WHERE assess.deleted_at IS NULL
            AND assess.availability = 'assess'
            AND assess_section.deleted_at IS NULL
            AND assess_question.deleted_at IS NULL
            AND assess_question.type_id = :typeId
        ";

        $statement = $this->getDatabaseInternal()->prepareQuery($query, [
            'typeId' => self::CLOZE_TYPE_ID
        ]);

        $statement->execute();

        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $assessId = $row['assess_id'];
            $title = $row['title'];
            $questionId = $row['question_id'];
            $question = trim((string)$row['question']);
            $answer = $row['answer'];

            /* Detect if multiple question stimuli functionally collide
             */
            $discriminator = sha1($question);

            if (!isset($stack[$assessId])) {
                $stack[$assessId] = [
                    'title' => $title,
                    'questions' => []
                ];
            }

            if (!isset($stack[$assessId]['questions'][$discriminator])) {
                $stack[$assessId]['questions'][$discriminator] = [
                    'question' => $question,
                    'questionId' => [$questionId],
                ];
            } else {
                /* Add it to the list of colliding questions
                 */
                $stack[$assessId]['questions'][$discriminator]['questionId'][] = $questionId;
            }
        }

        print_r($stack);
    }
}
