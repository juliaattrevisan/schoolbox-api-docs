<?php
require_once '../include/model.php';
require_once('../library/assess/assess-response.php');

/**
 * Handler for Fill Gap questions.
 */
class AssessAnswerFillgap extends AssessAnswer {

    /* HTML input element prefix used in conjunction with a question_id to
     * differentiate this Fillgap's inputs from other Fillgap inputs that might
     * appear on the page, as well as other inputs from different questions.
     */
    private $prefix = 'fillgap_';

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
    function modify($page, $question_id = '') {
        if (is_numeric($question_id)) {
            $currentAnswer = parent::getForQuestion($question_id);

            /* Show what words/phrases were detected. Preserve the original
             * answer text block in the db; this is a convenience for the user
             * so they can double-check their selections.
             */
            $page->setBlock('answerFormFragment', 'answer_list_items', 'answer_list_items_block');

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $currentAnswer['answer']
            ));

            $allMatches = array();

            preg_match_all('#<span>([^<]+)</span>#ms', $currentAnswer['answer'], $allMatches);

            if (count($allMatches[1])) {
                foreach($allMatches[1] as $currentMatch) {
                    $page->setVar(array(
                        'ANSWER_LIST_ITEM' => $currentMatch
                    ));

                    $page->parse('answer_list_items_block', 'answer_list_items', true);
                }
            }
            else {
                $page->setVar('answer_list_items_block', '');
            }
        }
        else {
            $page->setBlock('answerFormFragment', 'answer_list_items', 'answer_list_items_block');
            $page->setVar('answer_list_items_block', '');

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
    function prepareResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);
        
        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs and the already-submitted values
             */
            $ans = $currentAnswer['answer'];

            foreach($existingAnswer as $val) {
                $pattern = '#<span>([^<]+)</span>#ms';
                $replace = " <input name='fillgap_{$question_id}[]' type='text' value=\"$val\" /> ";

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);
            }

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
            ));
        }
        else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = '#<span>([^<]+)</span>#ms';
            $replace = " <input name='fillgap_{$question_id}[]' type='text' value='' /> ";

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, $currentAnswer['answer']),
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
    function saveResponse($data) {
        $processedResponse = serialize($data[$this->prefix . $data['question_id']]);

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     */
    function mark($currentResponse) {
        $response = unserialize($currentResponse['response']);
        $answer = $currentResponse['answer'];

        /* Pattern match spans to find answers
         */
        $matches = array();
        $pattern = '#<span>([^<]+)</span>#ms';

        preg_match_all($pattern, $answer, $matches);

        $actualAnswers = $matches[1];
        
        /* Find out how much each phrase is worth
         */
        $marksPerAnswer = $currentResponse['marks'] / count($actualAnswers);

        for ($i=0, $totalMarks=0; $i < count($actualAnswers); $i++) {
            if (trim($actualAnswers[$i]) == trim($response[$i])) {
                $totalMarks += $marksPerAnswer;
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
    function reviewResponse($page, $question_id) {
        $existing = $this->responseHandler->isResponseExists($_SESSION['attempt_id'], $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        /* Pattern match spans to find answers
         */
        $matches = array();
        $pattern = '#<span>([^<]+)</span>#ms';

        preg_match_all($pattern, $currentAnswer['answer'], $matches);

        $actualAnswers = $matches[1];

        /* Remember where the answers were, so we can compare between the
         * student's reponse and the actual answer
         */
        $replace = " <span class='fillgapCorrect'>$1</span> ";
        $correctResponse = preg_replace($pattern, $replace, $currentAnswer['answer']);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs and the already-submitted values
             */
            $ans = $currentAnswer['answer'];

            $i = 0;

            foreach($existingAnswer as $val) {
                $pattern = '#<span>([^<]+)</span>#ms';

                /* Indicate matching/non-matching responses to answers
                 */
                if (trim($actualAnswers[$i]) == trim($existingAnswer[$i])) {
                    $replace = " <span class='fillgapCorrect'>$val</span> ";
                }
                else {
                    $replace = " <span class='fillgapIncorrect'>$val</span> ";
                }

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);

                $i++;
            }

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_MARKS' => $existing['mark'],
            ));
        }
        else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = '#<span>([^<]+)</span>#ms';
            $replace = " <span class='fillgapIncorrect'>&nbsp;</span> ";

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, $currentAnswer['answer']),
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_MARKS' => 0,
            ));
        }

        /* Show correct answer
         */
        $page->setVar(array(
            'CORRECT_ANSWER' => $correctResponse
        ));

        $page->parse('answerOption_block', 'answerOption', true);
    }
}
?>