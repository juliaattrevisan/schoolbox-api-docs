<?php
/**
 * Handler for Matching Pairs questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class AnswerMatch extends EvaluationAnswer {
    const CORRECT_CLASS = 'matchCorrect';
    const INCORRECT_CLASS = 'matchIncorrect';

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

        $numItems = 0;

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
     * Updates an existing Answer.
     *
     * @param <type> $object
     */
    public function update($object) {
        $query = "DELETE FROM {$this->_table}
                  WHERE question_id = '$object[question_id]'";

        $this->db->query($query);

        $this->create($object, $object['question_id']);
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

            $page->setVar(array(
                'ANSWER_ID' => $answerGroup['answer_id']
            ));

            if ($answerGroup) {
                $allAnswers = unserialize($answerGroup['answer']);

                foreach($allAnswers as $currentAnswer) {
                    $page->setVar(array(
                        'ANSWER_LEFT_VALUE' => $currentAnswer->answerLeft,
                        'ANSWER_RIGHT_VALUE' => $currentAnswer->answerRight,
                    ));

                    $page->parse('answerOption_block', 'answerOption', true);
                }
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
     * TODO: If a response for the current user and attempt already exists,
     * prefill it.
     *
     * @param <type> $page
     * @param <type> $question_id
     */
    public function prepareResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        /* Define special blocks to handle this special answerOptions scenario,
         * because we have two columns to parse per iteration.
         */
        $page->setBlock('answerFormFragment', 'answerOptionLeft', 'answerOptionLeft_block');
        $page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');

        $left = array();
        $right = array();

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $answerCount = 0;
            $allAnswers = unserialize($answerGroup['answer']);

            foreach ($allAnswers as $currentAnswer) {
                $left[] = $currentAnswer->answerLeft;
                $right[] = $currentAnswer->answerRight;

                $answerCount++;
            }

            if ($existing['response']) {
                $right = unserialize($existing['response']);
            }
            else {

                /* Randomize right-side. Do this multiple times because it's not
                 * very random after one shuffle for some reason
                 */
                shuffle($right);
                shuffle($right);
                shuffle($right);
            }

            for ($i=0; $i<$answerCount; $i++) {
                $page->setVar(array(
                    'ANSWER_LEFT_VALUE' => $left[$i],
                    'ANSWER_RIGHT_VALUE' => $right[$i],
                ));

                $page->parse('answerOptionLeft_block', 'answerOptionLeft', true);
                $page->parse('answerOptionRight_block', 'answerOptionRight', true);
            }
        }

        /* Parse question number, so you can have multiple sortable instances
         */
        $page->setVar(array(
            'MATCHPAIRS_QUESTION_ID' => $question_id
        ));
    }

	/**
	 * Clear child blocks after parsing out the parent. Keep this in its own
	 * method for polymorphic algorithm
	 *
	 * @param  $page
	 * @return void
	 */
	public function cleanupParse($page) {
		$page->setVar('answerOptionLeft_block', '');
		$page->setVar('answerOptionRight_block', '');
	}

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    public function saveResponse($data) {
        $processedResponse = serialize($data['answerRight']);

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
        $page->setBlock('answerFormFragment', 'answerCorrect', 'answerCorrect_block');

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            for($i=0; $i<count($allAnswers); $i++) {
                $currentAnswer = $allAnswers[$i];

                $page->setVar(array(
                    'ANSWER_LEFT_VALUE' => $currentAnswer->answerLeft,
                    'ANSWER_RIGHT_VALUE' => $currentAnswer->answerRight,
                ));

                $page->parse('answerCorrect_block', 'answerCorrect', true);
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
        foreach($xmlObj->subquestion as $currentAnswer) {

            /* Set up data object in accordance with $this->create();
             */
            $answer['answerLeft'] = trim((string) $currentAnswer->text);
            $answer['answerRight'] = trim((string) $currentAnswer->answer);

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
			if ($this->getLegendHTML() !== false) {
				$data['responseText'] = '<ul><li>' . implode('</li><li>', $data) . '</li></ul>';
			}
			else {
				$data['responseText'] = strip_tags(implode(', ', $data));
			}
		}

		/* Append commentary
 		 */
		$data['comment'] = $response['comment'];

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
