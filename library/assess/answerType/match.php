<?php
require_once '../include/model.php';
require_once('../library/assess/assess-response.php');

/**
 * Handler for Short Answer questions.
 */
class AssessAnswerMatch extends AssessAnswer {

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

        $numItems = 0;

        foreach(json_decode($_POST['allAnswers']) as $currentAnswer) {
            $answer['answer'] = serialize($currentAnswer);

            $answer_ids[] = parent::create($answer);

            $answer['sequence']++;

            $numItems++;
        }

        /* Check that the number of saved items is the same as the number of
         * submitted items
         */
        if (count($answer_ids) != $numItems) {
            return false;
        }

        return $answer_ids;
    }

    /**
     * Updates an existing Answer.
     *
     * @param <type> $object
     */
    function update($object) {
        $query = "DELETE FROM {$this->_table}
                  WHERE question_id = '$_POST[question_id]'";

        $this->db->query($query);

        $this->create($object, $_POST['question_id']);
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

            if ($allAnswers) {
                foreach($allAnswers as $currentAnswer) {
                    $thisAnswer = unserialize($currentAnswer['answer']);

                    $page->setVar(array(
                        'ANSWER_LEFT_VALUE' => $thisAnswer->answerLeft,
                        'ANSWER_RIGHT_VALUE' => $thisAnswer->answerRight,
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
    function prepareResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);
        
        $left = array();
        $right = array();

        $allAnswers = $this->getByQuestionID($question_id);
        
        if ($allAnswers) {
            $answerCount = 0;

            foreach ($allAnswers as $currentAnswer) {
                $thisAnswer = unserialize($currentAnswer['answer']);

                $left[] = $thisAnswer->answerLeft;
                $right[] = $thisAnswer->answerRight;

                $answerCount++;
            }

            if ($existing) {
                $right = unserialize($existing['response']);
            }
            else {

                /* Randomize right-side
                 */
                shuffle(&$right);
            }

            for ($i=0; $i<$answerCount; $i++) {
                $page->setVar(array(
                    'ANSWER_LEFT_VALUE' => $left[$i],
                    'ANSWER_RIGHT_VALUE' => $right[$i],
                ));

                $page->parse('answerOption_block', 'answerOption', true);
            }
        }
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    function saveResponse($data, $question_id) {
        $processedResponse = serialize($data['answerRight']);

        return parent::saveResponse($question_id, $processedResponse);
    }
}
?>