<?php
/**
 * Evaluation_Response
 *
 * Abstract interface for Responses
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Response extends DeprecatedModel
{
    public $_table = 'assess_response';
    public $_index = 'response_id';

    /* A const to determine whether to randomise questions
     */
    const RANDOMISE_QUESTIONS = 1;
    const ORDERED_QUESTIONS = 2;

    /**
     * Finds out whether a response exists for a particular question during an
     * attempt.
     *
     * @param  $attempt_id
     * @param  $question_id
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
     * @param  $attempt_id
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
                  AND automarked_already = 'false'";

        return $this->db->getAll($query);
    }

    /**
     * Remember which responses have been marked automatically, so we don't keep
     * refetching them every time the results page loads.
     *
     * @param  $markedResponseIDs
     * @return void
     */
    public function updatedAutomatedMarks($markedResponseIDs)
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
     * Find the number of STUB RESPONSES that have been set up in $attempt_id
     * and $section_id
     *
     * @param  $attempt_id
     * @param  $section_id
     *
     * @return mixed
     */
    public function numStubResponsesInSection($attempt_id, $section_id)
    {
        $query = "SELECT COUNT(*) AS num
                  FROM {$this->_table}
                  WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  AND section_id = " . $this->db->smartQuote($section_id);

        return $this->db->getOne($query);
    }

    /**
     * Assign random questions to a section, so we can remember them and
     * navigate back and forth without losing questions during an attempt
     *
     * @param  $attempt_id
     * @param  $section_id
     * @param  $limit
     * @param bool $randomise
     * @return void
     */
    public function assignQuestions($attempt_id, $section_id, $limit, $randomise = false)
    {
        $query = "SELECT question_id
                  FROM assess_question
                  WHERE section_id = " . $this->db->smartQuote($section_id) . "
                  AND deleted_at IS NULL
                  ORDER BY sequence";

        $allQuestions = $this->db->getAll($query);

        /* Optionally randomise
         */
        if ($randomise === self::RANDOMISE_QUESTIONS) {
            shuffle($allQuestions);
            shuffle($allQuestions);
			shuffle($allQuestions);
        }

        /* Insert selected questions for this attempt
         */
        for ($i = 0; $i < count($allQuestions); $i++) {
            $new = array(
                'attempt_id' => $attempt_id,
                'section_id' => $section_id,
                'question_id' => $allQuestions[$i]['question_id'],
            );

            if ((($limit > 0) && ($i < $limit)) || ($limit == 0)) {
                parent::create($new);
            }
        }
    }

    /**
     * Find out when an attempt's responses were last updated, so we can see
     * when the student last saved data.
     *
     * @param  $attempt_id
     *
     * @return mixed
     */
    public function getLastSave($attempt_id)
    {
        $query = "SELECT updated_at
                  FROM {$this->_table}
                  WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
                  ORDER BY updated_at DESC";

        return $this->db->getOne($query);
    }

    /**
     * Count the number of questions that were automarked AND not reviewed.
     * This indicates questions that remain to be reviewed manually.
     *
     * @param  $attempt_id
     *
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
     * @param  $attempt_id
     *
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
                  AND automarked_already = 'false'";

        return $this->db->getOne($query);
    }

    /**
     * The total number of marks that were not automarked AND not reviewed.
     *
     * @param  $attempt_id
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
                  AND automarked_already = 'false'";

        $value = $this->db->getOne($query);

        if ($value) {
            return $value;
        }

        return 0;
    }

    /**
     * Mark as deleted all the responses for an attempt.
     *
     * @param  $attempt_id
     *
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
     * @param  $attempt_id
     * @param  $question_id
     * @return array|bool
     */
    public function getByAttemptAndQuestion($attempt_id, $question_id)
    {
        $query = "
			SELECT *
			FROM assess_response
			WHERE attempt_id = " . $this->db->smartQuote($attempt_id) . "
			AND question_id = " . $this->db->smartQuote($question_id);

        return $this->db->getRow($query);
    }

    /**
     * Fetches responses to a question, used by ranking question type for graphing
     *
     * @param $question_id
     * @return array
     */
    public function getResponsesToQuestion($question_id) {
        $query = "SELECT * FROM assess_response WHERE question_id = " . $this->db->smartQuote($question_id)." AND deleted_at IS NULL";
        return $this->db->getAll($query);
    }
}
