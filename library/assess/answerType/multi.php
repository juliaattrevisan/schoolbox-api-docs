<?php
/**
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

class AssessAnswerMulti extends AssessAnswer {
    const CORRECT_CLASS = 'multiCorrect';
    const INCORRECT_CLASS = 'multiIncorrect';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param <type> $object
     * @return <type>
     */
    function create($object, $question_id) {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));
        $answer['question_id'] = $question_id;
        $answer['sequence'] = 1;

        foreach(json_decode($object['allAnswers']) as $currentAnswer) {
            $answer['answer'][] = $currentAnswer;
            $answer['sequence']++;
        }

        $answer['answer'] = serialize($answer['answer']);

        $answer_id = parent::create($answer);

        if (!$answer_id) {
            return false;
        }

        return $answer_id;
    }

    /**
     * Updates an existing Answer. In this particular case, delete previous
     * options and recreate new ones due to preprocessing requirement
     *
     * @param <type> $object
     */
    function update($object) {
        $question_id = scrubTEXT($_POST['question_id']);

        $query = "DELETE FROM {$this->_table}
                  WHERE question_id = '$question_id'";

        $this->db->query($query);

        $this->create($object, $question_id);
    }

    /**
     * Renders answers when editing a Question. If a question_id was provided,
     * load its existing answers and populate fields; otherwise empty them or
     * set to other default values.
     *
     * @param <type> $page
     */
    function modify($page, $question_id = '') {
        if (is_numeric($question_id)) {
            $answerGroup = parent::getForQuestion($question_id);

            if ($answerGroup) {
                $allAnswers = unserialize($answerGroup['answer']);

                foreach($allAnswers as $currentAnswer) {
                    $page->setVar(array(
                        'ANSWER_VALUE' => $currentAnswer->value,
                        'ANSWER_DESCRIPTION' => $currentAnswer->description,
                        'ANSWER_CHECKED' => $currentAnswer->correct != '' ? 'checked=\'checked\'' : ''
                    ));

                    $page->parse('answerOption_block', 'answerOption', true);
                }

                $page->setVar(array(
                    'ANSWER_ID' => $answerGroup['answer_id']
                ));
            }
            else {
                $page->setVar('answerOption_block', '');
            }
        }
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * @param <type> $page
     * @param <type> $question_id
     */
    function prepareResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            foreach($allAnswers as $currentAnswer) {
                $page->setVar(array(
                    'ANSWER_ID' => $answerGroup['answer_id'],
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => $currentAnswer->description,
                ));

                if ($existing['response']) {
                    $existingAnswer = unserialize($existing['response']);

                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'checked=\'checked\'',
                        ));
                    }
                    else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                        ));
                    }
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        }
        else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    function saveResponse($data) {
        $processedResponse = serialize($data['answer']);

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     */
    function mark($currentResponse) {
        $response = unserialize($currentResponse['response']);
        $answer = unserialize($currentResponse['answer']);

        $marksPerAnswer = 0;

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
    function reviewResponse($page, $question_id) {
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

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param <type> $page
     * @param <type> $question_id
     */
    function exportMoodleAnswer($page, $question_id) {
        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            foreach($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => '100'
                    ));
                }
                else {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => '0'
                    ));
                }

                $page->setVar(array(
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => $currentAnswer->description,
                ));
                
                $page->parse('answerOption_block', 'answerOption', true);
            }
        }
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param <type> $obj
     */
    function importMoodleAnswer($xmlObj, $question_id) {
        foreach($xmlObj->answer as $currentAnswer) {

            /* Set up data object in accordance with $this->create();
             */
            $answer['description'] = trim((string) $currentAnswer->text);
            $answer['value'] = trim((string) $currentAnswer->value);
            $answer['correct'] = 0;

            /* Indicate this is a correct answer
             */
            if ($currentAnswer->attributes()->fraction > 0) {
                $answer['correct'] = 1;
            }

            $allAnswers[] = $answer;
        }

        $object = array(
            'answer_id' => '',
            'question_id' => $question_id,
            'sequence' => 1,
            'allAnswers' => json_encode($allAnswers),
        );

        return $this->create($object, $question_id);
    }

	/**
	 * Get the response for a particular question
	 *
	 * @param <type> $response_id
	 */
	public function exportResponseToCSV($attempt_id, $question_id) {
		$response = $this->responseHandler->getByAttemptAndQuestion($attempt_id, $question_id);

		$data = unserialize($response['response']);

		if (is_array($data)) {
			$data = implode(', ', $data);
		}

		return $data;
	}
}
?>