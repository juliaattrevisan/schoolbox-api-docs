<?php
/**
 * Evaluation_Answer_Multi
 *
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
abstract class Evaluation_Answer_Multi extends Evaluation_Answer
{

    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationMultipleChoice';

    protected $correctClass = 'multiCorrect';
    protected $incorrectClass = 'multiIncorrect';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * @param  $object
     * @param  $question_id
     * @return bool|int
     */
    public function create($object, $question_id)
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));
        $answer['question_id'] = $question_id;
        $answer['sequence'] = 1;

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
     * Updates an existing Answer. In this particular case, delete previous
     * options and recreate new ones due to preprocessing requirement
     *
     * @param  $object
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
     * @param  $page
     * @param string $question_id
     * @return void
     */
    public function modify($page, $question_id = '')
    {
        if (is_numeric($question_id)) {
            $answerGroup = parent::getForQuestion($question_id);

            if ($answerGroup) {
                $allAnswers = unserialize($answerGroup['answer']);

                if (is_array($allAnswers) && count($allAnswers)) {
                    foreach ($allAnswers as $currentAnswer) {
                        $page->setVar(array(
                            'ANSWER_VALUE' => $currentAnswer->value,
                            'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                            'ANSWER_CHECKED' => $currentAnswer->correct != '' ? 'checked=\'checked\'' : ''
                        ));

                        $page->parse('answerOption_block', 'answerOption', true);
                    }
                } else {
                    $page->setVar('answerOption_block', '');
                }

                $page->setVar(array(
                    'ANSWER_ID' => $answerGroup['answer_id']
                ));
            } else {
                $page->setVar('answerOption_block', '');
            }
        }
    }

    /**
     * Render answers in the QuestionBank
     *
     * @param  $page
     * @param string $answerGroup
     * @return void
     */
    public function modifyInQuestionBank($page, $answerGroup)
    {
        if (strlen($answerGroup)) {
            $allAnswers = unserialize($answerGroup);

            if (is_array($allAnswers) && count($allAnswers)) {
                foreach ($allAnswers as $currentAnswer) {
                    $page->setVar(array(
                        'ANSWER_VALUE' => $currentAnswer->value,
                        'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                        'ANSWER_CHECKED' => $currentAnswer->correct != '' ? 'checked=\'checked\'' : ''
                    ));

                    $page->parse('answerOption_block', 'answerOption', true);
                }
            } else {
                $page->setVar('answerOption_block', '');
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * Gets answers out of the answer group array.
     *
     * @param  array $answerGroup an answer group from $this->getByQuestionId()
     * @return array
     */
    protected function getAnswersFromAnswerGroup($answerGroup)
    {
        return unserialize($answerGroup['answer']);
    }

    /**
     * Serialize for db storage (we shouldn't be using serialize anymore)
     *
     * @param $data
     * @return string
     */
    public function encodeForQuestionBank($data)
    {
        return serialize(json_decode($data));
    }

    /**
     * Translate an integer to letters
     *
     * @param $num
     * @return string
     */
    protected function findLetterIndex($num)
    {
        $firstLetter = floor($num / 26);
        $lastLetter = $num % 26;

        /* Special case for z, az, bz, etc.
         */
        if (!$lastLetter) {
            $firstLetter--;
            $lastLetter = 26;
        }

        /* Convert from numeric to alpha
         */
        if ($firstLetter) {
            $string = chr($firstLetter+64) . chr($lastLetter+64);
        } else {
            $string = chr($lastLetter+64);
        }

        return $string;
    }

    /**
     * Has the student selected the given answer value in their response?
     *
     * @param string $answerValue
     * @param array  $existingResponse The response that the student gave for the question
     *
     * @return bool
     */
    protected function isAnswerSelected($answerValue, $existingResponse)
    {
        return is_array($existingResponse) && in_array($answerValue, $existingResponse);
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function prepareResponse($page, $question_id)
    {

        /* Optional configuration hook
		 */
        $this->configureResponse($page, $question_id);

        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = $this->getAnswersFromAnswerGroup($answerGroup);

            $i = 1;

            foreach ($allAnswers as $currentAnswer) {
                $valueFacade = $this->findLetterIndex($i);

                $page->setVar(array(
                    'QUESTION_ID' => $question_id,
                    'QUESTION_PLUGIN' => $this->plugin,
                    'ANSWER_ID' => $answerGroup['answer_id'],
                    'ANSWER_VALUE_FACADE' => $valueFacade,
                    'ANSWER_VALUE' => $currentAnswer->value,
                    'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                ));

                if ($existing['response']) {
                    if ($this->isAnswerSelected($currentAnswer->value, unserialize($existing['response']))) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'checked=\'checked\'',
                        ));
                    } else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                        ));
                    }
                } else {
                    $page->setVar(array(
                        'ANSWER_CHECKED' => '',
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);

                $i++;
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * Clear child blocks after parsing out the parent. Keep this in its own
     * method for polymorphic algorithm
     *
     * @param  $page
     * @return void
     */
    public function cleanupParse($page)
    {
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
        if ($data['answer'] == null) {
            $processedResponse = 'NULL';
        } else {
            $processedResponse = serialize($data['answer']);
        }

        return parent::saveResponse($data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * @param  $page
     * @param  $question_id
     * @return void
     */
    public function exportMoodleAnswer($page, $question_id)
    {
        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            if (is_array($allAnswers) && count($allAnswers)) {
                foreach ($allAnswers as $currentAnswer) {
                    if ($currentAnswer->correct) {
                        $page->setVar(array(
                            'ACTUAL_CORRECT_VALUE' => '100'
                        ));
                    } else {
                        $page->setVar(array(
                            'ACTUAL_CORRECT_VALUE' => '0'
                        ));
                    }

                    $page->setVar(array(
                        'ANSWER_VALUE' => $currentAnswer->value,
                        'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                    ));

                    $page->parse('answerOption_block', 'answerOption', true);
                }
            } else {
                $page->setVar('answerOption_block', '');
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
     * @return bool|int
     */
    public function importMoodleAnswer($xmlObj, $question_id)
    {
        foreach ($xmlObj->answer as $currentAnswer) {
            /* Set up data object in accordance with $this->create();
             */
            $answer['description'] = html_entity_decode(trim((string)$currentAnswer->text), ENT_IGNORE|ENT_QUOTES);
            $answer['value'] = trim((string)$currentAnswer->value);
            $answer['correct'] = 0;

            /* Indicate this is a correct answer
             */
            if ($currentAnswer->attributes()->fraction > 0) {
                $answer['correct'] = 1;
            }

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
     * @param  $attempt_id
     * @param  $question_id
     * @return mixed
     */
    public function exportResponseToCSV($attempt_id, $question_id)
    {
        $response = $this->getResponseHandler()->getByAttemptAndQuestion($attempt_id, $question_id);

        $data = unserialize($response['response']);

        if (is_array($data)) {
            /* Answer text to appear in export
                */
            if ($this->getUseLongAnswers()) {
                foreach ($data as $currentAnswer) {
                    $postprocessed[] = $this->getAnswerText($question_id, $currentAnswer);
                }
            } else {
                $postprocessed = $data;
            }

            if ($this->getLegendHTML() !== false) {
                $data['response'] = '<ul><li>' . implode('</li><li>', $postprocessed) . '</li></ul>';
            } else {
                $data['response'] = strip_tags(implode(', ', $postprocessed));
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
     * @return void
     */
    public function reviewResponse($page, $question_id)
    {
        $existing = $this->getResponseHandler()->isResponseExists($_SESSION['attempt_id'], $question_id);

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
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
                        'ACTUAL_CORRECT_VALUE' => 'correct-answer'
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

                if ($existing['response']) {
                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'checked=\'checked\'',
                        ));

                        /* Was this a correctly-selected response? If so apply a
                         * special class to it. The three cases are:
                         *
                         * Correct response, selected
                         * Incorrect response, selected
                         * Unselected
                         */
                        if ($currentAnswer->correct && in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getCorrectClass(),
                            ));
                        } elseif (in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getIncorrectClass(),
                            ));
                        } else {
                            $page->setVar(array(
                                'CORRECT' => ''
                            ));
                        }
                    } else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                            'CORRECT' => ''
                        ));
                    }
                } else {
                    $page->setVar(array(
                        'CORRECT' => ''
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
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
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $currentQuestion['question_id']);

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
        }

        $answerGroup = $this->getByQuestionID($currentQuestion['question_id']);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(array(
                'USER_EXTERNAL_ID' => $currentQuestion['synergy_id'],
                'USER_FIRSTNAME' => $currentQuestion['firstname'],
                'USER_LASTNAME' => $currentQuestion['lastname'],
                'ANSWER_ID' => $answerGroup['answer_id'],
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));

            foreach ($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => 'correct-answer'
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

                if ($existing['response']) {
                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => 'checked=\'checked\'',
                        ));

                        /* Was this a correctly-selected response? If so apply a
                         * special class to it. The three cases are:
                         *
                         * Correct response, selected
                         * Incorrect response, selected
                         * Unselected
						 */
                        if ($currentAnswer->correct && in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getCorrectClass(),
                            ));
                        } elseif (in_array($currentAnswer->value, $existingAnswer)) {
                            $page->setVar(array(
                                'CORRECT' => $this->getIncorrectClass(),
                            ));
                        } else {
                            $page->setVar(array(
                                'CORRECT' => ''
                            ));
                        }
                    } else {
                        $page->setVar(array(
                            'ANSWER_CHECKED' => '',
                            'CORRECT' => ''
                        ));
                    }
                } else {
                    $page->setVar(array(
                        'CORRECT' => ''
                    ));
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
        }
    }

    /**
     * The answer for a question during horizontal marking, so it appears at the
     * top under the question itself, and not repeated for each attempt
     *
     * @param $page
     * @param $currentQuestion
     * @return void
     */
    public function reviewResponseHorizontalAnswerForQuestion($page, $currentQuestion)
    {
        $answerGroup = $this->getByQuestionID($currentQuestion['question_id']);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(array(
                'ANSWER_ID' => $answerGroup['answer_id'],
            ));

            foreach ($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(array(
                        'ACTUAL_CORRECT_VALUE' => 'correct-answer'
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

                $page->parse('answerTop_block', 'answerTop', true);
            }
        } else {
            $page->setVar('answerTop_block', '');
        }
    }

    /* Getters/Setters
     */

    /**
     * Get the css class to use for correct responses
     *
     * @return string
     */
    protected function getCorrectClass()
    {
        return $this->correctClass;
    }

    /**
     * Get the css class to use for incorrect responses
     *
     * @return string
     */
    protected function getIncorrectClass()
    {
        return $this->incorrectClass;
    }
}
