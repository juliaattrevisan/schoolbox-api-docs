<?php

/**
 * Evaluation_Answer_Ranking
 * Handler for Ranking questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Answer_Ranking extends Evaluation_Answer
{

    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationRanking';

    protected $correctClass = 'matchCorrect';
    protected $incorrectClass = 'matchIncorrect';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param  $object
     * @param  $question_id
     *
     * @return bool|int
     */
    public function create($object, $question_id)
    {
        $answer                = array_intersect_key($object, array_flip($this->allowedFields));
        $answer['question_id'] = $question_id;
        $answer['sequence']    = 1;

        if (isset($object['allAnswers']) && $object['allAnswers'] != '') {
            foreach (json_decode($object['allAnswers']) as $currentAnswer) {
                $answer['answer'][] = $currentAnswer;
                $answer['sequence']++;
            }

            $answer['answer'] = serialize($answer['answer']);

            // only important on an update but we want to make sure we dont delete anything if there is no answers
            $this->db->delete($this->_table, 'question_id', $question_id);
        }

        $answer_id = parent::create($answer);

        if (!$answer_id) {
            return false;
        }

        return $answer_id;
    }

    /**
     * Configure question/configure answer during creation/modification
     *
     * @param $page
     * @param $config
     */
    public function configure($page, $config) {
        $options = json_decode($config);

        $page->setVar(array(
            'CONFIGURATION_MANDATORY_CHECKED' => $options->mandatory ? 'checked="checked"' : ''
        ));
    }

    /**
     * Updates an existing Answer.
     *
     * @param  $object
     *
     * @return void
     */
    public function update($object)
    {
        $this->create($object, $object['question_id']);
    }

    /**
     * Renders answers when editing a Question. If a question_id was provided,
     * load its existing answers and populate fields; otherwise empty them or
     * set to other default values.
     *
     * @param        $page
     * @param string $question_id
     *
     * @return void
     */
    public function modify($page, $question_id = '')
    {
        if (is_numeric($question_id)) {
            $answerGroup = parent::getForQuestion($question_id);

            $page->setVar(
                array(
                    'ANSWER_ID' => $answerGroup['answer_id']
                )
            );

            if ($answerGroup) {
                $allAnswers = unserialize($answerGroup['answer']);

                if (is_array($allAnswers) && count($allAnswers)) {
                    foreach ($allAnswers as $currentAnswer) {
                        $page->setVar(
                            array(
                                'ANSWER_RIGHT_VALUE' => Scrub::text($currentAnswer->answerRight),
                            )
                        );

                        $page->parse('answerOption_block', 'answerOption', true);
                    }
                }
                else {
                    $page->setVar('answerOption_block', '');
                }
            } else {
                $page->setVar('answerOption_block', '');
            }
        }
    }

    /**
     * Render answers in the QuestionBank
     *
     * @param        $page
     * @param string $answerGroup
     *
     * @return void
     */
    public function modifyInQuestionBank($page, $answerGroup)
    {
        if (strlen($answerGroup)) {
            $allAnswers = unserialize($answerGroup);

            foreach ($allAnswers as $currentAnswer) {
                $page->setVar(
                    array(
                        'ANSWER_RIGHT_VALUE' => Scrub::text($currentAnswer->answerRight),
                    )
                );

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * Serialize for db storage (we shouldn't be using serialize anymore)
     *
     * @param $data
     *
     * @return string
     */
    public function encodeForQuestionBank($data)
    {
        return serialize(json_decode($data));
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     * TODO: If a response for the current user and attempt already exists,
     * prefill it.
     *
     * @param  $page
     * @param  $question_id
     *
     * @return void
     */
    public function prepareResponse($page, $question_id)
    {

        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        /* Define special blocks to handle this special answerOptions scenario,
         * because we have two columns to parse per iteration.
         */
        $page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');
        $page->setBlock('answerFormFragment', 'answerOptionLeft', 'answerOptionLeft_block');

        $left  = array();
        $right = array();

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $answerCount = 0;
            $allAnswers  = unserialize($answerGroup['answer']);

            foreach ($allAnswers as $currentAnswer) {
                $right[] = $currentAnswer->answerRight;

                $answerCount++;
            }

            if ($existing['response']) {
                $right = unserialize($existing['response']);
            } else {
                $originalRight = $right;

                /* Shuffle until they are totally different
                 */
                $x = 0;
                while ($originalRight === $right && $x++ < 10) {
                    shuffle($right);
                }
            }

            for ($i = 0; $i < $answerCount; $i++) {
                $page->setVar(
                    array(
                        'ANSWER_LEFT_VALUE'  => ($i+1).')',
                        'ANSWER_RIGHT_VALUE' => Scrub::text($right[$i]),
                    )
                );

                $page->parse('answerOptionRight_block', 'answerOptionRight', true);
                $page->parse('answerOptionLeft_block', 'answerOptionLeft', true);
            }
        }

        /* Parse question number, so you can have multiple sortable instances
         */
        $page->setVar(
            array(
                'MATCHPAIRS_QUESTION_ID' => $question_id,
                'QUESTION_ID'            => $question_id,
                'QUESTION_PLUGIN'        => $this->plugin,
            )
        );
    }

    /**
     * Clear child blocks after parsing out the parent. Keep this in its own
     * method for polymorphic algorithm
     *
     * @param  $page
     *
     * @return void
     */
    public function cleanupParse($page)
    {
        $page->setVar('answerOptionRight_block', '');
        $page->setVar('answerOptionLeft_block', '');
    }

    /**
     * Saves a response for this Answer type. This should be customised based on
     * the format and structure of the answer (e.g. serialize multichoice)
     *
     * @param  $data
     *
     * @return array
     */
    public function saveResponse($data)
    {
        $processedResponse = serialize($data['answerRight']);

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param  $page
     * @param  $question_id
     *
     * @return void
     */
    public function exportMoodleAnswer($page, $question_id)
    {
        $page->setBlock('answerFormFragment', 'answerCorrect', 'answerCorrect_block');

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            for ($i = 0; $i < count($allAnswers); $i++) {
                $currentAnswer = $allAnswers[$i];

                $page->setVar(
                    array(
                        'ANSWER_RIGHT_VALUE' => Scrub::text($currentAnswer->answerRight),
                    )
                );

                $page->parse('answerCorrect_block', 'answerCorrect', true);
            }
        }
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param  $xmlObj
     * @param  $question_id
     *
     * @return bool|int
     */
    public function importMoodleAnswer($xmlObj, $question_id)
    {
        foreach ($xmlObj->subquestion as $currentAnswer) {
            /* Set up data object in accordance with $this->create();
             */

            $answer['answerRight'] = html_entity_decode(trim((string)$currentAnswer->text), ENT_IGNORE | ENT_QUOTES);

            $allAnswers[] = $answer;
        }

        $object = array(
            'answer_id'   => '',
            'question_id' => $question_id,
            'sequence'    => 1,
            'allAnswers'  => json_encode($allAnswers),
        );

        return $this->create($object, $question_id);
    }

    /**
     * Get the response for a particular question
     *
     * @param  $attempt_id
     * @param  $question_id
     *
     * @return mixed
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        $data = unserialize($response['response']);

        if (is_array($data)) {
            if ($this->getLegendHTML() !== false) {
                $data['response'] = '<ul><li>' . implode('</li><li>', $data) . '</li></ul>';
            } else {
                $data['response'] = strip_tags(implode(', ', $data));
            }
        }

        /* Append commentary
         */
        $data['comment'] = $response['comment'];

        /* Append awarded mark
         */
        $data['mark'] = $response['mark'];

        return $data;
    }

    /**
     * Gets the text associated with an array-based multiple choice answer
     *
     * @param  $question_id
     * @param  $value
     *
     * @return string
     */
    public function getAnswerText($question_id, $value)
    {
        $answer = $this->getByQuestionID($question_id);

        $allOptions = unserialize($answer['answer']);

        foreach ($allOptions as $currentOption) {
            if ($currentOption->value == $value) {
                return $currentOption->description;
            }
        }

        return '';
    }

    /**
     * Get the response for a particular question
     *
     * @param  $attempt_id
     * @param  $question_id
     *
     * @return mixed
     */
    public function exportResponseToArray($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        $data = unserialize($response['response']);

        return $data;
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param  $page
     * @param  $question_id
     *
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $right = array();

        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        if ($existing['response']) {
            $right = unserialize($existing['response']);
        }

        /* Define special blocks to handle this special answerOptions scenario,
           * because we have three columns to parse per iteration.
           */
        $page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');
        $page->setBlock('answerFormFragment', 'answerOptionCorrectResponse', 'answerOptionCorrectResponse_block');

        $answerGroup = $this->getByQuestionID($question_id);

        $page->setVar('answerOptionCorrectResponse_block', '');
        if ($answerGroup) {
            $page->setVar(
                array(
                    'ANSWER_ID'        => $answerGroup['answer_id'],
                    'RESPONSE_ID'      => $existing['response_id'],
                    'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                    'REVIEWED_MARKS'   => $existing['mark'],
                )
            );

            $allAnswers = unserialize($answerGroup['answer']);

            for ($i = 0; $i < count($allAnswers); $i++) {
                $currentAnswer = $allAnswers[$i];

                if ($right[$i] == $currentAnswer->answerRight) {
                    $correct = $this->getCorrectClass();
                } else {
                    $correct = $this->getIncorrectClass();
                }

                $page->setVar(
                    array(
                        'ANSWER_LEFT_VALUE'             => ($i+1).')',
                        'ANSWER_RIGHT_VALUE'            => $right[$i],
                        'ANSWER_CORRECT_RESPONSE_VALUE' => Scrub::text($currentAnswer->answerRight),
                        'CORRECT'                       => $correct,
                    )
                );

                $page->parse('answerOptionRight_block', 'answerOptionRight', true);
                $page->parse('answerOptionCorrectResponse_block', 'answerOptionCorrectResponse', true);
            }
        }
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param $page
     * @param $attemptId
     * @param $currentQuestion
     */
    public function reviewResponseHorizontal($page, $attemptId, $currentQuestion)
    {
        $right = array();

        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $currentQuestion['question_id']);

        if ($existing['response']) {
            $right = unserialize($existing['response']);
        }

        /* Define special blocks to handle this special answerOptions scenario,
         * because we have three columns to parse per iteration.
         */
        $page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');
        $page->setBlock('answerFormFragment', 'answerOptionCorrectResponse', 'answerOptionCorrectResponse_block');

        $answerGroup = $this->getByQuestionID($currentQuestion['question_id']);

        if ($answerGroup) {
            $page->setVar(
                array(
                    'USER_EXTERNAL_ID' => $currentQuestion['synergy_id'],
                    'USER_FIRSTNAME'   => $currentQuestion['firstname'],
                    'USER_LASTNAME'    => $currentQuestion['lastname'],
                    'ANSWER_ID'        => $answerGroup['answer_id'],
                    'RESPONSE_ID'      => $existing['response_id'],
                    'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                    'REVIEWED_MARKS'   => $existing['mark'],
                )
            );

            $allAnswers = unserialize($answerGroup['answer']);

            for ($i = 0; $i < count($allAnswers); $i++) {
                $currentAnswer = $allAnswers[$i];

                if ($right[$i] == $currentAnswer->answerRight) {
                    $correct = $this->getCorrectClass();
                } else {
                    $correct = $this->getIncorrectClass();
                }

                $page->setVar(
                    array(
                        'ANSWER_RIGHT_VALUE'            => $right[$i],
                        'ANSWER_CORRECT_RESPONSE_VALUE' => Scrub::text($currentAnswer->answerRight),
                        'CORRECT'                       => $correct,
                    )
                );

                $page->parse('answerOptionRight_block', 'answerOptionRight', true);
                $page->parse('answerOptionCorrectResponse_block', 'answerOptionCorrectResponse', true);
            }
        }
    }

    /**
     * The answer for a question during horizontal marking, so it appears at the
     * top under the question itself, and not repeated for each attempt
     *
     * @param $page
     * @param $currentQuestion
     *
     * @return void
     */
    public function reviewResponseHorizontalAnswerForQuestion($page, $currentQuestion)
    {

        /* Define special blocks to handle this special answerOptions scenario,
         * because we have three columns to parse per iteration.
         */
        $page->setBlock('answerFormFragmentTop', 'answerOptionTopRight', 'answerOptionTopRight_block');
        $page->setBlock('answerFormFragmentTop', 'answerOptionTopCorrectResponse', 'answerOptionTopCorrectResponse_block');

        $answerGroup = $this->getByQuestionID($currentQuestion['question_id']);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            for ($i = 0; $i < count($allAnswers); $i++) {
                $currentAnswer = $allAnswers[$i];

                $page->setVar(
                    array(
                        'ANSWER_RIGHT_VALUE'            => Scrub::text($currentAnswer->answerRight),
                        'ANSWER_CORRECT_RESPONSE_VALUE' => Scrub::text($currentAnswer->answerRight),
                        'CORRECT'                       => $this->getCorrectClass(),
                    )
                );

                $page->parse('answerOptionTopRight_block', 'answerOptionTopRight', true);
                $page->parse('answerOptionTopCorrectResponse_block', 'answerOptionTopCorrectResponse', true);
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Get correct class
     *
     * @return string
     */
    protected function getCorrectClass()
    {
        return $this->correctClass;
    }

    /**
     * Get incorrect class
     *
     * @return string
     */
    protected function getIncorrectClass()
    {
        return $this->incorrectClass;
    }
}
