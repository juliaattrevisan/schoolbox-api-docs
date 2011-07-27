<?php

abstract class EvaluationQuestionBank extends DeprecatedModel {
    public $_table = 'assess_question_bank';
    public $_index = 'question_bank_id';

	/**
	 * Get a list of all the questions in the question bank, and show what types
	 * they are, and what categories they belong to
	 *
	 * @return array|bool
	 */
	public function getContents() {
		$query = "SELECT
			assess_question_bank.question_bank_id,
			assess_question_category.question_category_id,
			assess_question_category.name,
			assess_question_bank.question,
			assess_luQuestionType.description,
			assess_question_bank.marks
			FROM assess_question_bank
			LEFT JOIN assess_question_category ON assess_question_bank.question_category_id = assess_question_category.question_category_id
			LEFT JOIN assess_luQuestionType ON assess_question_bank.type_id = assess_luQuestionType.type_id
			WHERE assess_question_bank.deleted_at IS NULL
			ORDER BY name";

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
	 * @return void
	 */
	public function addQuestion($questionCategoryID, $typeID, $questionID, $answerID, $marks) {
		$question = $this->questionHandler->getByID($questionID);
		$answer = $this->answerHandler->getByID($answerID);

		$this->create(array(
			'question_category_id' => $questionCategoryID,
			'type_id' => $typeID,
			'question' => $question['question'],
			'answer' => $answer['answer'],
			'marks' => $marks
		));
	}
}
?>
