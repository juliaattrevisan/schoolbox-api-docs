<?php

/**
 * Evaluation_Answer_Ranking
 * Handler for Ranking questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Answer_Ranking extends Evaluation_Answer
{
    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationRanking';

    /**
     * @var string
     */
    protected $correctClass = 'matchCorrect';

    /**
     * @var string
     */
    protected $incorrectClass = 'matchIncorrect';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param array $object
     * @param int|string $question_id
     * @return bool|int
     */
    public function create($object, $question_id = '')
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
     * @inheritdoc
     */
    public function configure(SitePage $page, $config)
    {
        $options = json_decode($config);

        $page->setVar(array(
            'CONFIGURATION_MANDATORY_CHECKED' => ($options->mandatory ?? false) ? 'checked="checked"' : ''
        ));
    }

    /**
     * Updates an existing Answer.
     *
     * @param array $object
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
     * @param SitePage $page
     * @param string $question_id
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
                } else {
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
     * @param SitePage $page
     * @param string $answerGroup
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
     * @param string $data
     * @return string
     */
    public function encodeForQuestionBank($data)
    {
        return serialize(json_decode($data));
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * TODO: If a response for the current user and attempt already exists,
     *  prefill it.
     *
     * @inheritdoc
     */
    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

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
                        'ANSWER_LEFT_VALUE'  => ($i + 1) . ')',
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
     * @param SitePage $page
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
     * @param array $data
     * @return array
     */
    public function saveResponse($data)
    {
        return parent::saveResponseInternal($data['attempt_id'], $data['section_id'], $data['question_id'], serialize($data['answerRight']));
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param SitePage $page
     * @param int $question_id
     * @return void
     */
    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        $page->setBlock('answerFormFragment', 'answerCorrect', 'answerCorrect_block');
        $page->setVar('answerOption_block', '');

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);
            if (is_array($allAnswers)) {
                foreach ($allAnswers as $currentAnswer) {
                    $page->setVar('ANSWER_RIGHT_VALUE', Scrub::text($currentAnswer->answerRight));
                    $page->parse('answerCorrect_block', 'answerCorrect', true);
                }
            }
        }
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param SimpleXMLElement $xmlObj
     * @param int $question_id
     * @return bool|int
     */
    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        foreach ($xmlObj->subquestion as $currentAnswer) {
            /* Set up data object in accordance with $this->create();
             */

            $answer['answerRight'] = html_entity_decode(trim((string)$currentAnswer->text), ENT_IGNORE | ENT_QUOTES);

            $allAnswers[] = $answer;
        }

        $object = array(
            'question_id' => $question_id,
            'sequence'    => 1,
            'allAnswers'  => json_encode($allAnswers),
        );

        return $this->create($object, $question_id);
    }

    /**
     * Get the response for a particular question
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return array
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist
         */
        if (!$response) {
            return [
                'response' => '',
                'comment' => '',
                'mark' => ''
            ];
        }

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
     * @param int $question_id
     * @param int $value
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
     * @param int $attempt_id
     * @param int $question_id
     *
     * @return mixed
     */
    public function exportResponseToArray($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        /* If a question was added after the attempt was made by the student,
         * then $response['response'] might not even exist; return empty array
         */
        if ($response) {
            return unserialize($response['response']);
        }

        return [];
    }

    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param SitePage $page
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $right = array();
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

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
                        'ANSWER_LEFT_VALUE'             => ($i + 1) . ')',
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
     * @param SitePage $page
     * @param int $attemptId
     * @param array $currentQuestion
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
                    'RESPONSE_USER_ID' => $currentQuestion['user_id'],
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
     * @param SitePage $page
     * @param array $currentQuestion
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
