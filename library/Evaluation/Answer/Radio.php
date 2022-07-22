<?php

/**
 * Evaluation_Answer_Radio
 *
 * Handler for Multiple Choice questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Answer_Radio extends Evaluation_Answer
{

    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationRadio';

    /**
     * @var string
     */
    protected $correctClass = 'multiCorrect';

    /**
     * @var string
     */
    protected $incorrectClass = 'multiIncorrect';

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

            if ($answerGroup) {
                $allAnswers = unserialize($answerGroup['answer']);

                if (is_array($allAnswers) && count($allAnswers)) {
                    foreach ($allAnswers as $currentAnswer) {
                        $page->setVar(
                            array(
                                'ANSWER_VALUE' => $currentAnswer->value,
                                'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                                'ANSWER_OPTION_STATUS' => $currentAnswer->correct != ''
                                    ? 'evaluation-selected-answer-option'
                                    : '',
                                'ANSWER_CHECKED' => $currentAnswer->correct != '' ? 'checked=\'checked\'' : ''
                            )
                        );

                        $page->parse('answerOption_block', 'answerOption', true);
                    }
                } else {
                    $page->setVar('answerOption_block', '');
                }

                $page->setVar(
                    array(
                        'ANSWER_ID' => $answerGroup['answer_id']
                    )
                );
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
    /**
     * Translate an integer to letters
     *
     * @param int $num
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
            $string = chr($firstLetter + 64) . chr($lastLetter + 64);
        } else {
            $string = chr($lastLetter + 64);
        }

        return $string;
    }

    /**
     * Resets answer tokens so a user can fill them in while actually taking
     * the test.
     *
     * @inheritdoc
     */
    public function prepareResponse(SitePage $page, $attemptId, $question_id)
    {
        /* Optional configuration hook
         */
        $this->configureResponse($page, $question_id);

        $questionConfig = json_decode($this->questionHandler->getByID($question_id)['config']);
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $i = 1;

            foreach ($allAnswers as $currentAnswer) {
                $valueFacade = $this->findLetterIndex($i);

                $page->setVar(
                    array(
                        'QUESTION_ID' => $question_id,
                        'QUESTION_PLUGIN' => $this->plugin,
                        'ANSWER_ID' => $answerGroup['answer_id'],
                        'ANSWER_VALUE_FACADE' => $valueFacade,
                        'ANSWER_VALUE' => $currentAnswer->value,
                        'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                        'ANSWER_AUTO_COMPLETE' => ($questionConfig->randomiseOptionOrder ?? false) ? 'off' : 'on',
                    )
                );

                if ($existing['response']) {
                    $existingAnswer = unserialize($existing['response']);

                    if (is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                        $page->setVar(
                            array(
                                'ANSWER_CHECKED' => 'checked=\'checked\'',
                            )
                        );
                    } else {
                        $page->setVar(
                            array(
                                'ANSWER_CHECKED' => '',
                            )
                        );
                    }
                } else {
                    $page->setVar(
                        array(
                            'ANSWER_CHECKED' => '',
                        )
                    );
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
     * @param SitePage $page
     * @return void
     */
    public function cleanupParse($page)
    {
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

        /* Enforce array format for radio answers
           */
        if (!isset($data['answer'])) {
            $processedResponse = 'NULL';
        } else {
            $processedResponse = serialize(array($data['answer']));
        }

        return parent::saveResponseInternal($data['attempt_id'], $data['section_id'], $data['question_id'], $processedResponse);
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
        $answerGroup = $this->getByQuestionID($question_id);
        $page->setVar('answerOption_block', '');

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            if (is_array($allAnswers)) {
                foreach ($allAnswers as $currentAnswer) {
                    $page->setVar([
                        'ACTUAL_CORRECT_VALUE' => $currentAnswer->correct ? '100' : '0',
                        'ANSWER_VALUE'         => $currentAnswer->value,
                        'ANSWER_DESCRIPTION'   => Scrub::text($currentAnswer->description),
                    ]);
                    $page->parse('answerOption_block', 'answerOption', true);
                }
            }
        }
    }

    /**
     * Imports form a Moodle XML object. This is largely an alias of create()
     * but we create the array specially because of the naming conventions and
     * structure of the original xml export.
     *
     * @param  SimpleXMLElement $xmlObj
     * @param  int $question_id
     * @return bool|int
     */
    public function importMoodleAnswer(SimpleXMLElement $xmlObj, $question_id)
    {
        $allAnswers = [];
        foreach ($xmlObj->answer as $currentAnswer) {
            /* Set up data object in accordance with $this->create();
             */
            $answer['description'] = trim((string)$currentAnswer->text);
            $answer['value'] = trim((string)$currentAnswer->value);
            $answer['correct'] = 0;

            /* Indicate this is a correct answer
             */
            if ((int)$currentAnswer->attributes()->fraction > 0) {
                $answer['correct'] = 1;
            }

            $allAnswers[] = $answer;
        }

        $object = array(
            'question_id' => $question_id,
            'sequence' => 1,
            'allAnswers' => json_encode($allAnswers),
        );

        return $this->create($object, $question_id);
    }

    /**
     * Get the response for a particular question
     *
     * @param int $attempt_id
     * @param int $question_id
     * @return mixed
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
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

        if ($existing) {
            $existingAnswer = unserialize($existing['response']);
        }

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $allAnswers = unserialize($answerGroup['answer']);

            $page->setVar(
                array(
                    'ANSWER_ID' => $answerGroup['answer_id'],
                    'RESPONSE_ID' => $existing['response_id'],
                    'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                    'REVIEWED_MARKS' => $existing['mark'],
                )
            );

            foreach ($allAnswers as $currentAnswer) {
                if ($currentAnswer->correct) {
                    $page->setVar(
                        array(
                            'ACTUAL_CORRECT_VALUE' => 'icon-approve'
                        )
                    );
                } else {
                    $page->setVar(
                        array(
                            'ACTUAL_CORRECT_VALUE' => ''
                        )
                    );
                }

                $page->setVar(
                    array(
                        'ANSWER_VALUE' => $currentAnswer->value,
                        'ANSWER_DESCRIPTION' => Scrub::text($currentAnswer->description),
                    )
                );

                if ($existing['response'] && is_array($existingAnswer) && in_array($currentAnswer->value, $existingAnswer)) {
                    $page->setVar(
                        array(
                            'ANSWER_OPTION_STATUS' => 'evaluation-selected-answer-option',
                            'ANSWER_CHECKED' => 'checked',
                        )
                    );
                } else {
                    $page->setVar(
                        array(
                            'ANSWER_OPTION_STATUS' => '',
                            'ANSWER_CHECKED' => '',
                        )
                    );
                }

                $page->parse('answerOption_block', 'answerOption', true);
            }
        } else {
            $page->setVar('answerOption_block', '');
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
