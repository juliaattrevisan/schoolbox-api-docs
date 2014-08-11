<?php
/**
 * Evaluation_Question_Bank
 *
 * Abstract interface for all question banks
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Evaluation_Question_Bank extends DeprecatedModel
{
    const MATCHING_PAIRS_TYPE_ID = 4;

    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';

    /**
     * Get a list of all the questions in the question bank, and show what types
     * they are.
     *
     * @param  $category_id
     * @return array|bool
     */
    public function getContents($category_id)
    {
        $query = "SELECT
			assess_question_bank.question_bank_id,
			assess_question_bank.question,
			assess_luQuestionType.description,
			assess_question_bank.marks,
			assess_question_bank.type_id,
			assess_question_bank.answer
			FROM assess_question_bank
			LEFT JOIN assess_luQuestionType ON assess_question_bank.type_id = assess_luQuestionType.type_id
			WHERE question_category_id = " . $this->db->smartQuote($category_id) . " AND assess_question_bank.deleted_at IS NULL";

        return $this->db->getAll($query);
    }

    /**
     * Adds a question to the question bank, by copying its question text and
     * answer.
     *
     * @param  $questionCategoryID
     * @param  $typeID
     * @param  $questionID
     * @param  $answerID
     * @param  $marks
     * @return void
     */
    public function addQuestion($questionCategoryID, $typeID, $questionID, $answerID, $marks)
    {
        $question = $this->getQuestionHandler()->getByID($questionID);
        $answer = $this->getAnswerHandler()->getByID($answerID);

        $this->create(array(
            'question_category_id' => $questionCategoryID,
            'type_id' => $typeID,
            'question' => $question['question'],
            'answer' => $answer['answer'],
            'marks' => $marks,
			'config' => $question['config'],
        ));
    }
}
