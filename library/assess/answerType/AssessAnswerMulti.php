<?php
/**
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

class AssessAnswerMulti extends AnswerMulti {
    const CORRECT_CLASS = 'multiCorrect';
    const INCORRECT_CLASS = 'multiIncorrect';

	/**
	 * Get an instance of a responseHandler upon instantiation
	 */
    public function __construct() {
        parent::__construct();

        $this->responseHandler = new AssessResponse();
    }

	/*
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     */
    public function mark($currentResponse) {
        $response = unserialize($currentResponse['response']);
        $answer = unserialize($currentResponse['answer']);

        /* Find out how much each correct answer is worth
         *
         * i: the actual number of correct responses for the question
         * numCorrect: the number of correct responses submitted
         */
        $i = 0;
        $numCorrect = 0;

		if (is_array($answer)) {
			foreach($answer as $currentAnswer) {
				if ($currentAnswer->correct) {

					/* If the user responded with a correct answer, accumulate
					 */
					if (is_array($response) && in_array($currentAnswer->value, $response)) {
						$numCorrect++;
					}

					$i++;
				}
			}
		}

        /* If the student selects exactly the correct responses then give them
         * the marks, otherwise penalise them. The teacher can review and
         * adjust the mark accordingly
         */
        if ($numCorrect == count($response)) {
            if ($i) {
                $marksPerAnswer = $currentResponse['marks']/$i;
                $total = $marksPerAnswer * $numCorrect;
            }
        }
        else {
            $total = 0;
        }

        /* Total marks for this question
         */
        $marks = array(
            'response_id' => $currentResponse['response_id'],
            'mark' => $total,
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
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
        }

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(array(
                'ANSWER_ID' => $answerGroup['answer_id'],
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => $existing['comment'],
                'REVIEWED_MARKS' => $existing['mark'],
            ));

            foreach($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => 'correct-answer'
                    ));
                }
                else {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => ''
                    ));
                }

                $page->setVar(array(
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => $currentAnswer->description,
                ));

                if ($existing['response']) {
                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'checked=\'checked\'',
                        ));

                        /* Was this a correctly-selected response? If so apply a
                         * special class to it. The three cases are:
                         *
                         * Correct response, selected
                         * Incorrect response, selected
                         * Unselected
                         */
                        if ($currentAnswer->correct && in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => self::CORRECT_CLASS,
                            ));
                        }
                        else if (in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => self::INCORRECT_CLASS,
                            ));
                        }
                        else {
                            $page->setVar(array(
                                'CORRECT' => ''
                            ));
                        }
                    }
                    else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                            'CORRECT' => ''
                        ));
                    }
                }
                else {
                    $page->setVar(array(
                        'CORRECT' => ''
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        }
        else {
            $page->setVar('answerOption_block', '');
        }
    }
}
?>
