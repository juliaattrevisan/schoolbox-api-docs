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
    function modify($page, $question_id = '') {
        if (is_numeric($question_id)) {
            $answerGroup = parent::getForQuestion($question_id);

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
    function prepareResponse($page, $question_id) {
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

            if ($existing) {
                $right = unserialize($existing['response']);
            }
            else {

                /* Randomize right-side. Do this multiple times because it's not
                 * very random after one shuffle for some reason
                 */
                shuffle(&$right);
                shuffle(&$right);
                shuffle(&$right);
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
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    function saveResponse($data, $question_id) {
        $processedResponse = serialize($data['answerRight']);

        return parent::saveResponse($question_id, $processedResponse);
    }
    
    /**
     * Marks a response. Customised to be applicable to the way in which the
     * response is actually stored.
     */
    function mark($currentResponse) {
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

    function reviewResponse($page, $question_id) {
    }
}
?>