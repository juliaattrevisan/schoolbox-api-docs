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
	 * Handler
	 *
	 * @var Assess_Question
	 */
	protected $questionHandler = null;

	/**
	 * Handler
	 *
	 * @var null
	 */
	protected $answerHandler = null;

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
	 * @param  $questionHandler
     * @param  $questionCategoryID
     * @param  $questionID
     * @return void
     */
    public function addQuestion($questionHandler, $questionCategoryID,  $questionID) {

		/* Raw questionHandler context, to get the question info
		 */
        $question = $questionHandler->getByID($questionID);
        $answer   = $this->getAnswerHandler()->getByQuestionID($questionID);

        $this->create(array(
            'question_category_id' => $questionCategoryID,
            'type_id' => $question['type_id'],
            'question' => $question['question'],
            'answer' => $answer['answer'],
            'marks' => $question['marks'],
			'config' => $question['config'],
        ));
    }

	/**
	 * Make the assess_question_bank table behave like assess_question
	 */
	protected function setContext() {
		$this->getQuestionHandler()->_table = 'assess_question_bank';
		$this->getQuestionHandler()->_index = 'question_bank_id';

		/* Use context-specific fields
		 */
		$this->getQuestionHandler()->setAllowedFields(array(
			'question_bank_id', 'question_category_id', 'type_id',
			'question', 'answer', 'marks',
			'config', 'deleted_at'
		));
	}

	/* Getters/Setters
 	 */

	/**
	 * Set question handler
	 *
	 * @param \Evaluation_Question|null $questionHandler
	 */
	protected function setQuestionHandler($questionHandler) {
		$this->questionHandler = $questionHandler;
	}

	/**
	 * Get question handler
	 *
	 * @return \Evaluation_Question|null
	 */
	public function getQuestionHandler() {
		return $this->questionHandler;
	}

	/**
	 * Set answer handler
	 *
	 * @param Assess_Answer $answerHandler
	 */
	protected function setAnswerHandler($answerHandler) {
		$this->answerHandler = $answerHandler;
	}

	/**
	 * Get answer handler
	 *
	 * @return Assess_Answer
	 */
	public function getAnswerHandler() {
		return $this->answerHandler;
	}
}
