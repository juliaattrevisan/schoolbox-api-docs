<?php

/**
 * Assess_Question
 *
 * All QuestionTypes inherit from this class. At time of
 * writing, there is nothing needed in the children QuestionType classes.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Assess_Question extends Evaluation_Question
{

    public function __construct()
    {
        parent::__construct();
        $this->setSectionHandler(new Assess_Section());
    }

    /**
     * Get all available question types.
     *
     * @return array|bool
     */
    public function getAllQuestionTypes()
    {
        $query = "SELECT *
                  FROM assess_luQuestionType
                  WHERE availability = 'all'
                  OR availability = 'assess'
                  ORDER BY type_id";

        return $this->db->getAll($query);
    }

    /**
     * Get the next question in the sequence
     *
     * @param int $assessId
     * @param int $questionId
     * @return array|bool
     */
    public function getNextQuestion($assessId, $questionId)
    {
        $query = "
			SELECT *
			FROM assess_question
			LEFT JOIN assess_section ON assess_question.section_id = assess_section.section_id
			WHERE assess_section.assess_id = '{$assessId}'
			ORDER BY assess_question.section_id, assess_question.sequence
		";

        $allQuestions = $this->db->getAll($query);

        /* PHP traversal because assess_question.sequence resets to 1 for each section
         * This makes navigation across sections non-trivial with a simple "next" link
         */
        if (is_array($allQuestions) && count($allQuestions)) {
            for ($i = 0; $i < count($allQuestions); $i++) {
                if (($allQuestions[$i]['question_id'] == $questionId) && isset($allQuestions[$i + 1])) {
                    return $allQuestions[$i + 1];
                }
            }
        }

        return false;
    }

    /**
     * Get the previous question in the sequence
     *
     * @param int $assessId
     * @param int $questionId
     * @return array|bool
     */
    public function getPreviousQuestion($assessId, $questionId)
    {
        $query = "
			SELECT *
			FROM assess_question
			LEFT JOIN assess_section ON assess_question.section_id = assess_section.section_id
			WHERE assess_section.assess_id = '{$assessId}'
			ORDER BY assess_question.section_id, assess_question.sequence
		";

        $allQuestions = $this->db->getAll($query);

        /* PHP traversal because assess_question.sequence resets to 1 for each section
         * This makes navigation across sections non-trivial with a simple "next" link
         */
        if (is_array($allQuestions) && count($allQuestions)) {
            for ($i = 0; $i < count($allQuestions); $i++) {
                if (($allQuestions[$i]['question_id'] == $questionId) && isset($allQuestions[$i - 1])) {
                    return $allQuestions[$i - 1];
                }
            }
        }

        return false;
    }
}
