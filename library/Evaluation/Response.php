<?php

/**
 * Evaluation_Response
 *
 * Abstract interface for Responses
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Response extends DeprecatedModel
{
    /**
     * A const to determine whether to randomise questions
     */
    public const RANDOMISE_QUESTIONS = 1;

    /**
     * A const to determine whether to keep the original ordering of questions
     */
    public const ORDERED_QUESTIONS = 2;

    /**
     * @var string
     */
    public $_table = 'assess_response';

    /**
     * @var string
     */
    public $_index = 'response_id';

    /**
     * Finds out whether a response exists for a particular question during an
     * attempt.
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return array|bool
     */
    public function isResponseExists($attempt_id, $question_id)
    {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND question_id = " . $this->db->smartQuote($question_id);

        return $this->db->getRow($query);
    }

    /**
     * Gets all the responses to a particular assessment and attempt which can
     * be marked automatically. The marking is performed by the answerType's
     * handler.
     *
     * @param int $attempt_id
     * @return array|bool
     */
    public function getAutomarkableResponses($attempt_id)
    {
        $query = "SELECT
                      assess_id, response_id,
                      assess_attempt.attempt_id,
                      {$this->_table}.question_id,
                      assess_luQuestionType.type_id,
                      automated_marking, marks, response, answer
                  FROM {$this->_table}
                  LEFT JOIN assess_attempt ON {$this->_table}.attempt_id = assess_attempt.attempt_id
                  LEFT JOIN assess_question ON assess_question.question_id = {$this->_table}.question_id
                  LEFT JOIN assess_luQuestionType ON assess_luQuestionType.type_id = assess_question.type_id
                  LEFT JOIN assess_answer ON assess_answer.question_id = assess_question.question_id
                  WHERE assess_attempt.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND automated_marking = 'true'
                  AND (automarked_already = 'false' OR automarked_already IS NULL)";

        return $this->db->getAll($query);
    }

    /**
     * Remember which responses have been marked automatically, so we don't keep
     * refetching them every time the results page loads.
     *
     * @param array $markedResponseIDs
     * @return void
     */
    public function updatedAutomatedMarks(array $markedResponseIDs)
    {
        if (count($markedResponseIDs)) {
            $ids = implode(',', $markedResponseIDs);

            $query = "UPDATE {$this->_table}
                      SET automarked_already = 'true'
                      WHERE response_id IN ($ids)";

            $this->db->query($query);
        }
    }


    /**
     * Assign random questions to a section, so we can remember them and
     * navigate back and forth without losing questions during an attempt
     *
     * @param int $attempt_id
     * @param int $section_id
     * @param int $randomPoolSize 0 indicates to use all available
     * @param int $randomise
     * @return void
     */
    public function assignQuestions($attempt_id, $section_id, $randomPoolSize, $randomise = self::ORDERED_QUESTIONS)
    {
        // Ensure correct data type
        $randomPoolSize = (int)$randomPoolSize;

        $query = "SELECT question_id
                  FROM assess_question
                  WHERE section_id = " . $this->db->smartQuote($section_id) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        $allQuestions = $this->db->getAll($query) ?: [];

        /* Optionally randomise
         */
        if ($randomise === self::RANDOMISE_QUESTIONS) {
            shuffle($allQuestions);
            shuffle($allQuestions);
            shuffle($allQuestions);
        }

        /* Insert selected questions for this attempt
         */
        $i = 0;
        foreach ($allQuestions as $question) {
            // Are we limiting the pool size, and we've reached our limit?
            if (Evaluation_Section::POOL_SIZE_ALL !== $randomPoolSize && $i++ === $randomPoolSize) {
                break;
            }
            $new = [
                'attempt_id'  => $attempt_id,
                'section_id'  => $section_id,
                'question_id' => $question['question_id'],
            ];
            parent::create($new);
        }
    }


    /**
     * Count the number of questions that were automarked AND not reviewed.
     * This indicates questions that remain to be reviewed manually.
     *
     * @param int $attempt_id
     * @return mixed
     */
    public function numAutomarked($attempt_id)
    {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  LEFT JOIN assess_attempt ON assess_attempt.attempt_id = {$this->_table}.attempt_id
                  LEFT JOIN assess_question ON assess_question.question_id = {$this->_table}.question_id
                  WHERE {$this->_table}.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_attempt.reviewed_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  AND automarked_already = 'true'";

        return $this->db->getOne($query);
    }

    /**
     * Count the number of questions that were not automarked AND not reviewed.
     * This indicates questions that remain to be reviewed manually.
     *
     * @param int $attempt_id
     * @return mixed
     */
    public function numNotAutomarked($attempt_id)
    {
        $query = "SELECT COUNT(*)
                  FROM {$this->_table}
                  LEFT JOIN assess_attempt ON assess_attempt.attempt_id = {$this->_table}.attempt_id
                  LEFT JOIN assess_question ON assess_question.question_id = {$this->_table}.question_id
                  WHERE {$this->_table}.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_attempt.reviewed_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  AND (automarked_already = 'false' OR automarked_already IS NULL)";

        return $this->db->getOne($query);
    }

    /**
     * The total number of marks that were not automarked AND not reviewed.
     *
     * @param int $attempt_id
     * @return int
     */
    public function valueNotAutomarked($attempt_id)
    {
        $query = "SELECT SUM(assess_question.marks)
                  FROM assess_question
                  LEFT JOIN {$this->_table} ON assess_question.question_id = {$this->_table}.question_id
                  LEFT JOIN assess_attempt ON assess_attempt.attempt_id = {$this->_table}.attempt_id
                  WHERE {$this->_table}.attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND assess_attempt.reviewed_at IS NULL
                  AND assess_question.deleted_at IS NULL
                  AND (automarked_already = 'false' OR automarked_already IS NULL)";

        $value = $this->db->getOne($query);

        if ($value) {
            return $value;
        }

        return 0;
    }

    /**
     * Mark as deleted all the responses for an attempt.
     *
     * @param int $attempt_id
     * @return mixed
     */
    public function deleteResponsesForAttempt($attempt_id)
    {
        $query = "UPDATE {$this->_table}
                  SET deleted_at = NOW()
                  WHERE attempt_id = " . $this->db->smartQuote($attempt_id);

        return $this->db->query($query);
    }

    /**
     * Get a response for a particular attempt/question
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return array|bool
     */
    public function getByAttemptAndQuestion($attempt_id, $question_id)
    {
        $query = "
            SELECT *
            FROM assess_response
            WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
            AND question_id = " . $this->db->smartQuote($question_id) . "
            AND assess_response.deleted_at IS NULL";

        return $this->db->getRow($query);
    }

    /**
     * Fetches responses to a question, used by ranking question type for graphing
     *
     * @param int $question_id
     * @return array
     */
    public function getResponsesToQuestion($question_id)
    {
        $query = "SELECT * FROM assess_response WHERE question_id = " . $this->db->smartQuote($question_id) . " AND deleted_at IS NULL";
        return $this->db->getAll($query);
    }
}
