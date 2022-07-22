<?php

use Evaluation\Answer\Comparison;

/**
 * Evaluation_Answer_Fillgap
 *
 * Handler for Fill Gap questions.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Evaluation_Answer_Fillgap extends Evaluation_Answer
{
    use Comparison;


    /**
     * This regex is used to identify the bits that students provided to fill in the gaps
     *
     * https://stackoverflow.com/questions/9253027/get-everything-between-tag-and-tag-with-php
     * @ "You can use the following:" by piouPiouM (I modified his response a bit)
     */
    public const FILLGAP_TOKEN_REGEX = '#<?span class="selected-word">(.*?)</span\b[^>]*>#ms';


    /**
     * Javascript class to use when creating UI
     *
     * @var string
     */
    protected $plugin = 'evaluationFillGap';

    /**
     * HTML input element prefix used in conjunction with a question_id to
     * differentiate this Fillgap's inputs from other Fillgap inputs that might
     * appear on the page, as well as other inputs from different questions.
     *
     * @var string
     */
    private $prefix = 'fillgap_';

    /**
     * Creates an Answer. This is used when creating Questions and Answers in
     * the setup phase.
     *
     * TODO: $question_id should be an int, but its default value is empty string
     *
     * @param array $object
     * @param int|string $question_id
     * @return bool|int
     */
    public function create($object, $question_id = '')
    {
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
     * @param array $object
     * @return void
     */
    public function update($object)
    {
        $answer = array_intersect_key($object, array_flip($this->allowedFields));

        parent::update($answer);
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
            $currentAnswer = parent::getForQuestion($question_id);
            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => Scrub::text($currentAnswer['answer'])
            ));
        } else {
            $page->setVar(array(
                'ANSWER_ID' => '',
                'ANSWER' => '',
            ));
        }
    }

    /**
     * Render answers in the QuestionBank
     *
     * @param SitePage $page
     * @param string $answer
     * @return void
     */
    public function modifyInQuestionBank($page, $answer)
    {
        $page->setVar(array(
            'ANSWER' => Scrub::text($answer)
        ));
    }

    /**
     * Serialize for db storage (we shouldn't be using serialize anymore)
     *
     * @param string $data
     * @return string
     */
    public function encodeForQuestionBank($data)
    {
        return $data;
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

        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

        $currentAnswer = $this->getByQuestionID($question_id);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs and the already-submitted values
             */
            $ans = $currentAnswer['answer'];

            foreach ($existingAnswer as $val) {
                $pattern = self::FILLGAP_TOKEN_REGEX;
                $replace = " <input spellcheck='false' name='fillgap_{$question_id}[]' type='text' value=\"$val\" /> ";

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);
            }

            $page->setVar(array(
                'QUESTION_ID' => $question_id,
                'QUESTION_PLUGIN' => $this->plugin,
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
            ));
        } else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = self::FILLGAP_TOKEN_REGEX;
            $replace = " <input spellcheck='false' name='fillgap_{$question_id}[]' type='text' value='' /> ";

            $page->setVar(array(
                'QUESTION_ID' => $question_id,
                'QUESTION_PLUGIN' => $this->plugin,
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, $currentAnswer['answer']),
            ));
        }

        $page->parse('answerOption_block', 'answerOption', true);
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
        $isNotEmpty = false;

        foreach ($data[$this->prefix . $data['question_id']] as $answer) {
            if ($answer != '') {
                $isNotEmpty = true;
            }
        }

        /* Cater for empty answers. If nothing was provided, restore stub
           * placeholder (null) which causes %complete indicator to drop
           */
        if ($isNotEmpty) {
            $processedResponse = serialize($data[$this->prefix . $data['question_id']]);
        } else {
            $processedResponse = null;
        }

        return parent::saveResponseInternal($data['attempt_id'], $data['section_id'], $data['question_id'], $processedResponse);
    }

    /**
     * Exports the answer to Moodle XML format. This is a modified version of
     * $this->reviewResponse()
     *
     * NOTE: This question type has very little moodle documentation.
     *
     * @param SitePage $page
     * @param int $question_id
     * @return void
     */
    public function exportMoodleAnswer(SitePage $page, $question_id)
    {
        $currentAnswer = $this->getByQuestionID($question_id);
        $page->setVar('CORRECT_ANSWER', Scrub::html($currentAnswer['answer']));
        $page->parse('answerOption_block', 'answerOption');
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
        $object = array(
            'question_id' => $question_id,
            'sequence' => 1,
            'answer' => trim((string)$xmlObj->answer->text),
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

        return $answer['answer'];
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

        $currentAnswer = $this->getByQuestionID($question_id);
        $decodedAnswer = html_entity_decode($currentAnswer['answer'], ENT_IGNORE | ENT_QUOTES);


        /* Pattern match spans to find answers
           */
        $matches = array();
        $pattern = self::FILLGAP_TOKEN_REGEX;

        preg_match_all($pattern, $decodedAnswer, $matches);

        $actualAnswers = $matches[1];

        /* Remember where the answers were, so we can compare between the
           * student's reponse and the actual answer
           */
        $replace = " <span class='fillgap correct'>$1</span> ";
        $correctResponse = preg_replace($pattern, $replace, $decodedAnswer);

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
                * %%token%% tokens with inputs and the already-submitted values
                */
            $ans = $decodedAnswer;

            $i = 0;

            foreach ($existingAnswer as $val) {
                $pattern = self::FILLGAP_TOKEN_REGEX;
                $val = Scrub::text($val);

                /* Indicate matching/non-matching responses to answers
                     */
                if ($this->compareStrings((string)$actualAnswers[$i], (string)$existingAnswer[$i])) {
                    $replace = " <span class='fillgap correct'>$val</span> ";
                } else {
                    $replace = " <span class='fillgap incorrect'>$val</span> ";
                }

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);

                $i++;
            }

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => $ans,
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));
        } else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = self::FILLGAP_TOKEN_REGEX;
            $replace = " <span class='fillgap incorrect'>&nbsp;</span> ";

            $page->setVar(array(
                'ANSWER_ID' => $currentAnswer['answer_id'],
                'ANSWER' => preg_replace($pattern, $replace, Scrub::html($decodedAnswer)),
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => '',
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
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $currentQuestion['question_id']);

        $currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

        /* Pattern match spans to find answers
           */
        $matches = array();
        $pattern = self::FILLGAP_TOKEN_REGEX;

        preg_match_all($pattern, $currentAnswer['answer'], $matches);

        $actualAnswers = $matches[1];

        /* Remember where the answers were, so we can compare between the
         * student's reponse and the actual answer
         */
        $replace = " <span class='fillgap correct'>$1</span> ";
        $correctResponse = preg_replace($pattern, $replace, $currentAnswer['answer']);

        $page->setVar(array(
            'RESPONSE_USER_ID' => $currentQuestion['user_id'],
            'USER_FIRSTNAME' => $currentQuestion['firstname'],
            'USER_LASTNAME' => $currentQuestion['lastname'],
            'ANSWER_ID' => $currentAnswer['answer_id'],
        ));

        if ($existing['response']) {
            $existingAnswer = unserialize($existing['response']);

            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs and the already-submitted values
             */
            $ans = $currentAnswer['answer'];

            $i = 0;

            foreach ($existingAnswer as $val) {
                $pattern = self::FILLGAP_TOKEN_REGEX;
                $val = Scrub::text($val);

                /* Indicate matching/non-matching responses to answers
                     */
                if (strtolower(trim($actualAnswers[$i])) == strtolower(trim($existingAnswer[$i]))) {
                    $replace = " <span class='fillgap correct'>$val</span> ";
                } else {
                    $replace = " <span class='fillgap incorrect'>$val</span> ";
                }

                $ans = preg_replace($pattern, $replace, $ans, 1, $x);

                $i++;
            }

            $page->setVar(array(
                'ANSWER' => $ans,
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => Scrub::text($existing['comment']),
                'REVIEWED_MARKS' => $existing['mark'],
            ));
        } else {
            /* Prepare a special way to show Fillgap answers. This replaces
             * %%token%% tokens with inputs.
             */
            $pattern = self::FILLGAP_TOKEN_REGEX;
            $replace = " <span class='submitted-late label'>&nbsp;</span> ";

            $page->setVar(array(
                'ANSWER' => preg_replace($pattern, $replace, Scrub::html($currentAnswer['answer'])),
                'RESPONSE_ID' => $existing['response_id'],
                'REVIEWED_COMMENT' => '',
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
        $currentAnswer = $this->getByQuestionID($currentQuestion['question_id']);

        /* Pattern match spans to find answers
         */
        $matches = array();
        $pattern = self::FILLGAP_TOKEN_REGEX;

        preg_match_all($pattern, $currentAnswer['answer'], $matches);

        $page->setVar(array(
            'ANSWER' => Scrub::html($currentAnswer['answer']),
        ));

        $page->parse('answerTop_block', 'answerTop', true);
    }
}
