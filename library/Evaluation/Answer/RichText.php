<?php
/**
 * Evaluation_Answer_RichText
 *
 * Handler for Rich Text questions.
 * It's the same as Short Answer, except with a unique UI
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Answer_RichText extends Evaluation_Answer_Short {

	/**
	 * Javascript class to use when creating UI
	 *
	 * @var string
	 */
	protected $plugin = 'evaluationRichText';

	/**
	 * Exports the answer to Moodle XML format. This is a modified version of
	 * $this->reviewResponse()
	 *
	 * @param  $page
	 * @param  $question_id
	 * @return void
	 */
	public function exportMoodleAnswer($page, $question_id) {
		$currentAnswer = $this->getByQuestionID($question_id);

		/* Show correct answer
		 */
		$page->setVar(
			array(
				'CORRECT_ANSWER' => Scrub::html($currentAnswer['answer'])
			)
		);

		$page->parse('answerOption_block', 'answerOption', true);
	}

	/**
	 * Show the response someone submitted and also show the correct answer.
	 * Provide an option for the teacher to review the mark and adjust.
	 *
	 * @param  $page
	 * @param  $question_id
	 * @return void
	 */
	public function reviewResponse($page, $question_id) {
		$existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

		$currentAnswer = $this->getByQuestionID($question_id);

		/* Removed this check because it was wrongly matching a blank saved
		   * response with no response at all, preventing reviewed marks from
		   * displaying properly.
		   *
		   * if ($existing['response'] != null) { ... } else { ... }
		   */
		$page->setVar(
			array(
				'ANSWER_ID' => $currentAnswer['answer_id'],
				'ANSWER' => Scrub::html($existing['response']),
				'RESPONSE_ID' => $existing['response_id'],
				'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
				'REVIEWED_MARKS' => $existing['mark'],
			)
		);

		/* Show correct answer
		   */
		$page->setVar(
			array(
				'CORRECT_ANSWER' => Scrub::html($currentAnswer['answer'])
			)
		);

		$page->parse('answerOption_block', 'answerOption', true);
	}
}
