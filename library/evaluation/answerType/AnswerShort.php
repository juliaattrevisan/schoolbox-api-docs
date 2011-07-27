<?php
/**
 * Handler for Short Answer questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

abstract class AnswerShort extends EvaluationAnswer {

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

        /* 'Short' always has only one answer per question so we can override
         * the sequence here
         */
        $answer['sequence'] = 1;

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
        $answer = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($answer);
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
            $currentAnswer = parent::getForQuestion($question_id);

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $currentAnswer['answer'],
            ));
        }
        else {
            $page->setVar(array(
                'ANSWER_ID' => '',
                'ANSWER' => '',
            ));
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

        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $existing['response'],
            ));
        }
        else {
            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => '',
            ));
        }

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    public function saveResponse($data) {
        $processedResponse = $data['answer'];

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
        $currentAnswer = $this->getByQuestionID($question_id);

        /* Show correct answer
         */
        $page->setVar(array(
            'CORRECT_ANSWER' => $currentAnswer['answer']
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param <type> $obj
     */
    public function importMoodleAnswer($xmlObj, $question_id) {
        $object = array(
            'answer_id' => '',
            'question_id' => $question_id,
            'sequence' => 1,
            'answer' => trim((string) $xmlObj->answer->text),
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

		/* Remove tab characters
		 */
		$data = str_replace("\t", '', $response['response']);

		return $data;
	}
}
?>
