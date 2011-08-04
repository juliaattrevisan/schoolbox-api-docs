<?php
/**
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

abstract class AnswerMulti extends EvaluationAnswer {

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param <type> $object
     * @return <type>
     */
    public function create($object, $question_id) {
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
    public function update($object) {
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
    public function modify($page, $question_id = '') {
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
    public function prepareResponse($page, $question_id) {
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
				else {
					$page->setVar(array(
						'ANSWER_CHECKED' => '',
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
	 * Clear child blocks after parsing out the parent. Keep this in its own
	 * method for polymorphic algorithm
	 *
	 * @param  $page
	 * @return void
	 */
	public function cleanupParse($page) {
	}

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    public function saveResponse($data) {
        $processedResponse = serialize($data['answer']);

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param <type> $page
     * @param <type> $question_id
     */
    public function exportMoodleAnswer($page, $question_id) {
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
    public function importMoodleAnswer($xmlObj, $question_id) {
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

	/**
	 * Gets the text associated with an array-based multiple choice answer
	 *
	 * @param  $question_id
	 * @param  $value
	 * @return string
	 */
	public function getAnswerText($question_id, $value) {
		$answer = $this->getByQuestionID($question_id);

		$allOptions = unserialize($answer['answer']);

		foreach($allOptions as $currentOption) {
			if ($currentOption->value == $value) {
				return $currentOption->description;
			}
		}

		return '';
	}

	/**
	 * Get the response for a particular question
	 *
	 * @param <type> $response_id
	 */
	public function exportResponseToArray($attempt_id, $question_id) {
		$response = $this->responseHandler->getByAttemptAndQuestion($attempt_id, $question_id);

		$data = unserialize($response['response']);

		return $data;
	}
}
?>
