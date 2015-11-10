<?php
/**
 * Assess_Answer_Multi
 *
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
class Assess_Answer_Multi extends Evaluation_Answer_Multi
{

    /**
    * Setup
    */
    protected function setup()
    {
        $this->setResponseHandler(new Assess_Response());
        $this->setQuestionHandler(new Assess_Question());
    }

    /**
     * Automatically marks a response. Customised to be applicable to the way in
     * which the response is actually stored.
     *
     * @param  $currentResponse
     * @return void
     */
    public function mark($currentResponse)
    {
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
            foreach ($answer as $currentAnswer) {
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
                $marksPerAnswer = $currentResponse['marks'] / $i;
                $total = $marksPerAnswer * $numCorrect;
            }
        } else {
            $total = 0;
        }

        /* Total marks for this question
         */
        $marks = array(
            'response_id' => $currentResponse['response_id'],
            'mark' => $total,
        );

        $this->getResponseHandler()->update($marks);
    }

    /**
     * Gets answers out of the answer group array, and shuffles answers if the
     * question's configuration calls for them to be shuffled.
     *
     * @param  array $answerGroup an answer group from $this->getByQuestionId()
     * @return array
     */
    protected function getAnswersFromAnswerGroup($answerGroup)
    {
        $allAnswers = parent::getAnswersFromAnswerGroup($answerGroup);
        $question   = $this->questionHandler->getByID($answerGroup['question_id']);
        $config     = json_decode($question['config']);
        if ($config->randomiseOptionOrder) {
            shuffle($allAnswers);
        }
        return $allAnswers;
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
            // Treat single answers as a single multiple response
            if (!is_array($existingAnswer)) {
                $existingAnswer = array($existingAnswer);
            }
        }

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(array(
                'ANSWER_ID' => $answerGroup['answer_id'],
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));

            foreach ($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => 'evaluation-correct-answer-option'
                    ));
                } else {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => ''
                    ));
                }

                $page->setVar(array(
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                ));

                if ($existing['response']
                    && is_array($existingAnswer)
                    && in_array($currentAnswer->value, $existingAnswer)
                ) {
                    $page->setVar(array(
                        'ANSWER_CHECKED' => 'evaluation-selected-answer-option',
                    ));
                } else {
                    $page->setVar(array(
                        'ANSWER_CHECKED' => '',
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
    * Output configuration settings in modify
    *
    * @param $page
    * @param $config
    */
    public function configure($page, $config)
    {
        $options = json_decode($config);

        if ($options->randomiseOptionOrder) {
            $page->setVar(array(
                'CONFIGURATION_RANDOMISEOPTIONORDER_CHECKED' => 'checked="checked"'
            ));
        } else {
            $page->setVar(array(
                'CONFIGURATION_RANDOMISEOPTIONORDER_CHECKED' => ''
            ));
        }
    }
}
