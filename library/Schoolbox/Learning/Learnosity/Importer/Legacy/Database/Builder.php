<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Database;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Database\Strategy\Factory;
use Schoolbox\Learning\Learnosity\Importer\RemoteInlineImageConverter;

/**
 * Takes a given schoolbox.assess_id and fetches all the sections, questions,
 * and answers, constructing a complete array representation.
 */
class Builder
{
    /**
     * Get a particular schoolbox.assess row
     */
    private const QUERY_QUIZ = "
        SELECT *
        FROM assess
        WHERE deleted_at IS NULL
        AND availability = 'assess'
        AND assess_id = :assessId
    ";


    /**
     * Get the sections in a particular schoolbox.assess
     */
    private const QUERY_SECTION = '
        SELECT *
        FROM assess_section
        WHERE deleted_at IS NULL
        AND assess_id = :assessId
        ORDER BY sequence ASC
    ';


    /**
     * Get the questions in a particular schoolbox.assess_section
     */
    private const QUERY_QUESTION = '
        SELECT *
        FROM assess_question
        WHERE deleted_at IS NULL
        AND section_id = :sectionId
        ORDER BY sequence ASC
    ';


    /**
     * Get the answers in a particular schoolbox.assess_question
     */
    private const QUERY_ANSWER = '
        SELECT *
        FROM assess_answer
        WHERE deleted_at IS NULL
        AND question_id = :questionId
        ORDER BY sequence ASC
    ';


    /**
     * A set of prepared statements we can recycle
     *
     * @var \PDOStatement
     */
    private \PDOStatement $quizStatement;
    private \PDOStatement $sectionsStatement;
    private \PDOStatement $questionStatement;
    private \PDOStatement $answerStatement;


    /**
     * Constructor
     *
     * This happens to make some prepared statements;
     * ideally you only need to instantiate this once
     *
     * @param IConnector $db
     * @param Factory $strategyFactory
     * @param RemoteInlineImageConverter $inlineImageConverter
     * @param LoggerInterface $logger
     */
    public function __construct(
        IConnector $db,
        private \Schoolbox\Learning\Learnosity\Importer\Legacy\Database\Strategy\Factory $strategyFactory,
        private RemoteInlineImageConverter $inlineImageConverter,
        private LoggerInterface $logger
    ) {
        $this->quizStatement = $db->prepare(self::QUERY_QUIZ);
        $this->sectionsStatement = $db->prepare(self::QUERY_SECTION);
        $this->questionStatement = $db->prepare(self::QUERY_QUESTION);
        $this->answerStatement = $db->prepare(self::QUERY_ANSWER);
    }


    /**
     * Get a quiz that:
     *     * is not deleted
     *     * is not yet in learnosity
     *
     * @param int $assessId
     * @return array
     * @throws \DOMException
     * @throws \JsonException
     * @throws \PDOException
     */
    public function build(int $assessId): array
    {
        $this->quizStatement->execute([
            'assessId' => $assessId
        ]);

        /* Final stack
         */
        $quizRow = $this->quizStatement->fetch(\PDO::FETCH_ASSOC);
        $quizRow['_sections'] = [];

        /* Get its sections
         */
        $this->sectionsStatement->execute([
            'assessId' => $quizRow['assess_id']
        ]);

        while ($sectionRow = $this->sectionsStatement->fetch(\PDO::FETCH_ASSOC)) {
            $sectionRow['_questions'] = [];

            /* Get each section's questions
             */
            $this->questionStatement->execute([
                'sectionId' => $sectionRow['section_id']
            ]);

            while ($questionRow = $this->questionStatement->fetch(\PDO::FETCH_ASSOC)) {
                $questionRow['_answers'] = [];

                /* Clean out any weird whitespace/control chars from the end of this,
                 * and convert base64 inline images to storage files
                 */
                try {
                    $questionRow['question'] = $this->inlineImageConverter->convert((string)$questionRow['question']);
                } catch (\RuntimeException $e) {
                    /* This question has busted html
                     * Skip it quietly
                     */
                    $this->logger->warning(sprintf(
                        'question_id %s has unparseable html - it might be truncated; skipping',
                        $questionRow['question_id']
                    ));

                    continue;
                }

                /* Get each question's answers
                 */
                $this->answerStatement->execute([
                    'questionId' => $questionRow['question_id']
                ]);

                try {
                    while ($answerRow = $this->answerStatement->fetch(\PDO::FETCH_ASSOC)) {
                        $answerRow['answer'] = $this->decompileAnswer($questionRow, $answerRow);

                        $questionRow['_answers'][] = $answerRow;
                    }
                } catch (\RuntimeException $e) {
                    /* This question has busted answers
                     * Skip it quietly
                     */
                    $this->logger->warning(sprintf(
                        'question_id %s has broken serialization: %s; skipping',
                        $questionRow['question_id'],
                        $e->getMessage()
                    ));

                    continue;
                }

                /* Decompile config
                 */
                $questionRow['config'] = $this->decompileQuestionConfig($questionRow);

                /* Stack it up
                 */
                $sectionRow['_questions'][] = $questionRow;
            }

            $quizRow['_sections'][] = $sectionRow;
        }

        return $quizRow;
    }


    /**
     * Question Configurations hid some settings which we can use in Learnosity Land
     *
     * @param array $question
     * @return mixed
     * @throws \JsonException
     */
    private function decompileQuestionConfig(array $question): mixed
    {
        if ('' !== (string)$question['config']) {
            return json_decode($question['config'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $question['config'];
    }


    /**
     * Certain question types were supposed to have serialized answers
     * These were never really treated like a codec and the handlers
     * are intertwined with phplibs, which is annoying
     *
     * @param array $question
     * @param array $answer
     * @return mixed
     * @throws \RuntimeException
     */
    private function decompileAnswer(array $question, array $answer): mixed
    {
        /* Quiz only uses types 2, 4 and 5
         * (6,8,9,10,17 are all Survey)
         */
        return $this->strategyFactory->produce((int)$question['type_id'])?->decompileAnswer($answer);
    }
}
