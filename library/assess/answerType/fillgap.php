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
    function renderAnswers($page, $question_id = '') {
        if (is_numeric($question_id)) {
            $allAnswers = parent::getForQuestion($question_id);

            foreach($allAnswers as $currentAnswer) {
                $page->setVar(array(
                    'ANSWER_ID' => $currentAnswer['answer_id'],
                    'ANSWER' => $currentAnswer['answer']
                ));
            }

            /* Show what words/phrases were detected. Preserve the original
             * answer text block in the db; this is a convenience for the user
             * so they can double-check their selections.
             */
            $page->setBlock('answerFormFragment', 'answer_list_items', 'answer_list_items_block');

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
        $allAnswers = $this->getByQuestionID($question_id);

        foreach($allAnswers as $currentAnswer) {

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = '#<span>([^<]+)</span>#ms';
            $replace = "<input name='fillgap_{$question_id}[]' type='text' value='' />";

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, $currentAnswer['answer']),
            ));
        }
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param <type> $response
     */
    function saveResponse($data, $question_id) {
        $processedResponse = serialize($data[$this->prefix . $question_id]);

        parent::saveResponse($question_id, $processedResponse);
    }
}
?>