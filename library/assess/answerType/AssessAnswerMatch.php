<?php
/**
 * Handler for Matching Pairs questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

class AssessAnswerMatch extends AnswerMatch {

	/**
	 * Automatically marks a response. Customised to be applicable to the way in
	 * which the response is actually stored.
	 */
	public function mark($currentResponse) {
		$response = unserialize($currentResponse['response']);
		$answer = unserialize($currentResponse['answer']);

		$marksPerAnswer = $currentResponse['marks'] / count($answer);

		/* Find out how much each correct answer is worth
		 */
		for ($i=0, $numCorrect=0, $totalMarks=0; $i < count($answer); $i++) {
			if ($answer[$i]->answerRight == $response[$i]) {
				$totalMarks += $marksPerAnswer;
				$numCorrect++;
			}
		}

		/* Total marks for this question
		 */
		$marks = array(
			'response_id' => $currentResponse['response_id'],
			'mark' => $totalMarks,
		);

		$this->responseHandler->update($marks);
	}

	/**
	 * Show the response someone submitted and also show the correct answer.
	 * Provide an option for the teacher to review the mark and adjust.
	 *
	 * @param <type> $page
	 * @param <type> $question_id
	 */
	public function reviewResponse($page, $question_id) {
		$right = array();

		$existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

		if ($existing['response']) {
			$right = unserialize($existing['response']);
		}

		/* Define special blocks to handle this special answerOptions scenario,
		 * because we have three columns to parse per iteration.
		 */
		$page->setBlock('answerFormFragment', 'answerOptionLeft', 'answerOptionLeft_block');
		$page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');
		$page->setBlock('answerFormFragment', 'answerOptionCorrectResponse', 'answerOptionCorrectResponse_block');

		$answerGroup = $this->getByQuestionID($question_id);

		if ($answerGroup) {
			$page->setVar(array(
				'ANSWER_ID' => $answerGroup['answer_id'],
				'RESPONSE_ID' => $existing['response_id'],
				'REVIEWED_COMMENT' => $existing['comment'],
				'REVIEWED_MARKS' => $existing['mark'],
			));

			$allAnswers = unserialize($answerGroup['answer']);

			for($i=0; $i<count($allAnswers); $i++) {
				$currentAnswer = $allAnswers[$i];

				if ($right[$i] == $currentAnswer->answerRight) {
					$correct = self::CORRECT_CLASS;
				}
				else {
					$correct = self::INCORRECT_CLASS;
				}

				$page->setVar(array(
					'ANSWER_LEFT_VALUE' => $currentAnswer->answerLeft,
					'ANSWER_RIGHT_VALUE' => $right[$i],
					'ANSWER_CORRECT_RESPONSE_VALUE' => $currentAnswer->answerRight,
					'CORRECT' => $correct,
				));

				$page->parse('answerOptionLeft_block', 'answerOptionLeft', true);
				$page->parse('answerOptionRight_block', 'answerOptionRight', true);
				$page->parse('answerOptionCorrectResponse_block', 'answerOptionCorrectResponse', true);
			}
		}
	}
}
?>
