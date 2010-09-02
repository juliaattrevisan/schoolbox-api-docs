<?php
require_once '../include/model.php';
require_once('../library/assess/assess-response.php');

/**
 * Handler for Short Answer questions.
 */
class AssessAnswerShort extends AssessAnswer {

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
    function update($object) {
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
    function renderAnswers($page, $question_id = '') {
        if (is_numeric($question_id)) {
            $allAnswers = parent::getForQuestion($question_id);

            foreach($allAnswers as $currentAnswer) {
                $page->setVar(array(
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => $currentAnswer['answer'],
                ));
            }
        }
        else {
            $page->setVar(array(
                'ANSWER_ID' => '',
                'ANSWER' => '',
            ));
        }
    }

    /**
     * Gets an answer(s) for a particular Question. This is used when attempting
     * the test and might be customised for each Answer type.
     * 
     * @param <type> $question_id
     * @return <type>
     */
    function getByQuestionID($question_id) {
        $query = "SELECT *
                  FROM {$this->_table}
                  WHERE question_id = '$question_id'
                  ORDER BY sequence";

        return $this->db->getAll($query);
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    function saveResponse($data, $question_id) {
        $processedResponse = $data['answer'];

        $response = array(
            'attempt_id' => $_SESSION['attempt_id'],
            'question_id' => $question_id,
            'response' => $processedResponse
        );

        $responseHandler = new AssessResponse();

        $result['question_id'] = $question_id;
        $result['response_id'] = $responseHandler->create($response);
        
        echo json_encode($result);

        /* Prevent rendering of template
         */
        exit();
    }
}
?>